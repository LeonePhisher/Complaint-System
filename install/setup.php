<?php
// Installation Script for HTU Complaint System

// Prevent direct access
// if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
//     die('Direct access not permitted');
// }

// Check if system is already installed
if (file_exists('../config/database.php') && file_exists('../.env')) {
    header('Location: ../../index.php');
    exit();
}

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // Verify requirements
            $requirements = checkRequirements();
            if ($requirements['all_passed']) {
                header('Location: ?step=2');
                exit();
            } else {
                $error = 'Please fix all requirements before proceeding';
            }
            break;
            
        case 2:
            // Database configuration
            $host = $_POST['db_host'] ?? 'localhost';
            $name = $_POST['db_name'] ?? '';
            $user = $_POST['db_user'] ?? 'root';
            $pass = $_POST['db_pass'] ?? '';
            
            if (empty($name)) {
                $error = 'Database name is required';
                break;
            }
            
            // Test database connection
            try {
                $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Create database if not exists
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `$name`");
                
                // Store database config for next step
                $_SESSION['install_db'] = compact('host', 'name', 'user', 'pass');
                header('Location: ?step=3');
                exit();
                
            } catch (PDOException $e) {
                $error = 'Database connection failed: ' . $e->getMessage();
            }
            break;
            
        case 3:
            // Import database schema
            $sql_file = __DIR__ . '/install.sql';
            
            if (!file_exists($sql_file)) {
                $error = 'Database schema file not found';
                break;
            }
            
            $sql = file_get_contents($sql_file);
            
            try {
                $config = $_SESSION['install_db'];
                $pdo = new PDO("mysql:host={$config['host']};dbname={$config['name']};charset=utf8mb4", 
                              $config['user'], $config['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Execute SQL
                $pdo->exec($sql);
                
                // Create super admin
                $username = $_POST['admin_username'] ?? 'superadmin';
                $email = $_POST['admin_email'] ?? '';
                $password = $_POST['admin_password'] ?? '';
                $confirm = $_POST['admin_confirm'] ?? '';
                
                if (empty($email) || empty($password)) {
                    $error = 'Admin email and password are required';
                    break;
                }
                
                if ($password !== $confirm) {
                    $error = 'Passwords do not match';
                    break;
                }
                
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    UPDATE admins 
                    SET username = ?, email = ?, password_hash = ?
                    WHERE role = 'super_admin'
                    LIMIT 1
                ");
                $stmt->execute([$username, $email, $password_hash]);
                
                $_SESSION['install_admin'] = compact('username', 'email');
                header('Location: ?step=4');
                exit();
                
            } catch (PDOException $e) {
                $error = 'Database import failed: ' . $e->getMessage();
            }
            break;
            
        case 4:
            // Create configuration files
            $config = $_SESSION['install_db'];
            $admin = $_SESSION['install_admin'];
            
            // Create .env file
            $env_content = <<<ENV
# Database Configuration
DB_HOST={$config['host']}
DB_NAME={$config['name']}
DB_USER={$config['user']}
DB_PASS={$config['pass']}

# Application Settings
APP_ENV=production
APP_DEBUG=false
APP_URL={$_POST['app_url']}

# Security
SECRET_KEY={bin2hex(random_bytes(32))}
CSRF_TOKEN_LIFETIME=3600

# Email Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM=noreply@htu.edu.gh
SMTP_FROM_NAME="HTU Complaint System"
ENV;
            
            if (file_put_contents('../.env', $env_content) === false) {
                $error = 'Failed to create .env file';
                break;
            }
            
            // Create config directory if not exists
            if (!is_dir('../config')) {
                mkdir('../config', 0755, true);
            }
            
            // Create upload directories
            $upload_dirs = [
                '../assets/uploads/complaints',
                '../assets/uploads/avatars',
                '../logs',
                '../temp'
            ];
            
            foreach ($upload_dirs as $dir) {
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
            }
            
            // Set permissions
            chmod('../assets/uploads', 0755);
            chmod('../logs', 0755);
            chmod('../temp', 0755);
            
            // Create installation lock file
            file_put_contents('../install.lock', date('Y-m-d H:i:s'));
            
            // Cleanup session
            unset($_SESSION['install_db']);
            unset($_SESSION['install_admin']);
            
            $success = 'Installation completed successfully!';
            break;
    }
}

