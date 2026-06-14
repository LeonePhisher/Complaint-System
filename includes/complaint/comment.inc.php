Includes/complaints/comment.inc.php



<?php
/**
 * Comment Management
 * Handles adding, viewing, and managing comments on complaints
 */

require_once '../../config/constants.php';
require_once '../utilities/security.php';
require_once '../utilities/helpers.php';

/**
 * Add a comment to a complaint
 */
function addComment($complaint_id, $user_id, $user_type, $content, $is_anonymous = false) {
    try {
        // Verify complaint exists
        $stmt = db()->prepare("SELECT id, student_id, status FROM complaints WHERE id = ?");
        $stmt->execute([$complaint_id]);
        $complaint = $stmt->fetch();
        
        if (!$complaint) {
            return ['success' => false, 'message' => 'Complaint not found'];
        }
        
        // Check if user can comment
        if ($user_type === 'student') {
            // Students can only comment on published complaints
            if ($complaint['status'] !== 'published' && $complaint['status'] !== 'resolved') {
                return ['success' => false, 'message' => 'You can only comment on published or resolved complaints'];
            }
        }
        
        // Insert comment
        if ($user_type === 'student') {
            $stmt = db()->prepare("
                INSERT INTO comments (complaint_id, student_id, content, is_anonymous, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$complaint_id, $user_id, $content, $is_anonymous ? 1 : 0]);
        } else {
            $stmt = db()->prepare("
                INSERT INTO comments (complaint_id, admin_id, content, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$complaint_id, $user_id, $content]);
        }
        
        $comment_id = db()->lastInsertId();
        
        // Update complaint's last activity
        $stmt = db()->prepare("UPDATE complaints SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$complaint_id]);
        
        // Notify complaint owner if not anonymous and not self-comment
        if ($user_type === 'admin' || ($user_type === 'student' && $user_id != $complaint['student_id'])) {
            notifyCommentAdded($complaint_id, $comment_id, $user_type);
        }
        
        // Log activity
        logActivity(
            $user_id,
            $user_type,
            'comment_added',
            "Added comment to complaint #{$complaint_id}",
            $complaint_id
        );
        
        return [
            'success' => true,
            'message' => 'Comment added successfully',
            'comment_id' => $comment_id
        ];
        
    } catch (PDOException $e) {
        error_log("Add comment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to add comment'];
    }
}

/**
 * Get comments for a complaint
 */
function getComments($complaint_id, $page = 1, $limit = 20) {
    try {
        $offset = ($page - 1) * $limit;
        
        $stmt = db()->prepare("
            SELECT 
                c.*,
                CASE 
                    WHEN c.student_id IS NOT NULL AND c.is_anonymous = 1 THEN 'Anonymous Student'
                    WHEN c.student_id IS NOT NULL THEN s.full_name
                    WHEN c.admin_id IS NOT NULL THEN a.full_name
                END as commenter_name,
                CASE 
                    WHEN c.student_id IS NOT NULL THEN 'student'
                    WHEN c.admin_id IS NOT NULL THEN 'admin'
                END as commenter_type,
                s.profile_picture as student_avatar,
                a.profile_picture as admin_avatar,
                a.role as admin_role
            FROM comments c
            LEFT JOIN students s ON c.student_id = s.id
            LEFT JOIN admins a ON c.admin_id = a.id
            WHERE c.complaint_id = ?
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$complaint_id, $limit, $offset]);
        $comments = $stmt->fetchAll();
        
        // Get total count for pagination
        $stmt = db()->prepare("SELECT COUNT(*) as total FROM comments WHERE complaint_id = ?");
        $stmt->execute([$complaint_id]);
        $total = $stmt->fetch()['total'];
        
        return [
            'success' => true,
            'comments' => $comments,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
        
    } catch (PDOException $e) {
        error_log("Get comments error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to load comments'];
    }
}

/**
 * Delete a comment
 */
function deleteComment($comment_id, $user_id, $user_type) {
    try {
        // Check if comment exists and user has permission
        if ($user_type === 'student') {
            $stmt = db()->prepare("
                SELECT id, complaint_id, student_id 
                FROM comments 
                WHERE id = ? AND student_id = ?
            ");
            $stmt->execute([$comment_id, $user_id]);
        } else {
            // Admins can delete any comment
            $stmt = db()->prepare("
                SELECT c.id, c.complaint_id, c.student_id, c.admin_id,
                       co.student_id as complaint_owner_id
                FROM comments c
                JOIN complaints co ON c.complaint_id = co.id
                WHERE c.id = ?
            ");
            $stmt->execute([$comment_id]);
        }
        
        $comment = $stmt->fetch();
        
        if (!$comment) {
            return ['success' => false, 'message' => 'Comment not found or permission denied'];
        }
        
        // For admins, check if they own the complaint or are super admin
        if ($user_type === 'admin') {
            if($user_type ==='super_admin') {
                $is_super_admin = true;
            } else {
                $is_super_admin = false;
            }
            $is_complaint_owner = ($comment['complaint_owner_id'] ?? null) == $user_id;
            
            if (!$is_super_admin && !$is_complaint_owner) {
                return ['success' => false, 'message' => 'Permission denied'];
            }
        }
        
        // Delete comment
        $stmt = db()->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$comment_id]);
        
        // Log activity
        logActivity(
            $user_id,
            $user_type,
            'comment_deleted',
            "Deleted comment #{$comment_id} from complaint #{$comment['complaint_id']}",
            $comment['complaint_id']
        );
        
        return ['success' => true, 'message' => 'Comment deleted successfully'];
        
    } catch (PDOException $e) {
        error_log("Delete comment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete comment'];
    }
}

/**
 * Edit a comment
 */
function editComment($comment_id, $user_id, $user_type, $new_content) {
    try {
        // Check if comment exists and user has permission
        if ($user_type === 'student') {
            $stmt = db()->prepare("
                SELECT id, complaint_id, student_id, created_at 
                FROM comments 
                WHERE id = ? AND student_id = ?
            ");
            $stmt->execute([$comment_id, $user_id]);
        } else {
            $stmt = db()->prepare("
                SELECT c.*, co.student_id as complaint_owner_id
                FROM comments c
                JOIN complaints co ON c.complaint_id = co.id
                WHERE c.id = ?
            ");
            $stmt->execute([$comment_id]);
        }
        
        $comment = $stmt->fetch();
        
        if (!$comment) {
            return ['success' => false, 'message' => 'Comment not found or permission denied'];
        }
        
        // Check if comment is too old to edit (e.g., 30 minutes for students)
        if ($user_type === 'student') {
            $comment_time = strtotime($comment['created_at']);
            $time_diff = time() - $comment_time;
            
            if ($time_diff > 1800) { // 30 minutes
                return ['success' => false, 'message' => 'Comments can only be edited within 30 minutes of posting'];
            }
        }
        
        // Update comment
        $stmt = db()->prepare("
            UPDATE comments 
            SET content = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$new_content, $comment_id]);
        
        // Log activity
        logActivity(
            $user_id,
            $user_type,
            'comment_edited',
            "Edited comment #{$comment_id} on complaint #{$comment['complaint_id']}",
            $comment['complaint_id']
        );
        
        return ['success' => true, 'message' => 'Comment updated successfully'];
        
    } catch (PDOException $e) {
        error_log("Edit comment error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update comment'];
    }
}

/**
 * Notify user when comment is added
 */
function notifyCommentAdded($complaint_id, $comment_id, $commenter_type) {
    try {
        // Get complaint details
        $stmt = db()->prepare("
            SELECT c.*, s.email, s.full_name, s.id as student_id,
                   cat.admin_id as category_admin_id
            FROM complaints c
            JOIN students s ON c.student_id = s.id
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.id = ?
        ");
        $stmt->execute([$complaint_id]);
        $complaint = $stmt->fetch();
        
        if (!$complaint) return;
        
        // Notify complaint owner (if comment is not by them)
        if ($commenter_type !== 'student' || $complaint['student_id'] != $commenter_type) {
            // Send email notification
            $subject = "New comment on your complaint #{$complaint['complaint_code']}";
            sendEmailNotification(
                $complaint['email'],
                $subject,
                'new_comment',
                [
                    'name' => $complaint['full_name'],
                    'complaint_code' => $complaint['complaint_code'],
                    'complaint_title' => $complaint['title'],
                    'comment_url' => APP_URL . "/pages/student/complaint.php?id={$complaint_id}#comment-{$comment_id}",
                    'app_name' => APP_NAME
                ]
            );
            
            // Create in-app notification
            createNotification(
                $complaint['student_id'],
                'student',
                'new_comment',
                "Someone commented on your complaint #{$complaint['complaint_code']}",
                $complaint_id
            );
        }
        
        // Notify category admin (if comment is by student)
        if ($commenter_type === 'student' && $complaint['category_admin_id']) {
            $stmt = db()->prepare("SELECT email, full_name FROM admins WHERE id = ?");
            $stmt->execute([$complaint['category_admin_id']]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                $subject = "New comment on complaint #{$complaint['complaint_code']}";
                sendEmailNotification(
                    $admin['email'],
                    $subject,
                    'admin_new_comment',
                    [
                        'name' => $admin['full_name'],
                        'complaint_code' => $complaint['complaint_code'],
                        'complaint_title' => $complaint['title'],
                        'comment_url' => APP_URL . "/pages/admin/complaint.php?id={$complaint_id}#comment-{$comment_id}",
                        'app_name' => APP_NAME
                    ]
                );
                
                createNotification(
                    $admin['id'],
                    'admin',
                    'new_comment',
                    "New comment on complaint #{$complaint['complaint_code']}",
                    $complaint_id
                );
            }
        }
        
    } catch (PDOException $e) {
        error_log("Comment notification error: " . $e->getMessage());
    }
}

/**
 * Create in-app notification
 */
function createNotification($user_id, $user_type, $type, $message, $reference_id = null) {
    try {
        $stmt = db()->prepare("
            INSERT INTO notifications (user_id, user_type, type, message, reference_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $user_type, $type, $message, $reference_id]);
        
    } catch (PDOException $e) {
        error_log("Create notification error: " . $e->getMessage());
    }
}

/**
 * Get comment count for a complaint
 */
function getCommentCount($complaint_id) {
    try {
        $stmt = db()->prepare("SELECT COUNT(*) as count FROM comments WHERE complaint_id = ?");
        $stmt->execute([$complaint_id]);
        return $stmt->fetch()['count'];
        
    } catch (PDOException $e) {
        error_log("Comment count error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Format comment for display
 */
function formatComment($comment) {
    $avatar = '';
    $name = $comment['commenter_name'] ?? 'Unknown';
    $badge = '';
    
    if ($comment['commenter_type'] === 'admin') {
        $avatar = $comment['admin_avatar'] ?? 'default-admin.png';
        $badge = '<span class="admin-badge">' . 
                 ($comment['admin_role'] === 'super_admin' ? 'Super Admin' : 'Admin') . 
                 '</span>';
    } else {
        $avatar = $comment['student_avatar'] ?? 'default-student.png';
        if ($comment['is_anonymous']) {
            $name = 'Anonymous Student';
            $badge = '<span class="anonymous-badge">Anonymous</span>';
        }
    }
    
    return [
        'id' => $comment['id'],
        'name' => $name,
        'avatar' => $avatar,
        'badge' => $badge,
        'content' => nl2br(htmlspecialchars($comment['content'])),
        'time' => timeAgo($comment['created_at']),
        'created_at' => $comment['created_at'],
        'updated_at' => $comment['updated_at'],
        'is_edited' => $comment['created_at'] != $comment['updated_at'],
        'commenter_type' => $comment['commenter_type'],
        'is_anonymous' => $comment['is_anonymous'] ?? false
    ];
}