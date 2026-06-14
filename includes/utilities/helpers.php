<?php
// Helper Functions for HTU Complaint System

/**
 * Generate initials from name
 */
if (!function_exists('getInitials')) {
function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper($word[0]);
        }
    }
    
    return substr($initials, 0, 2);
}
}
/**
 * Format time ago
 */
if (!function_exists('timeAgo')) {
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}
}
/**
 * Generate unique ID
 */
if (!function_exists('generateUniqueId')) {
function generateUniqueId($prefix = '') {
    return $prefix . '_' . uniqid() . '_' . bin2hex(random_bytes(4));
}
}
/**
 * Generate CSRF token
 */
if (!function_exists('generateCSRFToken')) {
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}
}
/**
 * Validate CSRF token
 */
if (!function_exists('validateCSRFToken')) {
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    $token_age = time() - $_SESSION['csrf_token_time'];
    
    // Token expires after 1 hour
    if ($token_age > 3600) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}
}
/**
 * Sanitize input
 */
if (!function_exists('sanitizeInput')) {
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return $input;
}
}
/**
 * Check if user is logged in
 */
if (!function_exists('isLoggedIn')) {
function isLoggedIn() {
    return isset($_SESSION['student_id']) && isset($_SESSION['logged_in']);
}
}
/**
 * Check if user is admin
 */
if (!function_exists('isAdmin')) {
function isAdmin() {
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']);
}
}
/**
 * Redirect based on user role
 */
if (!function_exists('redirectBasedOnRole')) {
function redirectBasedOnRole() {
    if (isset($_SESSION['admin_id'])) {
        header('Location: ' . APP_URL . '/pages/admin/dashboard.php');
        exit();
    } elseif (isset($_SESSION['student_id'])) {
        header('Location: ' . APP_URL . '/pages/student/dashboard.php');
        exit();
    }
}
}
/**
 * Generate pagination HTML
 */
if (!function_exists('generatePagination')) {
function generatePagination($current_page, $total_pages, $url_pattern) {
    if ($total_pages <= 1) return '';
    
    $html = '<div class="pagination">';
    
    // Previous button
    if ($current_page > 1) {
        $html .= '<a href="' . sprintf($url_pattern, $current_page - 1) . '" class="page-link prev">';
        $html .= '<i class="fas fa-chevron-left"></i> Previous</a>';
    }
    
    // Page numbers
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    if ($start > 1) {
        $html .= '<a href="' . sprintf($url_pattern, 1) . '" class="page-link">1</a>';
        if ($start > 2) {
            $html .= '<span class="page-dots">...</span>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $current_page ? ' active' : '';
        $html .= '<a href="' . sprintf($url_pattern, $i) . '" class="page-link' . $active . '">' . $i . '</a>';
    }
    
    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            $html .= '<span class="page-dots">...</span>';
        }
        $html .= '<a href="' . sprintf($url_pattern, $total_pages) . '" class="page-link">' . $total_pages . '</a>';
    }
    
    // Next button
    if ($current_page < $total_pages) {
        $html .= '<a href="' . sprintf($url_pattern, $current_page + 1) . '" class="page-link next">';
        $html .= 'Next <i class="fas fa-chevron-right"></i></a>';
    }
    
    $html .= '</div>';
    
    return $html;
}
}
/**
 * Format file size
 */
if (!function_exists('formatFileSize')) {
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
}
/**
 * Validate file upload
 */
if (!function_exists('validateFileUpload')) {
function validateFileUpload($file, $allowed_types = null, $max_size = null) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error: ' . $file['error']];
    }
    
    $max_size = $max_size ?: MAX_FILE_SIZE;
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File size exceeds maximum allowed size'];
    }
    
    $allowed_types = $allowed_types ?: ALLOWED_FILE_TYPES;
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    if (isset($allowed_mimes[$extension]) && $mime_type !== $allowed_mimes[$extension]) {
        return ['success' => false, 'message' => 'Invalid file MIME type'];
    }
    
    return ['success' => true];
}
}
/**
 * Generate random color
 */
if (!function_exists('generateRandomColor')) {
function generateRandomColor() {
    $colors = ['#667eea', '#764ba2', '#f56565', '#48bb78', '#ed8936', '#4299e1', '#9f7aea', '#f687b3'];
    return $colors[array_rand($colors)];
}
}
/**
 * Generate slug from string
 */
if (!function_exists('generateSlug')) {
function generateSlug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}
}
/**
 * Send JSON response
 */
if (!function_exists('sendJsonResponse')) {
function sendJsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}
}
/**
 * Log activity
 */
