<?php
require_once '../config/constants.php';
require_once '../includes/auth/session.inc.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';

header('Content-Type: application/json');

// Check if user is super admin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$admin_id = intval($_GET['id'] ?? 0);

if (!$admin_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid admin ID']);
    exit();
}

try {
    // Get admin details
    $stmt = db()->prepare("
        SELECT * FROM admins WHERE id = ?
    ");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'Admin not found']);
        exit();
    }
    
    // Get categories assigned to this admin
    $stmt = db()->prepare("
        SELECT id, name, admin_id FROM categories WHERE admin_id = ? ORDER BY name
    ");
    $stmt->execute([$admin_id]);
    $assigned_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all available categories
    $stmt = db()->prepare("
        SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name
    ");
    $stmt->execute();
    $all_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mark which categories are assigned
    $assigned_ids = array_column($assigned_categories, 'id');
    $categories = array_map(function($cat) use ($assigned_ids) {
        $cat['selected'] = in_array($cat['id'], $assigned_ids);
        return $cat;
    }, $all_categories);
    
    // Parse permissions
    $available = availableAdminPermissions();
    $permissions_list = [];
    foreach ($available as $key => $meta) {
        $permissions_list[] = [
            'value' => $key,
            'label' => $meta['label'],
            'checked' => false
        ];
    }
    
    if (empty($admin['permissions'])) {
        // Backward-compatible: NULL/empty means unrestricted legacy admin
        foreach ($permissions_list as &$perm) {
            $perm['checked'] = true;
        }
    } else {
        $perms = json_decode($admin['permissions'], true);
        if (is_array($perms)) {
            foreach ($permissions_list as &$perm) {
                $perm['checked'] = in_array($perm['value'], $perms, true);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'admin' => $admin,
        'categories' => $categories,
        'permissions' => $permissions_list
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching admin details: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
