<?php
// Application Constants

// Application Constants
if (!defined('APP_NAME')) define('APP_NAME', 'HTU Anonymous Complaint System');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('APP_URL')) define('APP_URL', 'http://localhost/complaint-system');
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Africa/Accra');

// Path Constants
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(dirname(__FILE__)));
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads/');
if (!defined('COMPLAINT_UPLOADS')) define('COMPLAINT_UPLOADS', UPLOAD_PATH . 'complaints/');
if (!defined('AVATAR_UPLOADS')) define('AVATAR_UPLOADS', UPLOAD_PATH . 'avatars/');

// Session Constants
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600); // 1 hour
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOCKOUT_TIME')) define('LOCKOUT_TIME', 900); // 15 minutes

// Complaint Constants
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 5242880); // 5MB
if (!defined('ALLOWED_FILE_TYPES')) define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);
if (!defined('ITEMS_PER_PAGE')) define('ITEMS_PER_PAGE', 10);

// Email Constants
if (!defined('EMAIL_FROM')) define('EMAIL_FROM', getenv('EMAIL_FROM') ?: 'noreply@htu.edu.gh');
if (!defined('EMAIL_FROM_NAME')) define('EMAIL_FROM_NAME', getenv('EMAIL_FROM_NAME') ?: 'HTU Complaint System');

// SMTP defaults (override via environment variables / .env via mail_config.php)
if (!defined('SMTP_HOST')) define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');


// Detect API/JSON requests early so we never echo warnings/notices into JSON responses.
// This prevents false client-side "Network error" toasts when the backend actually succeeded.
$__uri = (string)($_SERVER['REQUEST_URI'] ?? '');
$__contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
$__accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
$__xrw = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');

$__isApiRequest = ($__uri !== '' && strpos($__uri, '/api/') !== false);
$__isJsonRequest = (stripos($__contentType, 'application/json') !== false) || (stripos($__accept, 'application/json') !== false) || (strcasecmp($__xrw, 'XMLHttpRequest') === 0);

if (!defined('IS_API_REQUEST')) define('IS_API_REQUEST', $__isApiRequest);
if (!defined('IS_JSON_REQUEST')) define('IS_JSON_REQUEST', $__isJsonRequest);

// Error reporting
error_reporting(E_ALL);
if (IS_API_REQUEST || IS_JSON_REQUEST) {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    // Local/dev default
    ini_set('display_errors', '1');
}

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set timezone
date_default_timezone_set(APP_TIMEZONE);
?>
