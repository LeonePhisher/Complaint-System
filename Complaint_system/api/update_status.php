<?php
// initialize session using the shared session handler so we can read admin_id
require_once '../config/constants.php';
require_once '../includes/auth/session.inc.php';
require_once '../config/database.php';
require_once '../includes/utilities/security.php';
require_once '../includes/utilities/validation.php';
require_once '../includes/utilities/helpers.php';
require_once '../includes/utilities/notifications.php';

// session.inc.php already calls session_start() with the custom handler

header('Content-Type: application/json');

// ensure admin session is available
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Permission gate (super_admin bypasses inside hasPermission)
if (!hasPermission('manage_complaints')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate CSRF token
if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

// Validate input
$complaint_id = intval($input['complaint_id'] ?? 0);
$status = sanitizeInput($input['status'] ?? '');
$rejection_reason = sanitizeInput($input['rejection_reason'] ?? '');
$notes = sanitizeInput($input['notes'] ?? '');

// Validate status
$valid_statuses = ['pending', 'under_review', 'published', 'resolved', 'rejected'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Check permission
$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'] ?? '';
if ($admin_role === '') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    // Get complaint details
    $stmt = db()->prepare("
        SELECT c.*, cat.admin_id as category_admin_id
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.id = ?
    ");
    $stmt->execute([$complaint_id]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        echo json_encode(['success' => false, 'message' => 'Complaint not found']);
        exit();
    }

    // Check if admin has permission to moderate this complaint
    if ($admin_role !== 'super_admin' && $complaint['category_admin_id'] != $admin_id) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to moderate this complaint']);
        exit();
    }

    // Special validation for rejection
    if ($status === 'rejected') {
        if (empty($rejection_reason)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a reason for rejection']);
            exit();
        }
        
        // Category admins cannot reject without super admin approval
        if ($admin_role !== 'super_admin') {
            // Check if super admin approval is required
            $stmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = 'require_superadmin_rejection'");
            $stmt->execute();
            $setting = $stmt->fetch();
            
            if ($setting && $setting['setting_value'] == '1') {
                // Send request to super admin for approval
                $stmt = db()->prepare("
                    INSERT INTO notifications (admin_id, title, message, type, related_id, related_type)
                    SELECT id, ?, ?, ?, ?, 'rejection_request'
                    FROM admins 
                    WHERE role = 'super_admin'
                ");
                
                $stmt->execute([
                    'Rejection Approval Request',
                    "Admin " . ($_SESSION['admin_name'] ?? 'Admin') . " requested to reject complaint #{$complaint['complaint_code']}",
                    'warning',
                    $complaint_id
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Rejection request sent to super admin for approval',
                    'requires_approval' => true
                ]);
                exit();
            }
        }
    }

    // Start transaction
    db()->beginTransaction();

    // Update complaint status
    $update_fields = ['status = ?'];
    $update_values = [$status];
    
    if ($status === 'rejected') {
        $update_fields[] = 'rejection_reason = ?';
        $update_fields[] = 'rejected_by = ?';
        $update_values[] = $rejection_reason;
        $update_values[] = $admin_id;
    } elseif ($status === 'published') {
        $update_fields[] = 'published_at = NOW()';
    } elseif ($status === 'resolved') {
        $update_fields[] = 'resolved_at = NOW()';
        $update_fields[] = 'assigned_to = ?';
        $update_values[] = $admin_id;
    } elseif ($status === 'under_review') {
        $update_fields[] = 'assigned_to = ?';
        $update_values[] = $admin_id;
    }
    
    $update_values[] = $complaint_id;
    
    $stmt = db()->prepare("
        UPDATE complaints 
        SET " . implode(', ', $update_fields) . " 
        WHERE id = ?
    ");
    $stmt->execute($update_values);

    // Create status history
    $stmt = db()->prepare("
        INSERT INTO status_history (complaint_id, old_status, new_status, changed_by, changed_by_type, notes)
        VALUES (?, ?, ?, ?, 'admin', ?)
    ");
    $stmt->execute([
        $complaint_id,
        $complaint['status'],
        $status,
        $admin_id,
        $notes
    ]);

    // Send notification to student if status is published or rejected
    if (in_array($status, ['published', 'rejected', 'resolved'])) {
        $notification_title = '';
        $notification_message = '';
        
        switch ($status) {
            case 'published':
                $notification_title = 'Your Complaint Has Been Published';
                $notification_message = "Your complaint '{$complaint['title']}' has been approved and published to the feed.";
                break;
            case 'rejected':
                $notification_title = 'Complaint Rejected';
                $notification_message = "Your complaint '{$complaint['title']}' has been rejected. Reason: {$rejection_reason}";
                break;
            case 'resolved':
                $notification_title = 'Complaint Resolved';
                $notification_message = "Your complaint '{$complaint['title']}' has been marked as resolved.";
                break;
        }
        
        // send via helper
        sendInAppNotification(
            $complaint['user_id'],
            $status === 'rejected' ? 'error' : 'success',
            $notification_title,
            $notification_message,
            '',
            $complaint_id,
            'complaint'
        );

        // Also notify the category admin about status changes (except if they made the change)
        if ($complaint['category_admin_id'] && $complaint['category_admin_id'] != $admin_id) {
            $admin_notification_title = "Complaint Status Updated";
            $admin_notification_message = "Complaint #{$complaint['complaint_code']} status changed to: {$status}";
            
            sendInAppNotification(
                null, // no user_id
                'info',
                $admin_notification_title,
                $admin_notification_message,
                '',
                $complaint_id,
                'complaint',
                $complaint['category_admin_id']
            );
        }
    }

    // Create audit log
    $stmt = db()->prepare("
        INSERT INTO audit_log (admin_id, action, table_name, record_id, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $admin_id,
        'COMPLAINT_' . strtoupper($status),
        'complaints',
        $complaint_id,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);

    // Commit transaction
    db()->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Complaint status updated successfully',
        'new_status' => $status,
        'complaint_id' => $complaint_id
    ]);
    exit();

} catch (PDOException $e) {
    // Rollback transaction
    db()->rollBack();
    
    error_log("Error updating complaint status: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update status. Please try again.'
    ]);
    exit();
}
?>
