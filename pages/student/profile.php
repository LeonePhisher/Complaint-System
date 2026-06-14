<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../includes/utilities/helpers.php';

// Check if user is logged in as student
if (!isStudent()) {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$success = '';
$error = '';

// Load student data
$student = [];
$stats = [];
try {
    // Get student details
    $stmt = db()->prepare("
        SELECT s.*, 
               (SELECT COUNT(*) FROM complaints WHERE user_id = s.id) as total_complaints,
               (SELECT MAX(created_at) FROM complaints WHERE user_id = s.id) as last_complaint_date
        FROM users s 
        WHERE s.id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        session_destroy();
        header('Location: ' . APP_URL . '/pages/auth/login.php');
        exit();
    }
    
    // Get complaint statistics
    $stmt = db()->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM complaints 
        WHERE user_id = ?
        GROUP BY status
    ");
    $stmt->execute([$student_id]);
    $status_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stats = [
        'total' => $student['total_complaints'] ?? 0,
        'pending' => $status_stats['pending'] ?? 0,
        'published' => $status_stats['published'] ?? 0,
        'under_review' => $status_stats['under_review'] ?? 0,
        'resolved' => $status_stats['resolved'] ?? 0,
        'rejected' => $status_stats['rejected'] ?? 0
    ];
    
} catch (PDOException $e) {
    error_log("Error loading student profile: " . $e->getMessage());
    $error = 'Unable to load profile data. Please try again.';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // CSRF protection
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid security token. Please try again.';
        } else {
            try {
                $full_name = sanitizeInput($_POST['full_name']);
                $phone = sanitizeInput($_POST['phone'] ?? '');
                $department = sanitizeInput($_POST['department'] ?? '');
                $level = sanitizeInput($_POST['level'] ?? '');
                
                // Validate
                if (empty($full_name)) {
                    $error = 'Full name is required';
                } else {
                    $stmt = db()->prepare("
                        UPDATE users SET 
                        full_name = ?,
                        phone = ?,
                        department = ?,
                        level = ?,
                        updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$full_name, $phone, $department, $level, $student_id]);
                    
                    // Update session
                    $_SESSION['student_name'] = $full_name;
                    $_SESSION['user_name'] = $full_name;
                    
                    $success = 'Profile updated successfully!';

                    // Reload student data (fresh statement; UPDATE statement cannot be reused)
                    $stmt = db()->prepare("
                        SELECT s.*, 
                               (SELECT COUNT(*) FROM complaints WHERE user_id = s.id) as total_complaints,
                               (SELECT MAX(created_at) FROM complaints WHERE user_id = s.id) as last_complaint_date
                        FROM users s 
                        WHERE s.id = ?
                    ");
                    $stmt->execute([$student_id]);
                    $student = $stmt->fetch();
                }
                
            } catch (PDOException $e) {
                error_log("Profile update error: " . $e->getMessage());
                $error = 'Failed to update profile. Please try again.';
            }
        }
        
    } elseif ($action === 'send_password_otp' || $action === 'change_password_otp') {
        // CSRF protection
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid security token. Please try again.';
        } else {
            try {
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                $otp_raw = (string)($_POST['otp_code'] ?? '');
                // digits-only, ignore spaces/hyphens from paste/autofill
                $otp_entered = preg_replace('/\\D+/', '', $otp_raw);

                // Verify current password and load OTP fields
                $stmt = db()->prepare("SELECT id, email, full_name, password_hash, otp_code, otp_expires, otp_attempts FROM users WHERE id = ?");
                $stmt->execute([$student_id]);
                $result = $stmt->fetch();

                if (!$result || !password_verify($current_password, $result['password_hash'])) {
                    $error = 'Current password is incorrect';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New passwords do not match';
                } elseif (strlen($new_password) < 8) {
                    $error = 'New password must be at least 8 characters long';
                } else {
                    $otp_length = (int)getSetting('otp_length', 6);
                    $otp_minutes = (int)getSetting('otp_expiry_minutes', 10);
                    $otp_max_attempts = (int)getSetting('otp_max_attempts', 5);
                    if ($otp_length < 4) $otp_length = 4;
                    if ($otp_length > 10) $otp_length = 10;

                    if ($action === 'send_password_otp') {
                        $otp_code = generateOTP($otp_length > 0 ? $otp_length : 6);
                        $otp_expires = date('Y-m-d H:i:s', strtotime("+{$otp_minutes} minutes"));

                        $stmt = db()->prepare("UPDATE users SET otp_code = ?, otp_expires = ?, otp_attempts = 0 WHERE id = ?");
                        $stmt->execute([$otp_code, $otp_expires, $student_id]);

                        // Log to otp_history (purpose enum supports password_reset)
                        try {
                            $stmt = db()->prepare("INSERT INTO otp_history (user_id, otp_code, purpose, expires_at, ip_address) VALUES (?, ?, 'password_reset', ?, ?)");
                            $stmt->execute([$student_id, $otp_code, $otp_expires, $_SERVER['REMOTE_ADDR'] ?? '']);
                        } catch (PDOException $e) {
                            // non-fatal
                            error_log("OTP history insert failed: " . $e->getMessage());
                        }

                        $mail_sent = sendPasswordChangeOTPEmail($result['email'], $result['full_name'], $otp_code);
                        if ($mail_sent) {
                            $success = 'We sent a verification code to your email. Enter it below to confirm the password change.';
                        } else {
                            $error = 'Failed to send OTP email. Please try again.';
                        }
                    } else {
                        // change_password_otp
                        if (empty($otp_entered)) {
                            $error = 'Enter the verification code sent to your email';
                        } elseif (!preg_match('/^\\d{' . $otp_length . '}$/', $otp_entered)) {
                            $error = "Verification code must be {$otp_length} digits";
                        } elseif (empty($result['otp_code']) || empty($result['otp_expires'])) {
                            $error = 'No active verification code. Click "Send OTP" first.';
                        } elseif ((int)$result['otp_attempts'] >= $otp_max_attempts) {
                            $error = 'Too many incorrect attempts. Please request a new code.';
                        } elseif (strtotime($result['otp_expires']) < time()) {
                            $error = 'Verification code has expired. Please request a new code.';
                        } elseif (!hash_equals((string)$result['otp_code'], (string)$otp_entered)) {
                            $new_attempts = ((int)$result['otp_attempts']) + 1;
                            $stmt = db()->prepare("UPDATE users SET otp_attempts = ? WHERE id = ?");
                            $stmt->execute([$new_attempts, $student_id]);
                            $error = 'Incorrect verification code';
                        } else {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                            $stmt = db()->prepare("UPDATE users SET password_hash = ?, otp_code = NULL, otp_expires = NULL, otp_attempts = 0, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$hashed_password, $student_id]);

                            // Mark latest otp_history row as used (best-effort)
                            try {
                                $stmt = db()->prepare("UPDATE otp_history SET is_used = 1, used_at = NOW() WHERE user_id = ? AND otp_code = ? AND purpose = 'password_reset' AND is_used = 0 ORDER BY id DESC LIMIT 1");
                                $stmt->execute([$student_id, $result['otp_code']]);
                            } catch (PDOException $e) {
                                error_log("OTP history update failed: " . $e->getMessage());
                            }

                            logActivity('password_changed', 'Changed account password (OTP confirmed)', $student_id, 'student');
                            $success = 'Password changed successfully!';
                        }
                    }
                }

            } catch (PDOException $e) {
                error_log("Password change error: " . $e->getMessage());
                $error = 'Failed to change password. Please try again.';
            }
        }

    } elseif ($action === 'update_notifications') {
        // CSRF protection
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $error = 'Invalid security token. Please try again.';
        } else {
            try {
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $status_updates = isset($_POST['status_updates']) ? 1 : 0;
                $new_comments = isset($_POST['new_comments']) ? 1 : 0;
                $announcements = isset($_POST['announcements']) ? 1 : 0;
                
                $stmt = db()->prepare("
                    UPDATE student_preferences SET 
                    email_notifications = ?,
                    status_updates = ?,
                    new_comments = ?,
                    announcements = ?,
                    updated_at = NOW()
                    WHERE student_id = ?
                ");
                $stmt->execute([$email_notifications, $status_updates, $new_comments, $announcements, $student_id]);
                
                $success = 'Notification preferences updated successfully!';
                
            } catch (PDOException $e) {
                error_log("Notification update error: " . $e->getMessage());
                $error = 'Failed to update notifications. Please try again.';
            }
        }
    }
}

