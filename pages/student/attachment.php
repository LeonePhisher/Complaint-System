<?php
require_once '../../config/constants.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../config/database.php';
require_once '../../includes/utilities/helpers.php';

if (!isStudent()) {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

$student_id = (int)($_SESSION['student_id'] ?? 0);
$complaint_id = isset($_GET['complaint_id']) ? (int)$_GET['complaint_id'] : 0;
$idx = isset($_GET['idx']) ? (int)$_GET['idx'] : -1;

$error = '';
$att = null;
$preview_url = '';
$download_url = '';
$complaint_title = '';

if ($complaint_id <= 0 || $idx < 0) {
    $error = 'Invalid attachment link.';
} else {
    try {
        $stmt = db()->prepare('SELECT attachments, user_id, title FROM complaints WHERE id = ? LIMIT 1');
        $stmt->execute([$complaint_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }catch (Exception $e) {
        $error = 'Database error: ' . $e->getMessage();

    }
}