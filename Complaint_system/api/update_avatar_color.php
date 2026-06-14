<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/auth/session.inc.php';
require_once '../includes/utilities/helpers.php';

header('Content-Type: application/json');

if (!isStudent()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Accept JSON or form-data
$raw = file_get_contents('php://input');
if ($raw) {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        foreach ($json as $k => $v) {
            if (!isset($_POST[$k])) $_POST[$k] = $v;
        }
    }
}

$csrf = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrf)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$color = trim((string)($_POST['avatar_color'] ?? ''));
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid color']);
    exit();
}

try {
    $student_id = (int)$_SESSION['student_id'];
    $stmt = db()->prepare("UPDATE users SET avatar_color = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$color, $student_id]);

    $_SESSION['user_avatar'] = $color;

    echo json_encode(['success' => true, 'avatar_color' => $color]);
} catch (PDOException $e) {
    error_log("update_avatar_color error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

