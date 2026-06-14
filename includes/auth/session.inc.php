<?php
//Session Management with Database Storage
    require_once ROOT_PATH . '/config/database.php';
class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $table = 'sessions';
    
    public function __construct() {
        $this->pdo = db();
    }
    
    public function open(string $savePath, string $sessionName): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }
    
    public function read(string $sessionId): string {
        try {
            $stmt = $this->pdo->prepare("
                SELECT payload 
                FROM {$this->table} 
                WHERE id = ? AND expires_at > NOW()
            ");
            $stmt->execute([$sessionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['payload'] : '';
        } catch (PDOException $e) {
            error_log("Session read error: " . $e->getMessage());
            return '';
        }
    }
    
    public function write(string $sessionId, string $data): bool {
        try {
            $expires = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->table} (id, payload, expires_at, last_activity)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    payload = VALUES(payload),
                    expires_at = VALUES(expires_at),
                    last_activity = NOW()
            ");
            
            return $stmt->execute([$sessionId, $data, $expires]);
        } catch (PDOException $e) {
            error_log("Session write error: " . $e->getMessage());
            return false;
        }
    }
    
    public function destroy(string $sessionId): bool {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
            return $stmt->execute([$sessionId]);
        } catch (PDOException $e) {
            error_log("Session destroy error: " . $e->getMessage());
            return false;
        }
    }
    
    public function gc(int $maxLifetime): int|false {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE expires_at < NOW()");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Session garbage collection error: " . $e->getMessage());
            return false;
        }
    }
}

// Initialize session handler
// Only configure session if none is active
if (session_status() === PHP_SESSION_NONE) {

    // Initialize session handler
    $sessionHandler = new DatabaseSessionHandler();
    session_set_save_handler($sessionHandler, true);

    // Secure session settings
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }

    ini_set('session.cookie_samesite', 'Strict');

    session_start();
}

// Start session
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    // Session expired
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['session_expired'] = true;
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode(['session_expired' => true]);
        exit();
    } else {
        header('Location: ' . APP_URL . '/pages/auth/login.php?expired=1');
        exit();
    }
}

$_SESSION['last_activity'] = time();

// Check for remember me cookie
if (!isset($_SESSION['student_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $stmt = db()->prepare("
            SELECT user_id, user_type, payload 
            FROM sessions 
            WHERE id = ? AND expires_at > NOW()
        ");
        $stmt->execute([$_COOKIE['remember_token']]);
        $session = $stmt->fetch();
        
        if ($session) {
            $payload = json_decode($session['payload'], true);
            
            if ($session['user_type'] === 'user') {
                // Get user
                $stmt = db()->prepare("
                    SELECT id, index_number, email, full_name, avatar_color 
                    FROM users 
                    WHERE id = ? AND account_status = 'active'
                ");
                $stmt->execute([$session['user_id']]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $_SESSION['student_id'] = $user['id'];
                    $_SESSION['student_index'] = $user['index_number'];
                    // Backwards-compatible keys used in some pages
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_index'] = $user['index_number'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['student_name'] = $user['full_name'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_avatar'] = $user['avatar_color'];
                    $_SESSION['user_role'] = 'student';
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $stmt = db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                }
            } elseif ($session['user_type'] === 'admin') {
                // Get admin
                $stmt = db()->prepare("
                    SELECT id, username, email, full_name, role, avatar_color 
                    FROM admins 
                    WHERE id = ? AND is_active = 1
                ");
                $stmt->execute([$session['user_id']]);
                $admin = $stmt->fetch();
                
                if ($admin) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['admin_avatar'] = $admin['avatar_color'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_time'] = time();
                    
                    // Update last login
                    $stmt = db()->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$admin['id']]);
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Remember me error: " . $e->getMessage());
    }
}
?>