<?php
require_once '../../config/constants.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../includes/utilities/helpers.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

requirePermission('manage_categories');

$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_category':
            handleAddCategory();
            break;
        case 'update_category':
            handleUpdateCategory();
            break;
        case 'delete_category':
            handleDeleteCategory();
            break;
        case 'assign_admin':
            handleAssignAdmin();
            break;
    }
}

// Get all categories
$categories = [];
try {
    if ($admin_role === 'super_admin') {
        $query = "
            SELECT 
                c.*,
                a.full_name as admin_name,
                a.username as admin_username,
                COUNT(comp.id) as complaint_count
            FROM categories c
            LEFT JOIN admins a ON c.admin_id = a.id
            LEFT JOIN complaints comp ON c.id = comp.category_id
            GROUP BY c.id
            ORDER BY c.name ASC
        ";
        $stmt = db()->prepare($query);
        $stmt->execute();
    } else {
        $query = "
            SELECT 
                c.*,
                a.full_name as admin_name,
                a.username as admin_username,
                COUNT(comp.id) as complaint_count
            FROM categories c
            LEFT JOIN admins a ON c.admin_id = a.id
            LEFT JOIN complaints comp ON c.id = comp.category_id
            WHERE c.admin_id = ?
            GROUP BY c.id
            ORDER BY c.name ASC
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$admin_id]);
    }
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error loading categories: " . $e->getMessage());
}

// Get all admins for assignment (super admin only)
$admins = [];
if ($admin_role === 'super_admin') {
    try {
        $stmt = db()->prepare("SELECT id, username, full_name FROM admins WHERE role = 'category_admin' AND is_active = 1 ORDER BY full_name");
        $stmt->execute();
        $admins = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error loading admins: " . $e->getMessage());
    }
}

// Icons list for categories
$icons = [
    'home', 'university', 'book', 'book-open', 'utensils', 'shield',
    'bus', 'heart', 'monitor', 'more-horizontal', 'building', 'wifi',
    'bath', 'bed', 'graduation-cap', 'flask', 'microscope', 'calculator',
    'paint-brush', 'music', 'dumbbell', 'tree', 'sun', 'cloud-rain',
    'shopping-cart', 'credit-card', 'lock', 'key', 'bell', 'clock',
    'map-marker', 'phone', 'envelope', 'globe', 'users', 'user-friends',
    'chart-bar', 'chart-line', 'database', 'server', 'code', 'laptop',
    'mobile', 'tablet', 'camera', 'video', 'image', 'file',
    'folder', 'archive', 'paperclip', 'trash', 'recycle', 'truck',
    'car', 'bicycle', 'motorcycle', 'plane', 'ship', 'compass',
    'map', 'flag', 'gift', 'star', 'heartbeat', 'first-aid',
    'stethoscope', 'pills', 'syringe', 'thermometer', 'weight', 'running',
    'swimmer', 'basketball-ball', 'football-ball', 'baseball-ball', 'volleyball-ball'
];

