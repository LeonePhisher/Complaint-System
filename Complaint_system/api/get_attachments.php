<?php
require_once '../config/constants.php';
require_once '../includes/auth/session.inc.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$complaint_id = isset($_GET['complaint_id']) ? (int)$_GET['complaint_id'] : 0;
if ($complaint_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit();
}

try {
    $stmt = db()->prepare('SELECT attachments, user_id, status FROM complaints WHERE id = ? LIMIT 1');
    $stmt->execute([$complaint_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Complaint not found']);
        exit();
    }

    $is_admin = isset($_SESSION['admin_id']);

    if (!$is_admin) {
        $viewer_id = (int)($_SESSION['student_id'] ?? 0);
        $owner_id = (int)($row['user_id'] ?? 0);
        $status = (string)($row['status'] ?? '');

        $is_owner = ($owner_id > 0 && $viewer_id === $owner_id);
        $is_public = in_array($status, ['published', 'resolved'], true);

        if (!$is_owner && !$is_public) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You do not have permission to view these attachments']);
            exit();
        }
    }

    $attachments = [];
    if (!empty($row['attachments'])) {
        $decoded = json_decode($row['attachments'], true);
        if (is_array($decoded)) {
            $attachments = $decoded;
        }
    }

    $owner_user_id = (int)($row['user_id'] ?? 0);
    $base_url = rtrim(APP_URL, '/') . '/assets/uploads/complaints/' . $owner_user_id . '/';

    foreach ($attachments as &$att) {
        if (!is_array($att)) {
            $att = [];
        }
        $filename = (string)($att['filename'] ?? '');
        $file_type = (string)($att['file_type'] ?? '');

        if ($filename !== '') {
            $att['url'] = $base_url . rawurlencode($filename);
        }
        $att['is_image'] = ($file_type !== '' && strpos($file_type, 'image/') === 0);
    }
    unset($att);

    echo json_encode([
        'success' => true,
        'attachments' => $attachments,
        'base_url' => $base_url,
        'owner_user_id' => $owner_user_id
    ]);
} catch (PDOException $e) {
    error_log('Error fetching attachments: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
