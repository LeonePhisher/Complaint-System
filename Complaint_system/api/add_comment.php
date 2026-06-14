<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';
require_once '../includes/utilities/notifications.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to comment']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$complaint_id = isset($input['complaint_id']) ? intval($input['complaint_id']) : 0;
$content = isset($input['content']) ? sanitizeInput($input['content']) : '';
$parent_id = isset($input['parent_id']) ? intval($input['parent_id']) : null;
$is_private = isset($input['is_private']) ? boolval($input['is_private']) : false;
$csrf_token = isset($input['csrf_token']) ? sanitizeInput($input['csrf_token']) : '';

// Validate CSRF token
if (!validateCSRFToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

// Validate content
if (empty($content) || strlen($content) < 2) {
    echo json_encode(['success' => false, 'message' => 'Comment must be at least 2 characters']);
    exit();
}

if (strlen($content) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Comment must be less than 1000 characters']);
    exit();
}

// Check if complaint exists and can be commented on
try {
    $stmt = db()->prepare("
        SELECT status, is_anonymous 
        FROM complaints 
        WHERE id = ?
    ");
    $stmt->execute([$complaint_id]);
    $complaint = $stmt->fetch();
    
    if (!$complaint) {
        echo json_encode(['success' => false, 'message' => 'Complaint not found']);
        exit();
    }
    
    if (!in_array($complaint['status'], ['published', 'resolved'])) {
        echo json_encode(['success' => false, 'message' => 'Cannot comment on this complaint']);
        exit();
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

// Check if parent comment exists
if ($parent_id) {
    try {
        $stmt = db()->prepare("
            SELECT id 
            FROM comments 
            WHERE id = ? AND complaint_id = ?
        ");
        $stmt->execute([$parent_id, $complaint_id]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Parent comment not found']);
            exit();
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit();
    }
}

try {
    // Start transaction
    db()->beginTransaction();
    
    // Insert comment
    $stmt = db()->prepare("
        INSERT INTO comments (
            complaint_id, 
            user_id, 
            content, 
            is_anonymous, 
            is_private, 
            parent_id
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $complaint_id,
        $_SESSION['student_id'],
        $content,
        true, // Always anonymous for students
        $is_private,
        $parent_id
    ]);
    
    $comment_id = db()->lastInsertId();
    
    // Get comment with user info
    $stmt = db()->prepare("
        SELECT 
            c.*,
            u.avatar_color,
            CONCAT(SUBSTR(u.full_name, 1, 1), '****') as user_name
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();
    
    // Format comment for response
    $formatted_comment = [
        'id' => $comment['id'],
        'content' => $comment['content'],
        'time' => timeAgo($comment['created_at']),
        'created_at' => $comment['created_at'],
        'is_anonymous' => (bool)$comment['is_anonymous'],
        'is_private' => (bool)$comment['is_private'],
        'parent_id' => $comment['parent_id'],
        'user_avatar' => $comment['avatar_color'],
        'name' => $comment['user_name'],
        'badge' => '<span class="anonymous-badge">anonymous</span>',
        'can_edit' => false,
        'can_delete' => false,
        'is_edited' => false
    ];
    
    // Send notification to complaint owner if not the same user
    $stmt = db()->prepare("SELECT user_id FROM complaints WHERE id = ?");
    $stmt->execute([$complaint_id]);
    $complaint_owner = $stmt->fetch();
    
    if ($complaint_owner && $complaint_owner['user_id'] != $_SESSION['student_id']) {
        // Link notification to the specific comment so the student can deep-link and highlight it in the feed
        sendInAppNotification(
            $complaint_owner['user_id'],
            'info',
            'New Comment',
            'Someone commented on your complaint',
            '',
            $comment_id,
            'comment'
        );
    }
    
    // Send notification to parent comment owner if this is a reply
    if ($parent_id) {
        $stmt = db()->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$parent_id]);
        $parent_owner = $stmt->fetch();
        
        if ($parent_owner && $parent_owner['user_id'] != $_SESSION['student_id']) {
            sendInAppNotification(
                $parent_owner['user_id'],
                'info',
                'Reply to Your Comment',
                'Someone replied to your comment',
                '',
                $comment_id,
                'comment'
            );
        }
    }
    
    // Log activity
    logActivity(
        'COMMENT_ADDED',
        "Complaint #{$complaint_id}",
        $_SESSION['student_id'],
        'user'
    );
    
    // Commit transaction
    db()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully',
        'comment' => $formatted_comment
    ]);
    exit();
    
} catch (PDOException $e) {
    // Rollback transaction
    db()->rollBack();
    
    error_log("Comment error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add comment'
    ]);
    exit();
}
?>
