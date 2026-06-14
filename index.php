<?php

require_once 'config/constants.php';  // Must come first
require_once ROOT_PATH . '/includes/auth/session.inc.php';
require_once 'config/database.php';

// require_once 'config/constants.php';
// Main entry point for HTU Complaint System


// Define application path
// define('ROOT_PATH', __DIR__);
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// Load configuration
require_once 'config/constants.php';
require_once 'config/database.php';

// Auto-load classes
spl_autoload_register(function ($class) {
    $prefix = 'HtuComplaint\\';
    $base_dir = ROOT_PATH . '/includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Check if installation is complete
if (!file_exists('install.lock') && !file_exists('.env')) {
    if (!str_contains($_SERVER['REQUEST_URI'], '/install/')) {
        header('Location: /install/setup.php');
        exit();
    }
}

// Route requests
// $request = $_SERVER['REQUEST_URI'];
// $query = $_SERVER['QUERY_STRING'] ?? '';

// Remove query string
if (str_contains($request, '?')) {
    $request = strstr($request, '?', true);
}

// Remove trailing slash
$request = rtrim($request, '/');

// Default route
// if (empty($request) || $request === '/') {
//     // Check if user is logged in
//     if (isset($_SESSION['student_id'])) {
//         header('Location: /pages/student/dashboard.php');
//     } elseif (isset($_SESSION['admin_id'])) {
//         header('Location: /pages/admin/dashboard.php');
//     } else {
//         header('Location: /pages/auth/login.php');
//     }
//     exit();
// }

if (empty($request) || $request === '/') {
    if (isset($_SESSION['student_id'])) {
        header('Location: ' . APP_URL . '/pages/student/dashboard.php');
    } elseif (isset($_SESSION['admin_id'])) {
        header('Location: ' . APP_URL . '/pages/admin/dashboard.php');
    } else {
        header('Location: ' . APP_URL . '/pages/auth/login.php');
    }
    exit();
}


// API routes
if (str_starts_with($request, '/api/')) {
    $api_file = ROOT_PATH . $request . '.php';
    if (file_exists($api_file)) {
        require $api_file;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint not found']);
    }
    exit();
}

// Static file serving
if (str_starts_with($request, '/assets/') || 
    str_starts_with($request, '/vendor/')) {
    $file_path = ROOT_PATH . $request;
    
    if (file_exists($file_path)) {
        $mime_types = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        if (isset($mime_types[$extension])) {
            header('Content-Type: ' . $mime_types[$extension]);
        }
        
        readfile($file_path);
    } else {
        http_response_code(404);
        echo 'File not found';
    }
    exit();
}

// Page routes
$page_map = [
    // Auth pages
    '/login' => '/pages/auth/login.php',
    '/register' => '/pages/auth/register.php',
    '/verify' => '/pages/auth/verify.php',
    '/forgot-password' => '/pages/auth/forgot-password.php',
    '/reset-password' => '/pages/auth/reset-password.php',
    
    // Student pages
    '/dashboard' => '/pages/student/dashboard.php',
    '/feed' => '/pages/student/feed.php',
    '/submit' => '/pages/student/submit.php',
    '/my-complaints' => '/pages/student/my-complaints.php',
    '/profile' => '/pages/student/profile.php',
    
    // Admin pages
    '/admin' => '/pages/admin/dashboard.php',
    '/admin/dashboard' => '/pages/admin/dashboard.php',
    '/admin/complaints' => '/pages/admin/complaints.php',
    '/admin/users' => '/pages/admin/users.php',
    '/admin/categories' => '/pages/admin/categories.php',
    '/admin/reports' => '/pages/admin/reports.php',
    '/admin/settings' => '/pages/admin/settings.php'
];

if (isset($page_map[$request])) {
    $page_file = ROOT_PATH . $page_map[$request];
    
    if (file_exists($page_file)) {
        require $page_file;
    } else {
        http_response_code(404);
        require ROOT_PATH . '/pages/error/404.php';
    }
} else {
    // Try to match with file path
    $page_file = ROOT_PATH . $request . '.php';
    
    if (file_exists($page_file)) {
        require $page_file;
    } else {
        http_response_code(404);
        require ROOT_PATH . '/pages/error/404.php';
    }
}
?>