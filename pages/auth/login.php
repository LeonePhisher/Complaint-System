<?php
require_once '../../config/constants.php';
require_once '../../includes/utilities/helpers.php';
require_once '../../includes/auth/session.inc.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirectBasedOnRole();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../includes/auth/login.inc.php';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem;
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s linear infinite;
            opacity: 0.3;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            z-index: 1;
            animation: scaleIn 0.5s ease-out;
        }

        .logo {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h2 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--text-secondary);
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            z-index: 2;
        }

        .input-with-icon .form-control {
            padding-left: 3rem;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .remember-forgot a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .remember-forgot a:hover {
            text-decoration: underline;
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .divider {
            position: relative;
            text-align: center;
            margin: 2rem 0;
            color: var(--text-muted);
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-color);
        }

        .divider span {
            background: var(--bg-primary);
            padding: 0 1rem;
            position: relative;
            z-index: 1;
        }

        .register-link {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-secondary);
        }

        .register-link a {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
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

        .error-message {
            animation: shake 0.5s ease-in-out;
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 1.5rem;
            }
            
            .logo h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Theme Toggle -->
        <div class="theme-toggle">
            <button class="toggle-btn" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </div>

        <!-- Login Card -->
        <div class="glass-card login-card">
            <!-- Logo -->
            <div class="logo">
                <h1><i class="fas fa-comment-dots"></i> HTU Complaints</h1>
                <p>Anonymous Complaint System</p>
            </div>

            <!-- Form Header -->
            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Sign in to your account to continue</p>
            </div>

            <!-- Error/Success Messages -->
            <?php if ($error): ?>
                <div class="alert alert-error error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <!-- Index Number -->
                <div class="form-group">
                    <label class="form-label">Index Number</label>
                    <div class="input-with-icon">
                        <i class="fas fa-id-card"></i>
                        <input 
                            type="text" 
                            name="index_number" 
                            class="form-control glass-input" 
                            placeholder="0323080303"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <div class="flex justify-between items-center mb-2">
                        <label class="form-label">Password</label>
                        <a href="<?php echo APP_URL; ?>/pages/auth/forgot-password.php" class="text-sm text-primary hover:underline">
                            Forgot password?
                        </a>
                    </div>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input 
                            type="password" 
                            name="password" 
                            id="password"
                            class="form-control glass-input" 
                            placeholder="Enter your password"
                            required
                        >
                        
                        <span 
                            type="button" 
                            class="fas fa-eye "
                            onclick="togglePassword()"
                            id="passwordIcon"
                        >
                            
            </span>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="remember-forgot">
                    <label class="flex items-center">
                        <input 
                            type="checkbox" 
                            name="remember" 
                            class="mr-2 rounded border-gray-300 text-primary focus:ring-primary"
                        >
                        <span>Remember me</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-gradient login-btn">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Sign In
                </button>

                <!-- Divider -->
                <div class="divider">
                    <span>Don't have an account?</span>
                </div>

                <!-- Register Link -->
                <div class="register-link">
                    <p>
                        New to HTU Complaints? 
                        <a href="<?php echo APP_URL; ?>/pages/auth/register.php">Create an account</a>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo APP_URL; ?>/assets/js/theme-toggle.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/form-validation.js"></script>
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('passwordIcon');
            
            // if (password.type === 'password') {
            //     password.type = 'text';
            //     icon.className = 'fas fa-eye-slash';
            // } else {
            //     password.type = 'password';
            //     icon.className = 'fas fa-eye';
            // }
             if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const indexNumber = document.querySelector('input[name="index_number"]');
            const password = document.getElementById('password');
            
            if (!indexNumber.value.trim()) {
                e.preventDefault();
                showError(indexNumber, 'Index number is required');
                return;
            }
            
            if (!password.value.trim()) {
                e.preventDefault();
                showError(password, 'Password is required');
                return;
            }
            
            if (password.value.length < 6) {
                e.preventDefault();
                showError(password, 'Password must be at least 6 characters');
                return;
            }
        });

        function showError(input, message) {
            // Remove any existing error
            const existingError = input.parentElement.querySelector('.error-text');
            if (existingError) existingError.remove();
            
            // Add error message
            const error = document.createElement('div');
            error.className = 'error-text text-red-500 text-sm mt-1';
            error.textContent = message;
            input.parentElement.appendChild(error);
            
            // Add error class to input
            input.classList.add('border-red-500');
            
            // Remove error on input
            input.addEventListener('input', function() {
                error.remove();
                input.classList.remove('border-red-500');
            }, { once: true });
        }
    </script>
</body>
</html>