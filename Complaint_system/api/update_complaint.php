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
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$input = $_POST;
if (!is_array($input) || empty($input)) {
    $json = json_decode(file_get_contents('php://input'), true);
    if (is_array($json)) {
        $input = $json;
    }
}

$csrf = (string)($input['csrf_token'] ?? '');
if ($csrf === '' || !validateCSRFToken($csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$student_id = (int)($_SESSION['student_id'] ?? 0);
$complaint_id = (int)($input['complaint_id'] ?? 0);

if ($student_id <= 0 || $complaint_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit();
}

$title = sanitizeInput($input['title'] ?? '');
$category_id = (int)($input['category_id'] ?? 0);
$urgency = sanitizeInput($input['urgency'] ?? 'medium');
$location = sanitizeInput($input['location'] ?? '');
$is_anonymous = isset($input['is_anonymous']) ? 1 : 0;

$raw_description = (string)($input['description'] ?? '');
$allowed_tags = '<p><br><strong><em><ul><ol><li><a><b><i><u>';
$description = strip_tags($raw_description, $allowed_tags);
$description = preg_replace('/href\s*=\s*["\']\s*javascript:[^"\']*["\']/i', 'href="#"', $description);

$errors = [];
if (strlen($title) < 5) $errors[] = 'Title must be at least 5 characters';
if (strlen(strip_tags($description)) < 20) $errors[] = 'Description must be at least 20 characters';
if ($category_id <= 0) $errors[] = 'Please select a valid category';
if (!in_array($urgency, ['low', 'medium', 'high', 'critical'], true)) $errors[] = 'Invalid urgency level';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $errors[0], 'errors' => $errors]);
    exit();
}

try {
    db()->beginTransaction();

    $stmt = db()->prepare('SELECT title, description, category_id, urgency, location, is_anonymous, status FROM complaints WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$complaint_id, $student_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        db()->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Complaint not found']);
        exit();
    }

    if (($current['status'] ?? '') !== 'pending') {
        db()->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only pending complaints can be edited']);
        exit();
    }

    $stmt = db()->prepare('UPDATE complaints SET title = ?, description = ?, category_id = ?, urgency = ?, location = ?, is_anonymous = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$title, $description, $category_id, $urgency, $location, $is_anonymous, $complaint_id, $student_id]);

    $old_values = json_encode([
        'title' => $current['title'] ?? null,
        'description' => $current['description'] ?? null,
        'category_id' => $current['category_id'] ?? null,
        'urgency' => $current['urgency'] ?? null,
        'location' => $current['location'] ?? null,
        'is_anonymous' => $current['is_anonymous'] ?? null,
    ]);

    $new_values = json_encode([
        'title' => $title,
        'description' => $description,
        'category_id' => $category_id,
        'urgency' => $urgency,
        'location' => $location,
        'is_anonymous' => $is_anonymous,
    ]);

    try {
        $stmt = db()->prepare('INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $student_id,
            'COMPLAINT_UPDATED',
            'complaints',
            $complaint_id,
            $old_values,
            $new_values,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (PDOException $e) {
        // ignore audit logging failures
        error_log('audit_log insert failed: ' . $e->getMessage());
    }

    db()->commit();

    echo json_encode(['success' => true, 'message' => 'Complaint updated successfully']);
} catch (PDOException $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log('update_complaint error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
