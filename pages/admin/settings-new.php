<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/utilities/helpers.php';
require_once '../../includes/auth/session.inc.php';

// Check if user is super admin
if (!isAdmin() || $_SESSION['admin_role'] !== 'super_admin') {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

$success_msg = '';
$error_msg = '';

// Handle setting updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_settings') {
        try {
            db()->beginTransaction();
            
            // Collect all settings to update
            $settings_to_update = [
                'site_name' => sanitizeInput($_POST['site_name'] ?? ''),
                'site_description' => sanitizeInput($_POST['site_description'] ?? ''),
                'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
                'require_verification' => isset($_POST['require_verification']) ? '1' : '0',
                'complaint_auto_publish' => isset($_POST['complaint_auto_publish']) ? '1' : '0',
                'max_complaints_per_day' => intval($_POST['max_complaints_per_day'] ?? 5),
                'theme_mode' => sanitizeInput($_POST['theme_mode'] ?? 'auto'),
                'otp_enabled' => isset($_POST['otp_enabled']) ? '1' : '0',
                'otp_expiry_minutes' => intval($_POST['otp_expiry_minutes'] ?? 10),
                'otp_max_attempts' => intval($_POST['otp_max_attempts'] ?? 5),
                'notifications_per_page' => intval($_POST['notifications_per_page'] ?? 7),
            ];
            
            // Update each setting in the database
            foreach ($settings_to_update as $key => $value) {
                $stmt = db()->prepare("
                    INSERT INTO settings (setting_key, setting_value, updated_at)
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        setting_value = VALUES(setting_value),
                        updated_at = NOW()
                ");
                if (!$stmt->execute([$key, $value])) {
                    throw new Exception("Failed to update setting: $key");
                }
            }
            
            db()->commit();
            
            // Clear settings cache so new values are loaded
            // if (function_exists('clearSettingsCache')) {
            //     clearSettingsCache();
            // }
            
            $success_msg = 'Settings updated successfully!';
            logActivity('SETTINGS_UPDATED', 'System settings updated', $_SESSION['admin_id'], 'admin');
            
        } catch (Exception $e) {
            db()->rollBack();
            error_log("Settings update error: " . $e->getMessage());
            $error_msg = 'Failed to update settings: ' . $e->getMessage();
        }
    }
}

// Load current settings
$settings = [];
try {
    $stmt = db()->prepare("SELECT setting_key, setting_value FROM settings");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Error loading settings: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Dashboard</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .settings-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .settings-section { background: var(--glass-bg); padding: 2rem; border-radius: 12px; border: 1px solid var(--glass-border); }
        .settings-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        .form-input, .form-select { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--input-border); }
        .form-check { display: flex; align-items: center; gap: 0.75rem; }
        .form-check input[type="checkbox"] { width: 20px; height: 20px; }
        .btn-save { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 0.75rem 2rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3); }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .alert-success { background: #f0fdf4; border: 1px solid #22c55e; color: #166534; }
        .alert-error { background: #fef2f2; border: 1px solid #ef4444; color: #991b1b; }
        @media (max-width: 768px) { .settings-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php include '../../includes/layout/admin-nav.php'; ?>

<div class="settings-container">
    <h1><i class="fas fa-cog"></i> System Settings</h1>
    
    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>
    
    <?php if ($error_msg): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>
    
    <form method="POST" class="settings-grid">
        <input type="hidden" name="action" value="save_settings">
        
        <!-- General Settings -->
        <div class="settings-section">
            <h2 class="settings-title"><i class="fas fa-sliders-h"></i> General</h2>
            
            <div class="form-group">
                <label class="form-label">Site Name</label>
                <input type="text" name="site_name" class="form-input" value="<?php echo htmlspecialchars($settings['site_name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Site Description</label>
                <textarea name="site_description" class="form-input" rows="3"><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Theme Mode</label>
                <select name="theme_mode" class="form-select">
                    <option value="light" <?php echo ($settings['theme_mode'] ?? 'auto') === 'light' ? 'selected' : ''; ?>>Light</option>
                    <option value="dark" <?php echo ($settings['theme_mode'] ?? 'auto') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                    <option value="auto" <?php echo ($settings['theme_mode'] ?? 'auto') === 'auto' ? 'selected' : ''; ?>>Auto</option>
                </select>
            </div>
        </div>
        
        <!-- Registration & Verification -->
        <div class="settings-section">
            <h2 class="settings-title"><i class="fas fa-user-shield"></i> Registration</h2>
            
            <div class="form-group form-check">
                <input type="checkbox" id="allow_registration" name="allow_registration" <?php echo ($settings['allow_registration'] ?? '1') === '1' ? 'checked' : ''; ?>>
                <label for="allow_registration" class="form-label">Allow New Registration</label>
            </div>
            
            <div class="form-group form-check">
                <input type="checkbox" id="require_verification" name="require_verification" <?php echo ($settings['require_verification'] ?? '1') === '1' ? 'checked' : ''; ?>>
                <label for="require_verification" class="form-label">Require Email Verification</label>
            </div>
            
            <div class="form-group form-check">
                <input type="checkbox" id="otp_enabled" name="otp_enabled" <?php echo ($settings['otp_enabled'] ?? '1') === '1' ? 'checked' : ''; ?>>
                <label for="otp_enabled" class="form-label">Enable OTP Verification</label>
            </div>
            
            <div class="form-group">
                <label class="form-label">OTP Expiry (minutes)</label>
                <input type="number" name="otp_expiry_minutes" class="form-input" value="<?php echo htmlspecialchars($settings['otp_expiry_minutes'] ?? '10'); ?>" min="1" max="60">
            </div>
            
            <div class="form-group">
                <label class="form-label">Max OTP Attempts</label>
                <input type="number" name="otp_max_attempts" class="form-input" value="<?php echo htmlspecialchars($settings['otp_max_attempts'] ?? '5'); ?>" min="1" max="10">
            </div>
        </div>
        
        <!-- Complaints Settings -->
        <div class="settings-section">
            <h2 class="settings-title"><i class="fas fa-comments"></i> Complaints</h2>
            
            <div class="form-group form-check">
                <input type="checkbox" id="complaint_auto_publish" name="complaint_auto_publish" <?php echo ($settings['complaint_auto_publish'] ?? '0') === '1' ? 'checked' : ''; ?>>
                <label for="complaint_auto_publish" class="form-label">Auto-Publish After Approval</label>
            </div>
            
            <div class="form-group">
                <label class="form-label">Max Complaints Per Day</label>
                <input type="number" name="max_complaints_per_day" class="form-input" value="<?php echo htmlspecialchars($settings['max_complaints_per_day'] ?? '5'); ?>" min="1" max="100">
            </div>
        </div>
        
        <!-- UI Settings -->
        <div class="settings-section">
            <h2 class="settings-title"><i class="fas fa-layout"></i> User Interface</h2>
            
            <div class="form-group">
                <label class="form-label">Notifications Per Page</label>
                <input type="number" name="notifications_per_page" class="form-input" value="<?php echo htmlspecialchars($settings['notifications_per_page'] ?? '7'); ?>" min="1" max="50">
            </div>
            
            <div class="form-group" style="margin-top: 2rem;">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save All Settings
                </button>
            </div>
        </div>
    </form>
</div>

</body>
</html>
