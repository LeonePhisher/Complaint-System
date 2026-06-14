<?php
session_start();
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$csrf = $input['csrf_token'] ?? '';
if (!validateCSRFToken($csrf)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$comment_id = intval($input['comment_id'] ?? 0);
if (!$comment_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid comment id']);
    exit();
}

$user_id = $_SESSION['student_id'] ?? $_SESSION['admin_id'];
$is_admin = isset($_SESSION['admin_id']);

try {
    $stmt = db()->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->execute([$comment_id]);
    $row = $stmt->fetch();
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Comment not found']);
        exit();
    }
    if (!$is_admin && $row['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }

    $stmt = db()->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->execute([$comment_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
