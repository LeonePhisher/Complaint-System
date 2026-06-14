<?php
require_once '../config/constants.php';
require_once '../includes/auth/session.inc.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';

header('Content-Type: application/json');

// Check if user is super admin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = intval($_GET['id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit();
}

try {
    // Get user details
    $stmt = db()->prepare("
        SELECT 
            id,
            index_number,
            email,
            full_name,
            phone,
            department,
            level,
            account_status,
            is_verified,
            avatar_color,
            created_at,
            last_login,
            login_attempts
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    // Get initials
    $user['initials'] = getInitials($user['full_name']);
    $user['registered'] = date('M d, Y', strtotime($user['created_at']));
    if ($user['last_login']) {
        $user['last_login'] = timeAgo($user['last_login']);
    }
    
    // Get complaint statistics
    $stmt = db()->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM complaints
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['total'] = $stats['total'] ?? 0;
    $stats['published'] = $stats['published'] ?? 0;
    $stats['resolved'] = $stats['resolved'] ?? 0;
    $stats['rejected'] = $stats['rejected'] ?? 0;
    
    // Get recent complaints (last 5)
    // $stmt = db()->prepare("
    //     SELECT 
    //         id,
    //         complaint_code,
    //         title,
    //         status,
    //         created_at
    //     FROM complaints
    //     WHERE user_id = ?
    //     ORDER BY created_at DESC
    //     LIMIT 5
    // ");
    // $stmt->execute([$user_id]);
    // $recent_complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format recent complaints
    // foreach ($recent_complaints as &$complaint) {
    //     $complaint['time_ago'] = timeAgo($complaint['created_at']);
    // }
    
    echo json_encode([
        'success' => true,
        'user' => $user,
        'stats' => $stats,
        'recent_complaints' => $recent_complaints
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching user details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
