<?php
require_once '../config/constants.php';
require_once '../includes/auth/session.inc.php';
require_once '../config/database.php';

// Require auth (student or admin)
if (!isset($_SESSION['student_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

$complaint_id = isset($_GET['complaint_id']) ? (int)$_GET['complaint_id'] : 0;
$idx = isset($_GET['idx']) ? (int)$_GET['idx'] : -1;

if ($complaint_id <= 0 || $idx < 0) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

try {
    $stmt = db()->prepare('SELECT attachments, user_id FROM complaints WHERE id = ? LIMIT 1');
    $stmt->execute([$complaint_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo 'Not found';
        exit;
    }

    $is_admin = isset($_SESSION['admin_id']);

    // Student downloads: owner only (My Complaints requirement)
    if (!$is_admin) {
        $viewer_id = (int)($_SESSION['student_id'] ?? 0);
        $owner_id = (int)($row['user_id'] ?? 0);
        if ($viewer_id <= 0 || $owner_id <= 0 || $viewer_id !== $owner_id) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }

    $attachments = [];
    if (!empty($row['attachments'])) {
        $decoded = json_decode($row['attachments'], true);
        if (is_array($decoded)) {
            $attachments = $decoded;
        }
    }

    if (!isset($attachments[$idx]) || !is_array($attachments[$idx])) {
        http_response_code(404);
        echo 'Attachment not found';
        exit;
    }

    $att = $attachments[$idx];
    $filename = (string)($att['filename'] ?? '');

    if ($filename === '' || basename($filename) !== $filename || strpbrk($filename, "\\/")) {
        http_response_code(400);
        echo 'Invalid filename';
        exit;
    }

    $owner_user_id = (int)($row['user_id'] ?? 0);
    $fullPath = ROOT_PATH . '/assets/uploads/complaints/' . $owner_user_id . '/' . $filename;

    if (!is_file($fullPath)) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }

    $mime = (string)($att['file_type'] ?? '');
    if ($mime === '') {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = @finfo_file($finfo, $fullPath);
            @finfo_close($finfo);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
    }
    if ($mime === '') {
        $mime = 'application/octet-stream';
    }

    $downloadName = (string)($att['original_name'] ?? $filename);
    $downloadName = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $downloadName);
    if ($downloadName === '') {
        $downloadName = $filename;
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($fullPath));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');

    @readfile($fullPath);
    exit;

} catch (PDOException $e) {
    error_log('download_attachment error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Server error';
    exit;
}
?>
