<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../config/mail_config.php';
require_once __DIR__ . '/../utilities/security.php';
require_once __DIR__ . '/../utilities/validation.php';
require_once __DIR__ . '/../utilities/helpers.php';
require_once ROOT_PATH . '/config/database.php';

// Validate CSRF token
if (!validateCSRFToken($_POST['csrf_token'])) {
    $error = 'Invalid security token. Please try again.';
    return;
}

// Sanitize and validate input
$index_number = sanitizeInput($_POST['index_number'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$full_name = sanitizeInput($_POST['full_name'] ?? '');
$phone = sanitizeInput($_POST['phone'] ?? '');
$department = sanitizeInput($_POST['department'] ?? '');
$level = sanitizeInput($_POST['level'] ?? '');

// Validation
$errors = [];

// Validate index number
if (empty($index_number) || !preg_match('/^\d{10}$/', $index_number)) {
    $errors['index_number'] = 'Please enter a valid 10-digit index number';
}

// Check if index number already exists
if (empty($errors['index_number'])) {
    try {
        $stmt = db()->prepare("SELECT id FROM users WHERE index_number = ?");
        $stmt->execute([$index_number]);
        if ($stmt->fetch()) {
            $errors['index_number'] = 'This index number is already registered';
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = 'Registration failed. Please try again.';
        return;
    }
}

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address';
}

// Check if email already exists
if (empty($errors['email'])) {
    try {
        $stmt = db()->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'This email is already registered';
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = 'Registration failed. Please try again.';
        return;
    }
}

// Validate password
if (empty($password) || strlen($password) < 6) {
    $errors['password'] = 'Password must be at least 6 characters';
}

if ($password !== $confirm_password) {
    $errors['confirm_password'] = 'Passwords do not match';
}

// Validate personal information
if (empty($full_name)) {
    $errors['full_name'] = 'Full name is required';
}

if (empty($department)) {
    $errors['department'] = 'Department is required';
}

if (empty($level)) {
    $errors['level'] = 'Level is required';
}

// If there are validation errors
if (!empty($errors)) {
    $error = 'Please correct the errors below:';
    // Store errors in session to display them
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    return;
}

// Generate verification token
$verification_token = bin2hex(random_bytes(32));
$verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Generate avatar color based on index number
$colors = ['#667eea', '#764ba2', '#f56565', '#48bb78', '#ed8936', '#4299e1', '#9f7aea', '#f687b3'];
$avatar_color = $colors[array_rand($colors)];

// Hash password
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Generate unique user ID
$user_id = generateUniqueId('USR');

try {
    // Start transaction
    db()->beginTransaction();

    // Insert user
    $stmt = db()->prepare("
        INSERT INTO users (
            index_number, email, password_hash, full_name, phone, 
            department, level, avatar_color, verification_token, verification_expires
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $index_number,
        $email,
        $password_hash,
        $full_name,
        $phone,
        $department,
        $level,
        $avatar_color,
        $verification_token,
        $verification_expires
    ]);

    // Get the user ID
    $user_id = db()->lastInsertId();

    // Create audit log
    $stmt = db()->prepare("
        INSERT INTO audit_log (user_id, action, table_name, record_id, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        'REGISTER',
        'users',
        $user_id,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);

    // Commit transaction
    db()->commit();

    // Clear form data
    unset($_SESSION['form_errors']);
    unset($_SESSION['form_data']);

    // Set success message
    $_SESSION['success'] = 'Registration successful! You will receive a verification code via email.';
    
    // Redirect to verification page with OTP - the OTP system will auto-send
    header('Location: ' . APP_URL . '/pages/auth/verify.php?email=' . urlencode($email));
    exit();

} catch (PDOException $e) {
    // Rollback transaction
    db()->rollBack();
    
    error_log("Registration error: " . $e->getMessage());
    $error = 'Registration failed. Please try again.';
    
    // Store form data for repopulation
    $_SESSION['form_data'] = $_POST;
}

// If we get here, there was an error
return;
?>