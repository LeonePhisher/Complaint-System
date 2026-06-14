<?php

require_once '../config/constants.php';
require_once '../includes/auth/session.inc.php';
require_once '../includes/utilities/helpers.php';

// Check if user is admin
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

requirePermission('view_complaints');

$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$complaint_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit();
}

try {
    $admin_id = (int)($_SESSION['admin_id'] ?? 0);
    $admin_role = $_SESSION['admin_role'] ?? '';

    $query = "
        SELECT 
            c.id,
            c.complaint_code,
            c.title,
            c.description,
            c.location,
            c.urgency,
            c.status,
            c.view_count,
            c.upvotes,
            c.downvotes,
            c.created_at,
            c.rejection_reason,
            cat.name as category_name,
            cat.color as category_color
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.id = ?
    ";

    $params = [$complaint_id];
    if ($admin_role !== 'super_admin') {
        $query .= " AND cat.admin_id = ?";
        $params[] = $admin_id;
    }
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $complaint = $stmt->fetch();
    
    if (!$complaint) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Complaint not found']);
        exit();
    }
    
    // Format dates
    $complaint['created_at_formatted'] = date('M j, Y \a\t g:i A', strtotime($complaint['created_at']));
    $complaint['time_ago'] = timeAgo($complaint['created_at']);
    
    // Decode HTML entities in description (decode multiple times in case of multiple encoding)
    $complaint['description'] = html_entity_decode(html_entity_decode(html_entity_decode($complaint['description'])));
    
    echo json_encode([
        'success' => true,
        'complaint' => $complaint
    ]);
} catch (PDOException $e) {
    error_log("Error fetching complaint: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
