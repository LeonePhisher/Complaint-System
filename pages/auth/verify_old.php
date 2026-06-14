<?php
header('Content-Type: text/html; charset=utf-8');

require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../config/mail_config.php';
require_once '../../includes/utilities/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if logged in
if (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])) {
    header('Location: ' . APP_URL . '/pages/student/dashboard.php');
    exit();
}

$error = '';
$success = '';
$email = '';
$otp_sent = false;
$countdown_time = 0;
$step = 'request'; // request or verify

// Get email from URL parameter
$email_param = sanitizeInput($_GET['email'] ?? '');

// ==== AUTO-SEND OTP WHEN PAGE LOADS WITH EMAIL PARAMETER ====
if ($email_param && filter_var($email_param, FILTER_VALIDATE_EMAIL)) {
    try {
        // Find user
        $stmt = db()->prepare("SELECT id, full_name, is_verified FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email_param]);
        $user = $stmt->fetch();

        if ($user && !$user['is_verified']) {
            // Generate OTP
            $otp_code = generateOTP(6);
            $expires_in_minutes = 10; // OTP valid for 10 minutes
            $otp_expires = date('Y-m-d H:i:s', strtotime("+$expires_in_minutes minutes"));

            // Save OTP to database
            $stmt = db()->prepare("
                UPDATE users SET 
                    otp_code = ?,
                    otp_expires = ?,
                    otp_attempts = 0
                WHERE id = ?
            ");
            $stmt->execute([$otp_code, $otp_expires, $user['id']]);

            // Log to otp_history
            $stmt = db()->prepare("
                INSERT INTO otp_history (user_id, otp_code, purpose, expires_at, ip_address)
                VALUES (?, ?, 'verification', ?, ?)
            ");
            $stmt->execute([$user['id'], $otp_code, $otp_expires, $_SERVER['REMOTE_ADDR']]);

            // Show form immediately - send email in background
            $otp_sent = true;
            $email = $email_param;
            $countdown_time = $expires_in_minutes * 60;
            $success = 'We\'ve sent a verification code to your email. Check your inbox.';
            $step = 'verify';

            // Send OTP email (non-blocking)
            @sendOTPEmail($email_param, $user['full_name'], $otp_code);
        }
    } catch (Exception $e) {
        error_log("Auto OTP error: " . $e->getMessage());
        $error = 'An error occurred. Please enter your email to request a code.';
        $step = 'request';
    }
}

// ==== REQUEST OTP VIA FORM ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_otp'])) {
    $request_email = sanitizeInput($_POST['email'] ?? '');

    if (!filter_var($request_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
        $step = 'request';
    } else {
        try {
            // Find user
            $stmt = db()->prepare("SELECT id, full_name, is_verified FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$request_email]);
            $user = $stmt->fetch();

            if ($user && !$user['is_verified']) {
                // Generate OTP
                $otp_code = generateOTP(6);
                $expires_in_minutes = 10;
                $otp_expires = date('Y-m-d H:i:s', strtotime("+$expires_in_minutes minutes"));

                // Save OTP to database
                $stmt = db()->prepare("
                    UPDATE users SET 
                        otp_code = ?,
                        otp_expires = ?,
                        otp_attempts = 0
                    WHERE id = ?
                ");
                $stmt->execute([$otp_code, $otp_expires, $user['id']]);

                // Log to otp_history
                $stmt = db()->prepare("
                    INSERT INTO otp_history (user_id, otp_code, purpose, expires_at, ip_address)
                    VALUES (?, ?, 'verification', ?, ?)
                ");
                $stmt->execute([$user['id'], $otp_code, $otp_expires, $_SERVER['REMOTE_ADDR']]);

                // Show form immediately - send email in background
                $otp_sent = true;
                $email = $request_email;
                $countdown_time = $expires_in_minutes * 60;
                $success = 'Verification code sent to your email!';
                $step = 'verify';

                // Send OTP email (non-blocking)
                @sendOTPEmail($request_email, $user['full_name'], $otp_code);
            } else if ($user && $user['is_verified']) {
                $error = 'This email is already verified. Please log in.';
                $step = 'request';
            } else {
                $error = 'No account found with this email.';
                $step = 'request';
            }
        } catch (Exception $e) {
            error_log("Request OTP error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
            $step = 'request';
        }
    }
}

// ==== VERIFY OTP SUBMISSION ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $verify_email = sanitizeInput($_POST['email'] ?? '');
    $otp_entered = sanitizeInput($_POST['otp_code'] ?? '');

    if (empty($verify_email) || empty($otp_entered)) {
        $error = 'Please enter both email and verification code.';
        $otp_sent = true;
        $email = $verify_email;
        $step = 'verify';
    } else {
        try {
            // Find user
            $stmt = db()->prepare("
                SELECT id, otp_code, otp_expires, otp_attempts 
                FROM users 
                WHERE email = ? AND is_verified = 0
                LIMIT 1
            ");
            $stmt->execute([$verify_email]);
            $user = $stmt->fetch();

            if ($user) {
                $max_attempts = 5;

                // Check if too many attempts
                if ($user['otp_attempts'] >= $max_attempts) {
                    $error = 'Too many failed attempts. Please request a new code.';
                    $step = 'request';
                    $otp_sent = false;
                    $email = '';
                }
                // Check if OTP exists
                else if (empty($user['otp_code'])) {
                    $error = 'No verification code found. Please request one.';
                    $otp_sent = true;
                    $email = $verify_email;
                    $step = 'verify';
                }
                // Check if OTP expired
                else if (strtotime($user['otp_expires']) < time()) {
                    $error = 'Verification code expired. Please request a new one.';
                    $step = 'request';
                    $otp_sent = false;
                    $email = '';
                }
                // Check if OTP matches
                else if ($otp_entered !== $user['otp_code']) {
                    // Wrong OTP - increment attempts
                    $new_attempts = $user['otp_attempts'] + 1;
                    $stmt = db()->prepare("UPDATE users SET otp_attempts = ? WHERE email = ?");
                    $stmt->execute([$new_attempts, $verify_email]);

                    $remaining = $max_attempts - $new_attempts;
                    $error = "Invalid code. You have $remaining attempt(s) left.";
                    $otp_sent = true;
                    $email = $verify_email;
                    $step = 'verify';
                }
                // OTP is correct - Verify account
                else {
                    $stmt = db()->prepare("
                        UPDATE users SET 
                            is_verified = 1,
                            account_status = 'active',
                            otp_code = NULL,
                            otp_expires = NULL,
                            otp_attempts = 0,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$user['id']]);

                    // Mark OTP as used in history
                    $stmt = db()->prepare("
                        UPDATE otp_history SET is_used = 1, used_at = NOW() 
                        WHERE user_id = ? AND otp_code = ?
                    ");
                    $stmt->execute([$user['id'], $otp_entered]);

                    $success = 'Email verified successfully! Redirecting to login...';
                    $step = 'success';
                }
            } else {
                $error = 'No unverified account found. Please check your email.';
                $step = 'request';
            }
        } catch (Exception $e) {
            error_log("Verify OTP error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
            $otp_sent = true;
            $email = $verify_email;
            $step = 'verify';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - HTU Complaint System</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/responsive.css">
    <style>
        .verify-container {
            max-width: 450px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .verify-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .verify-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .verify-header p {
            color: #666;
        }
        .otp-input {
            font-size: 2em;
            letter-spacing: 10px;
            text-align: center;
            font-weight: bold;
            max-width: 300px;
        }
        .countdown {
            text-align: center;
            font-size: 1.1em;
            margin: 15px 0;
            color: #667eea;
            font-weight: bold;
        }
        .expired {
            color: #f56565;
        }
        .msg-info {
            background: #e6f2ff;
            color: #0055cc;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #0055cc;
        }
        .msg-success {
            background: #e6f5e6;
            color: #2d5016;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .msg-error {
            background: #ffe6e6;
            color: #cc0000;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #cc0000;
        }
    </style>
</head>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
    <div class="verify-container">
        <div class="verify-header">
            <h1>✉️ Email Verification</h1>
            <p>Verify your email to activate your account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="msg-error"><?php echo escapeHtml($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="msg-success"><?php echo escapeHtml($success); ?></div>
            <?php if ($step === 'success'): ?>
                <script>
                    setTimeout(function() {
                        window.location.href = '<?php echo APP_URL; ?>/pages/auth/login.php';
                    }, 2000);
                </script>
            <?php endif; ?>
        <?php endif; ?>

        <!-- REQUEST OTP FORM -->
        <?php if ($step === 'request'): ?>
            <form method="POST">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #333;">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        placeholder="Enter your email" 
                        required
                        value="<?php echo escapeHtml($email); ?>"
                        style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 1em;"
                    >
                </div>
                <button type="submit" name="request_otp" style="
                    width: 100%; 
                    padding: 12px; 
                    background: #667eea; 
                    color: white; 
                    border: none; 
                    border-radius: 5px; 
                    font-size: 1em; 
                    cursor: pointer; 
                    font-weight: bold;
                ">Request Verification Code</button>
            </form>
        <?php endif; ?>

        <!-- VERIFY OTP FORM -->
        <?php if ($step === 'verify'): ?>
            <?php if (!empty($success)): ?>
                <div class="msg-info"><?php echo escapeHtml($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #333;">Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        value="<?php echo escapeHtml($email); ?>"
                        readonly
                        style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; background: #f5f5f5;"
                    >
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: bold; color: #333;">Verification Code (6 digits)</label>
                    <input 
                        type="text" 
                        name="otp_code" 
                        maxlength="6" 
                        placeholder="000000" 
                        required
                        class="otp-input"
                        pattern="[0-9]{6}"
                    >
                </div>

                <?php if ($countdown_time > 0): ?>
                    <div class="countdown" id="countdown">
                        Time remaining: <span id="timer">10:00</span>
                    </div>
                <?php endif; ?>

                <button type="submit" name="verify_otp" style="
                    width: 100%; 
                    padding: 12px; 
                    background: #667eea; 
                    color: white; 
                    border: none; 
                    border-radius: 5px; 
                    font-size: 1em; 
                    cursor: pointer; 
                    font-weight: bold;
                ">Verify Account</button>

                <button type="submit" name="request_otp" style="
                    width: 100%; 
                    padding: 12px; 
                    margin-top: 10px; 
                    background: #f5f5f5; 
                    color: #333; 
                    border: 1px solid #ddd; 
                    border-radius: 5px; 
                    cursor: pointer;
                    font-weight: bold;
                " onclick="document.querySelector('input[name=email]').removeAttribute('readonly');">
                    Request Another Code
                </button>
            </form>
        <?php endif; ?>

        <div style="margin-top: 25px; text-align: center; border-top: 1px solid #eee; padding-top: 15px;">
            <p style="color: #666; margin-bottom: 10px;">Already verified?</p>
            <a href="<?php echo APP_URL; ?>/pages/auth/login.php" style="color: #667eea; text-decoration: none; font-weight: bold;">Go to Login</a>
        </div>
    </div>

    <script>
        // Countdown timer
        <?php if ($countdown_time > 0): ?>
        let timeLeft = <?php echo $countdown_time; ?>;
        const timerDisplay = document.getElementById('timer');
        const countdownDiv = document.getElementById('countdown');

        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerDisplay.textContent = 
                String(minutes).padStart(2, '0') + ':' + 
                String(seconds).padStart(2, '0');

            if (timeLeft <= 0) {
                countdownDiv.innerHTML = '<span class="expired">Code expired. Please request a new one.</span>';
                document.querySelector('button[name="verify_otp"]').disabled = true;
                return;
            }
            
            timeLeft--;
            setTimeout(updateTimer, 1000);
        }
        updateTimer();
        <?php endif; ?>

        // Format OTP input (only numbers)
        document.querySelector('input[name="otp_code"]')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>
