<?php
// Validation Utilities

/**
 * Validate email format
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 */
function validatePhone($phone) {
    // Remove non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Check if it's between 10 and 15 digits
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

/**
 * Validate student index number (10 digits)
 */
function validateIndexNumber($index_number) {
    return preg_match('/^\d{10}$/', $index_number) === 1;
}

/**
 * Validate URL
 */
function validateURL($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate IP address
 */
function validateIPAddress($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * Validate password strength
 */
function validatePasswordStrength($password) {
    // At least 8 characters
    if (strlen($password) < 8) {
        return ['valid' => false, 'reason' => 'At least 8 characters required'];
    }
    
    // At least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'reason' => 'At least one uppercase letter required'];
    }
    
    // At least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'reason' => 'At least one lowercase letter required'];
    }
    
    // At least one number
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'reason' => 'At least one number required'];
    }
    
    // At least one special character
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        return ['valid' => false, 'reason' => 'At least one special character required'];
    }
    
    return ['valid' => true];
}

/**
 * Validate username
 */
function validateUsername($username) {
    // 3-20 characters, alphanumeric and underscore only
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username) === 1;
}

/**
 * Validate hex color
 */
function validateColor($color) {
    return preg_match('/^#[0-9a-f]{6}$/i', $color) === 1;
}

/**
 * Sanitize string for database
 */
function sanitizeForDB($string) {
    return trim(stripslashes($string));
}

/**
 * Validate input against regex pattern
 */
function validatePattern($input, $pattern) {
    return preg_match($pattern, $input) === 1;
}

/**
 * Check if array has required keys
 */
function hasRequiredKeys(array $array, array $keys) {
    foreach ($keys as $key) {
        if (!isset($array[$key]) || empty($array[$key])) {
            return false;
        }
    }
    return true;
}

/**
 * Validate date format
 */
function validateDateFormat($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
?>
