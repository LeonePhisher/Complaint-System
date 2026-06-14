<?php
require_once '../../config/constants.php';
require_once '../../includes/utilities/helpers.php';
require_once '../../includes/utilities/notifications.php';

// Check if already logged in
if (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])) {
    header('Location: ' . APP_URL . '/pages/student/dashboard.php');
    exit();
}

//=====RIGHT ONE HER======

// $token = $_GET['token'] ?? '';
// $show_form = true;
// $error = '';
// $success = '';
// $verifying = false;

// // ==== VERIFY EMAIL IF TOKEN EXISTS ====
//  if ($token) {
//   $verifying = true;
//     try {
       
      
//         $stmt = db()->prepare("
//             SELECT * FROM users
//             WHERE verification_token = ?
//               AND verification_expires > NOW()
//               AND is_verified = 0
//             LIMIT 1
//         ");
//         $stmt->execute([$token]);
//         $student = $stmt->fetch();

//         if ($student) {
//             $stmt = db()->prepare("
//                 UPDATE users SET 
//                     is_verified = 1,
//                     updated_at = NOW(),
//                     verification_token = NULL,
//                     verification_expires = NULL,
//                     account_status = 'active'
//                 WHERE id = ?
//             ");
//             $stmt->execute([$student['id']]);
//             $stmt->execute([$student['id']]);
//             $errorInfo = $stmt->errorInfo();
//             var_dump($errorInfo); // debug


//             logActivity('email_verified', 'Email address verified', $student['id'], 'student');

//             $success = 'Email verified successfully! Redirecting to login...';
//             $show_form = false;

//         } else {
//             $error = 'Invalid or expired verification link.';
//         }

//     } catch (PDOException $e) {
//         error_log("Verification error: " . $e->getMessage());
//         $error = 'Verification from pdo exception failed. Please try again.';
//     }
// }

//=========RIGHT ONE ENDS HERE======== 
$error = '';
$success = '';
$show_form = true;

// Check for verification token
$token = $_GET['token'] ?? '';

