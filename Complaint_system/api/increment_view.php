<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/auth/session.inc.php';
require_once '../includes/utilities/helpers.php';

header('Content-Type: application/json');

// Views are only meaningful for authenticated sessions in this app.
if (!isLoggedIn() && !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Accept JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

$complaint_id = (int)($input['complaint_id'] ?? 0);
$csrf = (string)($input['csrf_token'] ?? '');

if ($complaint_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit();
}

// Require CSRF if a token is set in the session (normal case for logged-in users).
// This blocks drive-by increments from other sites.
if (!validateCSRFToken($csrf)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

// Per-session throttle so refreshing / opening comments repeatedly doesn't inflate views.
$throttle_seconds = 900; // 15 minutes per complaint per session
if (!isset($_SESSION['view_throttle']) || !is_array($_SESSION['view_throttle'])) {
    $_SESSION['view_throttle'] = [];
}

$now = time();
$last = (int)($_SESSION['view_throttle'][$complaint_id] ?? 0);

try {
    // Only count views for feed-visible complaints.
    $stmt = db()->prepare("SELECT id, status, view_count FROM complaints WHERE id = ? LIMIT 1");
    $stmt->execute([$complaint_id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$c) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Complaint not found']);
        exit();
    }

    if (!in_array($c['status'], ['published', 'resolved'], true)) {
        // Do not count views for non-feed complaints
        echo json_encode(['success' => true, 'incremented' => false, 'view_count' => (int)$c['view_count']]);
        exit();
    }

    if ($last && (($now - $last) < $throttle_seconds)) {
        echo json_encode(['success' => true, 'incremented' => false, 'view_count' => (int)$c['view_count']]);
        exit();
    }

    $_SESSION['view_throttle'][$complaint_id] = $now;

    $stmt = db()->prepare("UPDATE complaints SET view_count = view_count + 1 WHERE id = ?");
    $stmt->execute([$complaint_id]);

    $stmt = db()->prepare("SELECT view_count FROM complaints WHERE id = ? LIMIT 1");
    $stmt->execute([$complaint_id]);
    $new_count = (int)$stmt->fetchColumn();

    echo json_encode(['success' => true, 'incremented' => true, 'view_count' => $new_count]);
} catch (PDOException $e) {
    error_log("increment_view error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

