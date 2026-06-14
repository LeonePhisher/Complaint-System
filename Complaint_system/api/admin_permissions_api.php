<?php
/**
 * Admin Permissions API
 * Manages admin permissions from the junction table
 */
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';
require_once '../includes/auth/session.inc.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Only super admins can manage permissions
if (!isAdmin() || $_SESSION['admin_role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Define all available permissions
$AVAILABLE_PERMISSIONS = [
    'view_complaints'    => 'View complaints and their details',
    'manage_complaints'  => 'Manage complaints (publish, approve, resolve)',
    'view_reports'       => 'View system reports and analytics',
    'manage_users'       => 'Manage user accounts',
    'manage_admins'      => 'Manage other admin accounts',
    'view_settings'      => 'View system settings',
    'manage_settings'    => 'Manage system settings',
    'view_audit_log'     => 'View audit logs'
];

try {
    switch ($action) {
        // ===== GET ADMIN PERMISSIONS =====
        case 'get_admin_permissions':
            $admin_id = $_GET['admin_id'] ?? $_POST['admin_id'] ?? null;
            
            if (!$admin_id) {
                echo json_encode(['success' => false, 'message' => 'No admin ID provided']);
                exit;
            }
            
            // Get admin info
            $stmt = db()->prepare("SELECT id, username, email, role FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin) {
                echo json_encode(['success' => false, 'message' => 'Admin not found']);
                exit;
            }
            
            // Get permissions
            $stmt = db()->prepare("SELECT permission_name FROM admin_permissions WHERE admin_id = ?");
            $stmt->execute([$admin_id]);
            $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $permissions = array_map(function($p) { return $p['permission_name']; }, $perms);
            
            echo json_encode([
                'success' => true,
                'admin' => $admin,
                'permissions' => $permissions,
                'available_permissions' => $AVAILABLE_PERMISSIONS
            ]);
            exit;

        // ===== GET ALL ADMINS WITH PERMISSIONS =====
        case 'get_all_with_permissions':
            $stmt = db()->prepare("
                SELECT 
                    a.id,
                    a.username,
                    a.email,
                    a.full_name,
                    a.role,
                    a.is_active,
                    COUNT(ap.permission_name) as permission_count,
                    GROUP_CONCAT(ap.permission_name ORDER BY ap.permission_name SEPARATOR ',') as permissions_list
                FROM admins a
                LEFT JOIN admin_permissions ap ON a.id = ap.admin_id
                ORDER BY a.role DESC, a.created_at DESC
            ");
            $stmt->execute();
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Parse permissions list
            foreach ($admins as &$admin) {
                $admin['permissions'] = !empty($admin['permissions_list']) 
                    ? explode(',', $admin['permissions_list'])
                    : [];
                unset($admin['permissions_list']);
            }
            
            echo json_encode([
                'success' => true,
                'admins' => $admins,
                'available_permissions' => $AVAILABLE_PERMISSIONS
            ]);
            exit;

        // ===== UPDATE ADMIN PERMISSIONS =====
        case 'update_permissions':
            $admin_id = $_POST['admin_id'] ?? null;
            $permissions = $_POST['permissions'] ?? (json_decode(file_get_contents('php://input'), true)['permissions'] ?? []);
            
            if (!$admin_id) {
                echo json_encode(['success' => false, 'message' => 'No admin ID provided']);
                exit;
            }
            
            // Verify admin exists
            $stmt = db()->prepare("SELECT id, role FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                echo json_encode(['success' => false, 'message' => 'Admin not found']);
                exit;
            }
            
            // Super admin has all permissions by default
            if ($admin['role'] === 'super_admin') {
                echo json_encode(['success' => false, 'message' => 'Cannot modify super admin permissions']);
                exit;
            }
            
            // Start transaction
            db()->beginTransaction();
            
            try {
                // Delete existing permissions
                $stmt = db()->prepare("DELETE FROM admin_permissions WHERE admin_id = ?");
                $stmt->execute([$admin_id]);
                
                // Insert new permissions
                if (!empty($permissions)) {
                    $stmt = db()->prepare("
                        INSERT INTO admin_permissions (admin_id, permission_name)
                        VALUES (?, ?)
                    ");
                    
                    foreach ((array)$permissions as $perm) {
                        if (array_key_exists($perm, $AVAILABLE_PERMISSIONS)) {
                            $stmt->execute([$admin_id, $perm]);
                        }
                    }
                }
                
                db()->commit();
                
                // Clear admin's session permissions if logged in
                if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == $admin_id) {
                    unset($_SESSION['admin_permissions']);
                }
                
                // Log activity
                logActivity('admin_permissions_updated', 
                    "Updated permissions for admin ID: $admin_id", 
                    $_SESSION['admin_id'], 'admin');
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Permissions updated successfully',
                    'permissions' => $permissions
                ]);
            } catch (Exception $e) {
                db()->rollBack();
                throw $e;
            }
            exit;

        // ===== GRANT PERMISSION =====
        case 'grant_permission':
            $admin_id = $_POST['admin_id'] ?? null;
            $permission = $_POST['permission'] ?? null;
            
            if (!$admin_id || !$permission) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                exit;
            }
            
            if (!array_key_exists($permission, $AVAILABLE_PERMISSIONS)) {
                echo json_encode(['success' => false, 'message' => 'Invalid permission']);
                exit;
            }
            
            $stmt = db()->prepare("
                INSERT IGNORE INTO admin_permissions (admin_id, permission_name)
                VALUES (?, ?)
            ");
            
            if ($stmt->execute([$admin_id, $permission])) {
                unset($_SESSION['admin_permissions']);
                logActivity('permission_granted', 
                    "Granted '$permission' to admin ID: $admin_id", 
                    $_SESSION['admin_id'], 'admin');
                
                echo json_encode([
                    'success' => true,
                    'message' => "Permission '$permission' granted"
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to grant permission'
                ]);
            }
            exit;

        // ===== REVOKE PERMISSION =====
        case 'revoke_permission':
            $admin_id = $_POST['admin_id'] ?? null;
            $permission = $_POST['permission'] ?? null;
            
            if (!$admin_id || !$permission) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                exit;
            }
            
            $stmt = db()->prepare("
                DELETE FROM admin_permissions
                WHERE admin_id = ? AND permission_name = ?
            ");
            
            if ($stmt->execute([$admin_id, $permission])) {
                unset($_SESSION['admin_permissions']);
                logActivity('permission_revoked', 
                    "Revoked '$permission' from admin ID: $admin_id", 
                    $_SESSION['admin_id'], 'admin');
                
                echo json_encode([
                    'success' => true,
                    'message' => "Permission '$permission' revoked"
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to revoke permission'
                ]);
            }
            exit;

        // ===== CHECK PERMISSION =====
        case 'check_permission':
            $admin_id = $_GET['admin_id'] ?? $_POST['admin_id'] ?? null;
            $permission = $_GET['permission'] ?? $_POST['permission'] ?? null;
            
            if (!$admin_id || !$permission) {
                echo json_encode(['success' => false, 'message' => 'Missing parameters']);
                exit;
            }
            
            // Check if admin has permission
            $stmt = db()->prepare("
                SELECT 1 FROM admin_permissions
                WHERE admin_id = ? AND permission_name = ?
            ");
            $stmt->execute([$admin_id, $permission]);
            $has_permission = $stmt->fetch() ? true : false;
            
            echo json_encode([
                'success' => true,
                'has_permission' => $has_permission
            ]);
            exit;

        // ===== GET AVAILABLE PERMISSIONS =====
        case 'available_permissions':
            echo json_encode([
                'success' => true,
                'permissions' => $AVAILABLE_PERMISSIONS
            ]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            exit;
    }
} catch (PDOException $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log("Permissions API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
} catch (Exception $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log("Permissions API exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
?>
