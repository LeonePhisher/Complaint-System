<?php

require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/security.php';
require_once '../includes/utilities/validation.php';
require_once '../includes/utilities/helpers.php';
require_once '../includes/utilities/notifications.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to submit a complaint']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input OR form data (for FormData submissions)
$input = $_POST;
if (empty($input) || !isset($input['title'])) {
    $input = json_decode(file_get_contents('php://input'), true);
}

// Validate CSRF token
if (!isset($input['csrf_token']) || !validateCSRFToken($input['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$title = sanitizeInput($input['title'] ?? '');

// Preserve basic formatting tags from the rich text editor while removing disallowed tags
$raw_description = $input['description'] ?? '';
$allowed_tags = '<p><br><strong><em><ul><ol><li><a><b><i><u>';
$description = strip_tags($raw_description, $allowed_tags);

// Basic cleanup: prevent javascript: links
$description = preg_replace('/href\s*=\s*["\']\s*javascript:[^"\']*["\']/i', 'href="#"', $description);
$category_id = intval($input['category_id'] ?? 0);
$location = sanitizeInput($input['location'] ?? '');
$urgency = sanitizeInput($input['urgency'] ?? 'medium');
$attachments = $input['attachments'] ?? [];

// Validation
$errors = [];

if (empty($title) || strlen($title) < 5) {
    $errors['title'] = 'Title must be at least 5 characters';
}

if (empty($description) || strlen($description) < 20) {
    $errors['description'] = 'Description must be at least 20 characters';
}

if ($category_id <= 0) {
    $errors['category_id'] = 'Please select a valid category';
}

if (!in_array($urgency, ['low', 'medium', 'high', 'critical'])) {
    $errors['urgency'] = 'Invalid urgency level';
}

// Check daily submission limit
$user_id = $_SESSION['student_id'];
$today = date('Y-m-d');
try {
    $stmt = db()->prepare("
        SELECT COUNT(*) as count 
        FROM complaints 
        WHERE user_id = ? 
        AND DATE(created_at) = ?
    ");
    $stmt->execute([$user_id, $today]);
    $result = $stmt->fetch();
    
    if ($result['count'] >= 3) {
        $errors['limit'] = 'You have reached your daily submission limit';
    }
} catch (PDOException $e) {
    error_log("Error checking submission limit: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}

// If there are validation errors
if (!empty($errors)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Please correct the errors below',
        'errors' => $errors
    ]);
    exit();
}

try {
    // Start transaction
    db()->beginTransaction();

    $complaint_code = generateComplaintCode();

    // Insert complaint
    $stmt = db()->prepare("
        INSERT INTO complaints (
            complaint_code, user_id, category_id, title, description, 
            location, urgency, status, is_anonymous
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', TRUE)
    ");
    
    $stmt->execute([
        $complaint_code,
        $user_id,
        $category_id,
        $title,
        $description,
        $location,
        $urgency
    ]);

    $complaint_id = db()->lastInsertId();

    // Handle file uploads if any
    if (!empty($attachments)) {
        $attachment_paths = [];
        $upload_dir = COMPLAINT_UPLOADS . $complaint_id . '/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        foreach ($attachments as $attachment) {
            // Validate and save attachment
            // (Implementation depends on how files are uploaded)
        }
        
        // Update complaint with attachment paths
        $stmt = db()->prepare("UPDATE complaints SET attachments = ? WHERE id = ?");
        $stmt->execute([json_encode($attachment_paths), $complaint_id]);
    }

    // Create audit log
    $stmt = db()->prepare("
        INSERT INTO audit_log (user_id, action, table_name, record_id, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        'COMPLAINT_SUBMITTED',
        'complaints',
        $complaint_id,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);

    // Create status history
    $stmt = db()->prepare("
        INSERT INTO status_history (complaint_id, new_status, changed_by_type, notes)
        VALUES (?, 'pending', 'user', 'Complaint submitted')
    ");
    $stmt->execute([$complaint_id]);

    // Get category admin for email notification
    $stmt = db()->prepare("
        SELECT a.email, a.full_name
        FROM categories c
        JOIN admins a ON c.admin_id = a.id
        WHERE c.id = ? AND a.is_active = 1
    ");
    $stmt->execute([$category_id]);
    $category_admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // In-app notifications (category admin + all super admins)
    notifyAdminsOfNewComplaint($complaint_id, $complaint_code, $title, $category_id, $urgency);

    // Email notification (category admin only)
    if ($category_admin) {
        try {
            require_once '../config/mail_config.php';
            if (function_exists('getMailer')) {
                $mailer = getMailer();
                
                $email_message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                        .complaint-details { background: #fff; border-left: 4px solid #667eea; padding: 15px; margin: 15px 0; }
                        .btn { display: inline-block; background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
                        .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>New Complaint Submitted</h1>
                        </div>
                        <div class='content'>
                            <p>Hello {$category_admin['full_name']},</p>
                            <p>A new complaint has been submitted in your category. Here are the details:</p>
                            <div class='complaint-details'>
                                <h3>{$title}</h3>
                                <p><strong>Urgency Level:</strong> " . ucfirst($urgency) . "</p>
                                <p><strong>Location:</strong> {$location}</p>
                                <p><strong>Description:</strong></p>
                                <p>" . html_entity_decode($description) . "</p>
                            </div>
                            <p><a href='" . APP_URL . "/pages/admin/complaints.php?view={$complaint_id}' class='btn'>Review Complaint</a></p>
                            <p>Please log in to the admin panel to take necessary action on this complaint.</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated notification. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $email_result = @$mailer->sendNotification(
                    $category_admin['email'],
                    "New Complaint Submitted - " . APP_NAME,
                    $email_message
                );
                
                if (!$email_result) {
                    error_log("Failed to send email notification to admin {$category_admin['email']}");
                }
            }
        } catch (Exception $e) {
            error_log("Error sending email notification: " . $e->getMessage());
        }
    } else {
        error_log("No admin found for category {$category_id}");
    }

    // Commit transaction
    db()->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Complaint submitted successfully! It will be reviewed by an admin.',
        'complaint_id' => $complaint_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("COMPLAINT SUBMIT ERROR: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit complaint. Please try again.',
        'debug_error' => $e->getMessage()
    ]);
    exit();
}
?>
