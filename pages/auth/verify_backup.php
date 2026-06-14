<?php
// Set correct content type FIRST
header('Content-Type: text/html; charset=utf-8');

// Load files in the SAME ORDER as test_otp_email.php (this is critical!)
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../config/mail_config.php';  // Load mail config BEFORE helpers
require_once '../../includes/utilities/helpers.php';

// Start session if not already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])) {
    header('Location: ' . APP_URL . '/pages/student/dashboard.php');
    exit();
}

$token = $_GET['token'] ?? '';
$email_param = sanitizeInput($_GET['email'] ?? '');
$show_form = true;
$error = '';
$success = '';
$otp_sent = false;
$email_for_otp = '';
$countdown_time = 0;
$step = 'request'; // request, verify, success

// ==== VERIFY EMAIL IF TOKEN EXISTS (for backward compatibility) ====
if ($token) {
    try {
        $stmt = db()->prepare("
            SELECT * FROM users
            WHERE verification_token = ?
              AND verification_expires > NOW()
              AND is_verified = 0
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $student = $stmt->fetch();

        if ($student) {
            $stmt = db()->prepare("
                UPDATE users SET 
                    is_verified = 1,
                    updated_at = NOW(),
                    verification_token = NULL,
                    verification_expires = NULL,
                    account_status = 'active'
                WHERE id = ?
            ");
            $stmt->execute([$student['id']]);

            logActivity('email_verified', 'Email address verified', $student['id'], 'student');

            $success = 'Email verified successfully! Redirecting to login...';
            $show_form = false;
            $step = 'success';
        } else {
            $error = 'Invalid or expired verification link.';
        }
    } catch (PDOException $e) {
        error_log("Verification error: " . $e->getMessage());
        $error = 'Verification failed. Please try again.';
    }
}

// ==== AUTO-SEND OTP IF EMAIL PARAMETER EXISTS (registration redirect) ====
if ($email_param && !$token && !$otp_sent) {
    if (filter_var($email_param, FILTER_VALIDATE_EMAIL)) {
        try {
            $stmt = db()->prepare("
                SELECT id, full_name, is_verified 
                FROM users 
                WHERE email = ?
                LIMIT 1
            ");
            $stmt->execute([$email_param]);
            $student = $stmt->fetch();

            if ($student && !$student['is_verified']) {
                // Auto-generate and send OTP
                $otp = generateOTP(6);
                $expires_in_minutes = intval(getSetting('otp_expiry_minutes', 10));
                $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_in_minutes minutes"));

                // Update user with OTP
                $stmt = db()->prepare("
                    UPDATE users SET 
                        otp_code = ?,
                        otp_expires = ?,
                        otp_attempts = 0
                    WHERE id = ?
                ");
                $stmt->execute([$otp, $expires_at, $student['id']]);

                // Log OTP in history
                $stmt = db()->prepare("
                    INSERT INTO otp_history (user_id, otp_code, purpose, expires_at, ip_address)
                    VALUES (?, ?, 'verification', ?, ?)
                ");
                $stmt->execute([$student['id'], $otp, $expires_at, getUserIP()]);

                // Send OTP email
                $mail_sent = sendOTPEmail($email_param, $student['full_name'], $otp);

                if ($mail_sent) {
                    $otp_sent = true;
                    $email_for_otp = $email_param;
                    $countdown_time = $expires_in_minutes * 60;
                    $success = 'Welcome! We\'ve sent a 6-digit code to your email. Check your inbox.';
                    $step = 'verify';
                } else {
                    $error = 'Failed to send OTP. Please request manually below.';
                    $step = 'request';
                }
            }
        } catch (PDOException $e) {
            error_log("Auto OTP error: " . $e->getMessage());
            $error = 'Please request OTP below to get started.';
            $step = 'request';
        }
    }
}

// ==== REQUEST OTP ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_otp'])) {
    $email = sanitizeInput($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        try {
            $stmt = db()->prepare("
                SELECT id, full_name, is_verified 
                FROM users 
                WHERE email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $student = $stmt->fetch();

            if ($student) {
                if ($student['is_verified']) {
                    $error = 'Email is already verified. You can log in.';
                } else {
                    // Generate and send OTP
                    $otp = generateOTP(6);
                    $expires_in_minutes = intval(getSetting('otp_expiry_minutes', 10));
                    $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_in_minutes minutes"));

                    // Update user with OTP
                    $stmt = db()->prepare("
                        UPDATE users SET 
                            otp_code = ?,
                            otp_expires = ?,
                            otp_attempts = 0
                        WHERE id = ?
                    ");
                    $stmt->execute([$otp, $expires_at, $student['id']]);

                    // Log OTP in history
                    $stmt = db()->prepare("
                        INSERT INTO otp_history (user_id, otp_code, purpose, expires_at, ip_address)
                        VALUES (?, ?, 'verification', ?, ?)
                    ");
                    $stmt->execute([$student['id'], $otp, $expires_at, getUserIP()]);

                    // Send OTP email
                    $mail_sent = sendOTPEmail($email, $student['full_name'], $otp);

                    if ($mail_sent) {
                        $otp_sent = true;
                        $email_for_otp = $email;
                        $countdown_time = $expires_in_minutes * 60;
                        $success = 'OTP sent to your email! Check your inbox.';
                        $step = 'verify';
                    } else {
                        $error = 'Failed to send OTP. Please try again.';
                        $step = 'request';
                        error_log("Failed to send OTP to $email");
                    }
                }
            } else {
                $error = 'No account found with this email address.';
                $step = 'request';
            }
        } catch (PDOException $e) {
            error_log("Request OTP error: " . $e->getMessage());
            $error = 'Failed to request OTP. Please try again.';
            $step = 'request';
        }
    }
}

// ==== VERIFY OTP ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $email = sanitizeInput($_POST['email'] ?? '');
    $otp_code = sanitizeInput($_POST['otp_code'] ?? '');
    
    // Set these so the form stays visible during verification
    $otp_sent = true;
    $email_for_otp = $email;
    $countdown_time = intval(getSetting('otp_expiry_minutes', 10)) * 60;
    $step = 'verify';

    if (empty($email) || empty($otp_code)) {
        $error = 'Please enter both email and OTP code.';
    } else {
        try {
            $stmt = db()->prepare("
                SELECT id, otp_code, otp_expires, otp_attempts 
                FROM users 
                WHERE email = ? AND is_verified = 0
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $student = $stmt->fetch();

            if ($student) {
                $max_attempts = intval(getSetting('otp_max_attempts', 5));
                
                if ($student['otp_attempts'] >= $max_attempts) {
                    $error = "Too many failed attempts. Please request a new OTP.";
                } else if (empty($student['otp_code'])) {
                    $error = 'No OTP found. Please request a new one.';
                } else if (strtotime($student['otp_expires']) < time()) {
                    $error = 'OTP has expired. Please request a new one.';
                } else if ($otp_code !== $student['otp_code']) {
                    $new_attempts = $student['otp_attempts'] + 1;
                    $stmt = db()->prepare("
                        UPDATE users SET otp_attempts = ? WHERE email = ?
                    ");
                    $stmt->execute([$new_attempts, $email]);
                    
                    $remaining = $max_attempts - $new_attempts;
                    $error = "Invalid OTP. You have $remaining attempts remaining.";
                } else {
                    // Valid OTP - Mark user as verified
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
                    $stmt->execute([$student['id']]);

                    // Mark OTP as used
                    $stmt = db()->prepare("
                        UPDATE otp_history SET is_used = 1, used_at = NOW() 
                        WHERE user_id = ? AND otp_code = ?
                    ");
                    $stmt->execute([$student['id'], $otp_code]);

                    logActivity('email_verified', 'Email address verified via OTP', $student['id'], 'student');

                    $success = 'Email verified successfully! Redirecting to login...';
                    $show_form = false;
                    $step = 'success';
                }
            } else {
                $error = 'No unverified account found with this email.';
                // Keep step as 'verify' to show form again with error
                $step = 'verify';
                $otp_sent = true;
                $email_for_otp = $email;
                $countdown_time = intval(getSetting('otp_expiry_minutes', 10)) * 60;
            }
        } catch (PDOException $e) {
            error_log("OTP verification error: " . $e->getMessage());
            $error = 'Verification failed. Please try again.';
            // Keep step as 'verify' to show form again with error
            $step = 'verify';
            $otp_sent = true;
            $email_for_otp = $email;
            $countdown_time = intval(getSetting('otp_expiry_minutes', 10)) * 60;
        }
    }
}

// ==== RESEND OTP (via AJAX) ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    header('Content-Type: application/json');
    
    $email = sanitizeInput($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        exit;
    }

    try {
        $stmt = db()->prepare("
            SELECT id, full_name, is_verified 
            FROM users 
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $student = $stmt->fetch();

        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'No account found']);
            exit;
        }

        if ($student['is_verified']) {
            echo json_encode(['success' => false, 'message' => 'Email already verified']);
            exit;
        }

        // Generate new OTP
        $otp = generateOTP(6);
        $expires_in_minutes = intval(getSetting('otp_expiry_minutes', 10));
        $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_in_minutes minutes"));

        $stmt = db()->prepare("
            UPDATE users SET 
                otp_code = ?,
                otp_expires = ?,
                otp_attempts = 0
            WHERE id = ?
        ");
        $stmt->execute([$otp, $expires_at, $student['id']]);

        // Log new OTP
        $stmt = db()->prepare("
            INSERT INTO otp_history (user_id, otp_code, purpose, expires_at, ip_address)
            VALUES (?, ?, 'verification', ?, ?)
        ");
        $stmt->execute([$student['id'], $otp, $expires_at, getUserIP()]);

        // Send OTP
        $mail_sent = sendOTPEmail($email, $student['full_name'], $otp);

        if ($mail_sent) {
            echo json_encode([
                'success' => true, 
                'message' => 'New OTP sent successfully',
                'countdown' => $expires_in_minutes * 60
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send OTP']);
        }
        exit;
    } catch (PDOException $e) {
        error_log("Resend OTP error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error processing request']);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="app-url" content="<?php echo APP_URL; ?>">
<title>Verify Email - <?php echo APP_NAME; ?></title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glasmorphism.css">
<link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>
/* ... Your existing CSS from verify.php (verify-container, verify-card, steps, forms, success/error boxes) ... */
  .verify-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .verify-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            padding: 3rem;
            width: 100%;
            max-width: 500px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-2xl);
            text-align: center;
        }

        .verify-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 2rem;
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .verify-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .verify-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .verify-form {
            margin-top: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .success-box {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .success-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .error-box {
            background: linear-gradient(135deg, #f56565, #ed64a6);
            color: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .error-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .verification-steps {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .step {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .step:hover {
            transform: translateX(5px);
            border-color: var(--primary-color);
            background: var(--bg-tertiary);
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .step-content {
            flex: 1;
            text-align: left;
        }

        .step-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .step-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .countdown {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        .countdown .time {
            font-weight: 600;
            color: var(--primary-color);
        }

        .verification-info {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }

        .info-title {
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .info-list li i {
            color: var(--primary-color);
            margin-top: 0.25rem;
        }

        .links {
            margin-top: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .link-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            padding: 0.75rem;
            border-radius: var(--radius-md);
        }

        .link-item:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
        }

        @media (max-width: 640px) {
            .verify-container {
                padding: 1rem;
            }
            
            .verify-card {
                padding: 2rem;
            }
            
            .verify-title {
                font-size: 1.75rem;
            }
            
            .step {
                flex-direction: column;
                text-align: center;
            }
            
            .step-content {
                text-align: center;
            }
        }
</style>
</head>
<body>
<div class="verify-container">
    <div class="verify-card">
        <div class="verify-icon">
            <?php if ($step === 'success'): ?>
                <i class="fas fa-check-circle" style="color: #48bb78;"></i>
            <?php elseif ($error): ?>
                <i class="fas fa-exclamation-circle" style="color: #f56565;"></i>
            <?php elseif ($step === 'verify'): ?>
                <i class="fas fa-lock" style="color: #667eea;"></i>
            <?php else: ?>
                <i class="fas fa-envelope" style="color: #667eea;"></i>
            <?php endif; ?>
        </div>

        <?php if ($step === 'success' && $success): ?>
            <!-- ===== SUCCESS STATE ===== -->
            <div class="success-box">
                <div class="success-icon"><i class="fas fa-check-circle"></i></div>
                <h2 class="verify-title">Verification Complete!</h2>
                <p class="verify-subtitle"><?php echo htmlspecialchars($success); ?></p>
                <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-secondary);">
                    Redirecting to login in 3 seconds...
                </p>
            </div>
            <script>
                setTimeout(() => {
                    window.location.href = '<?php echo APP_URL; ?>/pages/auth/login.php';
                }, 3000);
            </script>

        <?php elseif ($step === 'verify' && $otp_sent): ?>
            <!-- ===== OTP VERIFICATION FORM ===== -->
            <h1 class="verify-title">Enter Your Verification Code</h1>
            <p class="verify-subtitle">
                We've sent a 6-digit code to <strong><?php echo htmlspecialchars(substr($email_for_otp, 0, 3) . '***' . substr($email_for_otp, strrpos($email_for_otp, '@') - 2)); ?></strong>
            </p>

            <?php if ($error): ?>
                <div class="error-box" style="margin: 1.5rem 0;">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-box" style="margin: 1.5rem 0;">
                    <i class="fas fa-check-circle"></i>
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <!-- OTP Entry Form -->
            <form method="POST" class="verify-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-input" 
                        value="<?php echo htmlspecialchars($email_for_otp); ?>"
                        readonly
                        style="background: var(--bg-secondary); cursor: not-allowed; opacity: 0.7;">
                    <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                        Code sent to this email
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">6-Digit Code</label>
                    <input 
                        type="text" 
                        name="otp_code" 
                        class="form-input" 
                        placeholder="000000" 
                        maxlength="6"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        required
                        autofocus>
                    <small style="display: block; margin-top: 0.5rem; color: var(--text-secondary);">
                        Check your email and enter the 6-digit code
                    </small>
                </div>

                <!-- Countdown Timer -->
                <div class="countdown" style="margin: 1rem 0; text-align: center;">
                    <span style="color: var(--text-secondary);">Code expires in: </span>
                    <span class="time" id="countdownTimer">10:00</span>
                </div>

                <!-- Submit Button -->
                <button type="submit" name="verify_otp" class="btn btn-gradient w-full" style="margin: 1.5rem 0;">
                    <i class="fas fa-check"></i> Verify Code
                </button>
            </form>

            <!-- Resend OTP Section -->
            <div style="text-align: center; margin-top: 1.5rem;">
                <p style="margin-bottom: 0.75rem; color: var(--text-secondary);">
                    Didn't receive the code?
                </p>
                <button 
                    type="button" 
                    class="btn btn-secondary" 
                    id="resendBtn"
                    onclick="resendOTP('<?php echo htmlspecialchars($email_for_otp); ?>')">
                    <i class="fas fa-redo"></i> Request Another Code
                </button>
            </div>

        <?php else: ?>
            <!-- ===== OTP REQUEST FORM ===== -->
            <h1 class="verify-title">Verify Your Email</h1>
            <p class="verify-subtitle">
                Enter your email address and we'll send you a 6-digit code to verify your account.
            </p>

            <?php if ($error): ?>
                <div class="error-box" style="margin: 1.5rem 0;">
                    <i class="fas fa-exclamation-circle"></i>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <!-- Request OTP Form -->
            <form method="POST" class="verify-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="your.email@htu.edu.gh"
                        value="<?php echo htmlspecialchars($email_param); ?>"
                        required
                        autofocus>
                </div>

                <button type="submit" name="request_otp" class="btn btn-gradient w-full">
                    <i class="fas fa-paper-plane"></i> Send Verification Code
                </button>
            </form>

            <!-- Info Box -->
            <div class="verification-info">
                <div class="info-title">
                    <i class="fas fa-info-circle"></i> How it works:
                </div>
                <ul class="info-list">
                    <li><i class="fas fa-check"></i> Enter your email address</li>
                    <li><i class="fas fa-check"></i> We'll send you a 6-digit code</li>
                    <li><i class="fas fa-check"></i> Enter the code to complete verification</li>
                    <li><i class="fas fa-check"></i> Login and start using your account</li>
                </ul>
            </div>

        <?php endif; ?>
    </div>
</div>

<script>
// ===== OTP Countdown Timer =====
function startCountdown(seconds) {
    const timerElement = document.getElementById('countdownTimer');
    if (!timerElement) return;

    let remaining = seconds;

    function updateTimer() {
        const minutes = Math.floor(remaining / 60);
        const secs = remaining % 60;
        timerElement.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;

        if (remaining > 0) {
            remaining--;
            setTimeout(updateTimer, 1000);
        } else {
            timerElement.textContent = 'EXPIRED';
            timerElement.style.color = '#f56565';
            document.querySelector('button[name="verify_otp"]').disabled = true;
            
            const notification = document.createElement('div');
            notification.className = 'toast toast-warning';
            notification.innerHTML = `
                <i class="fas fa-exclamation-triangle"></i>
                <span>Code expired. Please request another one.</span>
            `;
            document.body.appendChild(notification);
            setTimeout(() => notification.style.display = 'none', 4000);
        }
    }

    updateTimer();
}

// ===== Resend OTP via AJAX =====
function resendOTP(email) {
    const btn = document.getElementById('resendBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    fetch('<?php echo APP_URL; ?>/pages/auth/verify.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'resend_otp=1&email=' + encodeURIComponent(email)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            btn.innerHTML = '<i class="fas fa-check"></i> Code Sent!';
            btn.style.backgroundColor = '#48bb78';
            startCountdown(data.countdown);
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> Request Another Code';
                btn.style.backgroundColor = '';
            }, 3000);
        } else {
            alert('Error: ' + (data.message || 'Failed to send code'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-redo"></i> Request Another Code';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-redo"></i> Request Another Code';
    });
}

// ===== Initialize =====
document.addEventListener('DOMContentLoaded', function() {
    // Start countdown if on OTP verification page
    <?php if ($step === 'verify' && $countdown_time > 0): ?>
        startCountdown(<?php echo $countdown_time; ?>);
    <?php endif; ?>

    // Auto-focus on OTP field
    const otpInput = document.querySelector('input[name="otp_code"]');
    if (otpInput) {
        otpInput.focus();
        // Only allow numbers
        otpInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }

    // Auto-focus on email field for request form
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        emailInput.focus();
    }

    // Form submission handlers
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
            }
        });
    });
});
</script>