// Load notification preferences
$preferences = [
    'email_notifications' => 1,
    'status_updates' => 1,
    'new_comments' => 1,
    'announcements' => 1
];

try {
    $stmt = db()->prepare("SELECT * FROM student_preferences WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $prefs = $stmt->fetch();
    if ($prefs) {
        $preferences = $prefs;
    }
} catch (PDOException $e) {
    error_log("Error loading preferences: " . $e->getMessage());
}

// Generate CSRF tokens
$profile_token = generateCsrfToken();
$password_token = generateCsrfToken();
$notifications_token = generateCsrfToken();

// OTP UI length (keep in sync with settings/back-end). Clamp to a sensible range for patterns/maxlength.
$otp_len_ui = (int)getSetting('otp_length', 6);
if ($otp_len_ui < 4) $otp_len_ui = 4;
if ($otp_len_ui > 10) $otp_len_ui = 10;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta name="app-url" content="/complaint-system">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .profile-container {
            padding: 2rem;
            max-width: 1200px;
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

        /*=============== DARK THEME CODE HERE========== */
        
/* Dark Theme Variables */
* {
    --primary-color: #7c93fb;
    --primary-dark: #667eea;
    --secondary-color: #9f7aea;
    
    /* Background Colors */
    --bg-primary: #1a202c;
    --bg-secondary: #2d3748;
    --bg-tertiary: #4a5568;
    
    /* Text Colors */
    --text-primary: #f7fafc;
    --text-secondary: #e2e8f0;
    --text-muted: #a0aec0;
    
    /* Border Colors */
    --border-color: #4a5568;
    --border-light: #2d3748;
    
    /* Shadow */
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.25);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.25);
    --shadow-xl: 0 20px 25px rgba(0,0,0,0.3);
    
    /* Glassmorphism */
    --glass-bg: rgba(26, 32, 44, 0.95);
    --glass-border: rgba(255, 255, 255, 0.1);
}

        .page-header p {
            color: var(--text-secondary);
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
        }

        @media (max-width: 1024px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Sidebar */
        .profile-sidebar {
            position: sticky;
            top: 2rem;
            height: fit-content;
        }

        .profile-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--glass-border);
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 1.5rem;
        }

        .avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .avatar-edit {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 3px solid var(--bg-primary);
            transition: all 0.2s ease;
        }

        .avatar-edit:hover {
            transform: scale(1.1);
            background: var(--secondary-color);
        }

        .student-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .student-index {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .student-info {
            text-align: left;
            margin-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .info-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .info-value {
            font-weight: 500;
            color: var(--text-primary);
        }

        /* Stats Card */
        .stats-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--glass-border);
        }

        .stats-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
        }

        .stat-item:hover {
            background: var(--bg-tertiary);
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Main Content */
        .profile-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .content-section {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--glass-border);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--primary-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-label .required {
            color: #f56565;
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

        .form-help {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
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
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .checkbox-label {
            color: var(--text-primary);
            font-size: 0.875rem;
            cursor: pointer;
        }

        .danger-zone {
            border: 2px solid rgba(245, 101, 101, 0.3);
            background: rgba(245, 101, 101, 0.05);
        }

        .danger-header {
            border-bottom-color: rgba(245, 101, 101, 0.3);
        }

        .danger-title {
            color: #f56565;
        }

        .danger-text {
            color: #f56565;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .danger-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Activity Log */
        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .activity-item:hover {
            background: var(--bg-secondary);
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .empty-activity {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        /* Verification Status */
        .verification-status {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
        }

        .status-verified {
            background: rgba(72, 187, 120, 0.1);
            border: 1px solid rgba(72, 187, 120, 0.2);
        }

        .status-pending {
            background: rgba(237, 137, 54, 0.1);
            border: 1px solid rgba(237, 137, 54, 0.2);
        }

        .status-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
        }

        .status-verified .status-icon {
            background: #48bb78;
        }

        .status-pending .status-icon {
            background: #ed8936;
        }

        .status-text {
            flex: 1;
            font-size: 0.875rem;
        }

        .status-verified .status-text {
            color: #48bb78;
        }

        .status-pending .status-text {
            color: #ed8936;
        }

        /* Loading States */
        .skeleton {
            background: linear-gradient(90deg, var(--bg-secondary) 25%, var(--bg-tertiary) 50%, var(--bg-secondary) 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: var(--radius-md);
        }

        .skeleton-text {
            height: 1rem;
            margin-bottom: 0.5rem;
        }

        .skeleton-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-container {
                padding: 1rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .danger-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 640px) {
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .checkbox-group {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Student Navigation -->
    <?php include '../../includes/layout/student-nav.php'; ?>

    <!-- Main Content -->
    <div class="profile-container">
        <!-- Header -->
        <div class="page-header">
            <h1>My Profile</h1>
            <p>Manage your account settings and preferences</p>
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

        <!-- Profile Grid -->
        <div class="profile-grid">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="avatar-container">
                        <div class="avatar" style="background: <?php echo htmlspecialchars($student['avatar_color'] ?? '#667eea'); ?>;">
                            <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                        </div>
                        <div class="avatar-edit" onclick="document.getElementById('avatarColorPicker')?.click()">
                            <i class="fas fa-palette"></i>
                        </div>
                        <input type="color"
                               id="avatarColorPicker"
                               value="<?php echo htmlspecialchars($student['avatar_color'] ?? '#667eea'); ?>"
                               style="display: none;"
                               onchange="updateAvatarColor(this.value)">
                    </div>
                    
                    <h2 class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></h2>
                    <div class="student-index"><?php echo htmlspecialchars($student['index_number']); ?></div>
                    
                    <!-- Verification Status -->
                    <div class="verification-status <?php echo $student['is_verified'] ? 'status-verified' : 'status-pending'; ?>">
                        <div class="status-icon">
                            <i class="fas fa-<?php echo $student['is_verified'] ? 'check' : 'clock'; ?>"></i>
                        </div>
                        <div class="status-text">
                            <?php echo $student['is_verified'] ? 'Email Verified' : 'Email Verification Pending'; ?>
                        </div>
                    </div>
                    
                    <div class="student-info">
                        <div class="info-item">
                            <span class="info-label">Member Since</span>
                            <span class="info-value"><?php echo date('M Y', strtotime($student['created_at'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Last Login</span>
                            <span class="info-value">
                                <?php echo !empty($student['last_login']) ? date('M j, g:i A', strtotime($student['last_login'])) : 'Never'; ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status</span>
                            <?php $is_active = (($student['account_status'] ?? '') === 'active'); ?>
                            <span class="info-value <?php echo $is_active ? 'text-green-500' : 'text-red-500'; ?>">
                                <?php echo $is_active ? 'Active' : htmlspecialchars(ucfirst((string)($student['account_status'] ?? 'inactive'))); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Card -->
                <div class="stats-card">
                    <h3 class="stats-title">Complaint Statistics</h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['total']; ?></div>
                            <div class="stat-label">Total</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['pending']; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['published']; ?></div>
                            <div class="stat-label">Published</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['resolved']; ?></div>
                            <div class="stat-label">Resolved</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="profile-content">
                <!-- Personal Information -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-user"></i>
                            Personal Information
                        </h3>
                        <span class="text-sm text-gray-500">Last updated: <?php echo date('M j, Y', strtotime($student['updated_at'])); ?></span>
                    </div>
                    
                    <form method="POST" id="profileForm">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="csrf_token" value="<?php echo $profile_token; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    Full Name <span class="required">*</span>
                                </label>
                                <input type="text" name="full_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                                <div class="form-help">Your full legal name</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Index Number</label>
                                <input type="text" class="form-input" 
                                       value="<?php echo htmlspecialchars($student['index_number']); ?>" disabled>
                                <div class="form-help">Cannot be changed</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-input" 
                                       value="<?php echo htmlspecialchars($student['email']); ?>" disabled>
                                <div class="form-help">Contact support to change email</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" 
                                       pattern="[0-9]{10,15}">
                                <div class="form-help">Optional for contact purposes</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" class="form-input" 
                                       value="<?php echo htmlspecialchars($student['department'] ?? ''); ?>">
                                <div class="form-help">e.g., Computer Science, Engineering</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Level</label>
                                <select name="level" class="form-select">
                                    <option value="">Select Level</option>
                                    <?php
                                        $levels = ['100','200','300','400','500','600'];
                                        foreach ($levels as $lvl) {
                                            $sel = (($student['level'] ?? '') == $lvl) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($lvl, ENT_QUOTES) . "\" $sel>" . htmlspecialchars($lvl) . '</option>';
                                        }
                                    ?>
                                </select>
                                <div class="form-help">Your current academic level</div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button type="submit" class="btn btn-gradient">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security & Password -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-shield-alt"></i>
                            Security & Password
                        </h3>
                    </div>
                    
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $password_token; ?>">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    Current Password <span class="required">*</span>
                                </label>
                                <input type="password" name="current_password" class="form-input" required>
                                <div class="form-help">Enter your current password</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    New Password <span class="required">*</span>
                                </label>
                                <input type="password" name="new_password" id="newPassword" class="form-input" required minlength="8">
                                <div class="password-strength">
                                    <div class="strength-bar" id="passwordStrength"></div>
                                </div>
                                <div class="form-help">Minimum 8 characters with letters and numbers</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    Confirm New Password <span class="required">*</span>
                                </label>
                                <input type="password" name="confirm_password" class="form-input" required minlength="8">
                                <div class="form-help">Re-enter your new password</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Email OTP (<?php echo (int)$otp_len_ui; ?> digits)
                                </label>
                                <input type="text"
                                       name="otp_code"
                                       id="passwordOtp"
                                       class="form-input"
                                       inputmode="numeric"
                                       autocapitalize="off"
                                       autocorrect="off"
                                       spellcheck="false"
                                       autocomplete="one-time-code"
                                       placeholder="Enter the code sent to your email"
                                       pattern="[0-9]{<?php echo (int)$otp_len_ui; ?>}"
                                       minlength="<?php echo (int)$otp_len_ui; ?>"
                                       maxlength="<?php echo (int)$otp_len_ui; ?>"
                                       aria-describedby="passwordOtpHelp">
                                <div class="form-help" id="passwordOtpHelp">Click "Send OTP" first, then enter the code to confirm</div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button type="submit" name="action" value="send_password_otp" class="btn btn-outline" style="margin-right: 0.5rem;">
                                <i class="fas fa-envelope"></i> Send OTP
                            </button>
                            <button type="submit" name="action" value="change_password_otp" class="btn btn-gradient">
                                <i class="fas fa-key"></i> Confirm & Change
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Notification Preferences -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-bell"></i>
                            Notification Preferences
                        </h3>
                    </div>
                    
                    <form method="POST" id="notificationsForm">
                        <input type="hidden" name="action" value="update_notifications">
                        <input type="hidden" name="csrf_token" value="<?php echo $notifications_token; ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Email Notifications</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" name="email_notifications" id="email_notifications" 
                                           class="checkbox-input" <?php echo $preferences['email_notifications'] ? 'checked' : ''; ?>>
                                    <label for="email_notifications" class="checkbox-label">
                                        Receive email notifications
                                    </label>
                                </div>
                            </div>
                            <div class="form-help">Receive notifications via email</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Notification Types</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" name="status_updates" id="status_updates" 
                                           class="checkbox-input" <?php echo $preferences['status_updates'] ? 'checked' : ''; ?>>
                                    <label for="status_updates" class="checkbox-label">
                                        Complaint status updates
                                    </label>
                                </div>
                                
                                <div class="checkbox-item">
                                    <input type="checkbox" name="new_comments" id="new_comments" 
                                           class="checkbox-input" <?php echo $preferences['new_comments'] ? 'checked' : ''; ?>>
                                    <label for="new_comments" class="checkbox-label">
                                        New comments on complaints
                                    </label>
                                </div>
                                
                                <div class="checkbox-item">
                                    <input type="checkbox" name="announcements" id="announcements" 
                                           class="checkbox-input" <?php echo $preferences['announcements'] ? 'checked' : ''; ?>>
                                    <label for="announcements" class="checkbox-label">
                                        System announcements
                                    </label>
                                </div>
                            </div>
                            <div class="form-help">Choose what type of notifications to receive</div>
                        </div>
                        
                        <div class="flex justify-end mt-6">
                            <button type="submit" class="btn btn-gradient">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Recent Activity -->
                <div class="content-section">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-history"></i>
                            Recent Activity
                        </h3>
                    </div>
                    
                    <div class="activity-list" id="activityList">
                        <!-- Activity will be loaded via AJAX -->
                        <div class="skeleton skeleton-text"></div>
                        <div class="skeleton skeleton-text" style="width: 80%;"></div>
                        <div class="skeleton skeleton-text" style="width: 60%;"></div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="content-section danger-zone">
                    <div class="section-header danger-header">
                        <h3 class="section-title danger-title">
                            <i class="fas fa-exclamation-triangle"></i>
                            Danger Zone
                        </h3>
                    </div>
                    
                    <p class="danger-text">
                        These actions are irreversible. Please proceed with caution.
                    </p>
                    
                    <div class="danger-actions">
                        <button type="button" class="btn btn-outline-danger" onclick="requestDataExport()">
                            <i class="fas fa-download"></i> Export My Data
                        </button>
                        
                        <button type="button" class="btn btn-outline-danger" onclick="deactivateAccount()">
                            <i class="fas fa-user-slash"></i> Deactivate Account
                        </button>
                        
                        <button type="button" class="btn btn-danger" onclick="deleteAccount()">
                            <i class="fas fa-trash"></i> Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Account Actions -->
    <div class="modal" id="accountModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Account Action</h3>
                <button type="button" class="modal-close" onclick="closeModal('accountModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Content loaded dynamically -->
            </div>
            <div class="modal-footer" id="modalFooter">
                <!-- Footer loaded dynamically -->
            </div>
        </div>
    </div>

    <script>
        // Password strength checker
        const passwordInput = document.getElementById('newPassword');
        const strengthBar = document.getElementById('passwordStrength');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            
            // Complexity checks
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Update strength bar
            strengthBar.className = 'strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });
        
        // Load activity via AJAX
        function loadActivity() {
            fetch('<?php echo APP_URL; ?>/api/student_activity.php?student_id=<?php echo $student_id; ?>')
                .then(response => response.json())
                .then(data => {
                    const activityList = document.getElementById('activityList');
                    
                    if (data.success && data.activities.length > 0) {
                        let html = '';
                        data.activities.forEach(activity => {
                            const iconMap = {
                                'complaint_submitted': 'fas fa-plus-circle',
                                'complaint_updated': 'fas fa-edit',
                                'status_changed': 'fas fa-sync-alt',
                                'comment_added': 'fas fa-comment',
                                'password_changed': 'fas fa-key',
                                'profile_updated': 'fas fa-user-edit',
                                'login': 'fas fa-sign-in-alt',
                                'logout': 'fas fa-sign-out-alt'
                            };
                            
                            html += `
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="${iconMap[activity.action] || 'fas fa-bell'}"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-text">${activity.description}</div>
                                        <div class="activity-time">
                                            ${new Date(activity.created_at).toLocaleString()} - 
                                            ${activity.ip_address ? 'IP: ' + activity.ip_address : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                        
                        activityList.innerHTML = html;
                    } else {
                        activityList.innerHTML = `
                            <div class="empty-activity">
                                <i class="fas fa-stream"></i>
                                <h4>No Recent Activity</h4>
                                <p>Your account activity will appear here</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading activity:', error);
                    document.getElementById('activityList').innerHTML = `
                        <div class="empty-activity">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h4>Unable to Load Activity</h4>
                            <p>Please try again later</p>
                        </div>
                    `;
                });
        }
        
        // Load activity on page load
        document.addEventListener('DOMContentLoaded', loadActivity);
        
        // Update avatar color (users table stores avatar_color, not an image)
        function updateAvatarColor(color) {
            if (!color) return;

            showLoading();
            fetch('<?php echo APP_URL; ?>/api/update_avatar_color.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    avatar_color: color,
                    csrf_token: '<?php echo generateCsrfToken(); ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                hideLoading();
                if (data && data.success) {
                    showNotification('Avatar color updated!', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    showNotification(data.message || 'Failed to update avatar color', 'error');
                }
            })
            .catch(err => {
                hideLoading();
                console.error(err);
                showNotification('Failed to update avatar color', 'error');
            });
        }
        
        // Request data export
        function requestDataExport() {
            document.getElementById('modalTitle').textContent = 'Export My Data';
            document.getElementById('modalContent').innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-file-export text-4xl text-blue-500 mb-4"></i>
                    <h4 class="text-lg font-semibold mb-2">Export Your Data</h4>
                    <p class="text-gray-600 mb-4">
                        This will generate a downloadable file containing all your personal data, 
                        complaints, comments, and activity history.
                    </p>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h5 class="font-semibold text-blue-800 mb-1"><i class="fas fa-info-circle"></i> What's included?</h5>
                        <ul class="text-sm text-blue-700 text-left list-disc pl-5">
                            <li>Personal information</li>
                            <li>All submitted complaints</li>
                            <li>Comments and votes</li>
                            <li>Activity history</li>
                            <li>Account preferences</li>
                        </ul>
                    </div>
                    <p class="text-sm text-gray-500">
                        The export file will be sent to your email address within 24 hours.
                    </p>
                </div>
            `;
            
            document.getElementById('modalFooter').innerHTML = `
                <button type="button" class="btn btn-outline" onclick="closeModal('accountModal')">
                    Cancel
                </button>
                <button type="button" class="btn btn-gradient" onclick="submitDataExport()">
                    <i class="fas fa-download"></i> Request Export
                </button>
            `;
            
            document.getElementById('accountModal').classList.add('active');
        }
        
        function submitDataExport() {
            fetch('<?php echo APP_URL; ?>/api/request_data_export.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    student_id: <?php echo $student_id; ?>,
                    email: '<?php echo $student['email']; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalContent').innerHTML = `
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle text-4xl text-green-500 mb-4"></i>
                            <h4 class="text-lg font-semibold mb-2">Export Request Submitted</h4>
                            <p class="text-gray-600 mb-4">
                                Your data export has been queued. You will receive an email at 
                                <strong><?php echo $student['email']; ?></strong> within 24 hours with download instructions.
                            </p>
                        </div>
                    `;
                    
                    document.getElementById('modalFooter').innerHTML = `
                        <button type="button" class="btn btn-gradient" onclick="closeModal('accountModal')">
                            OK
                        </button>
                    `;
                } else {
                    showNotification(data.message || 'Failed to request export', 'error');
                    closeModal('accountModal');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to request export', 'error');
                closeModal('accountModal');
            });
        }
        
        // Deactivate account
        function deactivateAccount() {
            document.getElementById('modalTitle').textContent = 'Deactivate Account';
            document.getElementById('modalContent').innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-user-slash text-4xl text-orange-500 mb-4"></i>
                    <h4 class="text-lg font-semibold mb-2">Deactivate Your Account</h4>
                    <p class="text-gray-600 mb-4">
                        Your account will be deactivated immediately. You won't be able to:
                    </p>
                    <ul class="text-sm text-gray-600 text-left list-disc pl-5 mb-4">
                        <li>Submit new complaints</li>
                        <li>Comment or vote on complaints</li>
                        <li>Receive notifications</li>
                        <li>Access your profile</li>
                    </ul>
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-4">
                        <h5 class="font-semibold text-orange-800 mb-1"><i class="fas fa-exclamation-triangle"></i> Important</h5>
                        <p class="text-sm text-orange-700">
                            Your existing complaints will remain visible. You can reactivate your account 
                            by contacting support within 30 days.
                        </p>
                    </div>
                    <p class="text-sm text-gray-500">
                        Are you sure you want to proceed?
                    </p>
                </div>
            `;
            
            document.getElementById('modalFooter').innerHTML = `
                <button type="button" class="btn btn-outline" onclick="closeModal('accountModal')">
                    Cancel
                </button>
                <button type="button" class="btn btn-warning" onclick="submitDeactivation()">
                    <i class="fas fa-user-slash"></i> Deactivate Account
                </button>
            `;
            
            document.getElementById('accountModal').classList.add('active');
        }
        
        function submitDeactivation() {
            fetch('<?php echo APP_URL; ?>/api/deactivate_account.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    student_id: <?php echo $student_id; ?>,
                    reason: 'User requested deactivation'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Account deactivated successfully', 'success');
                    setTimeout(() => {
                        window.location.href = '<?php echo APP_URL; ?>/includes/auth/logout.inc.php';
                    }, 1500);
                } else {
                    showNotification(data.message || 'Failed to deactivate account', 'error');
                }
                closeModal('accountModal');
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to deactivate account', 'error');
                closeModal('accountModal');
            });
        }
        
        // Delete account
        function deleteAccount() {
            document.getElementById('modalTitle').textContent = 'Delete Account';
            document.getElementById('modalContent').innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-trash text-4xl text-red-500 mb-4"></i>
                    <h4 class="text-lg font-semibold mb-2">Delete Your Account</h4>
                    <p class="text-red-600 font-semibold mb-4">
                        ⚠️ This action is permanent and cannot be undone!
                    </p>
                    <p class="text-gray-600 mb-4">
                        All your data will be permanently deleted, including:
                    </p>
                    <ul class="text-sm text-gray-600 text-left list-disc pl-5 mb-4">
                        <li>Your account information</li>
                        <li>All submitted complaints</li>
                        <li>Comments and votes</li>
                        <li>Activity history</li>
                        <li>Preferences and settings</li>
                    </ul>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                        <h5 class="font-semibold text-red-800 mb-1"><i class="fas fa-exclamation-triangle"></i> Warning</h5>
                        <p class="text-sm text-red-700">
                            Your complaints will be anonymized (your name will be removed) but the content 
                            will remain visible for system integrity.
                        </p>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">
                        To confirm deletion, please enter your password:
                    </p>
                    <input type="password" id="deletePassword" class="form-input mb-4" 
                           placeholder="Enter your password" style="width: 100%;">
                </div>
            `;
            
            document.getElementById('modalFooter').innerHTML = `
                <button type="button" class="btn btn-outline" onclick="closeModal('accountModal')">
                    Cancel
                </button>
                <button type="button" class="btn btn-danger" onclick="submitDeletion()">
                    <i class="fas fa-trash"></i> Delete Account Permanently
                </button>
            `;
            
            document.getElementById('accountModal').classList.add('active');
        }
        
        function submitDeletion() {
            const password = document.getElementById('deletePassword').value;
            
            if (!password) {
                showNotification('Please enter your password', 'error');
                return;
            }
            
            fetch('<?php echo APP_URL; ?>/api/delete_account.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    student_id: <?php echo $student_id; ?>,
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Account deleted successfully', 'success');
                    setTimeout(() => {
                        window.location.href = '<?php echo APP_URL; ?>/pages/auth/login.php';
                    }, 1500);
                } else {
                    showNotification(data.message || 'Failed to delete account', 'error');
                }
                closeModal('accountModal');
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to delete account', 'error');
                closeModal('accountModal');
            });
        }
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Show loading
        function showLoading() {
            const loading = document.createElement('div');
            loading.id = 'loadingOverlay';
            loading.className = 'loading-overlay';
            loading.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                </div>
            `;
            document.body.appendChild(loading);
        }
        
        // Hide loading
        function hideLoading() {
            const loading = document.getElementById('loadingOverlay');
            if (loading) loading.remove();
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

        // OTP input: digits-only and clamp to configured length (helps paste and mobile keyboards).
        (function bindOtpInput() {
            const otpLen = <?php echo (int)$otp_len_ui; ?>;
            const otp = document.getElementById('passwordOtp');
            if (!otp) return;

            otp.addEventListener('input', function() {
                const digits = (otp.value || '').replace(/\\D+/g, '').slice(0, otpLen);
                if (otp.value !== digits) otp.value = digits;
            });
        })();
    </script>
</body>
</html>