function handleAddCategory() {
    global $admin_id, $admin_role;
    
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $icon = sanitizeInput($_POST['icon'] ?? 'home');
    $color = sanitizeInput($_POST['color'] ?? '#667eea');
    $assigned_admin = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : null;
    
    // Validation
    if (empty($name)) {
        $_SESSION['error'] = 'Category name is required';
        return;
    }
    
    if (strlen($name) > 100) {
        $_SESSION['error'] = 'Category name is too long';
        return;
    }
    
    // Generate slug
    $slug = generateSlug($name);
    
    try {
        // Check if category already exists
        $stmt = db()->prepare("SELECT id FROM categories WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = 'Category with similar name already exists';
            return;
        }
        
        // Set admin ID
        $admin_id_to_assign = $admin_role === 'super_admin' ? $assigned_admin : $admin_id;
        
        // Insert category
        $stmt = db()->prepare("
            INSERT INTO categories (name, slug, description, icon, color, admin_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$name, $slug, $description, $icon, $color, $admin_id_to_assign]);
        
        $_SESSION['success'] = 'Category created successfully';
        
        // Log activity
        logActivity('CATEGORY_CREATED', "Category: $name", $_SESSION['admin_id'], 'admin');
        
    } catch (PDOException $e) {
        error_log("Error creating category: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to create category';
    }
}

function handleUpdateCategory() {
    global $admin_role;
    
    $category_id = intval($_POST['category_id'] ?? 0);
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $icon = sanitizeInput($_POST['icon'] ?? 'home');
    $color = sanitizeInput($_POST['color'] ?? '#667eea');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (!$category_id) {
        $_SESSION['error'] = 'Invalid category ID';
        return;
    }
    
    // Check permission
    if ($admin_role !== 'super_admin') {
        // Check if category belongs to this admin
        try {
            $stmt = db()->prepare("SELECT id FROM categories WHERE id = ? AND admin_id = ?");
            $stmt->execute([$category_id, $_SESSION['admin_id']]);
            if (!$stmt->fetch()) {
                $_SESSION['error'] = 'You do not have permission to edit this category';
                return;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Database error';
            return;
        }
    }
    
    try {
        $stmt = db()->prepare("
            UPDATE categories 
            SET name = ?, description = ?, icon = ?, color = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $icon, $color, $is_active, $category_id]);
        
        $_SESSION['success'] = 'Category updated successfully';
        
        // Log activity
        logActivity('CATEGORY_UPDATED', "Category ID: $category_id", $_SESSION['admin_id'], 'admin');
        
    } catch (PDOException $e) {
        error_log("Error updating category: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to update category';
    }
}

function handleDeleteCategory() {
    global $admin_role;
    
    $category_id = intval($_POST['category_id'] ?? 0);
    
    if (!$category_id) {
        $_SESSION['error'] = 'Invalid category ID';
        return;
    }
    
    // Check permission
    if ($admin_role !== 'super_admin') {
        // Check if category belongs to this admin
        try {
            $stmt = db()->prepare("SELECT id FROM categories WHERE id = ? AND admin_id = ?");
            $stmt->execute([$category_id, $_SESSION['admin_id']]);
            if (!$stmt->fetch()) {
                $_SESSION['error'] = 'You do not have permission to delete this category';
                return;
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Database error';
            return;
        }
    }
    
    // Check if category has complaints
    try {
        $stmt = db()->prepare("SELECT COUNT(*) as count FROM complaints WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $_SESSION['error'] = 'Cannot delete category with existing complaints';
            return;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error';
        return;
    }
    
    try {
        $stmt = db()->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        
        $_SESSION['success'] = 'Category deleted successfully';
        
        // Log activity
        logActivity('CATEGORY_DELETED', "Category ID: $category_id", $_SESSION['admin_id'], 'admin');
        
    } catch (PDOException $e) {
        error_log("Error deleting category: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to delete category';
    }
}

function handleAssignAdmin() {
    if ($_SESSION['admin_role'] !== 'super_admin') {
        $_SESSION['error'] = 'Permission denied';
        return;
    }
    
    $category_id = intval($_POST['category_id'] ?? 0);
    $admin_id = intval($_POST['admin_id'] ?? 0);
    
    if (!$category_id) {
        $_SESSION['error'] = 'Invalid category ID';
        return;
    }
    
    if ($admin_id === 0) {
        $admin_id = null; // Unassign
    }
    
    try {
        $stmt = db()->prepare("UPDATE categories SET admin_id = ? WHERE id = ?");
        $stmt->execute([$admin_id, $category_id]);
        
        $_SESSION['success'] = 'Category assignment updated';
        
        // Log activity
        $action = $admin_id ? 'assigned' : 'unassigned';
        logActivity('CATEGORY_ASSIGNED', "Category ID: $category_id $action to admin ID: $admin_id", $_SESSION['admin_id'], 'admin');
        
    } catch (PDOException $e) {
        error_log("Error assigning category: " . $e->getMessage());
        $_SESSION['error'] = 'Failed to update assignment';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Color Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/classic.min.css">
    
    <style>
        .categories-container {
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

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .category-card {
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--category-color), transparent);
        }

        .category-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .category-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
            color: white;
        }

        .category-info {
            flex: 1;
        }

        .category-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .category-slug {
            font-size: 0.875rem;
            color: var(--text-muted);
            font-family: monospace;
        }

        .category-description {
            color: var(--text-secondary);
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .category-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .category-admin {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
        }

        .admin-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            margin-right: 0.75rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .admin-info {
            flex: 1;
        }

        .admin-name {
            font-weight: 500;
            font-size: 0.875rem;
        }

        .admin-username {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .category-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            flex: 1;
            padding: 0.5rem;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
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

        .btn-assign {
            background: rgba(159, 122, 234, 0.1);
            color: #9f7aea;
        }

        .btn-assign:hover {
            background: rgba(159, 122, 234, 0.2);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: auto;
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

        .icon-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
        }

        .icon-option {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--bg-primary);
            border: 2px solid transparent;
        }

        .icon-option:hover {
            background: var(--bg-tertiary);
            transform: scale(1.1);
        }

        .icon-option.selected {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.1);
        }

        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            border: 2px solid var(--border-color);
            margin-right: 1rem;
        }

        .color-input-group {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .color-value {
            font-family: monospace;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        @media (max-width: 1024px) {
            .categories-container {
                padding: 1rem;
            }
            
            .categories-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .category-actions {
                flex-direction: column;
            }
            
            .icon-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Admin Navigation -->
    <?php include '../../includes/layout/admin-nav.php'; ?>

    <!-- Main Content -->
    <div class="categories-container">
        <!-- Header -->
        <div class="page-header">
            <h1>Manage Categories</h1>
            <p>Organize complaints into categories and assign administrators</p>
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

        <!-- Add Category Button -->
        <div class="mb-4">
            <button class="btn btn-gradient" onclick="showAddCategoryModal()">
                <i class="fas fa-plus-circle me-2"></i> Add New Category
            </button>
        </div>

        <!-- Categories Grid -->
        <div class="categories-grid">
            <?php if (empty($categories)): ?>
                <div class="empty-state">
                    <i class="fas fa-tags"></i>
                    <h3>No Categories Found</h3>
                    <p>Create your first category to organize complaints</p>
                </div>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                    <div class="glass-card category-card" style="--category-color: <?php echo $category['color']; ?>">
                        <div class="category-header">
                            <div class="category-icon" style="background: <?php echo $category['color']; ?>">
                                <i class="fas fa-<?php echo $category['icon']; ?>"></i>
                            </div>
                            <div class="category-info">
                                <div class="category-name"><?php echo htmlspecialchars($category['name']); ?></div>
                                <div class="category-slug"><?php echo $category['slug']; ?></div>
                            </div>
                            <span class="status-badge <?php echo $category['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>

                        <?php if (!empty($category['description'])): ?>
                            <div class="category-description">
                                <?php echo htmlspecialchars($category['description']); ?>
                            </div>
                        <?php endif; ?>

                        <div class="category-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $category['complaint_count']; ?></div>
                                <div class="stat-label">Complaints</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">
                                    <?php 
                                    // Calculate resolution rate (mock data)
                                    $resolution_rate = $category['complaint_count'] > 0 ? rand(60, 95) : 0;
                                    echo $resolution_rate;
                                    ?>%
                                </div>
                                <div class="stat-label">Resolved</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">
                                    <?php echo $category['complaint_count'] > 0 ? rand(1, 3) : 0; ?>
                                </div>
                                <div class="stat-label">Days Avg</div>
                            </div>
                        </div>

                        <?php if ($category['admin_name']): ?>
                            <div class="category-admin">
                                <div class="admin-avatar">
                                    <?php echo getInitials($category['admin_name']); ?>
                                </div>
                                <div class="admin-info">
                                    <div class="admin-name"><?php echo htmlspecialchars($category['admin_name']); ?></div>
                                    <div class="admin-username">@<?php echo htmlspecialchars($category['admin_username']); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="category-actions">
                            <button class="action-btn btn-edit" onclick="editCategory(<?php echo $category['id']; ?>)"
                                    title="Edit Category">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            
                            <?php if ($admin_role === 'super_admin'): ?>
                                <button class="action-btn btn-assign" 
                                        onclick="assignCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')"
                                        title="Assign Admin">
                                    <i class="fas fa-user-check"></i> Assign
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($category['complaint_count'] === '0'): ?>
                                <button class="action-btn btn-delete" 
                                        onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')"
                                        title="Delete Category">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="addCategoryForm">
                    <input type="hidden" name="action" value="add_category">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name *</label>
                            <input type="text" name="name" class="form-control" required 
                                   placeholder="e.g., Hostel, Campus, Library">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" 
                                      placeholder="Brief description of this category"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Icon</label>
                            <div class="icon-grid" id="iconGrid">
                                <?php foreach ($icons as $icon): ?>
                                    <div class="icon-option <?php echo $icon === 'home' ? 'selected' : ''; ?>" 
                                         data-icon="<?php echo $icon; ?>"
                                         onclick="selectIcon(this, '<?php echo $icon; ?>')">
                                        <i class="fas fa-<?php echo $icon; ?>"></i>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="icon" id="selectedIcon" value="home">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Color</label>
                            <div class="color-input-group">
                                <div class="color-preview" id="colorPreview" style="background: #667eea;"></div>
                                <div>
                                    <input type="text" name="color" id="colorInput" class="form-control" 
                                           value="#667eea" readonly style="max-width: 120px;">
                                    <div class="color-value" id="colorValue">#667eea</div>
                                </div>
                            </div>
                            <div id="colorPicker"></div>
                        </div>
                        
                        <?php if ($admin_role === 'super_admin' && !empty($admins)): ?>
                            <div class="mb-3">
                                <label class="form-label">Assign to Admin</label>
                                <select name="admin_id" class="form-control">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach ($admins as $admin): ?>
                                        <option value="<?php echo $admin['id']; ?>">
                                            <?php echo htmlspecialchars($admin['full_name']); ?> (@<?php echo htmlspecialchars($admin['username']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-gradient">Create Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content glass-modal">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Assign Category Modal -->
    <div class="modal fade" id="assignCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="assignCategoryForm">
                    <input type="hidden" name="action" value="assign_admin">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="category_id" id="assignCategoryId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" class="form-control" id="assignCategoryName" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Assign to Admin</label>
                            <select name="admin_id" class="form-control" id="assignAdminSelect">
                                <option value="0">-- Unassign --</option>
                                <?php foreach ($admins as $admin): ?>
                                    <option value="<?php echo $admin['id']; ?>">
                                        <?php echo htmlspecialchars($admin['full_name']); ?> (@<?php echo htmlspecialchars($admin['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <small>Only category administrators can be assigned to manage categories.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-gradient">Save Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/theme-toggle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/pickr.min.js"></script>
    
    <script>
        // Initialize color picker
        const pickr = Pickr.create({
            el: '#colorPicker',
            theme: 'classic',
            default: '#667eea',
            swatches: [
                '#667eea', '#764ba2', '#f56565', '#48bb78', '#ed8936',
                '#4299e1', '#9f7aea', '#f687b3', '#4fd1c7', '#a0aec0'
            ],
            components: {
                preview: true,
                opacity: false,
                hue: true,
                interaction: {
                    hex: true,
                    rgba: true,
                    hsva: true,
                    input: true,
                    clear: false,
                    save: true
                }
            }
        });

        pickr.on('save', (color, instance) => {
            const hexColor = color.toHEXA().toString();
            document.getElementById('colorInput').value = hexColor;
            document.getElementById('colorPreview').style.background = hexColor;
            document.getElementById('colorValue').textContent = hexColor;
            instance.hide();
        });

        // Select icon
        function selectIcon(element, icon) {
            document.querySelectorAll('.icon-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            element.classList.add('selected');
            document.getElementById('selectedIcon').value = icon;
        }

        // Show add category modal
        function showAddCategoryModal() {
            const modal = new bootstrap.Modal(document.getElementById('addCategoryModal'));
            modal.show();
        }

        // Edit category
        async function editCategory(categoryId) {
            try {
                const response = await fetch(`<?php echo APP_URL; ?>/api/get_category.php?id=${categoryId}`);
                const data = await response.json();
                
                if (data.success) {
                    const modal = document.getElementById('editCategoryModal');
                    modal.querySelector('.modal-content').innerHTML = `
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Category</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_category">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="category_id" value="${data.category.id}">
                            
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Category Name *</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="${data.category.name}" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="3">${data.category.description || ''}</textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Icon</label>
                                    <div class="icon-grid" id="editIconGrid">
                                        ${data.icons.map(icon => `
                                            <div class="icon-option ${icon === data.category.icon ? 'selected' : ''}" 
                                                 data-icon="${icon}"
                                                 onclick="selectEditIcon(this, '${icon}')">
                                                <i class="fas fa-${icon}"></i>
                                            </div>
                                        `).join('')}
                                    </div>
                                    <input type="hidden" name="icon" id="editSelectedIcon" value="${data.category.icon}">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Color</label>
                                    <div class="color-input-group">
                                        <div class="color-preview" id="editColorPreview" style="background: ${data.category.color};"></div>
                                        <div>
                                            <input type="text" name="color" id="editColorInput" class="form-control" 
                                                   value="${data.category.color}" readonly style="max-width: 120px;">
                                            <div class="color-value">${data.category.color}</div>
                                        </div>
                                    </div>
                                    <div id="editColorPicker"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check">
                                        <input type="checkbox" name="is_active" class="form-check-input" id="editIsActive" ${data.category.is_active ? 'checked' : ''}>
                                        <label class="form-check-label" for="editIsActive">Active Category</label>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-gradient">Update Category</button>
                            </div>
                        </form>
                    `;
                    
                    // Initialize edit color picker
                    const editPickr = Pickr.create({
                        el: '#editColorPicker',
                        theme: 'classic',
                        default: data.category.color,
                        swatches: [
                            '#667eea', '#764ba2', '#f56565', '#48bb78', '#ed8936',
                            '#4299e1', '#9f7aea', '#f687b3', '#4fd1c7', '#a0aec0'
                        ],
                        components: {
                            preview: true,
                            opacity: false,
                            hue: true,
                            interaction: {
                                hex: true,
                                rgba: true,
                                hsva: true,
                                input: true,
                                clear: false,
                                save: true
                            }
                        }
                    });

                    editPickr.on('save', (color, instance) => {
                        const hexColor = color.toHEXA().toString();
                        document.getElementById('editColorInput').value = hexColor;
                        document.getElementById('editColorPreview').style.background = hexColor;
                        instance.hide();
                    });
                    
                    const editModal = new bootstrap.Modal(modal);
                    editModal.show();
                } else {
                    showToast('Failed to load category details', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error', 'error');
            }
        }

        // Select icon in edit modal
        function selectEditIcon(element, icon) {
            document.querySelectorAll('#editIconGrid .icon-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            element.classList.add('selected');
            document.getElementById('editSelectedIcon').value = icon;
        }

        // Delete category confirmation
        function deleteCategory(categoryId, categoryName) {
            if (confirm(`Are you sure you want to delete category "${categoryName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_category';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'category_id';
                idInput.value = categoryId;
                
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

        // Assign category
        function assignCategory(categoryId, categoryName) {
            document.getElementById('assignCategoryId').value = categoryId;
            document.getElementById('assignCategoryName').value = categoryName;
            
            const modal = new bootstrap.Modal(document.getElementById('assignCategoryModal'));
            modal.show();
        }

        // Initialize icons grid scroll
        document.addEventListener('DOMContentLoaded', function() {
            const iconGrid = document.getElementById('iconGrid');
            if (iconGrid) {
                // Add search functionality
                const searchInput = document.createElement('input');
                searchInput.type = 'search';
                searchInput.className = 'form-control mb-2';
                searchInput.placeholder = 'Search icons...';
                searchInput.oninput = function() {
                    const searchTerm = this.value.toLowerCase();
                    const icons = iconGrid.querySelectorAll('.icon-option');
                    
                    icons.forEach(icon => {
                        const iconName = icon.getAttribute('data-icon');
                        if (iconName.includes(searchTerm)) {
                            icon.style.display = 'flex';
                        } else {
                            icon.style.display = 'none';
                        }
                    });
                };
                
                iconGrid.parentNode.insertBefore(searchInput, iconGrid);
            }
        });
    </script>
</body>
</html>