function checkRequirements() {
    $requirements = [
        'php_version' => [
            'name' => 'PHP Version >= 7.4',
            'value' => PHP_VERSION,
            'required' => '7.4.0',
            'passed' => version_compare(PHP_VERSION, '7.4.0', '>=')
        ],
        'pdo_mysql' => [
            'name' => 'PDO MySQL Extension',
            'value' => extension_loaded('pdo_mysql') ? 'Enabled' : 'Disabled',
            'required' => 'Enabled',
            'passed' => extension_loaded('pdo_mysql')
        ],
        'mbstring' => [
            'name' => 'MBString Extension',
            'value' => extension_loaded('mbstring') ? 'Enabled' : 'Disabled',
            'required' => 'Enabled',
            'passed' => extension_loaded('mbstring')
        ],
        'json' => [
            'name' => 'JSON Extension',
            'value' => extension_loaded('json') ? 'Enabled' : 'Disabled',
            'required' => 'Enabled',
            'passed' => extension_loaded('json')
        ],
        'openssl' => [
            'name' => 'OpenSSL Extension',
            'value' => extension_loaded('openssl') ? 'Enabled' : 'Disabled',
            'required' => 'Enabled',
            'passed' => extension_loaded('openssl')
        ],
        'file_uploads' => [
            'name' => 'File Uploads',
            'value' => ini_get('file_uploads') ? 'Enabled' : 'Disabled',
            'required' => 'Enabled',
            'passed' => ini_get('file_uploads')
        ],
        'upload_max_size' => [
            'name' => 'Upload Max Size',
            'value' => ini_get('upload_max_filesize'),
            'required' => '5M',
            'passed' => (intval(ini_get('upload_max_filesize')) >= 5)
        ],
        'writable_config' => [
            'name' => 'Config Directory Writable',
            'value' => is_writable('../config') ? 'Writable' : 'Not Writable',
            'required' => 'Writable',
            'passed' => is_writable('../config') || (!is_dir('../config') && is_writable('..'))
        ],
        'writable_uploads' => [
            'name' => 'Uploads Directory Writable',
            'value' => is_writable('../assets/uploads') ? 'Writable' : 'Not Writable',
            'required' => 'Writable',
            'passed' => is_writable('../assets/uploads') || (!is_dir('../assets/uploads') && is_writable('../assets'))
        ]
    ];
    
    $all_passed = true;
    foreach ($requirements as $req) {
        if (!$req['passed']) {
            $all_passed = false;
            break;
        }
    }
    
    return compact('requirements', 'all_passed');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - HTU Complaint System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .install-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 800px;
            overflow: hidden;
        }
        
        .install-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .install-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .install-header p {
            opacity: 0.9;
        }
        
        .install-steps {
            display: flex;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .step {
            flex: 1;
            text-align: center;
            padding: 1rem;
            font-weight: 500;
            color: #a0aec0;
            border-bottom: 3px solid transparent;
        }
        
        .step.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .step.completed {
            color: #48bb78;
            border-bottom-color: #48bb78;
        }
        
        .install-content {
            padding: 2rem;
        }
        
        .requirements-list {
            margin: 1.5rem 0;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
        }
        
        .requirement.passed {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
        }
        
        .requirement.failed {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
        }
        
        .requirement-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .requirement.passed .requirement-icon {
            background: #48bb78;
            color: white;
        }
        
        .requirement.failed .requirement-icon {
            background: #f56565;
            color: white;
        }
        
        .requirement-info {
            flex: 1;
        }
        
        .requirement-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .requirement-value {
            font-size: 0.875rem;
            opacity: 0.8;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #4a5568;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .alert-error {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
            border: 1px solid rgba(245, 101, 101, 0.2);
        }
        
        .alert-success {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
            border: 1px solid rgba(72, 187, 120, 0.2);
        }
        
        .alert i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        .completion-message {
            text-align: center;
            padding: 2rem;
        }
        
        .completion-message i {
            font-size: 4rem;
            color: #48bb78;
            margin-bottom: 1rem;
        }
        
        .completion-message h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #2d3748;
        }
        
        .completion-message p {
            color: #718096;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <!-- Header -->
        <div class="install-header">
            <h1><i class="fas fa-comment-dots"></i> HTU Complaint System</h1>
            <p>Anonymous Complaint System Installation</p>
        </div>
        
        <!-- Steps -->
        <div class="install-steps">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                Requirements
            </div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                Database
            </div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?> <?php echo $step > 3 ? 'completed' : ''; ?>">
                Admin Setup
            </div>
            <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">
                Complete
            </div>
        </div>
        
        <!-- Content -->
        <div class="install-content">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php if ($step == 1): ?>
                    <!-- Step 1: Requirements Check -->
                    <h2>System Requirements</h2>
                    <p>Please ensure all requirements are met before proceeding.</p>
                    
                    <div class="requirements-list">
                        <?php $requirements = checkRequirements(); ?>
                        <?php foreach ($requirements['requirements'] as $req): ?>
                            <div class="requirement <?php echo $req['passed'] ? 'passed' : 'failed'; ?>">
                                <div class="requirement-icon">
                                    <i class="fas fa-<?php echo $req['passed'] ? 'check' : 'times'; ?>"></i>
                                </div>
                                <div class="requirement-info">
                                    <div class="requirement-name"><?php echo $req['name']; ?></div>
                                    <div class="requirement-value">
                                        Current: <?php echo $req['value']; ?> | Required: <?php echo $req['required']; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="form-actions">
                        <div></div>
                        <button type="submit" class="btn btn-primary" <?php echo !$requirements['all_passed'] ? 'disabled' : ''; ?>>
                            Continue <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                    
                <?php elseif ($step == 2): ?>
                    <!-- Step 2: Database Configuration -->
                    <h2>Database Configuration</h2>
                    <p>Enter your database connection details.</p>
                    
                    <div class="form-group">
                        <label class="form-label">Database Host</label>
                        <input type="text" name="db_host" class="form-control" value="localhost" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Database Name</label>
                        <input type="text" name="db_name" class="form-control" placeholder="htu_complaint_system" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Database Username</label>
                        <input type="text" name="db_user" class="form-control" value="root" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Database Password</label>
                        <input type="password" name="db_pass" class="form-control" placeholder="Leave empty if none">
                    </div>
                    
                    <div class="form-actions">
                        <a href="?step=1" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Continue <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                    
                <?php elseif ($step == 3): ?>
                    <!-- Step 3: Super Admin Setup -->
                    <h2>Super Administrator Setup</h2>
                    <p>Create the initial super administrator account.</p>
                    
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="admin_username" class="form-control" value="superadmin" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="admin_email" class="form-control" placeholder="admin@htu.edu.gh" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="admin_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="admin_confirm" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="form-actions">
                        <a href="?step=2" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Continue <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                    
                <?php elseif ($step == 4): ?>
                    <!-- Step 4: Final Configuration -->
                    <h2>Final Configuration</h2>
                    <p>Enter your application URL and complete installation.</p>
                    
                    <div class="form-group">
                        <label class="form-label">Application URL</label>
                        <input type="url" name="app_url" class="form-control" 
                               value="<?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])); ?>"
                               required>
                        <small>Make sure this URL is accessible from your network.</small>
                    </div>
                    
                    <div class="form-actions">
                        <a href="?step=3" class="btn btn-secondary">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Complete Installation <i class="fas fa-check ml-2"></i>
                        </button>
                    </div>
                    
                <?php elseif ($step == 5): ?>
                    <!-- Step 5: Completion -->
                    <div class="completion-message">
                        <i class="fas fa-check-circle"></i>
                        <h2>Installation Complete!</h2>
                        <p>HTU Complaint System has been successfully installed.</p>
                        
                        <div style="text-align: left; background: #f8f9fa; padding: 1.5rem; border-radius: 8px; margin: 2rem 0;">
                            <h3 style="margin-bottom: 1rem;">Next Steps:</h3>
                            <ol style="margin-left: 1.5rem; line-height: 2;">
                                <li>Login to the admin panel using the credentials you created</li>
                                <li>Configure email settings in the admin panel</li>
                                <li>Set up complaint categories</li>
                                <li>Test the student registration and complaint submission</li>
                            </ol>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; justify-content: center;">
                            <a href="../pages/auth/login.php" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
                            </a>
                            <a href="../pages/admin/dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-tachometer-alt mr-2"></i> Admin Dashboard
                            </a>
                        </div>
                        
                        <p style="margin-top: 2rem; font-size: 0.875rem; color: #a0aec0;">
                            <strong>Security Note:</strong> Delete the install directory after installation.
                        </p>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>