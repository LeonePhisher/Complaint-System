<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/auth/session.inc.php';
require_once '../includes/utilities/helpers.php';

header('Content-Type: application/json');

$is_admin = isAdmin();
$is_student = isLoggedIn();

// Allow admins (with permission) and students (own complaints only)
if (!$is_admin && !$is_student) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($is_admin && !hasPermission('manage_complaints')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$csrf = (string)($input['csrf_token'] ?? '');
if ($csrf === '' || !validateCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$complaint_id = (int)($input['complaint_id'] ?? 0);
if ($complaint_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit;
}

$pdo = null;
try {
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id, status, user_id FROM complaints WHERE id = ? LIMIT 1');
    $stmt->execute([$complaint_id]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$complaint) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Complaint not found']);
        exit;
    }

    // Students can only delete their own complaints
    if ($is_student && !$is_admin) {
        $student_id = (int)($_SESSION['student_id'] ?? 0);
        if ($student_id <= 0 || (int)$complaint['user_id'] !== $student_id) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
            exit;
        }
    }

    if (($complaint['status'] ?? '') !== 'pending') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only pending complaints can be deleted']);
        exit;
    }

    // Notifications table uses related_type/related_id (no complaint_id column)
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE related_id = ? AND related_type IN ('complaint','complaints','rejection_request')");
    $stmt->execute([$complaint_id]);

    // Child tables (comments/votes/status_history) are removed by FK ON DELETE CASCADE
    $stmt = $pdo->prepare('DELETE FROM complaints WHERE id = ?');
    $stmt->execute([$complaint_id]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Complaint deleted successfully']);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Delete complaint error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete complaint']);
}
