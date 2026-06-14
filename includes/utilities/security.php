<?php
// includes/utilities/security.php
class Security {
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        return true;
    }
    
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map('self::sanitizeInput', $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public static function validateIndexNumber($index) {
        return preg_match('/^\d{10}$/', $index);
    }
    
    public static function rateLimit($key, $limit = 5, $timeframe = 900) {
        $currentTime = time();
        $attempts = $_SESSION['rate_limit'][$key] ?? [];
        
        // Remove old attempts
        $attempts = array_filter($attempts, function($attempt) use ($currentTime, $timeframe) {
            return $attempt > ($currentTime - $timeframe);
        });
        
        if (count($attempts) >= $limit) {
            return false;
        }
        
        $attempts[] = $currentTime;
        $_SESSION['rate_limit'][$key] = $attempts;
        
        return true;
    }
    
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}
?>