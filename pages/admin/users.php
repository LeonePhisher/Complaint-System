<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../includes/utilities/helpers.php';

// Check if user is super admin
if (!isAdmin() || $_SESSION['admin_role'] !== 'super_admin') {
    header('Location: ' . APP_URL . '/pages/admin/dashboard.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_admin':
            handleAddAdmin();
            break;
        case 'update_admin':
            handleUpdateAdmin();
            break;
        case 'delete_admin':
            handleDeleteAdmin();
            break;
        case 'update_user_status':
            handleUpdateUserStatus();
            break;
    }
}

// Get all admins
$admins = [];
try {
    $stmt = db()->prepare("
        SELECT 
            a.*,
            COUNT(cat.id) as managed_categories,
            GROUP_CONCAT(cat.name SEPARATOR ', ') as category_names
        FROM admins a
        LEFT JOIN categories cat ON a.id = cat.admin_id
        LEFT JOIN complaints c ON cat.id = c.category_id
        GROUP BY a.id
        ORDER BY a.role DESC, a.created_at DESC
    ");
    $stmt->execute();
    $admins = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error loading admins: " . $e->getMessage());
}

// Get all users with stats
$users = [];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build user query
$where = [];
$params = [];

if ($search) {
    $where[] = "(u.index_number LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR u.department LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status && in_array($status, ['active', 'inactive', 'suspended'])) {
    $where[] = "u.account_status = ?";
    $params[] = $status;
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

try {
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM users u $where_clause";
    $stmt = db()->prepare($count_query);
    $stmt->execute($params);
    $total_users = $stmt->fetch()['total'];
    
    // Get users
    $query = "
        SELECT 
            u.*,
            COUNT(c.id) as total_complaints,
            SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved_complaints,
            SUM(CASE WHEN c.status = 'published' THEN 1 ELSE 0 END) as published_complaints,
            SUM(CASE WHEN c.status = 'rejected' THEN 1 ELSE 0 END) as rejected_complaints
        FROM users u
        LEFT JOIN complaints c ON u.id = c.user_id
        $where_clause
        GROUP BY u.id
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error loading users: " . $e->getMessage());
}

// Get all categories for assignment
$categories = [];
try {
    $stmt = db()->prepare("SELECT id, name, admin_id FROM categories WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error loading categories: " . $e->getMessage());
}

function handleAddAdmin() {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? 'category_admin');
    $permissions = isset($_POST['permissions']) ? json_encode($_POST['permissions']) : '[]';
    $category_ids = isset($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : [];
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $_SESSION['error'] = 'All fields are required';
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Invalid email address';
        return;
    }
    
    if ($password !== $confirm_password) {
        $_SESSION['error'] = 'Passwords do not match';
        return;
    }
    
    if (strlen($password) < 6) {
        $_SESSION['error'] = 'Password must be at least 6 characters';
        return;
    }
    
    try {
        // Check if username or email already exists
        $stmt = db()->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Username or email already exists';
            return;
        }
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Start transaction
        db()->beginTransaction();
        
        // Insert admin
        $stmt = db()->prepare("
            INSERT INTO admins (username, email, password_hash, full_name, role, permissions, avatar_color)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $avatar_color = generateRandomColor();
        $stmt->execute([$username, $email, $password_hash, $full_name, $role, $permissions, $avatar_color]);
        
        $admin_id = db()->lastInsertId();
        
        // Assign categories if provided
        if (!empty($category_ids) && $role === 'category_admin') {
            $stmt = db()->prepare("UPDATE categories SET admin_id = ? WHERE id = ?");
            foreach ($category_ids as $category_id) {
                $stmt->execute([$admin_id, $category_id]);
            }
        }
        
        // Commit transaction
        db()->commit();
        
        $_SESSION['success'] = 'Admin user created successfully';
        
        // Log activity
        logActivity('ADMIN_CREATED', "Created admin: $username", $_SESSION['admin_id'], 'admin');
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
        
    } catch (PDOException $e) {
        db()->rollBack();
        error_log("Error creating admin: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to create admin user';
    }
}

function handleUpdateAdmin() {
    $admin_id = intval($_POST['admin_id'] ?? 0);
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $role = sanitizeInput($_POST['role'] ?? 'category_admin');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $permissions = isset($_POST['permissions']) ? json_encode($_POST['permissions']) : '[]';
    $category_ids = isset($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : [];
    
    if (!$admin_id) {
        $_SESSION['error'] = 'Invalid admin ID';
        return;
    }
    
    try {
        // Start transaction
        db()->beginTransaction();
        
        // Update admin
        $stmt = db()->prepare("
            UPDATE admins 
            SET full_name = ?, role = ?, is_active = ?, permissions = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$full_name, $role, $is_active, $permissions, $admin_id]);
        
        // Clear existing category assignments for this admin
        $stmt = db()->prepare("UPDATE categories SET admin_id = NULL WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        
        // Assign new categories if provided and admin is category_admin
        if (!empty($category_ids) && $role === 'category_admin') {
            $stmt = db()->prepare("UPDATE categories SET admin_id = ? WHERE id = ?");
            foreach ($category_ids as $category_id) {
                $stmt->execute([$admin_id, $category_id]);
            }
        }
        
        // Commit transaction
        db()->commit();
        
        $_SESSION['success'] = 'Admin user updated successfully';
        
        // Log activity
        logActivity('ADMIN_UPDATED', "Updated admin ID: $admin_id", $_SESSION['admin_id'], 'admin');
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
        
    } catch (PDOException $e) {
        db()->rollBack();
        error_log("Error updating admin: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to update admin user';
    }
}

function handleDeleteAdmin() {
    $admin_id = intval($_POST['admin_id'] ?? 0);
    
    if (!$admin_id) {
        $_SESSION['error'] = 'Invalid admin ID';
        return;
    }
    
    // Prevent deleting self
    if ($admin_id == $_SESSION['admin_id']) {
        $_SESSION['error'] = 'You cannot delete your own account';
        return;
    }
    
    try {
        // Start transaction
        db()->beginTransaction();
        
        // Remove category assignments
        $stmt = db()->prepare("UPDATE categories SET admin_id = NULL WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        
        // Delete admin
        $stmt = db()->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        
        // Commit transaction
        db()->commit();
        
        $_SESSION['success'] = 'Admin user deleted successfully';
        
        // Log activity
        logActivity('ADMIN_DELETED', "Deleted admin ID: $admin_id", $_SESSION['admin_id'], 'admin');
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
        
    } catch (PDOException $e) {
        db()->rollBack();
        error_log("Error deleting admin: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to delete admin user';
    }
}

function handleUpdateUserStatus() {
    $user_id = intval($_POST['user_id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? '');
    
    if (!$user_id || !in_array($status, ['active', 'inactive', 'suspended'])) {
        $_SESSION['error'] = 'Invalid request';
        return;
    }
    
    try {
        $stmt = db()->prepare("UPDATE users SET account_status = ? WHERE id = ?");
        $stmt->execute([$status, $user_id]);
        
        $_SESSION['success'] = 'User status updated successfully';
        
        // Log activity
        logActivity('USER_STATUS_UPDATED', "User ID: $user_id -> $status", $_SESSION['admin_id'], 'admin');
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit();
        
    } catch (PDOException $e) {
        error_log("Error updating user status: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to update user status';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        .users-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--text-secondary);
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 1rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            border: none;
            background: none;
            color: var(--text-secondary);
            font-weight: 500;
            cursor: pointer;
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            transition: all 0.2s ease;
            position: relative;
        }

        .tab:hover {
            color: var(--primary-color);
            background: rgba(102, 126, 234, 0.05);
        }

        .tab.active {
            color: var(--primary-color);
            background: rgba(102, 126, 234, 0.1);
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-color);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .table-container {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--glass-border);
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: var(--bg-secondary);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            margin-right: 0.75rem;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .user-email {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-active {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
            border: 1px solid rgba(72, 187, 120, 0.2);
        }

        .status-inactive {
            background: rgba(160, 174, 192, 0.1);
            color: #a0aec0;
            border: 1px solid rgba(160, 174, 192, 0.2);
        }

        .status-suspended {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
            border: 1px solid rgba(245, 101, 101, 0.2);
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .role-super_admin {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .role-category_admin {
            background: rgba(159, 122, 234, 0.1);
            color: #9f7aea;
            border: 1px solid rgba(159, 122, 234, 0.2);
        }

        .role-support_admin {
            background: rgba(246, 173, 85, 0.1);
            color: #f6ad55;
            border: 1px solid rgba(246, 173, 85, 0.2);
        }

        .stats-cell {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .stat-item i {
            width: 16px;
            text-align: center;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .btn-edit {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .btn-edit:hover {
            background: rgba(102, 126, 234, 0.2);
        }

        .btn-delete {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
        }

        .btn-delete:hover {
            background: rgba(245, 101, 101, 0.2);
        }

        .btn-status {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
        }

        .btn-status:hover {
            background: rgba(72, 187, 120, 0.2);
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }

        .search-box {
            position: relative;
            max-width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .filter-select {
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
        }

        .modal-header {
            border-bottom: 1px solid var(--glass-border);
        }

        .modal-footer {
            border-top: 1px solid var(--glass-border);
        }

        @media (max-width: 1024px) {
            .users-container {
                padding: 1rem;
            }
            
            .tabs {
                flex-wrap: wrap;
            }
            
            .card-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .search-box {
                max-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .user-info {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-avatar {
                margin-right: 0;
                margin-bottom: 0.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Navigation -->
    <?php include '../../includes/layout/admin-nav.php'; ?>

    <!-- Main Content -->
    <div class="users-container">
        <!-- Header -->
        <div class="page-header">
            <h1>User Management</h1>
            <p>Manage system users and administrators</p>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('admins')">
                <i class="fas fa-users-cog me-2"></i> Administrators
            </button>
            <button class="tab" onclick="switchTab('users')">
                <i class="fas fa-users me-2"></i> Students
            </button>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success animate-slide-in-down">
                <i class="fas fa-check-circle"></i>
                <?php 
                echo htmlspecialchars($_SESSION['success']);
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error animate-slide-in-down">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                echo htmlspecialchars($_SESSION['error']);
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Admins Tab -->
        <div class="tab-content active" id="adminsTab">
            <div class="table-container">
                <div class="card-header">
                    <h3 class="card-title">System Administrators</h3>
                    <button class="btn btn-gradient" onclick="showAddAdminModal()">
                        <i class="fas fa-user-plus me-2"></i> Add Admin
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Admin</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Managed Categories</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($admins)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-users-slash"></i>
                                            <p>No administrators found</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($admins as $admin): ?>
                                    <?php 
                                    $initials = getInitials($admin['full_name']);
                                    $last_login = $admin['last_login'] ? timeAgo($admin['last_login']) : 'Never';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar" style="background: <?php echo $admin['avatar_color']; ?>">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div>
                                                    <div class="user-name"><?php echo htmlspecialchars($admin['full_name']); ?></div>
                                                    <div class="user-email"><?php echo htmlspecialchars($admin['email']); ?></div>
                                                    <small class="text-muted">@<?php echo htmlspecialchars($admin['username']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge role-<?php echo strtolower(str_replace(' ', '_', $admin['role'])); ?>">
                                                <?php echo str_replace('_', ' ', $admin['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $admin['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $admin['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="stats-cell">
                                                <div class="stat-item">
                                                    <i class="fas fa-tags"></i>
                                                    <span><?php echo $admin['managed_categories'] ?? 0; ?> categories</span>
                                                </div>
                                                <?php if (!empty($admin['category_names'])): ?>
                                                    <small class="text-muted" title="<?php echo htmlspecialchars($admin['category_names']); ?>">
                                                        <?php echo truncateText($admin['category_names'], 30); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo $last_login; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn btn-edit" onclick="editAdmin(<?php echo $admin['id']; ?>)"
                                                        title="Edit Admin">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                                    <button class="action-btn btn-delete" 
                                                            onclick="deleteAdmin(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['full_name']); ?>')"
                                                            title="Delete Admin">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Users Tab -->
        <div class="tab-content" id="usersTab">
            <div class="table-container">
                <div class="card-header">
                    <h3 class="card-title">Student Users</h3>
                    
                    <div class="d-flex gap-2">
                        <div class="search-box">
                            <i class="fas fa-search search-icon"></i>
                            <input type="search" class="search-input" placeholder="Search users..." 
                                   id="userSearch" onkeyup="searchUsers()">
                        </div>
                        
                        <select class="filter-select" id="statusFilter" onchange="filterUsers()">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table" id="usersTable">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Index Number</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Complaints</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-user-slash"></i>
                                            <p>No students found</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <?php 
                                    $initials = getInitials($user['full_name']);
                                    $registered = timeAgo($user['created_at']);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar" style="background: <?php echo $user['avatar_color']; ?>">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div>
                                                    <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($user['index_number']); ?></code>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($user['department']); ?>
                                            <div class="text-muted small">Level <?php echo htmlspecialchars($user['level']); ?></div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $user['account_status']; ?>">
                                                <?php echo ucfirst($user['account_status']); ?>
                                            </span>
                                            <?php if (!$user['is_verified']): ?>
                                                <small class="text-warning d-block mt-1">
                                                    <i class="fas fa-exclamation-circle"></i> Not verified
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="stats-cell">
                                                <div class="stat-item">
                                                    <i class="fas fa-inbox"></i>
                                                    <span>Total: <?php echo $user['total_complaints'] ?? 0; ?></span>
                                                </div>
                                                <div class="stat-item">
                                                    <i class="fas fa-check-circle text-success"></i>
                                                    <span>Resolved: <?php echo $user['resolved_complaints'] ?? 0; ?></span>
                                                </div>
                                                <div class="stat-item">
                                                    <i class="fas fa-times-circle text-danger"></i>
                                                    <span>Rejected: <?php echo $user['rejected_complaints'] ?? 0; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                            <div class="text-muted small"><?php echo $registered; ?></div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn btn-status" 
                                                        onclick="changeUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['account_status']; ?>')"
                                                        title="Change Status">
                                                    <i class="fas fa-user-cog"></i>
                                                </button>
                                                <button class="action-btn btn-edit" 
                                                        onclick="viewUserDetails(<?php echo $user['id']; ?>)"
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_users > $limit): ?>
                    <div class="pagination-container">
                        <?php echo generatePagination($page, ceil($total_users / $limit), 'users.php?tab=users&page=%d&search=' . urlencode($search) . '&status=' . $status); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Administrator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_admin">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password *</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm Password *</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-control" required onchange="toggleCategorySelection()">
                                <option value="category_admin">Category Admin</option>
                                <option value="support_admin">Support Admin</option>
                                <option value="super_admin">Super Admin</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="categorySelection">
                            <label class="form-label">Assign Categories</label>
                            <select name="category_ids[]" class="form-control" multiple size="5">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple categories</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Permissions</label>
                            <div class="permissions-grid">
                                <?php
                                $availablePerms = availableAdminPermissions();
                                $defaultChecked = ['view_complaints', 'manage_complaints', 'view_reports'];
                                foreach ($availablePerms as $permKey => $meta):
                                ?>
                                    <div class="form-check">
                                        <input type="checkbox"
                                               name="permissions[]"
                                               value="<?php echo htmlspecialchars($permKey); ?>"
                                               class="form-check-input"
                                               <?php echo in_array($permKey, $defaultChecked, true) ? 'checked' : ''; ?>>
                                        <label class="form-check-label"><?php echo htmlspecialchars($meta['label']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-gradient">Create Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div class="modal fade" id="editAdminModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content glass-modal">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content glass-modal">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Change Status Modal -->
    <div class="modal fade" id="changeStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Change User Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="statusForm">
                    <input type="hidden" name="action" value="update_user_status">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="user_id" id="statusUserId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Status</label>
                            <select name="status" class="form-control" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <small>Active: Can login and submit complaints<br>
                            Inactive: Cannot login<br>
                            Suspended: Account suspended by admin</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-gradient">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/theme-toggle.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    
    <script>
        // Tab switching
        function switchTab(tabName) {
            // Update tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelector(`.tab[onclick="switchTab('${tabName}')"]`).classList.add('active');
            
            // Update content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(tabName + 'Tab').classList.add('active');
            
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabName);
            window.history.pushState({}, '', url);
        }
        
        // Check URL for tab parameter
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab');
        if (activeTab && (activeTab === 'users' || activeTab === 'admins')) {
            switchTab(activeTab);
        }
        
        // Show add admin modal
        function showAddAdminModal() {
            const modal = new bootstrap.Modal(document.getElementById('addAdminModal'));
            modal.show();
        }
        
        // Toggle category selection based on role
        function toggleCategorySelection() {
            const role = document.querySelector('#addAdminModal select[name="role"]').value;
            const categorySelection = document.getElementById('categorySelection');
            
            if (role === 'category_admin') {
                categorySelection.style.display = 'block';
            } else {
                categorySelection.style.display = 'none';
            }
        }
        
        // Initialize category selection
        document.addEventListener('DOMContentLoaded', toggleCategorySelection);
        
        // Edit admin
        async function editAdmin(adminId) {
            try {
                const response = await fetch(`<?php echo APP_URL; ?>/api/get_admin.php?id=${adminId}`);
                const data = await response.json();
                
                if (data.success) {
                    const modal = document.getElementById('editAdminModal');
                    modal.querySelector('.modal-content').innerHTML = `
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Administrator</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_admin">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="admin_id" value="${data.admin.id}">
                            
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" class="form-control" value="${data.admin.username}" disabled>
                                        <small class="text-muted">Username cannot be changed</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" value="${data.admin.email}" disabled>
                                        <small class="text-muted">Email cannot be changed</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="full_name" class="form-control" value="${data.admin.full_name}" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Role *</label>
                                    <select name="role" class="form-control" required onchange="toggleEditCategorySelection()">
                                        <option value="category_admin" ${data.admin.role === 'category_admin' ? 'selected' : ''}>Category Admin</option>
                                        <option value="support_admin" ${data.admin.role === 'support_admin' ? 'selected' : ''}>Support Admin</option>
                                        <option value="super_admin" ${data.admin.role === 'super_admin' ? 'selected' : ''}>Super Admin</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3" id="editCategorySelection" style="${data.admin.role !== 'category_admin' ? 'display: none;' : ''}">
                                    <label class="form-label">Assign Categories</label>
                                    <select name="category_ids[]" class="form-control" multiple size="5">
                                        ${data.categories.map(cat => `
                                            <option value="${cat.id}" ${cat.selected ? 'selected' : ''}>${cat.name}</option>
                                        `).join('')}
                                    </select>
                                    <small class="text-muted">Hold Ctrl/Cmd to select multiple categories</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check">
                                        <input type="checkbox" name="is_active" class="form-check-input" id="isActive" ${data.admin.is_active ? 'checked' : ''}>
                                        <label class="form-check-label" for="isActive">Active Account</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Permissions</label>
                                    <div class="permissions-grid">
                                        ${data.permissions.map(perm => `
                                            <div class="form-check">
                                                <input type="checkbox" name="permissions[]" value="${perm.value}" class="form-check-input" ${perm.checked ? 'checked' : ''}>
                                                <label class="form-check-label">${perm.label}</label>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-gradient">Update Admin</button>
                            </div>
                        </form>
                    `;
                    
                    const editModal = new bootstrap.Modal(modal);
                    editModal.show();
                } else {
                    showToast('Failed to load admin details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error', 'error');
            }
        }
        
        // Delete admin confirmation
        function deleteAdmin(adminId, adminName) {
            if (confirm(`Are you sure you want to delete administrator "${adminName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_admin';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'admin_id';
                idInput.value = adminId;
                
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = 'csrf_token';
                tokenInput.value = '<?php echo generateCSRFToken(); ?>';
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                form.appendChild(tokenInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Search users
        function searchUsers() {
            const search = document.getElementById('userSearch').value;
            const status = document.getElementById('statusFilter').value;
            
            const url = new URL(window.location);
            url.searchParams.set('search', search);
            url.searchParams.set('status', status);
            url.searchParams.set('tab', 'users');
            url.searchParams.set('page', '1');
            
            window.location.href = url.toString();
        }
        
        // Filter users
        function filterUsers() {
            searchUsers();
        }
        
        // Change user status
        function changeUserStatus(userId, currentStatus) {
            document.getElementById('statusUserId').value = userId;
            
            const statusSelect = document.querySelector('#statusForm select[name="status"]');
            statusSelect.value = currentStatus;
            
            const modal = new bootstrap.Modal(document.getElementById('changeStatusModal'));
            modal.show();
        }
        
        // View user details
        async function viewUserDetails(userId) {
            try {
                const response = await fetch(`<?php echo APP_URL; ?>/api/get_user.php?id=${userId}`);
                const data = await response.json();
                
                if (data.success) {
                    const modal = document.getElementById('userDetailsModal');
                    modal.querySelector('.modal-content').innerHTML = `
                        <div class="modal-header">
                            <h5 class="modal-title">User Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-4">
                                <div class="col-md-2">
                                    <div class="user-avatar" style="background: ${data.user.avatar_color}; width: 60px; height: 60px;">
                                        ${data.user.initials}
                                    </div>
                                </div>
                                <div class="col-md-10">
                                    <h4>${data.user.full_name}</h4>
                                    <p class="text-muted">${data.user.email}</p>
                                    <span class="status-badge status-${data.user.account_status}">
                                        ${data.user.account_status.charAt(0).toUpperCase() + data.user.account_status.slice(1)}
                                    </span>
                                    ${!data.user.is_verified ? '<span class="badge bg-warning ms-2">Not Verified</span>' : ''}
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Index Number</h6>
                                    <p><code>${data.user.index_number}</code></p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Phone Number</h6>
                                    <p>${data.user.phone || 'Not provided'}</p>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6>Department</h6>
                                    <p>${data.user.department}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Level</h6>
                                    <p>Level ${data.user.level}</p>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <h6>Registered</h6>
                                    <p>${data.user.registered}</p>
                                </div>
                                <div class="col-md-4">
                                    <h6>Last Login</h6>
                                    <p>${data.user.last_login || 'Never'}</p>
                                </div>
                                <div class="col-md-4">
                                    <h6>Login Attempts</h6>
                                    <p>${data.user.login_attempts}</p>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h6>Complaint Statistics</h6>
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <div class="stat-box">
                                            <div class="stat-number">${data.stats.total}</div>
                                            <div class="stat-label">Total</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="stat-box">
                                            <div class="stat-number text-success">${data.stats.published}</div>
                                            <div class="stat-label">Published</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="stat-box">
                                            <div class="stat-number text-primary">${data.stats.resolved}</div>
                                            <div class="stat-label">Resolved</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="stat-box">
                                            <div class="stat-number text-danger">${data.stats.rejected}</div>
                                            <div class="stat-label">Rejected</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6>Recent Complaints</h6>
                                ${data.recent_complaints.length > 0 ? 
                                    data.recent_complaints.map(complaint => `
                                        <div class="card mb-2">
                                            <div class="card-body p-2">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong>${complaint.title}</strong>
                                                        <br>
                                                        <small class="text-muted">${complaint.complaint_code} • ${complaint.time_ago}</small>
                                                    </div>
                                                    <span class="badge bg-${getStatusColor(complaint.status)}">
                                                        ${complaint.status.replace('_', ' ')}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    `).join('') :
                                    '<p class="text-muted">No recent complaints</p>'
                                }
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-gradient" onclick="changeUserStatus(${data.user.id}, '${data.user.account_status}')">
                                <i class="fas fa-user-cog"></i> Change Status
                            </button>
                        </div>
                    `;
                    
                    const detailsModal = new bootstrap.Modal(modal);
                    detailsModal.show();
                } else {
                    showToast('Failed to load user details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error', 'error');
            }
        }
        
        function getStatusColor(status) {
            const colors = {
                'pending': 'warning',
                'under_review': 'info',
                'published': 'success',
                'resolved': 'primary',
                'rejected': 'danger'
            };
            return colors[status] || 'secondary';
        }
        
        // Toggle category selection in edit modal
        function toggleEditCategorySelection() {
            const role = document.querySelector('#editAdminModal select[name="role"]').value;
            const categorySelection = document.getElementById('editCategorySelection');
            
            if (role === 'category_admin') {
                categorySelection.style.display = 'block';
            } else {
                categorySelection.style.display = 'none';
            }
        }
        
        // Initialize DataTable for users
        $(document).ready(function() {
            $('#usersTable').DataTable({
                responsive: true,
                paging: false,
                searching: false,
                info: false,
                order: [[0, 'asc']]
            });
        });
    </script>
</body>
</html>