if (!function_exists('logActivity')) {
function logActivity($action, $details = null, $user_id = null, $user_type = null) {
    try {
        $stmt = db()->prepare("
            INSERT INTO audit_log (user_id, admin_id, action, notes, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $user_id_val = $user_type === 'admin' ? null : $user_id;
        $admin_id_val = $user_type === 'admin' ? $user_id : null;
        
        $stmt->execute([
            $user_id_val,
            $admin_id_val,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}
}
/**
 * Get setting value
 */
if (!function_exists('getSetting')) {
function getSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        try {
            $stmt = db()->prepare("SELECT setting_key, setting_value FROM settings");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            error_log("Error loading settings: " . $e->getMessage());
            $settings = [];
        }
    }
    
    return $settings[$key] ?? $default;
}
}
/**
 * Update setting
 */
if (!function_exists('updateSetting')) {
function updateSetting($key, $value) {
    try {
        $stmt = db()->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (PDOException $e) {
        error_log("Error updating setting: " . $e->getMessage());
        return false;
    }
}
}
/**
 * Escape HTML for output
 */
if (!function_exists('escapeHtml')) {
function escapeHtml($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
}
/**
 * Truncate text
 */
if (!function_exists('truncateText')) {
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    
    return mb_substr($text, 0, $length) . $suffix;
}
}
/**
 * Get user IP address
 */
if (!function_exists('getUserIP')) {
function getUserIP() {
    $ip_keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ip_keys as $key) {
        if (isset($_SERVER[$key])) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
}
/**
 * Validate email domain
 */
if (!function_exists('validateEmailDomain')) {
function validateEmailDomain($email, $allowed_domains = ['htu.edu.gh']) {
    $domain = substr(strrchr($email, "@"), 1);
    return in_array($domain, $allowed_domains);
}
}
/**
 * Generate OTP
 */
if (!function_exists('generateOTP')) {
function generateOTP($length = 6) {
    $digits = '0123456789';
    $otp = '';
    
    for ($i = 0; $i < $length; $i++) {
        $otp .= $digits[rand(0, strlen($digits) - 1)];
    }
    
    return $otp;
}
}

/**
 * Check if user is student
 */
if (!function_exists('isStudent')) {
function isStudent() {
    return isset($_SESSION['student_id']) && isset($_SESSION['student_index']);
}
}
/**
 * Get HTML badge for complaint status
 */
if (!function_exists('getStatusBadge')) {
function getStatusBadge($status) {
    $s = strtolower(trim($status));
    $map = [
        'pending' => 'warning',
        'under_review' => 'info',
        'published' => 'primary',
        'resolved' => 'success',
        'rejected' => 'danger'
    ];

    $label = ucwords(str_replace(['_', '-'], ' ', $s));
    $cls = $map[$s] ?? 'secondary';

    return '<span class="badge badge-' . $cls . '">' . escapeHtml($label) . '</span>';
}
}
/**
 * Generate unique complaint code
 */
if (!function_exists('generateComplaintCode')) {
function generateComplaintCode() {
    $prefix = 'CMP';
    $timestamp = date('Ymd');
    $random = strtoupper(bin2hex(random_bytes(4)));
    return "{$prefix}-{$timestamp}-{$random}";
}
}

/**
 * Send OTP via email
 */
if (!function_exists('sendOTPEmail')) {
function sendOTPEmail($email, $name, $otp) {
    try {
        if (!function_exists('getMailer')) {
            // helpers.php is used widely; load mailer lazily to avoid hard coupling.
            $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
            $mailConfig = $root . '/config/mail_config.php';
            if (file_exists($mailConfig)) {
                require_once $mailConfig;
            }
        }

        if (!function_exists('getMailer')) {
            error_log('sendOTPEmail: getMailer() is not available (missing mail_config.php?)');
            return false;
        }

        $mailer = getMailer();
        
        $subject = "Your Verification Code - HTU Complaint System";
        $message = "<html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .otp-box { background: white; padding: 20px; text-align: center; border: 2px solid #667eea; border-radius: 8px; margin: 20px 0; }
                .otp-code { font-size: 2.5em; font-weight: bold; color: #667eea; letter-spacing: 5px; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'><h1>HTU Complaint System</h1></div>
                <div class='content'>
                    <h2>Email Verification</h2>
                    <p>Hi " . escapeHtml($name) . ",</p>
                    <p>Thank you for registering! Your verification code is:</p>
                    <div class='otp-box'><div class='otp-code'>" . htmlspecialchars($otp) . "</div></div>
                    <p>This code will expire in <strong>10 minutes</strong>.</p>
                    <p>If you didn't request this code, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply.</p>
                    <p>&copy; HTU Complaint System. All rights reserved.</p>
                </div>
            </div>
        </body></html>";
        
        return $mailer->sendNotification($email, $subject, $message, true);
    } catch (Exception $e) {
        error_log("OTP email error: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Check admin permission
 */
if (!function_exists('hasPermission')) {
function availableAdminPermissions() {
    return [
        'view_complaints'    => ['label' => 'View Complaints',    'description' => 'View complaints and their details'],
        'manage_complaints'  => ['label' => 'Manage Complaints',  'description' => 'Publish, reject, resolve complaints'],
        'view_reports'       => ['label' => 'View Reports',       'description' => 'View reports and analytics'],
        'manage_categories'  => ['label' => 'Manage Categories',  'description' => 'Create and manage complaint categories'],
        'manage_users'       => ['label' => 'Manage Users',       'description' => 'Manage student accounts'],
        'manage_admins'      => ['label' => 'Manage Admins',      'description' => 'Manage other admin accounts'],
        'view_settings'      => ['label' => 'View Settings',      'description' => 'View system settings'],
        'manage_settings'    => ['label' => 'Manage Settings',    'description' => 'Modify system settings'],
        'view_audit_log'     => ['label' => 'View Audit Log',     'description' => 'View audit logs'],
    ];
}

function hasPermission($permission) {
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }
    
    // Super admins have all permissions
    if (($_SESSION['admin_role'] ?? '') === 'super_admin') {
        return true;
    }
    
    if (!$permission) {
        return false;
    }

    // Per-request cache (do not store in session; permissions can be changed by super admins)
    static $permCacheByAdmin = [];
    $adminId = (int)($_SESSION['admin_id'] ?? 0);

    if (!array_key_exists($adminId, $permCacheByAdmin)) {
        try {
            $stmt = db()->prepare("SELECT permissions FROM admins WHERE id = ?");
            $stmt->execute([$adminId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Backward-compatible behavior:
            // - NULL permissions: treat as unrestricted (legacy admins).
            // - Non-NULL: treat as the authoritative allow-list (can be empty).
            $raw = $row['permissions'] ?? null;
            if ($raw === null || trim((string)$raw) === '') {
                $permCacheByAdmin[$adminId] = null; // unrestricted
            } else {
                $decoded = json_decode($raw, true);
                $permCacheByAdmin[$adminId] = is_array($decoded) ? array_values($decoded) : [];
            }
        } catch (PDOException $e) {
            error_log("Error fetching admin permissions: " . $e->getMessage());
            $permCacheByAdmin[$adminId] = [];
        }
    }

    // NULL cache value means unrestricted (legacy)
    if ($permCacheByAdmin[$adminId] === null) {
        return true;
    }

    return in_array($permission, $permCacheByAdmin[$adminId], true);
}
}

/**
 * Send OTP for password change confirmation
 */
if (!function_exists('sendPasswordChangeOTPEmail')) {
function sendPasswordChangeOTPEmail($email, $name, $otp) {
    try {
        if (!function_exists('getMailer')) {
            $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
            $mailConfig = $root . '/config/mail_config.php';
            if (file_exists($mailConfig)) {
                require_once $mailConfig;
            }
        }

        if (!function_exists('getMailer')) {
            error_log('sendPasswordChangeOTPEmail: getMailer() is not available (missing mail_config.php?)');
            return false;
        }

        $mailer = getMailer();
        $minutes = (int)getSetting('otp_expiry_minutes', 10);

        $subject = "Password Change Code - HTU Complaint System";
        $message = "<html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .otp-box { background: white; padding: 20px; text-align: center; border: 2px solid #667eea; border-radius: 8px; margin: 20px 0; }
                .otp-code { font-size: 2.5em; font-weight: bold; color: #667eea; letter-spacing: 5px; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'><h1>HTU Complaint System</h1></div>
                <div class='content'>
                    <h2>Confirm Password Change</h2>
                    <p>Hi " . escapeHtml($name) . ",</p>
                    <p>Use this code to confirm your password change:</p>
                    <div class='otp-box'><div class='otp-code'>" . htmlspecialchars($otp) . "</div></div>
                    <p>This code will expire in <strong>" . htmlspecialchars((string)$minutes) . " minutes</strong>.</p>
                    <p>If you did not request a password change, you can ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body></html>";

        return $mailer->sendNotification($email, $subject, $message, true);
    } catch (Exception $e) {
        error_log("Password change OTP email error: " . $e->getMessage());
        return false;
    }
}
}

if (!function_exists('hasAnyPermission')) {
function hasAnyPermission($permissions) {
    foreach ((array)$permissions as $p) {
        if (hasPermission($p)) return true;
    }
    return false;
}
}

if (!function_exists('requireAnyPermission')) {
function requireAnyPermission($permissions) {
    if (hasAnyPermission($permissions)) return;

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    $wantsJson = (strpos($uri, '/api/') !== false)
        || (stripos($contentType, 'application/json') !== false)
        || (stripos($accept, 'application/json') !== false)
        || $isAjax;

    if ($wantsJson) {
        sendJsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }

    $redirect = (strpos($uri, '/pages/admin/') !== false)
        ? (APP_URL . '/pages/admin/dashboard.php?error=access_denied')
        : (APP_URL . '/pages/auth/login.php?error=access_denied');

    header('Location: ' . $redirect);
    exit();
}
}

/**
 * Require permission (redirect if denied)
 */
if (!function_exists('requirePermission')) {
function requirePermission($permission) {
    if (!hasPermission($permission)) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
        $wantsJson = (strpos($uri, '/api/') !== false)
            || (stripos($contentType, 'application/json') !== false)
            || (stripos($accept, 'application/json') !== false)
            || $isAjax;

        if ($wantsJson) {
            sendJsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        }

        $redirect = (strpos($uri, '/pages/admin/') !== false)
            ? (APP_URL . '/pages/admin/dashboard.php?error=access_denied')
            : (APP_URL . '/pages/auth/login.php?error=access_denied');

        header('Location: ' . $redirect);
        exit();
    }
}
}

/**
 * Search and paginate notifications
 */
if (!function_exists('getPaginatedNotifications')) {
function getPaginatedNotifications($user_id, $page = 1, $per_page = 7, $load_all = false) {
    try {
        $offset = ($page - 1) * $per_page;
        
        if ($load_all) {
            // Get all notifications
            $stmt = db()->prepare("
                SELECT * FROM notifications
                WHERE user_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Get paginated notifications
        $stmt = db()->prepare("
            SELECT * FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user_id, $per_page, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Get total notification count
 */
if (!function_exists('getTotalNotificationCount')) {
function getTotalNotificationCount($user_id) {
    try {
        $stmt = db()->prepare("
            SELECT COUNT(*) as total FROM notifications WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error counting notifications: " . $e->getMessage());
        return 0;
    }
}
}

/**
 * Notify relevant admins (category admin + all super admins) about a newly submitted complaint.
 * This is in-app only; email is handled elsewhere.
 */
if (!function_exists('notifyAdminsOfNewComplaint')) {
function notifyAdminsOfNewComplaint($complaint_id, $complaint_code, $title, $category_id, $urgency = null) {
    try {
        // category + assigned category admin
        $stmt = db()->prepare("
            SELECT c.name AS category_name, a.id AS admin_id
            FROM categories c
            JOIN admins a ON a.id = c.admin_id AND a.is_active = 1
            WHERE c.id = ?
            LIMIT 1
        ");
        $stmt->execute([$category_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $category_name = $row['category_name'] ?? 'Category';
        $category_admin_id = $row['admin_id'] ?? null;

        $urgency_text = $urgency ? strtoupper((string)$urgency) : 'N/A';
        $notif_type = ($urgency === 'critical' || $urgency === 'high') ? 'warning' : 'info';
        $notif_title = "New Complaint: {$category_name}";
        $notif_message = "Complaint #{$complaint_code} submitted. Urgency: {$urgency_text}. Title: {$title}";

        // Category admin notification
        if (!empty($category_admin_id)) {
            $stmt = db()->prepare("
                INSERT INTO notifications (admin_id, title, message, type, related_id, related_type, is_read, created_at)
                VALUES (?, ?, ?, ?, ?, 'complaint', 0, NOW())
            ");
            $stmt->execute([$category_admin_id, $notif_title, $notif_message, $notif_type, $complaint_id]);
        }

        // Super admin notifications (exclude category admin if they happen to be a super admin)
        $stmt = db()->prepare("
            INSERT INTO notifications (admin_id, title, message, type, related_id, related_type, is_read, created_at)
            SELECT id, ?, ?, ?, ?, 'complaint', 0, NOW()
            FROM admins
            WHERE role = 'super_admin' AND is_active = 1 AND id <> ?
        ");
        $stmt->execute([$notif_title, $notif_message, $notif_type, $complaint_id, intval($category_admin_id ?: 0)]);

        return true;
    } catch (PDOException $e) {
        error_log('notifyAdminsOfNewComplaint error: ' . $e->getMessage());
        return false;
    }
}
}
?>
