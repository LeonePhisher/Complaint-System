<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$user_id = $_SESSION['student_id'] ?? null;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = intval($_GET['per_page'] ?? 7);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $offset = ($page - 1 ) * $per_page;
    
    // Get total count
    $stmt = db()->prepare("
        SELECT COUNT(*) as total FROM notifications WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $total = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $per_page);
    
    // Get paginated notifications
    $stmt = db()->prepare("
        SELECT * FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $per_page, $offset]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add time_ago for each notification
    foreach ($notifications as &$n) {
        $n['time_ago'] = timeAgo($n['created_at']);
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'page' => $page,
        'per_page' => $per_page,
        'total' => $total,
        'total_pages' => $total_pages
    ]);
} catch (PDOException $e) {
    error_log("Error fetching paginated notifications: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching notifications']);
}
?>
