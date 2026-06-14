<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ .  '/../../config/database.php';
require_once __DIR__ . '/../utilities/helpers.php';

// CSRF check
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $error = 'Invalid security token.';
    return;
}

$email = sanitizeInput($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Valid email is required';
    return;
}

if (empty($password)) {
    $error = 'Password is required';
    return;
}

$ip = getUserIP();
$lockout_time = date('Y-m-d H:i:s', strtotime('-' . LOCKOUT_TIME . ' seconds'));

try {
    // Lockout check
    $stmt = db()->prepare("
        SELECT COUNT(*) 
        FROM audit_log 
        WHERE ip_address = ? 
        AND action = 'ADMIN_LOGIN_FAILED'
        AND created_at > ?
    ");
    $stmt->execute([$ip, $lockout_time]);

    if ($stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS) {
        $error = 'Too many failed attempts. Try again later.';
        return;
    }

    // Fetch admin
    $stmt = db()->prepare("
        SELECT * FROM admins 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        logActivity('ADMIN_LOGIN_FAILED', "Email: $email", null, 'admin');
        $error = 'Invalid email or password';
        return;
    }

    if (!$admin['is_active']) {
        $error = 'Admin account disabled';
        return;
    }

    // Successful login
    session_regenerate_id(true);

    $_SESSION['admin_id']    = $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_name']  = $admin['full_name'];
    $_SESSION['admin_role']  = $admin['role'];
    $_SESSION['user_role']   = 'admin';
    $_SESSION['logged_in']   = true;
    $_SESSION['login_time']  = time();

    // Update last login
    $stmt = db()->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$admin['id']]);

    // Audit log
    logActivity('ADMIN_LOGIN_SUCCESS', null, $admin['id'], 'admin');

    header('Location: ' . APP_URL . '/pages/admin/dashboard.php');
    exit();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Login failed. Please try again.';
}
?>