if ($token) {
    try {
        // Verify token
        $stmt = db()->prepare("
            SELECT s.* 
            FROM students s
            JOIN verification_tokens vt ON s.id = vt.user_id
            WHERE vt.token = ? 
            AND vt.type = 'email_verification'
            AND vt.expires_at > NOW()
            AND vt.used_at IS NULL
        ");
        $stmt->execute([$token]);
        $student = $stmt->fetch();
        
        if ($student) {
            // Update student verification status
            $stmt = db()->prepare("
                UPDATE students SET 
                is_verified = 1,
                verified_at = NOW(),
                updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$student['id']]);
            
            // Mark token as used
            $stmt = db()->prepare("
                UPDATE verification_tokens SET 
                used_at = NOW(),
                updated_at = NOW()
                WHERE token = ?
            ");
            $stmt->execute([$token]);
            
            // Log verification
            logActivity('email_verified', 'Email address verified', $student['id'], 'student');
            
            $success = 'Email verified successfully! You can now log in.';
            $show_form = false;
            
        } else {
            $error = 'Invalid or expired verification link.';
        }
        
    } catch (PDOException $e) {
        error_log("Verification error: " . $e->getMessage());
        $error = 'Verification failed. Please try again.';
    }
}

// Handle resend verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_verification'])) {
    $email = sanitizeInput($_POST['email']);
    
    try {
        // Check if student exists and is not verified
        $stmt = db()->prepare("
            SELECT id, full_name, is_verified 
            FROM students 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $student = $stmt->fetch();
        
        if ($student) {
            if ($student['is_verified']) {
                $error = 'Email is already verified. You can log in.';
            } else {
                // Generate new verification token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Delete old tokens
                $stmt = db()->prepare("
                    DELETE FROM verification_tokens 
                    WHERE user_id = ? 
                    AND type = 'email_verification'
                ");
                $stmt->execute([$student['id']]);
                
                // Insert new token
                $stmt = db()->prepare("
                    INSERT INTO verification_tokens (user_id, token, type, expires_at, created_at)
                    VALUES (?, ?, 'email_verification', ?, NOW())
                ");
                $stmt->execute([$student['id'], $token, $expires_at]);
                
                // Send verification email
                $verification_link = APP_URL . "/pages/auth/verify.php?token=" . $token;
                $mail_sent = sendVerificationEmail($email, $student['full_name'], $verification_link);
                
                if ($mail_sent) {
                    $success = 'Verification email sent! Please check your inbox.';
                } else {
                    $error = 'Failed to send verification email. Please try again.';
                }
            }
        } else {
            $error = 'No account found with this email address.';
        }
        
    } catch (PDOException $e) {
        error_log("Resend verification error: " . $e->getMessage());
        $error = 'Failed to resend verification. Please try again.';
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
            <!-- Logo/Brand -->
            <div class="verify-icon">
                <i class="fas fa-envelope"></i>
            </div>
            
            <?php if ($success): ?>
                <!-- Success Message -->
                <div class="success-box">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 class="verify-title">Verification Complete!</h2>
                    <p class="verify-subtitle"><?php echo $success; ?></p>
                    <div class="links">
                        <a href="<?php echo APP_URL; ?>/pages/auth/login.php" class="btn btn-light">
                            <i class="fas fa-sign-in-alt"></i> Proceed to Login
                        </a>
                    </div>
                </div>
                
            <?php elseif ($error): ?>
                <!-- Error Message -->
                <div class="error-box">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h2 class="verify-title">Verification Failed</h2>
                    <p class="verify-subtitle"><?php echo $error; ?></p>
                    
                    <?php if ($show_form): ?>
                        <div class="verification-steps">
                            <div class="step">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <div class="step-title">Check Your Email</div>
                                    <div class="step-description">
                                        Look for the verification email from <?php echo APP_NAME; ?>. 
                                        Check your spam folder if you don't see it.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <div class="step-title">Click Verification Link</div>
                                    <div class="step-description">
                                        Open the email and click the verification link to confirm your email address.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <div class="step-title">Resend if Needed</div>
                                    <div class="step-description">
                                        If you didn't receive the email or it expired, you can request a new one below.
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Resend Form -->
                        <form method="POST" class="verify-form">
                            <div class="form-group">
                                <label class="form-label">Enter your email address</label>
                                <input type="email" name="email" class="form-input" 
                                       placeholder="your.email@htu.edu.gh" required>
                            </div>
                            <button type="submit" name="resend_verification" class="btn btn-gradient w-full">
                                <i class="fas fa-paper-plane"></i> Resend Verification Email
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="links">
                        <a href="<?php echo APP_URL; ?>/pages/auth/login.php" class="link-item">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                        <a href="<?php echo APP_URL; ?>/pages/auth/forgot-password.php" class="link-item">
                            <i class="fas fa-question-circle"></i> Forgot Password?
                        </a>
                        <a href="<?php echo APP_URL; ?>/pages/auth/register.php" class="link-item">
                            <i class="fas fa-user-plus"></i> Create New Account
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Default Verification Page -->
                <h1 class="verify-title">Verify Your Email</h1>
                <p class="verify-subtitle">
                    We've sent a verification link to your email address. 
                    Please check your inbox and click the link to complete your registration.
                </p>
                
                <!-- Verification Steps -->
                <div class="verification-steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Check Your Inbox</div>
                            <div class="step-description">
                                Look for an email from <?php echo APP_NAME; ?> with the subject 
                                "Verify Your Email Address".
                            </div>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Click Verification Link</div>
                            <div class="step-description">
                                Open the email and click the "Verify Email" button or link to 
                                confirm your email address.
                            </div>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Start Using the System</div>
                            <div class="step-description">
                                Once verified, you'll be redirected to login and can start 
                                submitting complaints.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Important Information -->
                <div class="verification-info">
                    <h3 class="info-title">
                        <i class="fas fa-info-circle"></i> Important Information
                    </h3>
                    <ul class="info-list">
                        <li>
                            <i class="fas fa-clock"></i>
                            <span>Verification links expire in 24 hours for security reasons.</span>
                        </li>
                        <li>
                            <i class="fas fa-shield-alt"></i>
                            <span>Email verification helps protect your account and ensures 
                            you receive important notifications.</span>
                        </li>
                        <li>
                            <i class="fas fa-spam"></i>
                            <span>Can't find the email? Check your spam or junk folder.</span>
                        </li>
                        <li>
                            <i class="fas fa-redo"></i>
                            <span>Didn't receive the email? You can request a new verification 
                            email below.</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Countdown Timer -->
                <div class="countdown">
                    <i class="fas fa-clock"></i>
                    <span>Verification link expires in: <span class="time" id="countdownTimer">24:00:00</span></span>
                </div>
                
                <!-- Resend Form -->
                <form method="POST" class="verify-form">
                    <div class="form-group">
                        <label class="form-label">Need a new verification email?</label>
                        <input type="email" name="email" class="form-input" 
                               placeholder="Enter your email address" required>
                    </div>
                    <button type="submit" name="resend_verification" class="btn btn-gradient w-full">
                        <i class="fas fa-paper-plane"></i> Resend Verification Email
                    </button>
                </form>
                
                <!-- Additional Links -->
                <div class="links">
                    <a href="<?php echo APP_URL; ?>/pages/auth/login.php" class="link-item">
                        <i class="fas fa-sign-in-alt"></i> Already verified? Login here
                    </a>
                    <a href="<?php echo APP_URL; ?>/pages/auth/register.php" class="link-item">
                        <i class="fas fa-user-plus"></i> Register a different account
                    </a>
                    <a href="<?php echo APP_URL; ?>/pages/student/dashboard.php" class="link-item">
                        <i class="fas fa-home"></i> Go to Homepage
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Countdown Timer
        function startCountdown() {
            let hours = 24;
            let minutes = 0;
            let seconds = 0;
            
            const timerElement = document.getElementById('countdownTimer');
            
            function updateTimer() {
                // Format time
                const formattedHours = hours.toString().padStart(2, '0');
                const formattedMinutes = minutes.toString().padStart(2, '0');
                const formattedSeconds = seconds.toString().padStart(2, '0');
                
                timerElement.textContent = `${formattedHours}:${formattedMinutes}:${formattedSeconds}`;
                
                // Update time
                if (seconds > 0) {
                    seconds--;
                } else {
                    if (minutes > 0) {
                        minutes--;
                        seconds = 59;
                    } else {
                        if (hours > 0) {
                            hours--;
                            minutes = 59;
                            seconds = 59;
                        } else {
                            // Countdown finished
                            timerElement.textContent = 'Expired!';
                            timerElement.style.color = '#f56565';
                            
                            // Show resend notification
                            const notification = document.createElement('div');
                            notification.className = 'toast toast-warning';
                            notification.innerHTML = `
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Verification link has expired. Please request a new one.</span>
                            `;
                            document.querySelector('.verify-card').prepend(notification);
                            
                            setTimeout(() => {
                                notification.style.display = 'none';
                            }, 5000);
                            
                            clearInterval(timerInterval);
                            return;
                        }
                    }
                }
            }
            
            // Update immediately
            updateTimer();
            
            // Update every second
            const timerInterval = setInterval(updateTimer, 1000);
        }
        
        // Start countdown if timer element exists
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('countdownTimer')) {
                startCountdown();
            }
            
            // Auto-focus email input
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput) {
                emailInput.focus();
            }
        });
        
        // Form validation
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const emailInput = this.querySelector('input[name="email"]');
                if (emailInput) {
                    const email = emailInput.value.trim();
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
                    if (!emailRegex.test(email)) {
                        e.preventDefault();
                        showNotification('Please enter a valid email address', 'error');
                        emailInput.focus();
                        return;
                    }
                    
                    // Check if it's a valid HTU email
                    if (!email.endsWith('@htu.edu.gh')) {
                        const shouldContinue = confirm(
                            'You entered a non-HTU email address. ' +
                            'HTU students should use their @htu.edu.gh email. ' +
                            'Do you want to continue?'
                        );
                        
                        if (!shouldContinue) {
                            e.preventDefault();
                            emailInput.focus();
                            return;
                        }
                    }
                }
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    submitBtn.disabled = true;
                }
            });
        });
        
        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `toast toast-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }
        
        // Check URL for token parameter
        function checkUrlToken() {
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');
            
            if (token) {
                // Show loading state
                const card = document.querySelector('.verify-card');
                card.innerHTML = `
                    <div class="text-center py-8">
                        <div class="spinner">
                            <i class="fas fa-spinner fa-spin fa-3x" style="color: #667eea;"></i>
                        </div>
                        <h2 class="verify-title mt-4">Verifying Email...</h2>
                        <p class="verify-subtitle">Please wait while we verify your email address.</p>
                    </div>
                `;
            }
        }
        
        // Check on page load
        document.addEventListener('DOMContentLoaded', checkUrlToken);
    </script>
</body>
</html>