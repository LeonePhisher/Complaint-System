<?php
/**
 * Unified Notifications API
 * Handles all notification operations for both students and admins
 */
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// If JSON payload present, merge into $_POST for convenience.
$rawInput = file_get_contents('php://input');
if ($rawInput) {
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        foreach ($jsonData as $k => $v) {
            if (!isset($_POST[$k])) {
                $_POST[$k] = $v;
            }
        }
    }
}

// Determine user type and ID
$session_role = strtolower((string)($_SESSION['user_role'] ?? ''));

$user_id = $_SESSION['student_id'] ?? null;
$admin_id = $_SESSION['admin_id'] ?? null;

$is_student = !empty($user_id);
$is_admin = !empty($admin_id);

// If both roles exist in the same session (can happen when switching accounts),
// choose based on the explicit session role.
if ($session_role === 'admin') {
    $is_student = false;
    $user_id = null;
} elseif ($session_role === 'student') {
    $is_admin = false;
    $admin_id = null;
} elseif ($is_admin && $is_student) {
    // Default to admin when ambiguous to keep the admin navbar dropdown working.
    $is_student = false;
    $user_id = null;
}

if (!$is_student && !$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        // ===== GET RECENT UNREAD NOTIFICATIONS FOR MODAL =====
        case 'recent_unread':
            $limit = intval($_GET['limit'] ?? 5);
            $query = $is_student 
                ? "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?"
                : "SELECT * FROM notifications WHERE admin_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?";
            
            $stmt = db()->prepare($query);
            $user_param = $is_student ? $user_id : $admin_id;
            $stmt->execute([$user_param, $limit]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($notifications as &$n) {
                $n['time_ago'] = timeAgo($n['created_at']);
            }
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications)
            ]);
            exit;

        // ===== GET UNREAD COUNT =====
        case 'unread_count':
            $query = $is_student
                ? "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0"
                : "SELECT COUNT(*) as count FROM notifications WHERE admin_id = ? AND is_read = 0";
            
            $stmt = db()->prepare($query);
            $user_param = $is_student ? $user_id : $admin_id;
            $stmt->execute([$user_param]);
            $result = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'count' => $result['count'] ?? 0
            ]);
            exit;

        // ===== MARK SINGLE NOTIFICATION AS READ =====
        case 'mark_read':
            $notification_id = $_POST['notification_id'] ?? $_GET['notification_id'] ?? null;
            
            if (!$notification_id) {
                // Mark all as read
                $query = $is_student
                    ? "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0"
                    : "UPDATE notifications SET is_read = 1 WHERE admin_id = ? AND is_read = 0";
                
                $stmt = db()->prepare($query);
                $user_param = $is_student ? $user_id : $admin_id;
                $result = $stmt->execute([$user_param]);
                
                echo json_encode(['success' => $result]);
            } else {
                // Mark specific as read
                $query = $is_student
                    ? "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?"
                    : "UPDATE notifications SET is_read = 1 WHERE id = ? AND admin_id = ?";
                $stmt = db()->prepare($query);
                $user_param = $is_student ? $user_id : $admin_id;
                $result = $stmt->execute([$notification_id, $user_param]);
                
                echo json_encode(['success' => $result]);
            }
            exit;

        // ===== GET PAGINATED NOTIFICATIONS =====
        case 'paginated':
            $page = intval($_GET['page'] ?? 1);
            $per_page = intval($_GET['per_page'] ?? 7);
            
            if ($page < 1) $page = 1;
            if ($per_page < 1 || $per_page > 50) $per_page = 7;
            
            $offset = ($page - 1) * $per_page;
            
            // Get total count
            $query = $is_student
                ? "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?"
                : "SELECT COUNT(*) as total FROM notifications WHERE admin_id = ?";
            
            $stmt = db()->prepare($query);
            $user_param = $is_student ? $user_id : $admin_id;
            $stmt->execute([$user_param]);
            $total = $stmt->fetch()['total'] ?? 0;
            $total_pages = ceil($total / $per_page);
            
            // Get paginated notifications
            $query = $is_student
                ? "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?"
                : "SELECT * FROM notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
            
            $stmt = db()->prepare($query);
            $stmt->execute([$user_param, $per_page, $offset]);
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
                'total_pages' => $total_pages,
                'current_page' => $page,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]);
            exit;

        // ===== MARK ALL AS READ =====
        case 'mark_all_read':
            $query = $is_student
                ? "UPDATE notifications SET is_read = 1 WHERE user_id = ?"
                : "UPDATE notifications SET is_read = 1 WHERE admin_id = ?";
            
            $stmt = db()->prepare($query);
            $user_param = $is_student ? $user_id : $admin_id;
            $result = $stmt->execute([$user_param]);
            
            echo json_encode(['success' => $result]);
            exit;

        // ===== DELETE NOTIFICATION =====
        case 'delete':
            $notification_id = $_POST['notification_id'] ?? $_GET['notification_id'] ?? null;
            
            if (!$notification_id) {
                echo json_encode(['success' => false, 'message' => 'No notification ID provided']);
                exit;
            }
            
            // Verify ownership
            $query = $is_student
                ? "DELETE FROM notifications WHERE id = ? AND user_id = ?"
                : "DELETE FROM notifications WHERE id = ? AND admin_id = ?";
            
            $stmt = db()->prepare($query);
            $user_param = $is_student ? $user_id : $admin_id;
            $result = $stmt->execute([$notification_id, $user_param]);
            
            echo json_encode(['success' => $result]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            exit;
    }
} catch (PDOException $e) {
    error_log("Notification API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
} catch (Exception $e) {
    error_log("Notification API exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
?>
