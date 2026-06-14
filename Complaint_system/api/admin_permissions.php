<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';
require_once '../includes/auth/session.inc.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check if super admin
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Allow JSON body as well
$jsonBody = json_decode(file_get_contents('php://input'), true);
if (!is_array($jsonBody)) $jsonBody = [];

$admin_id = intval($_POST['admin_id'] ?? $_GET['admin_id'] ?? ($jsonBody['admin_id'] ?? 0));
$available = availableAdminPermissions();

try {
    switch ($action) {
        case 'get_permissions':
            // Get current admin permissions
            if (!$admin_id) {
                echo json_encode(['success' => false, 'message' => 'Admin ID required']);
                exit;
            }

            $stmt = db()->prepare("SELECT id, username, role, permissions FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$admin) {
                echo json_encode(['success' => false, 'message' => 'Admin not found']);
                exit;
            }
            
            $permissions = [];
            if (empty($admin['permissions'])) {
                // Backward-compatible: NULL/empty means unrestricted legacy admin
                $permissions = array_keys($available);
            } else {
                $permissions = json_decode($admin['permissions'], true) ?? [];
                if (!is_array($permissions)) $permissions = [];
            }

            echo json_encode([
                'success' => true,
                'admin' => [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'role' => $admin['role'],
                ],
                'permissions' => $permissions,
                'available' => $available
            ]);
            break;
            
        case 'update_permissions':
            if (!$admin_id) {
                echo json_encode(['success' => false, 'message' => 'Admin ID required']);
                exit;
            }

            // Never modify super admin's permissions
            $stmt = db()->prepare("SELECT role FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $role = $stmt->fetchColumn();
            if (!$role) {
                echo json_encode(['success' => false, 'message' => 'Admin not found']);
                exit;
            }
            if ($role === 'super_admin') {
                echo json_encode(['success' => false, 'message' => 'Cannot modify super admin permissions']);
                exit;
            }

            $permissions = $_POST['permissions'] ?? ($jsonBody['permissions'] ?? []);
            if (is_string($permissions)) {
                $permissions = json_decode($permissions, true) ?? [];
            }
            if (!is_array($permissions)) $permissions = [];

            // Validate permission keys
            $permissions = array_values(array_unique(array_filter($permissions, function($p) use ($available) {
                return is_string($p) && array_key_exists($p, $available);
            })));
            
            // Validate that admin exists
            $stmt = db()->prepare("SELECT id FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Admin not found']);
                exit;
            }
            
            // Update permissions
            $permissions_json = json_encode(array_values(array_filter($permissions)));
            $stmt = db()->prepare("UPDATE admins SET permissions = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$permissions_json, $admin_id])) {
                logActivity('PERMISSIONS_UPDATED', "Admin permissions updated for admin ID: $admin_id", $_SESSION['admin_id'], 'admin');
                
                // Clear cached permissions for this admin if they're logged in
                // (They'll need to re-login or their session will be refreshed)
                
                echo json_encode(['success' => true, 'message' => 'Permissions updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update permissions']);
            }
            break;
            
        case 'check_permission':
            $permission = sanitizeInput($_GET['permission'] ?? '');
            
            if (!$permission) {
                echo json_encode(['success' => false, 'message' => 'Permission required']);
                exit;
            }
            
            $stmt = db()->prepare("SELECT permissions FROM admins WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch();
            
            $has_permission = false;
            if ($_SESSION['admin_role'] === 'super_admin') {
                $has_permission = true;
            } elseif ($admin && empty($admin['permissions'])) {
                // Backward-compatible: NULL/empty means unrestricted legacy admin
                $has_permission = true;
            } elseif ($admin && !empty($admin['permissions'])) {
                $permissions = json_decode($admin['permissions'], true) ?? [];
                $has_permission = in_array($permission, $permissions, true);
            }
            
            echo json_encode(['success' => true, 'has_permission' => $has_permission]);
            break;
            
        default:
            // Backward-compat: old UI called this endpoint with only admin_id
            if ($admin_id && $action === '') {
                $_GET['action'] = 'get_permissions';
                $action = 'get_permissions';
                // recurse once
                $stmt = db()->prepare("SELECT id, username, role, permissions FROM admins WHERE id = ?");
                $stmt->execute([$admin_id]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$admin) {
                    echo json_encode(['success' => false, 'message' => 'Admin not found']);
                    exit;
                }
                $permissions = [];
                if (empty($admin['permissions'])) {
                    $permissions = array_keys($available);
                } else {
                    $permissions = json_decode($admin['permissions'], true) ?? [];
                    if (!is_array($permissions)) $permissions = [];
                }
                echo json_encode([
                    'success' => true,
                    'admin' => [
                        'id' => $admin['id'],
                        'username' => $admin['username'],
                        'role' => $admin['role'],
                    ],
                    'permissions' => $permissions,
                    'available' => $available
                ]);
                exit;
            }

            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("Admin permissions error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
