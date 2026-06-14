<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once __DIR__. '/../utilities/security.php';
require_once __DIR__. '/../utilities/validation.php';
require_once __DIR__. '/../utilities/helpers.php';

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'])) {
    $error = 'Invalid security token. Please try again.';
    return;
}

// Sanitize and validate input
$index_number = sanitizeInput($_POST['index_number'] ?? '');
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// Validation
if (empty($index_number) || !preg_match('/^\d{10}$/', $index_number)) {
    $error = 'Please enter a valid 10-digit index number';
    return;
}

if (empty($password)) {
    $error = 'Please enter your password';
    return;
}

// Check login attempts
$ip_address = $_SERVER['REMOTE_ADDR'];
$lockout_time = date('Y-m-d H:i:s', strtotime('-' . LOCKOUT_TIME . ' seconds'));

try {
    // Check if IP is locked out
    $stmt = db()->prepare("
        SELECT COUNT(*) as attempts 
        FROM audit_log 
        WHERE ip_address = ? 
        AND action = 'LOGIN_FAILED' 
        AND created_at > ?
    ");
    $stmt->execute([$ip_address, $lockout_time]);
    $result = $stmt->fetch();
    
    if ($result['attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $error = 'Too many failed login attempts. Please try again in ' . (LOCKOUT_TIME / 60) . ' minutes.';
        return;
    }

    // Get user by index number
    $stmt = db()->prepare("
        SELECT id, index_number, email, password_hash, full_name, 
               avatar_color, is_verified, account_status, login_attempts
        FROM users 
        WHERE index_number = ?
    ");
    $stmt->execute([$index_number]);
    $user = $stmt->fetch();

    if (!$user) {
        // Log failed attempt
        logFailedAttempt($ip_address, $index_number);
        $error = 'Invalid index number or password';
        return;
    }

    // Check account status
    if ($user['account_status'] !== 'active') {
        if ($user['account_status'] === 'suspended') {
            $error = 'Your account has been suspended. Please contact support.';
        } elseif ($user['account_status'] === 'inactive') {
            $error = 'Please verify your email address to activate your account.';
        }
        return;
    }

    // Check if account is verified
    if (!$user['is_verified']) {
        $error = 'Please verify your email address before logging in.';
        return;
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        // Increment login attempts
        incrementLoginAttempts($user['id']);
        logFailedAttempt($ip_address, $index_number);
        $error = 'Invalid index number or password';
        return;
    }

    // Reset login attempts on successful login
    resetLoginAttempts($user['id']);

    // Update last login
    $stmt = db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Create session
    session_regenerate_id(true);
    
    $_SESSION['student_id'] = $user['id'];
    $_SESSION['student_index'] = $user['index_number'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['student_name'] = $user['full_name'];
    $_SESSION['user_avatar'] = $user['avatar_color'];
    $_SESSION['user_role'] = 'student';
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();

    // Set remember me cookie if requested
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
        
        setcookie('remember_token', $token, $expiry, '/', '', true, true);
        
        // Store token in database
        $stmt = db()->prepare("
            INSERT INTO sessions (id, user_id, user_type, payload, expires_at)
            VALUES (?, ?, 'user', ?, ?)
        ");
        $stmt->execute([
            $token,
            $user['id'],
            json_encode(['ip' => $ip_address]),
            date('Y-m-d H:i:s', $expiry)
        ]);
    }

    // Log successful login
    $stmt = db()->prepare("
        INSERT INTO audit_log (user_id, action, ip_address, user_agent)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $user['id'],
        'LOGIN_SUCCESS',
        $ip_address,
        $_SERVER['HTTP_USER_AGENT']
    ]);

    // Redirect to dashboard
    header('Location: ' . APP_URL . '/pages/student/dashboard.php');
    exit();

} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    $error = 'Login failed. Please try again.';
    return;
}

function logFailedAttempt($ip, $identifier) {
    try {
        $stmt = db()->prepare("
            INSERT INTO audit_log (action, ip_address, user_agent, notes)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            'LOGIN_FAILED',
            $ip,
            $_SERVER['HTTP_USER_AGENT'],
            "Failed login attempt for: $identifier"
        ]);
    } catch (PDOException $e) {
        error_log("Failed to log login attempt: " . $e->getMessage());
    }
}

function incrementLoginAttempts($user_id) {
    try {
        $stmt = db()->prepare("
            UPDATE users 
            SET login_attempts = login_attempts + 1 
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        error_log("Failed to increment login attempts: " . $e->getMessage());
    }
}

function resetLoginAttempts($user_id) {
    try {
        $stmt = db()->prepare("UPDATE users SET login_attempts = 0 WHERE id = ?");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        error_log("Failed to reset login attempts: " . $e->getMessage());
    }
}
?>