<?php
session_start();
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';
header('Content-Type: application/json');

$complaint_id = intval($_GET['complaint_id'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

if (!$complaint_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid complaint id']);
    exit();
}

$user_id = $_SESSION['student_id'] ?? $_SESSION['admin_id'] ?? null;
$is_admin = isset($_SESSION['admin_id']);

try {
    // total count
    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM comments WHERE complaint_id = ? AND status = 'active'");
    $stmt->execute([$complaint_id]);
    $total = $stmt->fetch()['total'];
    $pages = ceil($total / $limit);

    // fetch comments
    $stmt = db()->prepare(
        "SELECT c.id, c.complaint_id, c.user_id, c.admin_id, c.content, c.is_anonymous, c.is_private, c.parent_id, c.status, c.created_at,
                u.full_name AS user_name, u.avatar_color
         FROM comments c
         LEFT JOIN users u ON u.id = c.user_id
         WHERE c.complaint_id = ? AND c.status = 'active'
         ORDER BY c.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$complaint_id, $limit, $offset]);
    $rows = $stmt->fetchAll();

    $comments = [];
    foreach ($rows as $r) {
        $is_current_user = $user_id && $user_id == $r['user_id'];
        $display_name = $r['is_anonymous'] ? 'Anonymous' : htmlspecialchars($r['user_name'] ?? 'User');
        
        // Add "You:" prefix if it's the current user's comment
        if ($is_current_user && !$r['is_anonymous']) {
            $display_name = 'You: ' . $display_name;
        }
        
        $c = [
            'id' => $r['id'],
            'content' => nl2br(htmlspecialchars($r['content'])),
            'name' => $display_name,
            'time' => timeAgo($r['created_at']),
            'is_edited' => false,
            'badge' => '',
            'can_edit' => false,
            'can_delete' => false,
            'student_id' => $r['user_id'],
            'avatar' => $r['avatar_color'] ?? '#667eea',
            'is_current_user' => $is_current_user
        ];
        if ($r['is_anonymous']) {
            $c['badge'] = '<span class="anonymous-badge">anonymous</span>';
        }
        if ($is_admin || $is_current_user) {
            $c['can_edit'] = true;
            $c['can_delete'] = true;
        }
        $comments[] = $c;
    }

    echo json_encode([
        'success' => true,
        'comments' => $comments,
        'total' => $total,
        'pages' => $pages
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
