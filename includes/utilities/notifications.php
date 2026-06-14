<?php
// Notification Utilities
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once ROOT_PATH . '/config/mail_config.php';
require_once ROOT_PATH . '/includes/utilities/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// header('Content-Type: application/json');

// if JSON payload present, merge into \\$_POST for convenience
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

// ---------- helper functions ----------

function getUnreadNotificationCount($user_id) {
    try {
        $stmt = db()->prepare("
            SELECT COUNT(*) as count
            FROM notifications
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting unread count: " . $e->getMessage());
        return 0;
    }
}

function getRecentNotifications($user_id, $limit = 5) {
    try {
        $stmt = db()->prepare("
            SELECT * FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting recent notifications: " . $e->getMessage());
        return [];
    }
}

function getAllNotifications($user_id) {
    try {
        $stmt = db()->prepare("
            SELECT * FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching all notifications: " . $e->getMessage());
        return [];
    }
}

function markNotificationAsRead($notification_id) {
    try {
        $stmt = db()->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE id = ?
        ");
        return $stmt->execute([$notification_id]);
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

function markAllNotificationsRead($user_id) {
    try {
        $stmt = db()->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ?
        ");
        return $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        error_log("Error marking all notifications read: " . $e->getMessage());
        return false;
    }
}

// function sendInAppNotification($user_id, $type, $title, $message, $icon = '', $reference_id = null, $reference_type = null) {
//     try {
//         $stmt = db()->prepare("
//             INSERT INTO notifications (
//                 user_id, notification_type, title, message, icon, 
//                 reference_id, reference_type, is_read, created_at
//             ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
//         ");
        
//         return $stmt->execute([
//             $user_id,
//             $type,
//             $title,
//             $message,
//             $icon,
//             $reference_id,
//             $reference_type
//         ]);
//     } catch (PDOException $e) {
//         error_log("In-app notification error: " . $e->getMessage());
//         return false;
//     }
// }

// ---------- AJAX action handling ----------

// If this file is requested directly (AJAX), handle actions. When included by other
// scripts for helper functions, skip the action handling to avoid accidental output.
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $action = $_GET['action'] ?? '';
    $user_id = $_SESSION['student_id'] ?? null;

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    switch ($action) {
        case 'count':
            echo json_encode(['success' => true, 'count' => getUnreadNotificationCount($user_id)]);
            exit;
        case 'recent':
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
            $notes = getRecentNotifications($user_id, $limit);
            foreach ($notes as &$n) {
                $n['time_ago'] = timeAgo($n['created_at']);
            }
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'notifications' => $notes]);
            exit;
        case 'mark_read':
            $id = $_POST['notification_id'] ?? $_GET['notification_id'] ?? null;
            if ($id) {
                $res = markNotificationAsRead($id);
            } else {
                $res = markAllNotificationsRead($user_id);
            }
            echo json_encode(['success' => $res]);
            exit;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
}

function sendVerificationEmail($to, $name, $token) {
    try {
        $mailer = getMailer();
        return $mailer->sendVerificationEmail($to, $name, $token);
    } catch (Exception $e) {
        error_log("Verification email error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $name, $reset_link) {
    try {
        $mailer = getMailer();
        
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .btn { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>HTU Complaint System</h1>
                </div>
                <div class='content'>
                    <h2>Password Reset Request</h2>
                    <p>Hi $name,</p>
                    <p>We received a request to reset your password. Click the button below to proceed:</p>
                    <a href='$reset_link' class='btn'>Reset Password</a>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, you can ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $mailer->sendNotification($email, 'Password Reset Request - HTU Complaint System', $message, true);
    } catch (Exception $e) {
        error_log("Password reset email error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email notification
 */
function sendEmailNotification($email, $subject, $template, $data = []) {
    try {
        $mailer = getMailer();
        // Generate email content from template
        $body = getEmailTemplate($template, $data);

        if (!$body) {
            error_log("Email template not found: {$template}");
            return false;
        }

        // Use Mailer wrapper method
        return $mailer->sendNotification($email, $subject, $body, true);
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send password changed confirmation email
 */
function sendPasswordChangedEmail($email, $name) {
    try {
        $mailer = getMailer();

        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>HTU Complaint System</h1>
                </div>
                <div class='content'>
                    <h2>Password Changed</h2>
                    <p>Hi " . htmlspecialchars($name) . ",</p>
                    <p>Your password was successfully changed. If you did not perform this action, please contact support immediately or use the password recovery flow.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message, please do not reply.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $mailer->sendNotification($email, 'Your Password Was Changed - HTU Complaint System', $message, true);
    } catch (Exception $e) {
        error_log("Password changed email error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send SMS notification (stub - implement as needed)
 */
function sendSMSNotification($phone, $message) {
    try {
        // TODO: Implement SMS service integration (Twilio, etc.)
        error_log("SMS stub called for: {$phone}");
        return true;
    } catch (Exception $e) {
        error_log("SMS sending error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send in-app notification
 */
if (!function_exists('sendInAppNotification')) {

function sendInAppNotification($user_id = null, $type, $title, $message, $icon = '', $reference_id = null, $reference_type = null, $admin_id = null) {
    try {
        // Support both user and admin notifications
        if ($user_id) {
            $stmt = db()->prepare("
                INSERT INTO notifications (
                    user_id, type, title, message,
                    related_id, related_type, is_read, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            
            return $stmt->execute([
                $user_id,
                $type,
                $title,
                $message,
                $reference_id,
                $reference_type
            ]);
        } elseif ($admin_id) {
            $stmt = db()->prepare("
                INSERT INTO notifications (
                    admin_id, type, title, message,
                    related_id, related_type, is_read, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            
            return $stmt->execute([
                $admin_id,
                $type,
                $title,
                $message,
                $reference_id,
                $reference_type
            ]);
        } else {
            error_log("sendInAppNotification: No user_id or admin_id provided");
            return false;
        }
    } catch (PDOException $e) {
        error_log("In-app notification error: " . $e->getMessage());
        return false;
    }
}
}

/**
 * Get email template
 */
function getEmailTemplate($template_name, $data = []) {
    try {
        $template_path = ROOT_PATH . '/templates/emails/' . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            return null;
        }
        
        // Extract data for use in template
        extract($data, EXTR_SKIP);
        
        // Capture template output
        ob_start();
        include $template_path;
        $content = ob_get_clean();
        
        return $content;
    } catch (Exception $e) {
        error_log("Error loading email template: " . $e->getMessage());
        return null;
    }
}

/**
 * Mark notification as read
 */
// function markNotificationAsRead($notification_id) {
//     try {
//         $stmt = db()->prepare("
//             UPDATE notifications 
//             SET is_read = 1, read_at = NOW()
//             WHERE id = ?
//         ");
//         return $stmt->execute([$notification_id]);
//     } catch (PDOException $e) {
//         error_log("Error marking notification as read: " . $e->getMessage());
//         return false;
//     }
// }

/**
 * Get unread notification count for user
 */
if (!function_exists('getUnreadNotificationCount')) {
function getUnreadNotificationCount($user_id) {
    try {
        $stmt = db()->prepare("
            SELECT COUNT(*) as count 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error getting unread count: " . $e->getMessage());
        return 0;
    }
}
}

/**
 * Get recent notifications
 */
// function getRecentNotifications($user_id, $limit = 5) {
//     try {
//         $stmt = db()->prepare("
//             SELECT * FROM notifications 
//             WHERE user_id = ? 
//             ORDER BY created_at DESC 
//             LIMIT ?
//         ");
//         $stmt->execute([$user_id, $limit]);
//         return $stmt->fetchAll();
//     } catch (PDOException $e) {
//         error_log("Error getting recent notifications: " . $e->getMessage());
//         return [];
//     }
// }
?>
