<?php
session_start();
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';
require_once '../includes/utilities/notifications.php';
require_once '../config/constants.php';
require_once '../config/mail_config.php';

if (!isAdmin() || $_SESSION['admin_role'] !== 'super_admin') {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "htu_complaint_system";

$backup_dir = __DIR__ . "/../backups/";

if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

$backup_file = "$backup_dir" . $db . "_" . date("Y-m-d-H-i-s") . ".sql";

$command = "\"C:/xampp/mysql/bin/mysqldump\" --user=$user --password=$pass --host=$host $db > \"$backup_file\"";

exec($command, $output, $result);

if ($result === 0 && file_exists($backup_file)) {

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($backup_file) . '"');
    header('Content-Length: ' . filesize($backup_file));

    readfile($backup_file);

    unlink($backup_file);
    exit();

} else {
    header('Content-Type: application/json');
    echo json_encode([
        "success" => false,
        "message" => "Backup failed. Check mysqldump path."
    ]);
}

?>