<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/auth/session.inc.php';
require_once '../includes/utilities/helpers.php';
require_once '../config/mail_config.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

$csrf = (string)($input['csrf_token'] ?? '');
if ($csrf === '' || !validateCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$to = trim((string)($input['test_email'] ?? ($_SESSION['admin_email'] ?? '')));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid test email address']);
    exit();
}

try {
    $mailer = getMailer();

    $subject = 'SMTP Test - ' . (defined('APP_NAME') ? APP_NAME : 'HTU Complaint System');
    $body = '<p>This is a test email to verify SMTP connectivity.</p><p>Time: ' . date('Y-m-d H:i:s') . '</p>';

    $ok = $mailer->sendNotification($to, $subject, $body, true, false);
    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Test email sent']);
        exit();
    }

    $err = method_exists($mailer, 'getLastErrorInfo') ? $mailer->getLastErrorInfo() : 'Unknown error';
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $err ?: 'Failed to send test email']);
} catch (Throwable $e) {
    error_log('test_email error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
