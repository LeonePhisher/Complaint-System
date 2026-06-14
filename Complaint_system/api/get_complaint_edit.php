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

if (isset($_SESSION['allow_complaint_editing']) && !$_SESSION['allow_complaint_editing']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Editing is disabled']);
    exit();
}

$student_id = (int)($_SESSION['student_id'] ?? 0);
$complaint_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id <= 0 || $complaint_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit();
}

try {
    $stmt = db()->prepare('SELECT id, title, description, category_id, urgency, location, is_anonymous, status FROM complaints WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$complaint_id, $student_id]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$complaint) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Complaint not found']);
        exit();
    }

    if (($complaint['status'] ?? '') !== 'pending') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only pending complaints can be edited']);
        exit();
    }

    $cats = [];
    try {
        $cats = db()->query('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $cats = [];
    }

    $csrf = generateCSRFToken();

    $title = htmlspecialchars((string)($complaint['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $location = htmlspecialchars((string)($complaint['location'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $desc_raw = html_entity_decode((string)($complaint['description'] ?? ''));
    $desc = htmlspecialchars($desc_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $urgency = (string)($complaint['urgency'] ?? 'medium');
    $category_id = (int)($complaint['category_id'] ?? 0);
    $is_anonymous = (int)($complaint['is_anonymous'] ?? 1);

    $catOptions = '';
    foreach ($cats as $c) {
        $id = (int)($c['id'] ?? 0);
        $name = htmlspecialchars((string)($c['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($id <= 0) continue;
        $selected = ($id === $category_id) ? 'selected' : '';
        $catOptions .= "<option value=\"{$id}\" {$selected}>{$name}</option>";
    }

    $urgencies = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];
    $urgOptions = '';
    foreach ($urgencies as $k => $label) {
        $sel = ($k === $urgency) ? 'selected' : '';
        $urgOptions .= "<option value=\"{$k}\" {$sel}>{$label}</option>";
    }

    $checked = $is_anonymous ? 'checked' : '';

    $html = "
        <form id=\"editComplaintForm\" class=\"space-y-4\" onsubmit=\"return submitComplaintEdit(event)\">
            <input type=\"hidden\" name=\"complaint_id\" value=\"{$complaint_id}\">
            <input type=\"hidden\" name=\"csrf_token\" value=\"{$csrf}\">

            <div class=\"bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800\">
                <i class=\"fas fa-info-circle\"></i> You can only edit <strong>pending</strong> complaints.
            </div>

            <div>
                <label class=\"block text-sm font-semibold mb-2\">Title</label>
                <input class=\"w-full p-3 border rounded-lg\" name=\"title\" required minlength=\"5\" value=\"{$title}\">
            </div>

            <div class=\"grid grid-cols-2 gap-4\">
                <div>
                    <label class=\"block text-sm font-semibold mb-2\">Category</label>
                    <select class=\"w-full p-3 border rounded-lg\" name=\"category_id\" required>
                        {$catOptions}
                    </select>
                </div>
                <div>
                    <label class=\"block text-sm font-semibold mb-2\">Urgency</label>
                    <select class=\"w-full p-3 border rounded-lg\" name=\"urgency\" required>
                        {$urgOptions}
                    </select>
                </div>
            </div>

            <div>
                <label class=\"block text-sm font-semibold mb-2\">Location (optional)</label>
                <input class=\"w-full p-3 border rounded-lg\" name=\"location\" value=\"{$location}\" placeholder=\"e.g. Library\">
            </div>

            <div>
                <label class=\"block text-sm font-semibold mb-2\">Description</label>
                <textarea class=\"w-full p-3 border rounded-lg\" name=\"description\" rows=\"6\" required minlength=\"20\">{$desc}</textarea>
            </div>

            <label class=\"flex items-center gap-2\">
                <input type=\"checkbox\" name=\"is_anonymous\" value=\"1\" {$checked}>
                <span class=\"text-sm\">Submit anonymously</span>
            </label>

            <div class=\"flex gap-3 justify-end pt-2\">
                <button type=\"button\" class=\"btn btn-outline\" onclick=\"closeModal('editModal')\">Cancel</button>
                <button type=\"submit\" class=\"btn btn-gradient\"><i class=\"fas fa-save\"></i> Save Changes</button>
            </div>
        </form>
    ";

    echo json_encode(['success' => true, 'html' => $html]);
} catch (PDOException $e) {
    error_log('get_complaint_edit error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
