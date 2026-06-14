<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$user_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null;
$admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
$user_type = isset($_SESSION['admin_id']) ? 'admin' : 'user';

try {
    if ($user_type === 'user') {
        // Get user's recent activity
        $query = "
            SELECT 
                a.action,
                a.created_at,
                a.notes,
                c.complaint_code,
                c.title
            FROM audit_log a
            LEFT JOIN complaints c ON a.record_id = c.id AND a.table_name = 'complaints'
            WHERE a.user_id = ?
            ORDER BY a.created_at DESC
            LIMIT ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$user_id, $limit]);
    } else {
        // Get admin's recent activity
        $query = "
            SELECT 
                a.action,
                a.created_at,
                a.notes,
                c.complaint_code,
                c.title,
                u.full_name as user_name
            FROM audit_log a
            LEFT JOIN complaints c ON a.record_id = c.id AND a.table_name = 'complaints'
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.admin_id = ? OR a.table_name = 'complaints'
            ORDER BY a.created_at DESC
            LIMIT ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$admin_id, $limit]);
    }
    
    $activities = $stmt->fetchAll();
    
    // Format activities
    $formatted_activities = [];
    foreach ($activities as $activity) {
        $icon = '';
        $color = '';
        $title = '';
        
        switch ($activity['action']) {
            case 'COMPLAINT_SUBMITTED':
                $icon = 'fa-plus';
                $color = '#ed8936';
                $title = 'Complaint Submitted';
                break;
            case 'COMPLAINT_APPROVED':
            case 'COMPLAINT_PUBLISHED':
                $icon = 'fa-check';
                $color = '#48bb78';
                $title = 'Complaint Approved';
                break;
            case 'COMPLAINT_REJECTED':
                $icon = 'fa-times';
                $color = '#f56565';
                $title = 'Complaint Rejected';
                break;
            case 'COMPLAINT_RESOLVED':
                $icon = 'fa-flag-checkered';
                $color = '#4299e1';
                $title = 'Complaint Resolved';
                break;
            case 'VOTE_UPVOTE':
                $icon = 'fa-arrow-up';
                $color = '#48bb78';
                $title = 'Upvoted Complaint';
                break;
            case 'VOTE_DOWNVOTE':
                $icon = 'fa-arrow-down';
                $color = '#f56565';
                $title = 'Downvoted Complaint';
                break;
            case 'COMMENT_ADDED':
                $icon = 'fa-comment';
                $color = '#667eea';
                $title = 'Comment Added';
                break;
            case 'REGISTER':
                $icon = 'fa-user-plus';
                $color = '#9f7aea';
                $title = 'User Registered';
                break;
            case 'LOGIN_SUCCESS':
                $icon = 'fa-sign-in-alt';
                $color = '#48bb78';
                $title = 'Login Successful';
                break;
            default:
                $icon = 'fa-info-circle';
                $color = '#a0aec0';
                $title = 'System Activity';
        }
        
        $formatted_activities[] = [
            'action' => $activity['action'],
            'title' => $title,
            'notes' => $activity['notes'],
            'time_ago' => timeAgo($activity['created_at']),
            'created_at' => $activity['created_at'],
            'icon' => $icon,
            'color' => $color,
            'complaint_code' => $activity['complaint_code'] ?? null,
            'complaint_title' => $activity['title'] ?? null,
            'user_name' => $activity['user_name'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'activities' => $formatted_activities
    ]);
    
} catch (PDOException $e) {
    error_log("Recent activity error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load recent activity'
    ]);
}
?>