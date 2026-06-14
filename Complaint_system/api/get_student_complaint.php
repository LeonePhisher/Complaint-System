<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/auth/session.inc.php';
require_once '../includes/utilities/helpers.php';

header('Content-Type: application/json');

// Student-only endpoint: returns complaint details only for the logged-in student
if (!isStudent()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = (int)($_SESSION['student_id'] ?? 0);
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($complaint_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit();
}

try {
    // Be resilient to schema variants (some installs may not have these columns).
    $cols = [];
    try {
        $cols = db()->query("SHOW COLUMNS FROM complaints")->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        $cols = [];
    }

    $has_last_status_change = in_array('last_status_change', $cols, true);
    $has_resolution_notes = in_array('resolution_notes', $cols, true);

    $last_status_change_sel = $has_last_status_change ? "c.last_status_change" : "NULL AS last_status_change";
    $resolution_notes_sel = $has_resolution_notes ? "c.resolution_notes" : "NULL AS resolution_notes";

    $stmt = db()->prepare("
        SELECT
            c.id,
            c.complaint_code,
            c.title,
            c.description,
            c.location,
            c.urgency,
            c.status,
            c.is_anonymous,
            c.view_count,
            c.upvotes,
            c.downvotes,
            c.created_at,
            c.published_at,
            c.resolved_at,
            c.assigned_to,
            $last_status_change_sel,
            $resolution_notes_sel,
            c.rejection_reason,
            c.rejected_by,
            cat.name AS category_name,
            cat.color AS category_color
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.id = ? AND c.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$complaint_id, $student_id]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$complaint) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Complaint not found']);
        exit();
    }

    // Decode HTML entities in description (decode multiple times in case of multiple encoding)
    $complaint['description'] = html_entity_decode(html_entity_decode(html_entity_decode($complaint['description'])));

    echo json_encode(['success' => true, 'complaint' => $complaint]);
} catch (PDOException $e) {
    error_log("get_student_complaint error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
