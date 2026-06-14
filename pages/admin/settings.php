<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../includes/utilities/helpers.php';

// Check if user is super admin
if (!isAdmin() || $_SESSION['admin_role'] !== 'super_admin') {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_general') {
            $site_name = sanitizeInput($_POST['site_name']);
            $site_email = sanitizeInput($_POST['site_email']);
            $support_email = sanitizeInput($_POST['support_email']);
            $items_per_page = intval($_POST['items_per_page']);
            
            $stmt = db()->prepare("
                UPDATE settings SET 
                site_name = ?, 
                site_email = ?, 
                support_email = ?, 
                items_per_page = ?,
                updated_at = NOW()
                WHERE id = 1
            ");
            $stmt->execute([$site_name, $site_email, $support_email, $items_per_page]);
            
            $success = 'General settings updated successfully';
            
        } elseif ($action === 'update_email') {
            $smtp_host = sanitizeInput($_POST['smtp_host']);
            $smtp_port = intval($_POST['smtp_port']);
            $smtp_username = sanitizeInput($_POST['smtp_username']);
            $smtp_secure = sanitizeInput($_POST['smtp_secure']);
            $email_from = sanitizeInput($_POST['email_from']);
            $email_from_name = sanitizeInput($_POST['email_from_name']);
            
            $stmt = db()->prepare("
                UPDATE email_settings SET 
                smtp_host = ?, 
                smtp_port = ?, 
                smtp_username = ?, 
                smtp_secure = ?,
                email_from = ?,
                email_from_name = ?,
                updated_at = NOW()
                WHERE id = 1
            ");
            $stmt->execute([$smtp_host, $smtp_port, $smtp_username, $smtp_secure, $email_from, $email_from_name]);
            
            $success = 'Email settings updated successfully';
            
        } elseif ($action === 'update_security') {
            $max_login_attempts = intval($_POST['max_login_attempts']);
            $lockout_duration = intval($_POST['lockout_duration']);
            $session_timeout = intval($_POST['session_timeout']);
            $require_email_verification = isset($_POST['require_email_verification']) ? 1 : 0;
            $allow_complaint_editing = isset($_POST['allow_complaint_editing']) ? 1 : 0;
            
            $stmt = db()->prepare("
                UPDATE security_settings SET 
                max_login_attempts = ?, 
                lockout_duration = ?, 
                session_timeout = ?,
                require_email_verification = ?,
                allow_complaint_editing = ?,
                updated_at = NOW()
                WHERE id = 1
            ");
            $stmt->execute([$max_login_attempts, $lockout_duration, $session_timeout, $require_email_verification, $allow_complaint_editing]);
            
            $success = 'Security settings updated successfully';
            
        } elseif ($action === 'update_notifications') {
            $notify_new_complaints = isset($_POST['notify_new_complaints']) ? 1 : 0;
            $notify_status_changes = isset($_POST['notify_status_changes']) ? 1 : 0;
            $notify_admin_on_reject = isset($_POST['notify_admin_on_reject']) ? 1 : 0;
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            
            $stmt = db()->prepare("
                UPDATE notification_settings SET 
                notify_new_complaints = ?, 
                notify_status_changes = ?,
                notify_admin_on_reject = ?,
                email_notifications = ?,
                updated_at = NOW()
                WHERE id = 1
            ");
            $stmt->execute([$notify_new_complaints, $notify_status_changes, $notify_admin_on_reject, $email_notifications]);
            
            $success = 'Notification settings updated successfully';
            
        } elseif ($action === 'add_admin') {
            $full_name = sanitizeInput($_POST['full_name']);
            $username = sanitizeInput($_POST['username']);
            $email = sanitizeInput($_POST['email']);
            $role = sanitizeInput($_POST['role']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // Check if username or email exists
            $checkStmt = db()->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);
            
            if ($checkStmt->fetch()) {
                $error = 'Username or email already exists';
            } else {
                $stmt = db()->prepare("
                    INSERT INTO admins (full_name, username, email, password, role, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$full_name, $username, $email, $password, $role]);
                
                $success = 'Admin added successfully';
            }
            
        } elseif ($action === 'update_admin_status') {
            $admin_id = intval($_POST['admin_id']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = db()->prepare("UPDATE admins SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$is_active, $admin_id]);
            
            $success = 'Admin status updated successfully';
            
        }
        
    } catch (PDOException $e) {
        error_log("Settings update error: " . $e->getMessage());
        $error = 'Failed to update settings. Please try again.';
    }
}

// Load current settings
$settings = [];
$email_settings = [];
$security_settings = [];
$notification_settings = [];
$admins = [];

try {
    // General settings
    $stmt = db()->prepare("SELECT * FROM settings WHERE id = 1");
    $stmt->execute();
    $settings = $stmt->fetch() ?: [];
    
    // Email settings
    $stmt = db()->prepare("SELECT * FROM email_settings WHERE id = 1");
    $stmt->execute();
    $email_settings = $stmt->fetch() ?: [];
    
    // Security settings
    $stmt = db()->prepare("SELECT * FROM security_settings WHERE id = 1");
    $stmt->execute();
    $security_settings = $stmt->fetch() ?: [];
    
    // Notification settings
    $stmt = db()->prepare("SELECT * FROM notification_settings WHERE id = 1");
    $stmt->execute();
    $notification_settings = $stmt->fetch() ?: [];
    
    // Load admins (permissions are stored on admins.permissions as JSON)
    $stmt = db()->prepare("
        SELECT *
        FROM admins 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $admins = $stmt->fetchAll()?:[];
    
    
} catch (PDOException $e) {
    error_log("Settings load error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .settings-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--text-secondary);
        }

        .settings-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border-radius: var(--radius-md);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .tab-btn:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 6px rgba(102, 126, 234, 0.2);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .settings-section {
            margin-bottom: 3rem;
        }

        .section-header {
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-color);
        }

        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-header .icon {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .settings-form {
            display: grid;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label .required {
            color: #f56565;
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-help {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 0.5rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .checkbox-input {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-primary);
            cursor: pointer;
        }

        .checkbox-input:checked {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: #667eea;
        }

        .checkbox-label {
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--bg-tertiary);
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .admin-table th {
            background: var(--bg-secondary);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
        }

        .admin-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .admin-table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
            border: 1px solid rgba(72, 187, 120, 0.2);
        }

        .status-inactive {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
            border: 1px solid rgba(245, 101, 101, 0.2);
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .permission-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .permission-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.7rem;
            font-weight: 500;
        }

        .permission-yes {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
        }

        .permission-no {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-2xl);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1.25rem;
            transition: color 0.2s ease;
        }

        .modal-close:hover {
            color: var(--text-primary);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .settings-container {
                padding: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .admin-table {
                display: block;
                overflow-x: auto;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <!-- Admin Navigation -->
    <?php include '../../includes/layout/admin-nav.php'; ?>

    <!-- Main Content -->
    <div class="settings-container">
        <!-- Header -->
        <div class="page-header">
            <h1>System Settings</h1>
            <p>Configure and manage system preferences</p>
        </div>

        <!-- Notifications -->
        <?php if ($success): ?>
            <div class="toast toast-success" id="successToast">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
            <script>
                setTimeout(() => {
                    document.getElementById('successToast').style.display = 'none';
                }, 3000);
            </script>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="toast toast-error" id="errorToast">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
            <script>
                setTimeout(() => {
                    document.getElementById('errorToast').style.display = 'none';
                }, 3000);
            </script>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="settings-tabs">
            <button class="tab-btn active" data-tab="general">
                <i class="fas fa-cog"></i> General
            </button>
            <button class="tab-btn" data-tab="email">
                <i class="fas fa-envelope"></i> Email
            </button>
            <button class="tab-btn" data-tab="security">
                <i class="fas fa-shield-alt"></i> Security
            </button>
            <button class="tab-btn" data-tab="notifications">
                <i class="fas fa-bell"></i> Notifications
            </button>
            <button class="tab-btn" data-tab="admins">
                <i class="fas fa-users-cog"></i> Admin Management
            </button>
            <button class="tab-btn" data-tab="backup">
                <i class="fas fa-database"></i> Backup & Restore
            </button>
        </div>

        <!-- Tab Contents -->
        
        <!-- General Settings -->
        <div class="tab-content active" id="general-tab">
            <form method="POST" class="settings-form">
                <input type="hidden" name="action" value="update_general">
                
                <div class="settings-section">
                    <div class="section-header">
                        <h2><span class="icon"><i class="fas fa-globe"></i></span> Site Settings</h2>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-signature"></i>
                                Site Name
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="site_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['site_name'] ?? APP_NAME); ?>" 
                                   required>
                            <div class="form-help">The name displayed throughout the system</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i>
                                Site Email
                                <span class="required">*</span>
                            </label>
                            <input type="email" name="site_email" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['site_email'] ?? ''); ?>" 
                                   required>
                            <div class="form-help">Default email for system communications</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-headset"></i>
                                Support Email
                            </label>
                            <input type="email" name="support_email" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['support_email'] ?? ''); ?>">
                            <div class="form-help">Email displayed for user support</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-list"></i>
                                Items Per Page
                            </label>
                            <select name="items_per_page" class="form-select">
                                <option value="10" <?php echo ($settings['items_per_page'] ?? 10) == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo ($settings['items_per_page'] ?? 10) == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo ($settings['items_per_page'] ?? 10) == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo ($settings['items_per_page'] ?? 10) == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                            <div class="form-help">Default number of items displayed per page</div>
                        </div>
                    </div>
                </div>
                
                <div class="settings-section">
                    <div class="section-header">
                        <h2><span class="icon"><i class="fas fa-palette"></i></span> Appearance</h2>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-moon"></i>
                                Dark Mode
                            </label>
                            <div class="checkbox-item">
                                <label class="switch">
                                    <input type="checkbox" name="dark_mode" <?php echo ($settings['dark_mode'] ?? 0) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="checkbox-label">Enable dark mode by default</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-robot"></i>
                                AI Suggestions
                            </label>
                            <div class="checkbox-item">
                                <label class="switch">
                                    <input type="checkbox" name="ai_suggestions" <?php echo ($settings['ai_suggestions'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="checkbox-label">Show AI-powered suggestions</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save"></i> Save General Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Email Settings -->
        <div class="tab-content" id="email-tab">
            <form method="POST" class="settings-form">
                <input type="hidden" name="action" value="update_email">
                
                <div class="settings-section">
                    <div class="section-header">
                        <h2><span class="icon"><i class="fas fa-server"></i></span> SMTP Configuration</h2>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-server"></i>
                                SMTP Host
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="smtp_host" class="form-input" 
                                   value="<?php echo htmlspecialchars($email_settings['smtp_host'] ?? 'smtp.gmail.com'); ?>" 
                                   required>
                            <div class="form-help">SMTP server address (e.g., smtp.gmail.com)</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-plug"></i>
                                SMTP Port
                                <span class="required">*</span>
                            </label>
                            <input type="number" name="smtp_port" class="form-input" 
                                   value="<?php echo htmlspecialchars($email_settings['smtp_port'] ?? 587); ?>" 
                                   required>
                            <div class="form-help">Usually 587 for TLS, 465 for SSL</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user"></i>
                                SMTP Username
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="smtp_username" class="form-input" 
                                   value="<?php echo htmlspecialchars($email_settings['smtp_username'] ?? ''); ?>" 
                                   required>
                            <div class="form-help">Email address for authentication</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock"></i>
                                SMTP Password
                            </label>
                            <input type="password" name="smtp_password" class="form-input" 
                                   placeholder="●●●●●●●●●●●●">
                            <div class="form-help">Leave blank to keep current password</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-shield-alt"></i>
                                Encryption
                            </label>
                            <select name="smtp_secure" class="form-select">
                                <option value="tls" <?php echo ($email_settings['smtp_secure'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($email_settings['smtp_secure'] ?? 'tls') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="" <?php echo empty($email_settings['smtp_secure']) ? 'selected' : ''; ?>>None</option>
                            </select>
                            <div class="form-help">Encryption method</div>
                        </div>
                    </div>
                </div>
                
                <div class="settings-section">
                    <div class="section-header">
                        <h2><span class="icon"><i class="fas fa-paper-plane"></i></span> Email Content</h2>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-at"></i>
                                From Email
                                <span class="required">*</span>
                            </label>
                            <input type="email" name="email_from" class="form-input" 
                                   value="<?php echo htmlspecialchars($email_settings['email_from'] ?? ''); ?>" 
                                   required>
                            <div class="form-help">Sender email address</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-signature"></i>
                                From Name
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="email_from_name" class="form-input" 
                                   value="<?php echo htmlspecialchars($email_settings['email_from_name'] ?? APP_NAME); ?>" 
                                   required>
                            <div class="form-help">Sender display name</div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save"></i> Save Email Settings
                    </button>
                    <button type="button" class="btn btn-outline" onclick="testEmail()">
                        <i class="fas fa-vial"></i> Test Email Configuration
                    </button>
                </div>
            </form>
        </div>

        <!-- Security Settings -->
        <div class="tab-content" id="security-tab">
            <form method="POST" class="settings-form">
                <input type="hidden" name="action" value="update_security">
                
                <div class="settings-section">
                    <div class="section-header">
                        <h2><span class="icon"><i class="fas fa-user-lock"></i></span> Authentication</h2>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-ban"></i>
                                Max Login Attempts
                            </label>
                            <input type="number" name="max_login_attempts" class="form-input" 
                                   value="<?php echo htmlspecialchars($security_settings['max_login_attempts'] ?? 5); ?>" 
                                   min="1" max="10">
                            <div class="form-help">Maximum failed attempts before lockout</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-clock"></i>
                                Lockout Duration (minutes)
                            </label>
                            <input type="number" name="lockout_duration" class="form-input" 
                                   value="<?php echo htmlspecialchars($security_settings['lockout_duration'] ?? 15); ?>" 
                                   min="1" max="1440">
                            <div class="form-help">Account lockout duration in minutes</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-hourglass-end"></i>
                                Session Timeout (minutes)
                            </label>
                            <input type="number" name="session_timeout" class="form-input" 
                                   value="<?php echo htmlspecialchars($security_settings['session_timeout'] ?? 30); ?>" 
                                   min="1" max="1440">
                            <div class="form-help">Session expiration time in minutes</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope-check"></i>
                                Email Verification
                            </label>
                            <div class="checkbox-item">
                                <label class="switch">
                                    <input type="checkbox" name="require_email_verification" 
                                           <?php echo ($security_settings['require_email_verification'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="checkbox-label">Require email verification for registration</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="settings-section">
                    <div class="section-header">
                        <h2><span class="icon"><i class="fas fa-edit"></i></span> Content Management</h2>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-pen"></i>
                                Allow Complaint Editing
                            </label>
                            <div class="checkbox-item">
                                <label class="switch">
                                    <input type="checkbox" name="allow_complaint_editing" 
                                           <?php echo ($security_settings['allow_complaint_editing'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="checkbox-label">Allow students to edit their complaints</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save"></i> Save Security Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Notification Settings -->
        <div class="tab-content" id="notifications-tab">
            <form method="POST" class="settings-form">
                <input type="hidden" name="action" value="update_notifications">
                
                <div class="settings-section">
                    <div class="section-header">
                        <h2><span class="icon"><i class="fas fa-bell"></i></span> Notification Preferences</h2>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-plus-circle"></i>
                                New Complaints
                            </label>
                            <div class="checkbox-item">
                                <label class="switch">
                                    <input type="checkbox" name="notify_new_complaints" 
                                           <?php echo ($notification_settings['notify_new_complaints'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="checkbox-label">Notify admins about new complaints</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-sync-alt"></i>
                                Status Changes
                            </label>
                            <div class="checkbox-item">
                                <label class="switch">
                                    <input type="checkbox" name="notify_status_changes" 
                                           <?php echo ($notification_settings['notify_status_changes'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="checkbox-label">Notify students about status updates</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-times-circle"></i>
                                Rejection Notifications
                            </label>
                            <div class="checkbox-item">
                                <label class="switch">
                                    <input type="checkbox" name="notify_admin_on_reject" 
                                           <?php echo ($notification_settings['notify_admin_on_reject'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="checkbox-label">Notify super admin when complaints are rejected</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i>
                                Email Notifications
                            </label>
                            <div class="checkbox-item">
                                <label class="switch">
                                    <input type="checkbox" name="email_notifications" 
                                           <?php echo ($notification_settings['email_notifications'] ?? 1) ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <span class="checkbox-label">Enable email notifications</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save"></i> Save Notification Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Admin Management -->
        <div class="tab-content" id="admins-tab">
            <div class="settings-section">
                <div class="section-header">
                    <h2><span class="icon"><i class="fas fa-users"></i></span> System Administrators</h2>
                    <button type="button" class="btn btn-gradient btn-sm" onclick="openAddAdminModal()">
                        <i class="fas fa-plus"></i> Add Admin
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Permissions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                                <tr>
                                    <td>
                                        <div class="font-medium"><?php echo htmlspecialchars($admin['full_name']); ?></div>
                                        <div class="text-sm text-gray-500">ID: <?php echo $admin['id']; ?></div>
                                    </td>
                                    <td>@<?php echo htmlspecialchars($admin['username']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                    <td>
                                        <span class="role-badge">
                                            <?php echo ucfirst(str_replace('_', ' ', $admin['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" class="inline-form" onsubmit="return confirm('Are you sure?')">
                                            <input type="hidden" name="action" value="update_admin_status">
                                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                            <label class="switch">
                                                <input type="checkbox" name="is_active" 
                                                       <?php echo $admin['is_active'] ? 'checked' : ''; ?>
                                                       onchange="this.form.submit()">
                                                <span class="slider"></span>
                                            </label>
                                            <span class="status-badge <?php echo $admin['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $admin['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="permission-badges">
                                            <?php if ($admin['role'] === 'super_admin'): ?>
                                                <span class="permission-badge permission-yes">Full Access</span>
                                            <?php else: ?>
                                                <?php
                                                $adminPerms = [];
                                                if (empty($admin['permissions'])) {
                                                    // Backward-compatible: NULL/empty means unrestricted legacy admin
                                                    $adminPerms = array_keys(availableAdminPermissions());
                                                } else {
                                                    $decoded = json_decode($admin['permissions'], true);
                                                    if (is_array($decoded)) $adminPerms = $decoded;
                                                }
                                                $hasViewComplaints = in_array('view_complaints', $adminPerms, true);
                                                $hasManageComplaints = in_array('manage_complaints', $adminPerms, true);
                                                $hasReports = in_array('view_reports', $adminPerms, true);
                                                $hasCategories = in_array('manage_categories', $adminPerms, true);
                                                ?>
                                                <span class="permission-badge <?php echo $hasViewComplaints ? 'permission-yes' : 'permission-no'; ?>">View Complaints</span>
                                                <span class="permission-badge <?php echo $hasManageComplaints ? 'permission-yes' : 'permission-no'; ?>">Manage Complaints</span>
                                                <span class="permission-badge <?php echo $hasReports ? 'permission-yes' : 'permission-no'; ?>">Reports</span>
                                                <span class="permission-badge <?php echo $hasCategories ? 'permission-yes' : 'permission-no'; ?>">Categories</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex gap-2">
                                            <?php if ($admin['role'] !== 'super_admin'): ?>
                                                <button type="button" class="btn btn-outline btn-sm" 
                                                        onclick="openPermissionsModal(<?php echo $admin['id']; ?>)">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                                <form method="POST" onsubmit="return confirm('Delete this admin?')">
                                                    <input type="hidden" name="action" value="delete_admin">
                                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Backup & Restore -->
        <div class="tab-content" id="backup-tab">
            <div class="settings-section">
                <div class="section-header">
                    <h2><span class="icon"><i class="fas fa-database"></i></span> Database Management</h2>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <div class="glass-card p-6 text-center">
                            <i class="fas fa-download text-4xl mb-4" style="color: #667eea;"></i>
                            <h3 class="text-xl font-semibold mb-2">Create Backup</h3>
                            <p class="text-gray-500 mb-4">Backup your database to a SQL file</p>
                            <button type="button" class="btn btn-gradient" onclick="createBackup()">
                                <i class="fas fa-download"></i> Backup Database
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="glass-card p-6 text-center">
                            <i class="fas fa-upload text-4xl mb-4" style="color: #48bb78;"></i>
                            <h3 class="text-xl font-semibold mb-2">Restore Backup</h3>
                            <p class="text-gray-500 mb-4">Restore database from SQL file</p>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="restore_backup">
                                <input type="file" name="backup_file" accept=".sql,.gz" class="form-input mb-3" required>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-upload"></i> Restore Backup
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="settings-section">
                    <div class="section-header">
                        <h2><span class="icon"><i class="fas fa-history"></i></span> Recent Backups</h2>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>File Name</th>
                                    <th>Date</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $backup_dir = '../../backups/';
                                if (is_dir($backup_dir)) {
                                    $files = glob($backup_dir . '*.sql');
                                    foreach ($files as $file) {
                                        $filename = basename($file);
                                        $filetime = date('Y-m-d H:i:s', filemtime($file));
                                        $filesize = filesize($file);
                                        $filesize_human = $filesize > 1024 * 1024 ? 
                                            round($filesize / (1024 * 1024), 2) . ' MB' : 
                                            round($filesize / 1024, 2) . ' KB';
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($filename); ?></td>
                                            <td><?php echo $filetime; ?></td>
                                            <td><?php echo $filesize_human; ?></td>
                                            <td>
                                                <div class="flex gap-2">
                                                    <a href="<?php echo APP_URL . '/backups/' . $filename; ?>" 
                                                       download class="btn btn-outline btn-sm">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-danger btn-sm" 
                                                            onclick="deleteBackup('<?php echo $filename; ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal" id="addAdminModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Admin</h3>
                <button type="button" class="modal-close" onclick="closeModal('addAdminModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addAdminForm">
                    <input type="hidden" name="action" value="add_admin">
                    
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Role *</label>
                        <select name="role" class="form-select" required>
                            <option value="super_admin">Super Admin</option>
                            <option value="category_admin">Category Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-input" required minlength="8">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-input" required minlength="8">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addAdminModal')">
                    Cancel
                </button>
                <button type="submit" form="addAdminForm" class="btn btn-gradient">
                    Add Admin
                </button>
            </div>
        </div>
    </div>

    <!-- Permissions Modal -->
    <div class="modal" id="permissionsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Permissions</h3>
                <button type="button" class="modal-close" onclick="closeModal('permissionsModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form method="POST" id="permissionsForm">
                    <input type="hidden" name="admin_id" id="permissionAdminId">
                    <div id="permissionsFields"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('permissionsModal')">
                    Cancel
                </button>
                <button type="submit" form="permissionsForm" class="btn btn-gradient">
                    Save Permissions
                </button>
            </div>
        </div>
    </div>

    <script>
        // Tab Switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.dataset.tab;
                
                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Show active tab content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });
        
        // Modal Functions
        function openAddAdminModal() {
            document.getElementById('addAdminModal').classList.add('active');
        }
        
        function openPermissionsModal(adminId) {
            document.getElementById('permissionAdminId').value = adminId;
            document.getElementById('permissionsModal').classList.add('active');
            
            // Load current permissions via AJAX
            fetch(`<?php echo APP_URL; ?>/api/admin_permissions.php?action=get_permissions&admin_id=${adminId}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('permissionsFields');
                    if (!container) return;

                    if (!data.success) {
                        container.innerHTML = `<div class="form-help">${data.message || 'Failed to load permissions'}</div>`;
                        return;
                    }

                    const selected = new Set(Array.isArray(data.permissions) ? data.permissions : []);
                    const available = data.available || {};
                    const keys = Object.keys(available);

                    container.innerHTML = keys.map(key => {
                        const meta = available[key] || {};
                        const label = meta.label || key;
                        const desc = meta.description || '';
                        const inputId = `perm_${key}`;
                        const checked = selected.has(key) ? 'checked' : '';

                        return `
                            <div class="form-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" name="permissions[]" value="${key}" id="${inputId}" ${checked}>
                                    <label for="${inputId}" class="checkbox-label">${label}</label>
                                </div>
                                ${desc ? `<div class="form-help">${desc}</div>` : ''}
                            </div>
                        `;
                    }).join('');
                })
                .catch(err => {
                    const container = document.getElementById('permissionsFields');
                    if (container) container.innerHTML = `<div class="form-help">Failed to load permissions</div>`;
                    console.error(err);
                });
        }

        // Save permissions (AJAX)
        document.getElementById('permissionsForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const adminId = document.getElementById('permissionAdminId')?.value;
            if (!adminId) return;

            const perms = Array.from(this.querySelectorAll('input[name="permissions[]"]:checked')).map(i => i.value);

            fetch(`<?php echo APP_URL; ?>/api/admin_permissions.php?action=update_permissions`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({admin_id: parseInt(adminId, 10), permissions: perms})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message || 'Permissions updated', 'success');
                    closeModal('permissionsModal');
                    setTimeout(() => location.reload(), 600);
                } else {
                    showNotification(data.message || 'Failed to update permissions', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showNotification('Network error updating permissions', 'error');
            });
        });
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Test Email Configuration
        function testEmail() {
            fetch('<?php echo APP_URL; ?>/api/test_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    test_email: '<?php echo $_SESSION['admin_email'] ?? ''; ?>',
                    csrf_token: '<?php echo generateCSRFToken(); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Test email sent successfully!', 'success');
                } else {
                    showNotification('Failed to send test email: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Error: ' + error.message, 'error');
            });
        }
        
        // Create Backup
        // function createBackup() {
        //     fetch('<?php //echo APP_URL; ?>/api/create_backup.php')
        //         .then(response => response.json())
        //         .then(data => {
        //             if (data.success) {
        //                 showNotification('Backup created successfully!', 'success');
        //                 setTimeout(() => location.reload(), 2000);
        //             } else {
        //                 showNotification('Failed to create backup: ' + data.message, 'error');
        //             }
        //         });
        // }

        function createBackup() {
    fetch('<?php echo APP_URL; ?>/api/create_backup.php')
        .then(response => {
            if (response.ok) {
                // Trigger download
                return response.blob().then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'backup.sql';
                    a.click();
                    showNotification('Backup downloaded!', 'success');
                });
            } else {
                response.json().then(data => {
                    showNotification('Failed: ' + data.message, 'error');
                });
            }
        });
}

        
        // Delete Backup
        function deleteBackup(filename) {
            if (confirm('Delete backup file: ' + filename + '?')) {
                fetch('<?php echo APP_URL; ?>/api/delete_backup.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ filename: filename })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Backup deleted successfully!', 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showNotification('Failed to delete backup: ' + data.message, 'error');
                    }
                });
            }
        }
        
        // Notification function
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
    </script>
</body>
</html>
