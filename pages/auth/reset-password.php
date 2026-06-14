<?php
header('Content-Type: text/html; charset=utf-8');
require_once '../../config/constants.php';
require_once '../../includes/utilities/helpers.php';
require_once '../../includes/utilities/notifications.php';
header('Content-Type: text/html; charset=utf-8');
// Check if already logged in
if (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])) {
    header('Location: ' . APP_URL . '/pages/student/dashboard.php');
    exit();
}

$error = '';
$success = '';
$show_form = false;
$token_valid = false;
$user_type = '';
$email = '';

// Check for reset token
$token = $_GET['token'] ?? '';
$type = $_GET['type'] ?? 'student';

// Validate token
if ($token) {
    try {
        $stmt = db()->prepare("
            SELECT * FROM password_reset_tokens 
            WHERE token = ? 
            AND user_type = ?
            AND expires_at > NOW()
            AND used_at IS NULL
        ");
        $stmt->execute([$token, $type]);
        $token_data = $stmt->fetch();
        
        if ($token_data) {
            $token_valid = true;
            $user_type = $token_data['user_type'];
            $email = $token_data['email'];
            $show_form = true;
        } else {
            $error = 'Invalid or expired password reset link.';
        }
        
    } catch (PDOException $e) {
        error_log("Token validation error: " . $e->getMessage());
        $error = 'Failed to validate reset link. Please try again.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate token again
    try {
        $stmt = db()->prepare("
            SELECT * FROM password_reset_tokens 
            WHERE token = ? 
            AND user_type = ?
            AND expires_at > NOW()
            AND used_at IS NULL
        ");
        $stmt->execute([$token, $user_type]);
        $token_data = $stmt->fetch();
        
        if (!$token_data) {
            $error = 'Invalid or expired password reset link.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $email = $token_data['email'];
            
            if ($user_type === 'student') {
                $stmt = db()->prepare("
                    UPDATE students SET 
                    password = ?,
                    password_changed_at = NOW(),
                    updated_at = NOW()
                    WHERE email = ?
                ");
            } else {
                $stmt = db()->prepare("
                    UPDATE admins SET 
                    password = ?,
                    password_changed_at = NOW(),
                    updated_at = NOW()
                    WHERE email = ?
                ");
            }
            
            $stmt->execute([$hashed_password, $email]);
            
            // Get user ID for logging
            if ($user_type === 'student') {
                $stmt = db()->prepare("SELECT id FROM students WHERE email = ?");
            } else {
                $stmt = db()->prepare("SELECT id FROM admins WHERE email = ?");
            }
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // Mark token as used
            $stmt = db()->prepare("
                UPDATE password_reset_tokens SET 
                used_at = NOW(),
                updated_at = NOW()
                WHERE token = ?
            ");
            $stmt->execute([$token]);
            
            // Log password reset
            if ($user) {
                logActivity('password_reset', 'Password reset via reset link', $user['id'], $user_type);
            }
            
            // Send confirmation email
            if ($user_type === 'student') {
                $stmt = db()->prepare("SELECT full_name FROM students WHERE email = ?");
            } else {
                $stmt = db()->prepare("SELECT full_name FROM admins WHERE email = ?");
            }
            $stmt->execute([$email]);
            $user_data = $stmt->fetch();
            
            if ($user_data) {
                sendPasswordChangedEmail($email, $user_data['full_name']);
            }
            
            $success = 'Password reset successfully! You can now login with your new password.';
            $show_form = false;
        }
        
    } catch (PDOException $e) {
        error_log("Password reset error: " . $e->getMessage());
        $error = 'Failed to reset password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .reset-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .reset-card {
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

        .reset-icon {
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

        .reset-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .reset-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .reset-form {
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

        .password-strength {
            height: 4px;
            background: var(--bg-tertiary);
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background: #f56565;
            width: 33%;
        }

        .strength-medium {
            background: #ecc94b;
            width: 66%;
        }

        .strength-strong {
            background: #48bb78;
            width: 100%;
        }

        .password-requirements {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }

        .requirement.met {
            color: #48bb78;
        }

        .requirement.unmet {
            color: var(--text-secondary);
        }

        .requirement i {
            font-size: 0.875rem;
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

        .user-info {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .info-content {
            flex: 1;
        }

        .info-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 500;
            color: var(--text-primary);
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

        .security-info {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .security-note {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-align: center;
            line-height: 1.5;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .password-input-container {
            position: relative;
        }

        @media (max-width: 640px) {
            .reset-container {
                padding: 1rem;
            }
            
            .reset-card {
                padding: 2rem;
            }
            
            .reset-title {
                font-size: 1.75rem;
            }
            
            .info-item {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            
            .info-content {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <!-- Icon -->
            <div class="reset-icon">
                <i class="fas fa-key"></i>
            </div>
            
            <?php if ($success): ?>
                <!-- Success Message -->
                <div class="success-box">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 class="reset-title">Password Reset!</h2>
                    <p class="reset-subtitle"><?php echo $success; ?></p>
                    
                    <div class="user-info">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Account Type</div>
                                <div class="info-value">
                                    <?php echo ucfirst($user_type); ?>
                                    <?php if ($user_type === 'student'): ?>
                                        <i class="fas fa-user-graduate ml-1"></i>
                                    <?php else: ?>
                                        <i class="fas fa-user-shield ml-1"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($email); ?></div>
                            </div>
                        </div>
                    </div>
                    
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
                    <h2 class="reset-title">Reset Failed</h2>
                    <p class="reset-subtitle"><?php echo $error; ?></p>
                    
                    <div class="links">
                        <a href="<?php echo APP_URL; ?>/pages/auth/forgot-password.php" class="link-item">
                            <i class="fas fa-redo"></i> Request New Reset Link
                        </a>
                        <a href="<?php echo APP_URL; ?>/pages/auth/login.php" class="link-item">
                            <i class="fas fa-sign-in-alt"></i> Back to Login
                        </a>
                        <a href="<?php echo APP_URL; ?>/pages/student/dashboard.php" class="link-item">
                            <i class="fas fa-home"></i> Go to Homepage
                        </a>
                    </div>
                </div>
                
            <?php elseif ($token_valid && $show_form): ?>
                <!-- Reset Password Form -->
                <h1 class="reset-title">Set New Password</h1>
                <p class="reset-subtitle">
                    Create a strong, secure password for your account.
                </p>
                
                <!-- User Information -->
                <div class="user-info">
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Account Type</div>
                            <div class="info-value">
                                <?php echo ucfirst($user_type); ?>
                                <?php if ($user_type === 'student'): ?>
                                    <i class="fas fa-user-graduate ml-1"></i>
                                <?php else: ?>
                                    <i class="fas fa-user-shield ml-1"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Email Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($email); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Countdown Timer -->
                <div class="countdown">
                    <i class="fas fa-clock"></i>
                    <span>Reset link expires in: <span class="time" id="countdownTimer">01:00:00</span></span>
                </div>
                
                <!-- Password Requirements -->
                <div class="password-requirements" id="passwordRequirements">
                    <div class="requirement unmet" id="reqLength">
                        <i class="fas fa-circle"></i>
                        <span>At least 8 characters</span>
                    </div>
                    <div class="requirement unmet" id="reqUpper">
                        <i class="fas fa-circle"></i>
                        <span>Contains uppercase letter</span>
                    </div>
                    <div class="requirement unmet" id="reqLower">
                        <i class="fas fa-circle"></i>
                        <span>Contains lowercase letter</span>
                    </div>
                    <div class="requirement unmet" id="reqNumber">
                        <i class="fas fa-circle"></i>
                        <span>Contains number</span>
                    </div>
                </div>
                
                <!-- Reset Form -->
                <form method="POST" class="reset-form" id="resetForm">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="user_type" value="<?php echo htmlspecialchars($user_type); ?>">
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <div class="password-input-container">
                            <input type="password" name="password" id="password" 
                                   class="form-input" required minlength="8">
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-bar" id="passwordStrength"></div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <div class="password-input-container">
                            <input type="password" name="confirm_password" id="confirmPassword" 
                                   class="form-input" required minlength="8">
                            <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="passwordMatch" class="password-requirements"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-gradient w-full" id="submitBtn">
                        <i class="fas fa-save"></i> Reset Password
                    </button>
                </form>
                
                <!-- Security Note -->
                <div class="security-info">
                    <p class="security-note">
                        <i class="fas fa-shield-alt"></i>
                        For security, choose a strong password that you don't use elsewhere.
                        This link expires in 1 hour and can only be used once.
                    </p>
                </div>
                
                <!-- Additional Links -->
                <div class="links">
                    <a href="<?php echo APP_URL; ?>/pages/auth/forgot-password.php" class="link-item">
                        <i class="fas fa-redo"></i> Request New Reset Link
                    </a>
                    <a href="<?php echo APP_URL; ?>/pages/auth/login.php" class="link-item">
                        <i class="fas fa-sign-in-alt"></i> Back to Login
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Invalid Token Message -->
                <div class="error-box">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h2 class="reset-title">Invalid Reset Link</h2>
                    <p class="reset-subtitle">
                        The password reset link is invalid or has expired.
                    </p>
                    
                    <div class="links">
                        <a href="<?php echo APP_URL; ?>/pages/auth/forgot-password.php" class="btn btn-light">
                            <i class="fas fa-redo"></i> Request New Reset Link
                        </a>
                        <a href="<?php echo APP_URL; ?>/pages/auth/login.php" class="link-item">
                            <i class="fas fa-sign-in-alt"></i> Back to Login
                        </a>
                        <a href="<?php echo APP_URL; ?>/pages/student/dashboard.php" class="link-item">
                            <i class="fas fa-home"></i> Go to Homepage
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const strengthBar = document.getElementById('passwordStrength');
        const submitBtn = document.getElementById('submitBtn');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                checkPasswordStrength(password);
                checkPasswordMatch();
            });
        }
        
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }
        
        function checkPasswordStrength(password) {
            let strength = 0;
            const requirements = {
                length: password.length >= 8,
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /[0-9]/.test(password)
            };
            
            // Update requirement indicators
            Object.keys(requirements).forEach(req => {
                const element = document.getElementById('req' + req.charAt(0).toUpperCase() + req.slice(1));
                if (element) {
                    if (requirements[req]) {
                        element.classList.remove('unmet');
                        element.classList.add('met');
                        element.querySelector('i').className = 'fas fa-check-circle';
                        strength++;
                    } else {
                        element.classList.remove('met');
                        element.classList.add('unmet');
                        element.querySelector('i').className = 'fas fa-circle';
                    }
                }
            });
            
            // Update strength bar
            if (strengthBar) {
                strengthBar.className = 'strength-bar';
                if (strength <= 1) {
                    strengthBar.classList.add('strength-weak');
                } else if (strength <= 3) {
                    strengthBar.classList.add('strength-medium');
                } else {
                    strengthBar.classList.add('strength-strong');
                }
            }
            
            // Update submit button state
            updateSubmitButton();
        }
        
        function checkPasswordMatch() {
            const password = passwordInput ? passwordInput.value : '';
            const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : '';
            const matchElement = document.getElementById('passwordMatch');
            
            if (!matchElement) return;
            
            if (!password || !confirmPassword) {
                matchElement.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchElement.innerHTML = `
                    <div class="requirement met">
                        <i class="fas fa-check-circle"></i>
                        <span>Passwords match</span>
                    </div>
                `;
            } else {
                matchElement.innerHTML = `
                    <div class="requirement unmet">
                        <i class="fas fa-times-circle"></i>
                        <span>Passwords don't match</span>
                    </div>
                `;
            }
            
            updateSubmitButton();
        }
        
        function updateSubmitButton() {
            if (!submitBtn) return;
            
            const password = passwordInput ? passwordInput.value : '';
            const confirmPassword = confirmPasswordInput ? confirmPasswordInput.value : '';
            
            // Check all requirements
            const requirementsMet = 
                password.length >= 8 &&
                /[A-Z]/.test(password) &&
                /[a-z]/.test(password) &&
                /[0-9]/.test(password) &&
                password === confirmPassword;
            
            submitBtn.disabled = !requirementsMet;
            submitBtn.style.opacity = requirementsMet ? '1' : '0.6';
            submitBtn.style.cursor = requirementsMet ? 'pointer' : 'not-allowed';
        }
        
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        // Countdown Timer
        function startCountdown() {
            let hours = 1;
            let minutes = 0;
            let seconds = 0;
            
            const timerElement = document.getElementById('countdownTimer');
            if (!timerElement) return;
            
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
                            
                            // Disable form
                            const form = document.getElementById('resetForm');
                            if (form) {
                                form.querySelectorAll('input, button').forEach(element => {
                                    element.disabled = true;
                                });
                            }
                            
                            // Show notification
                            const notification = document.createElement('div');
                            notification.className = 'toast toast-warning';
                            notification.innerHTML = `
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Reset link has expired. Please request a new one.</span>
                            `;
                            document.querySelector('.reset-card').prepend(notification);
                            
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
        
        // Form submission handler
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                // Final validation
                if (password !== confirmPassword) {
                    e.preventDefault();
                    showNotification('Passwords do not match', 'error');
                    return;
                }
                
                if (password.length < 8) {
                    e.preventDefault();
                    showNotification('Password must be at least 8 characters', 'error');
                    return;
                }
                
                // Show loading state
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';
                submitBtn.disabled = true;
            });
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Start countdown if on reset page
            if (document.getElementById('countdownTimer')) {
                startCountdown();
            }
            
            // Auto-focus password input
            if (passwordInput) {
                passwordInput.focus();
            }
            
            // Initial button state
            updateSubmitButton();
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
        
        // Check if form should be disabled due to expired token
        function checkTokenExpiry() {
            const urlParams = new URLSearchParams(window.location.search);
            const token = urlParams.get('token');
            
            if (token) {
                // Simulate checking token expiry (in real app, this would be server-side)
                const createdTime = localStorage.getItem(`token_${token}_created`);
                if (createdTime) {
                    const elapsed = Date.now() - parseInt(createdTime);
                    const oneHour = 60 * 60 * 1000;
                    
                    if (elapsed > oneHour) {
                        // Token expired
                        const form = document.getElementById('resetForm');
                        if (form) {
                            form.style.display = 'none';
                            
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'error-box';
                            errorDiv.innerHTML = `
                                <div class="error-icon">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <h2 class="reset-title">Link Expired</h2>
                                <p class="reset-subtitle">
                                    This password reset link has expired. Please request a new one.
                                </p>
                                <div class="links">
                                    <a href="<?php echo APP_URL; ?>/pages/auth/forgot-password.php" class="btn btn-light">
                                        <i class="fas fa-redo"></i> Request New Link
                                    </a>
                                </div>
                            `;
                            
                            form.parentNode.insertBefore(errorDiv, form);
                        }
                    }
                } else {
                    // Store creation time
                    localStorage.setItem(`token_${token}_created`, Date.now().toString());
                }
            }
        }
        
        // Check token expiry on load
        if (window.location.search.includes('token')) {
            checkTokenExpiry();
        }
    </script>
</body>
</html>