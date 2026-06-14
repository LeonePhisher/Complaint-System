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
$can_resend = false; // Flag to allow resend after delay

// Get email from URL parameter
$email_param = sanitizeInput($_GET['email'] ?? '');

// ==== AUTO-SEND OTP WHEN PAGE LOADS WITH EMAIL PARAMETER ====
if ($email_param && filter_var($email_param, FILTER_VALIDATE_EMAIL) && !$_POST) {
    try {
        $stmt = db()->prepare("SELECT id, full_name, is_verified FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email_param]);
        $user = $stmt->fetch();

        if ($user && !$user['is_verified']) {
            $otp_code = generateOTP(6);
            $expires_in_minutes = 10;
            $otp_expires = date('Y-m-d H:i:s', strtotime("+$expires_in_minutes minutes"));

            $stmt = db()->prepare("
                UPDATE users SET 
                    otp_code = ?,
                    otp_expires = ?,
                    otp_attempts = 0
                WHERE id = ?
            ");
            $stmt->execute([$otp_code, $otp_expires, $user['id']]);

            $stmt = db()->prepare("
                INSERT INTO otp_history (user_id, otp_code, purpose, expires_at, ip_address)
                VALUES (?, ?, 'verification', ?, ?)
            ");
            $stmt->execute([$user['id'], $otp_code, $otp_expires, $_SERVER['REMOTE_ADDR']]);

            $otp_sent = true;
            $email = $email_param;
            $countdown_time = $expires_in_minutes * 60;
            $success = 'We\'ve sent a verification code to your email. Check your inbox.';
            $step = 'verify';

            // Send email in background (non-blocking)
            @sendOTPEmail($email_param, $user['full_name'], $otp_code);
        }
    } catch (Exception $e) {
        error_log("Auto OTP error: " . $e->getMessage());
        $error = 'An error occurred. Please try again.';
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
            $stmt = db()->prepare("SELECT id, full_name, is_verified, otp_expires FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$request_email]);
            $user = $stmt->fetch();

            if ($user && !$user['is_verified']) {
                // Check if OTP exists and is still valid
                if (!empty($user['otp_expires']) && strtotime($user['otp_expires']) > time()) {
                    // OTP is still valid - don't generate new one yet
                    $remaining_time = strtotime($user['otp_expires']) - time();
                    $error = 'Your current verification code is still valid. Please check your email or wait ' . ceil($remaining_time / 60) . ' minute(s) to request a new one.';
                    $step = 'verify';
                    $otp_sent = true;
                    $email = $request_email;
                    $countdown_time = $remaining_time;
                } else {
                    // Generate new OTP
                    $otp_code = generateOTP(6);
                    $expires_in_minutes = 10;
                    $otp_expires = date('Y-m-d H:i:s', strtotime("+$expires_in_minutes minutes"));

                    $stmt = db()->prepare("
                        UPDATE users SET 
                            otp_code = ?,
                            otp_expires = ?,
                            otp_attempts = 0
                        WHERE id = ?
                    ");
                    $stmt->execute([$otp_code, $otp_expires, $user['id']]);

                    $stmt = db()->prepare("
                        INSERT INTO otp_history (user_id, otp_code, purpose, expires_at, ip_address)
                        VALUES (?, ?, 'verification', ?, ?)
                    ");
                    $stmt->execute([$user['id'], $otp_code, $otp_expires, $_SERVER['REMOTE_ADDR']]);

                    $otp_sent = true;
                    $email = $request_email;
                    $countdown_time = $expires_in_minutes * 60;
                    $success = 'Verification code sent to your email!';
                    $step = 'verify';

                    // Send email in background (non-blocking)
                    @sendOTPEmail($request_email, $user['full_name'], $otp_code);
                }
            } else if ($user && $user['is_verified']) {
                $error = 'This email is already verified. Please log in.';
            } else {
                $error = 'No account found with this email.';
            }
        } catch (Exception $e) {
            error_log("Request OTP error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}

// ==== VERIFY OTP SUBMISSION ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $verify_email = sanitizeInput($_POST['email'] ?? '');
    $otp_entered = sanitizeInput($_POST['otp_code'] ?? '');

    // Always show the form during verification (non-blocking)
    $otp_sent = true;
    $email = $verify_email;
    $step = 'verify';

    if (empty($verify_email) || empty($otp_entered)) {
        $error = 'Please enter both email and verification code.';
    } else {
        try {
            $stmt = db()->prepare("
                SELECT id, otp_code, otp_expires, otp_attempts 
                FROM users 
                WHERE email = ? AND is_verified = 0
                LIMIT 1
            ");
            $stmt->execute([$verify_email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'No unverified account found. Please check your email.';
                $step = 'request';
                $otp_sent = false;
                $email = '';
            } else if ($user['otp_attempts'] >= 5) {
                $error = 'Too many failed attempts. Please request a new code.';
                $step = 'request';
                $otp_sent = false;
                $email = '';
            } else if (empty($user['otp_code'])) {
                $error = 'No verification code found. Please request one.';
            } else if (strtotime($user['otp_expires']) < time()) {
                $error = 'Verification code expired. Please request a new one.';
                $step = 'request';
                $otp_sent = false;
                $email = '';
            } else if ($otp_entered !== $user['otp_code']) {
                $new_attempts = $user['otp_attempts'] + 1;
                $stmt = db()->prepare("UPDATE users SET otp_attempts = ? WHERE id = ?");
                $stmt->execute([$new_attempts, $user['id']]);

                $remaining = 5 - $new_attempts;
                $error = "Invalid code. You have $remaining attempt(s) left.";
            } else {
                // Valid OTP - Update immediately without waiting
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

                $stmt = db()->prepare("
                    UPDATE otp_history SET is_used = 1, used_at = NOW() 
                    WHERE user_id = ? AND otp_code = ?
                ");
                $stmt->execute([$user['id'], $otp_entered]);

                $success = 'Email verified successfully! Redirecting to login...';
                $step = 'success';
            }
        } catch (Exception $e) {
            error_log("Verify OTP error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }

        .verify-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }

        .verify-container::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s linear infinite;
            opacity: 0.3;
        }

        .verify-card {
            width: 100%;
            max-width: 440px;
            z-index: 1;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            box-shadow: var(--glass-shadow);
            padding: 2.5rem;
        }

        .verify-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .verify-header h1 {
            font-size: 1.75rem;
            color: var(--text-primary);
            margin: 0 0 0.5rem 0;
            font-weight: 600;
        }

        .verify-header p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.95rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .alert-success {
            background: rgba(72, 187, 120, 0.1);
            color: var(--text-primary);
            border-left: 4px solid var(--success-color);
        }

        .alert-error {
            background: rgba(245, 101, 101, 0.1);
            color: var(--text-primary);
            border-left: 4px solid var(--danger-color);
        }

        .alert-info {
            background: rgba(66, 153, 225, 0.1);
            color: var(--text-primary);
            border-left: 4px solid var(--info-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.95rem;
        }

        input[type="email"],
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            color: var(--text-primary);
            background: var(--bg-primary);
            transition: all 0.3s ease;
            font-family: inherit;
        }

        input[type="email"]:focus,
        input[type="text"]:focus,
        input[type="number"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        input[readonly] {
            background: var(--bg-secondary);
            cursor: not-allowed;
            color: var(--text-muted);
        }

        .otp-input {
            font-size: 1.5rem;
            letter-spacing: 8px;
            text-align: center;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }

        .countdown {
            text-align: center;
            font-size: 1.1rem;
            margin: 1rem 0;
            font-weight: 600;
            color: var(--text-primary);
        }

        .countdown-time {
            color: var(--primary-color);
            font-size: 1.25rem;
        }

        .countdown-expired {
            color: var(--danger-color);
        }

        button {
            width: 100%;
            padding: 0.875rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            margin-bottom: 0.75rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--border-color);
            border-color: var(--primary-color);
        }

        button:disabled,
        button[disabled] {
            opacity: 0.5;
            cursor: not-allowed;
        }

        button:disabled:hover,
        button[disabled]:hover {
            transform: none;
        }

        .form-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            text-align: center;
        }

        .form-footer p {
            color: var(--text-secondary);
            margin: 0 0 0.5rem 0;
            font-size: 0.9rem;
        }

        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .form-footer a:hover {
            color: var(--primary-dark);
        }

        .theme-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1000;
        }

        .toggle-btn {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .toggle-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(30deg);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(20px); }
        }

        @media (max-width: 640px) {
            .glass-card {
                padding: 2rem 1.5rem;
            }

            .verify-header h1 {
                font-size: 1.5rem;
            }

            .toggle-btn {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
    <!-- Theme Toggle -->
    <div class="theme-toggle">
        <button class="toggle-btn" id="themeToggle">
            <i class="fas fa-moon"></i>
        </button>
    </div>

    <div class="verify-container">
        <div class="verify-card">
            <div class="glass-card">
                <div class="verify-header">
                    <h1>✉️ Email Verification</h1>
                    <p>Verify your email to activate your account</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo escapeHtml($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo escapeHtml($success); ?></span>
                    </div>
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
                    <form method="POST" novalidate>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input 
                                type="email" 
                                id="email"
                                name="email" 
                                placeholder="Enter your registered email" 
                                required
                                value="<?php echo escapeHtml($email); ?>"
                            >
                        </div>
                        <button type="submit" name="request_otp" class="btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Verification Code
                        </button>
                    </form>
                <?php endif; ?>

                <!-- VERIFY OTP FORM -->
                <?php if ($step === 'verify'): ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <span><?php echo escapeHtml($success); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" novalidate>
                        <div class="form-group">
                            <label for="email-display">Email</label>
                            <input 
                                type="email" 
                                id="email-display"
                                name="email" 
                                value="<?php echo escapeHtml($email); ?>"
                                readonly
                            >
                        </div>

                        <div class="form-group">
                            <label for="otp_code">Verification Code (6 digits)</label>
                            <input 
                                type="text" 
                                id="otp_code"
                                name="otp_code" 
                                maxlength="6" 
                                placeholder="000000" 
                                required
                                class="otp-input"
                                pattern="[0-9]{6}"
                                autocomplete="off"
                            >
                        </div>

                        <?php if ($countdown_time > 0): ?>
                            <div class="countdown">
                                Time remaining: <span class="countdown-time" id="timer">10:00</span>
                            </div>
                        <?php endif; ?>

                        <button type="submit" name="verify_otp" class="btn-primary">
                            <i class="fas fa-check"></i> Verify Account
                        </button>

                        <button type="submit" name="request_otp" class="btn-secondary" <?php echo ($countdown_time > 0 && $success) ? 'disabled' : ''; ?>>
                            <i class="fas fa-redo"></i> Request Another Code
                        </button>
                    </form>
                <?php endif; ?>

                <div class="form-footer">
                    <p>Already verified?</p>
                    <a href="<?php echo APP_URL; ?>/pages/auth/login.php">Go to Login</a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo APP_URL; ?>/assets/js/theme-toggle.js"></script>
    <script>
        // Countdown timer
        const countdownElement = document.getElementById('timer');
        if (countdownElement) {
            let timeLeft = <?php echo $countdown_time; ?>;
            const resendButton = document.querySelector('button[name="request_otp"]');

            function updateTimer() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                countdownElement.textContent = 
                    String(minutes).padStart(2, '0') + ':' + 
                    String(seconds).padStart(2, '0');

                // Disable resend button while timer is running
                if (resendButton) {
                    resendButton.disabled = true;
                }

                if (timeLeft <= 0) {
                    countdownElement.parentElement.innerHTML = '<span class="countdown-expired">Code expired. Please request a new one.</span>';
                    document.querySelector('button[name="verify_otp"]').disabled = true;
                    if (resendButton) {
                        resendButton.disabled = false;
                    }
                    return;
                }
                
                timeLeft--;
                setTimeout(updateTimer, 1000);
            }
            updateTimer();
        }

        // Format OTP input - only numbers
        const otpInput = document.getElementById('otp_code');
        if (otpInput) {
            otpInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
            
            // Auto-focus for better UX
            otpInput.focus();
        }
    </script>
</body>
</html>
