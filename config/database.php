<?php
// Prevent direct access

if (!class_exists('Database')) {
class Database {
    private static $instance = null;
    private $pdo;
    private $error;

    private function __construct() {
        $host = "localhost";
        $db   = "htu_complaint_system";
        $user = "root";
        $pass = "";
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            $this->error = $e->getMessage();
            die("Database Connection Failed: " . $this->error);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }
}
}
// Helper function for quick DB access
if (!function_exists('db')) {
    function db() {
        return Database::getInstance()->getConnection();
    }
}
?>