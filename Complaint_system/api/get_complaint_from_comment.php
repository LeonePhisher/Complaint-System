<?php
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$comment_id = $_GET['comment_id'] ?? null;

if (!$comment_id || !is_numeric($comment_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid comment ID']);
    exit;
}

try {
    $stmt = db()->prepare("SELECT complaint_id FROM comments WHERE id = ?");
    $stmt->execute([$comment_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(['success' => true, 'complaint_id' => $result['complaint_id']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Comment not found']);
    }
} catch (PDOException $e) {
    error_log("Error fetching complaint_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>