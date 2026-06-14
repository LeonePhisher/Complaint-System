<?php
header('Content-Type: text/html; charset=utf-8');

require_once '../../config/constants.php';
require_once '../../includes/utilities/helpers.php';
require_once '../../includes/utilities/notifications.php';

// Check if already logged in
if (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])) {
    header('Location: ' . APP_URL . '/pages/student/dashboard.php');
    exit();
}

$error = '';
$success = '';
$show_form = true;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $user_type = sanitizeInput($_POST['user_type'] ?? 'student');
    
    try {
        // Check if user exists
        if ($user_type === 'student') {
            $stmt = db()->prepare("SELECT id, full_name, email, is_verified FROM students WHERE email = ?");
        } else {
            $stmt = db()->prepare("SELECT id, full_name, email, is_active FROM admins WHERE email = ?");
        }
        
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check if student is verified
            if ($user_type === 'student' && !$user['is_verified']) {
                $error = 'Please verify your email address before resetting password.';
            } 
            // Check if admin is active
            elseif ($user_type === 'admin' && !$user['is_active']) {
                $error = 'Your admin account is not active. Please contact super admin.';
            }
            else {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Delete old tokens
                $stmt = db()->prepare("
                    DELETE FROM password_reset_tokens 
                    WHERE email = ? AND user_type = ?
                ");
                $stmt->execute([$email, $user_type]);
                
                // Insert new token
                $stmt = db()->prepare("
                    INSERT INTO password_reset_tokens (email, user_type, token, expires_at, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$email, $user_type, $token, $expires_at]);
                
                // Send reset email
                $reset_link = APP_URL . "/pages/auth/reset-password.php?token=" . $token . "&type=" . $user_type;
                $mail_sent = sendPasswordResetEmail($email, $user['full_name'], $reset_link);
                
                if ($mail_sent) {
                    $success = 'Password reset instructions sent to your email!';
                    $show_form = false;
                    
                    // Log password reset request
                    logActivity('password_reset_requested', 'Requested password reset', $user['id'], $user_type);
                } else {
                    $error = 'Failed to send reset email. Please try again.';
                }
            }
        } else {
            $error = 'No account found with this email address.';
        }
        
    } catch (PDOException $e) {
        error_log("Password reset error: " . $e->getMessage());
        $error = 'Failed to process request. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .forgot-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .forgot-card {
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

        .forgot-icon {
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

        .forgot-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .forgot-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .forgot-form {
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

        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-input:focus, .form-select:focus {
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

        .instructions-box {
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: left;
            border-left: 4px solid var(--primary-color);
        }

        .instructions-title {
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .instructions-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .instructions-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .instructions-list li i {
            color: var(--primary-color);
            margin-top: 0.25rem;
        }

        .user-type-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            background: var(--bg-secondary);
            padding: 0.5rem;
            border-radius: var(--radius-md);
        }

        .user-type-option {
            flex: 1;
            text-align: center;
        }

        .user-type-option input[type="radio"] {
            display: none;
        }

        .user-type-label {
            display: block;
            padding: 0.75rem;
            border-radius: var(--radius-sm);
            background: var(--bg-primary);
            color: var(--text-secondary);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-type-option input[type="radio"]:checked + .user-type-label {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 6px rgba(102, 126, 234, 0.2);
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

        @media (max-width: 640px) {
            .forgot-container {
                padding: 1rem;
            }
            
            .forgot-card {
                padding: 2rem;
            }
            
            .forgot-title {
                font-size: 1.75rem;
            }
            
            .user-type-selector {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <!-- Icon -->
            <div class="forgot-icon">
                <i class="fas fa-key"></i>
            </div>
            
            <?php if ($success): ?>
                <!-- Success Message -->
                <div class="success-box">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 class="forgot-title">Check Your Email!</h2>
                    <p class="forgot-subtitle"><?php echo $success; ?></p>
                    
                    <div class="instructions-box">
                        <h3 class="instructions-title">
                            <i class="fas fa-envelope"></i> What to do next:
                        </h3>
                        <ul class="instructions-list">
                            <li>
                                <i class="fas fa-inbox"></i>
                                <span>Check your email inbox for a password reset link</span>
                            </li>
                            <li>
                                <i class="fas fa-clock"></i>
                                <span>The link expires in 1 hour for security reasons</span>
                            </li>
                            <li>
                                <i class="fas fa-spam"></i>
                                <span>Can't find it? Check your spam or junk folder</span>
                            </li>
                            <li>
                                <i class="fas fa-redo"></i>
                                <span>Didn't receive it? You can request another reset</span>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="countdown">
                        <i class="fas fa-clock"></i>
                        <span>Reset link expires in: <span class="time" id="countdownTimer">01:00:00</span></span>
                    </div>
                    
                    <div class="links">
                        <a href="<?php echo APP_URL; ?>/pages/auth/login.php" class="btn btn-light">
                            <i class="fas fa-sign-in-alt"></i> Back to Login
                        </a>
                    </div>
                </div>
                
            <?php elseif ($error): ?>
                <!-- Error Message -->
                <div class="error-box">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h2 class="forgot-title">Oops!</h2>
                    <p class="forgot-subtitle"><?php echo $error; ?></p>
                    
                    <?php if ($show_form): ?>
                        <div class="instructions-box">
                            <h3 class="instructions-title">
                                <i class="fas fa-info-circle"></i> Need help?
                            </h3>
                            <ul class="instructions-list">
                                <li>
                                    <i class="fas fa-check"></i>
                                    <span>Ensure you're entering the correct email address</span>
                                </li>
                                <li>
                                    <i class="fas fa-user-check"></i>
                                    <span>Students: Make sure your email is verified</span>
                                </li>
                                <li>
                                    <i class="fas fa-headset"></i>
                                    <span>Contact support if you continue having issues</span>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- Reset Form -->
                        <form method="POST" class="forgot-form">
                            <div class="user-type-selector">
                                <div class="user-type-option">
                                    <input type="radio" name="user_type" value="student" id="type_student" checked>
                                    <label for="type_student" class="user-type-label">
                                        <i class="fas fa-user-graduate"></i> Student
                                    </label>
                                </div>
                                <div class="user-type-option">
                                    <input type="radio" name="user_type" value="admin" id="type_admin">
                                    <label for="type_admin" class="user-type-label">
                                        <i class="fas fa-user-shield"></i> Admin
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Enter your email address</label>
                                <input type="email" name="email" class="form-input" 
                                       placeholder="your.email@htu.edu.gh" required>
                            </div>
                            
                            <button type="submit" class="btn btn-gradient w-full">
                                <i class="fas fa-paper-plane"></i> Send Reset Instructions
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="links">
                        <a href="<?php echo APP_URL; ?>/pages/auth/login.php" class="link-item">
                            <i class="fas fa-arrow-left"></i> Back to Login
                        </a>
                        <a href="<?php echo APP_URL; ?>/pages/auth/register.php" class="link-item">
                            <i class="fas fa-user-plus"></i> Create New Account
                        </a>
                        <a href="<?php echo APP_URL; ?>/pages/student/dashboard.php" class="link-item">
                            <i class="fas fa-home"></i> Go to Homepage
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Default Forgot Password Form -->
                <h1 class="forgot-title">Forgot Password?</h1>
                <p class="forgot-subtitle">
                    No worries! Enter your email address and we'll send you 
                    instructions to reset your password.
                </p>
                
                <!-- Instructions -->
                <div class="instructions-box">
                    <h3 class="instructions-title">
                        <i class="fas fa-info-circle"></i> How it works:
                    </h3>
                    <ul class="instructions-list">
                        <li>
                            <i class="fas fa-envelope"></i>
                            <span>Enter your registered email address below</span>
                        </li>
                        <li>
                            <i class="fas fa-link"></i>
                            <span>We'll email you a password reset link</span>
                        </li>
                        <li>
                            <i class="fas fa-key"></i>
                            <span>Click the link and create a new password</span>
                        </li>
                        <li>
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Login with your new password</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Reset Form -->
                <form method="POST" class="forgot-form">
                    <div class="user-type-selector">
                        <div class="user-type-option">
                            <input type="radio" name="user_type" value="student" id="type_student" checked>
                            <label for="type_student" class="user-type-label">
                                <i class="fas fa-user-graduate"></i> Student
                            </label>
                        </div>
                        <div class="user-type-option">
                            <input type="radio" name="user_type" value="admin" id="type_admin">
                            <label for="type_admin" class="user-type-label">
                                <i class="fas fa-user-shield"></i> Admin
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" 
                               placeholder="your.email@htu.edu.gh" required>
                    </div>
                    
                    <button type="submit" class="btn btn-gradient w-full">
                        <i class="fas fa-paper-plane"></i> Send Reset Instructions
                    </button>
                </form>
                
                <!-- Security Note -->
                <div class="security-info">
                    <p class="security-note">
                        <i class="fas fa-shield-alt"></i>
                        For security reasons, reset links expire in 1 hour and can only be used once.
                    </p>
                </div>
                
                <!-- Additional Links -->
                <div class="links">
                    <a href="<?php echo APP_URL; ?>/pages/auth/login.php" class="link-item">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                    <a href="<?php echo APP_URL; ?>/pages/auth/verify.php" class="link-item">
                        <i class="fas fa-envelope"></i> Verify Email
                    </a>
                    <a href="<?php echo APP_URL; ?>/pages/auth/register.php" class="link-item">
                        <i class="fas fa-user-plus"></i> Create New Account
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Countdown Timer for success page
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
                            
                            // Show notification
                            const notification = document.createElement('div');
                            notification.className = 'toast toast-warning';
                            notification.innerHTML = `
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Reset link has expired. Please request a new one.</span>
                            `;
                            document.querySelector('.forgot-card').prepend(notification);
                            
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
        
        // Start countdown if on success page
        document.addEventListener('DOMContentLoaded', function() {
            startCountdown();
            
            // Auto-focus email input
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput) {
                emailInput.focus();
            }
            
            // User type selector interaction
            const userTypeOptions = document.querySelectorAll('.user-type-option');
            userTypeOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const input = this.querySelector('input[type="radio"]');
                    input.checked = true;
                    
                    // Update all labels
                    document.querySelectorAll('.user-type-label').forEach(label => {
                        label.style.background = 'var(--bg-primary)';
                        label.style.color = 'var(--text-secondary)';
                        label.style.boxShadow = 'none';
                    });
                    
                    // Update selected label
                    const label = this.querySelector('.user-type-label');
                    label.style.background = 'linear-gradient(135deg, #667eea, #764ba2)';
                    label.style.color = 'white';
                    label.style.boxShadow = '0 4px 6px rgba(102, 126, 234, 0.2)';
                });
            });
        });
        
        // Form validation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const emailInput = this.querySelector('input[name="email"]');
                const email = emailInput.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    showNotification('Please enter a valid email address', 'error');
                    emailInput.focus();
                    return;
                }
                
                // Check if it's a valid HTU email for students
                const userType = this.querySelector('input[name="user_type"]:checked').value;
                if (userType === 'student' && !email.endsWith('@htu.edu.gh')) {
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
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                    submitBtn.disabled = true;
                }
            });
        }
        
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
        
        // Auto-detect user type based on email
        const emailInput = document.querySelector('input[name="email"]');
        if (emailInput) {
            emailInput.addEventListener('blur', function() {
                const email = this.value.trim();
                
                // If email ends with admin domain or contains admin keyword, suggest admin
                if (email.includes('admin') || email.includes('administrator') || email.endsWith('@htu-admin.edu.gh')) {
                    const adminRadio = document.getElementById('type_admin');
                    if (adminRadio) {
                        adminRadio.checked = true;
                        
                        // Update labels
                        document.querySelectorAll('.user-type-label').forEach(label => {
                            label.style.background = 'var(--bg-primary)';
                            label.style.color = 'var(--text-secondary)';
                            label.style.boxShadow = 'none';
                        });
                        
                        const adminLabel = document.querySelector('label[for="type_admin"]');
                        if (adminLabel) {
                            adminLabel.style.background = 'linear-gradient(135deg, #667eea, #764ba2)';
                            adminLabel.style.color = 'white';
                            adminLabel.style.boxShadow = '0 4px 6px rgba(102, 126, 234, 0.2)';
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>