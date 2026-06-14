<?php
require_once __DIR__.'../../../../config/constants.php';
require_once __DIR__.'/../../../includes/utilities/helpers.php';
require_once __DIR__.'/../../../includes/auth/session.inc.php';

// Redirect if already logged in
if (isAdmin()) {
    header('Location: ' . APP_URL . '/pages/admin/dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__.'/../../../includes/auth/admin-login.inc.php';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo APP_NAME; ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">

    
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
    <div class="glass-card login-card">

        <div class="logo text-center mb-6">
            <h1><i class="fas fa-shield-alt"></i> Admin Panel</h1>
            <p>HTU Complaint System</p>
        </div>

        <div class="form-header text-center mb-6">
            <h2>Administrator Login</h2>
            <p>Restricted access</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo escapeHtml($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <!-- Email -->
            <div class="form-group">
                <label class="form-label">Admin Email</label>
                <div class="input-with-icon">
                    <i class="fas fa-envelope"></i>
                    <input type="email"
                           name="email"
                           class="form-control glass-input"
                           placeholder="admin@htu.edu.gh"
                           required>
                </div>
            </div>

            <!-- Password -->
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-lock"></i>
                    <input type="password"
                           name="password"
                           id="password"
                           class="form-control glass-input"
                           required>
                </div>
            </div>

            <button type="submit" class="btn btn-gradient login-btn">
                <i class="fas fa-sign-in-alt"></i>
                Login
            </button>
        </form>

    </div>
</div>
 <!-- JavaScript -->
    <script src="<?php echo APP_URL; ?>/assets/js/theme-toggle.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/form-validation.js"></script>
</body>
</html>
