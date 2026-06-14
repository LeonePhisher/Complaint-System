<?php
require_once '../../config/constants.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../includes/utilities/helpers.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

// Permission gate: super admin can always access, other admins need explicit permission
requirePermission('view_complaints');

$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'];
$can_manage_complaints = hasPermission('manage_complaints');

// Get filter parameters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$category = isset($_GET['category']) ? intval($_GET['category']) : null;
$urgency = isset($_GET['urgency']) ? sanitizeInput($_GET['urgency']) : null;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : null;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

// Role-based filtering
if ($admin_role !== 'super_admin') {
    $where[] = "cat.admin_id = ?";
    $params[] = $admin_id;
}

// Status filter
if ($status !== 'all') {
    $where[] = "c.status = ?";
    $params[] = $status;
}

// Category filter
if ($category) {
    $where[] = "c.category_id = ?";
    $params[] = $category;
}

// Urgency filter
if ($urgency && in_array($urgency, ['low', 'medium', 'high', 'critical'])) {
    $where[] = "c.urgency = ?";
    $params[] = $urgency;
}

// Search
if ($search) {
    $where[] = "(c.title LIKE ? OR c.description LIKE ? OR c.complaint_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total count
$total = 0;
try {
    $count_query = "
        SELECT COUNT(*) as total
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        $where_clause
    ";
    
    $stmt = db()->prepare($count_query);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log("Error counting complaints: " . $e->getMessage());
}

// Get categories for filter
$categories = [];
try {
    if ($admin_role === 'super_admin') {
        $stmt = db()->prepare("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
        $stmt->execute();
    } else {
        $stmt = db()->prepare("SELECT id, name FROM categories WHERE admin_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$admin_id]);
    }
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error loading categories: " . $e->getMessage());
}

// Status counts for tabs
$status_counts = [];
$statuses = ['pending', 'under_review', 'published', 'resolved', 'rejected'];
foreach ($statuses as $s) {
    try {
        if ($admin_role === 'super_admin') {
            $query = "SELECT COUNT(*) as count FROM complaints WHERE status = ?";
            $stmt = db()->prepare($query);
            $stmt->execute([$s]);
        } else {
            $query = "
                SELECT COUNT(*) as count
                FROM complaints c
                JOIN categories cat ON c.category_id = cat.id
                WHERE c.status = ? AND cat.admin_id = ?
            ";
            $stmt = db()->prepare($query);
            $stmt->execute([$s, $admin_id]);
        }
        $status_counts[$s] = $stmt->fetch()['count'];
    } catch (PDOException $e) {
        $status_counts[$s] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Complaints - <?php echo APP_NAME; ?></title>
    
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
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    
    <style>
        .complaints-container {
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

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
            align-items: stretch;
        }

        .stat-card {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 110px;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-primary);

            /* Match the rest of the UI cards */
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-md);

            /* Status accent */
            border-left: 4px solid transparent;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            opacity: 0.9;
            background: transparent;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
             
        .stat-card.active {
            background: rgba(102, 126, 234, 0.1);
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.35), var(--shadow-lg);
        }

        .stat-card.pending {
            border-left-color: #ed8936;
        }
        .stat-card.pending::before { background: linear-gradient(90deg, #ed8936, transparent); }

        .stat-card.under_review {
            border-left-color: #ecc94b;
        }
        .stat-card.under_review::before { background: linear-gradient(90deg, #ecc94b, transparent); }

        .stat-card.published {
            border-left-color: #48bb78;
        }
        .stat-card.published::before { background: linear-gradient(90deg, #48bb78, transparent); }

        .stat-card.resolved {
            border-left-color: #4299e1;
        }
        .stat-card.resolved::before { background: linear-gradient(90deg, #4299e1, transparent); }

        .stat-card.rejected {
            border-left-color: #f56565;
        }
        .stat-card.rejected::before { background: linear-gradient(90deg, #f56565, transparent); }

        .stat-count {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--text-primary), var(--text-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
        }

        .filters-card {
            margin-bottom: 2rem;
            padding: 1.5rem;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
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
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box {
            position: relative;
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

        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        .table-container {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--glass-border);
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        .dataTables_wrapper {
            padding: 1rem;
        }

        .complaint-row {
            transition: all 0.2s ease;
        }

        .complaint-row:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .urgency-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .urgency-critical {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
            border: 1px solid rgba(245, 101, 101, 0.2);
        }

        .urgency-high {
            background: rgba(237, 137, 54, 0.1);
            color: #ed8936;
            border: 1px solid rgba(237, 137, 54, 0.2);
        }

        .urgency-medium {
            background: rgba(236, 201, 75, 0.1);
            color: #ecc94b;
            border: 1px solid rgba(236, 201, 75, 0.2);
        }

        .urgency-low {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
            border: 1px solid rgba(72, 187, 120, 0.2);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(237, 137, 54, 0.1);
            color: #ed8936;
            border: 1px solid rgba(237, 137, 54, 0.2);
        }

        .status-under_review {
            background: rgba(236, 201, 75, 0.1);
            color: #ecc94b;
            border: 1px solid rgba(236, 201, 75, 0.2);
        }

        .status-published {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
            border: 1px solid rgba(72, 187, 120, 0.2);
        }

        .status-resolved {
            background: rgba(66, 153, 225, 0.1);
            color: #4299e1;
            border: 1px solid rgba(66, 153, 225, 0.2);
        }

        .status-rejected {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
            border: 1px solid rgba(245, 101, 101, 0.2);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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

        .btn-view {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .btn-view:hover {
            background: rgba(102, 126, 234, 0.2);
        }

        .btn-approve {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
        }

        .btn-approve:hover {
            background: rgba(72, 187, 120, 0.2);
        }

        .btn-reject {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
        }

        .btn-reject:hover {
            background: rgba(245, 101, 101, 0.2);
        }

        .btn-resolve {
            background: rgba(66, 153, 225, 0.1);
            color: #4299e1;
        }

        .btn-resolve:hover {
            background: rgba(66, 153, 225, 0.2);
        }

        .btn-assign {
            background: rgba(159, 122, 234, 0.1);
            color: #9f7aea;
        }

        .btn-assign:hover {
            background: rgba(159, 122, 234, 0.2);
        }

        .btn-delete {
            background: rgba(229, 62, 62, 0.1);
            color: #e53e3e;
        }

        .btn-delete:hover {
            background: rgba(229, 62, 62, 0.2);
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
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

        .export-buttons {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
        }

        @media (max-width: 1024px) {
            .complaints-container {
                padding: 1rem;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .table-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .export-buttons {
                margin-left: 0;
                width: 100%;
                justify-content: flex-start;
            }
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }

            .stat-card {
                min-height: 96px;
                padding: 1.25rem;
            }

            .stat-count {
                font-size: 1.75rem;
            }
        }

        /* Modal Complaint Details Styling */
        .complaint-details {
            font-family: 'Inter', sans-serif;
        }

        .complaint-details h5 {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.25rem;
        }

        .complaint-details .form-label {
            color: var(--text-secondary) !important;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .complaint-details .badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-full);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .complaint-details .text-muted {
            color: var(--text-muted) !important;
            font-size: 0.875rem;
        }

        .complaint-details code {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .complaint-details .border-bottom {
            border-color: var(--border-color) !important;
        }

        .complaint-details .p-3 {
            border-radius: var(--radius-md);
            padding: 1rem !important;
        }

        .complaint-details .bg-light {
            background: var(--bg-secondary) !important;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .complaint-details .alert {
            border-radius: var(--radius-md);
            border: none;
        }

        .complaint-details .alert-danger {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
            border-left: 4px solid #f56565;
        }

        .complaint-details .alert-heading {
            color: #f56565;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .complaint-details .row > [class*='col-'] {
            margin-bottom: 1rem;
        }

        .complaint-details .urgency-badge {
            display: inline-block;
        }

        .complaint-details .status-badge {
            display: inline-block;
        }

        /* Modal Glass Style */
        .glass-modal {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
        }

        .glass-modal .modal-header {
            border-color: var(--border-color);
            background: rgba(102, 126, 234, 0.05);
        }

        .glass-modal .modal-body {
            background: var(--bg-primary);
        }

        .glass-modal .modal-footer {
            border-color: var(--border-color);
            background: rgba(102, 126, 234, 0.02);
        }

        .glass-modal .btn-close {
            filter: invert(1);
        }

        /* Stats in Modal */
        .complaint-details .p-3[style*="background: rgba(102"] {
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .complaint-details .p-3[style*="background: rgba(72"] {
            border: 1px solid rgba(72, 187, 120, 0.2);
        }

        .complaint-details .p-3[style*="background: rgba(245"] {
            border: 1px solid rgba(245, 101, 101, 0.2);
        }

        .complaint-details h5 {
            margin-bottom: 0.25rem;
        }

        .complaint-details .mb-2 {
            margin-bottom: 0.5rem !important;
        }

        .complaint-details .mb-3 {
            margin-bottom: 1rem !important;
        }

        .complaint-details .mb-4 {
            margin-bottom: 1.5rem !important;
        }

        .complaint-details .text-md-end {
            text-align: right;
        }

        @media (max-width: 768px) {
            .complaint-details .text-md-end {
                text-align: left;
                margin-top: 1rem;
            }

            .complaint-details .row > [class*='col-'] {
                margin-bottom: 1rem;
            }

            .complaint-details .p-3 {
                padding: 0.75rem !important;
            }

            .complaint-details h5 {
                font-size: 1.1rem;
            }
        }
                /* Modal Glass Style */
        .glass-modal {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
        }

        .glass-modal .modal-header {
            border-color: var(--border-color);
            background: rgba(102, 126, 234, 0.05);
            text-align: center;
        }

        .glass-modal .modal-header .modal-title {
            margin-left: auto;
            margin-right: auto;
        }

        .glass-modal .modal-body {
            background: var(--bg-primary);
            padding: 2rem;
        }

        .glass-modal .modal-footer {
            border-color: var(--border-color);
            background: rgba(102, 126, 234, 0.02);
            justify-content: center;
            gap: 1rem;
        }

        .glass-modal .btn-close {
            position: absolute;
            right: 1rem;
            top: 1rem;
            filter: invert(1);
        }

        /* Center complaint details */
        .complaint-details {
            text-align: center;
        }

        .complaint-details .mb-4.pb-3 {
            text-align: center;
        }

        .complaint-details .row {
            justify-content: center;
        }

        .complaint-details .badge,
        .complaint-details .urgency-badge,
        .complaint-details .status-badge {
            justify-content: center;
            display: inline-flex;
        }

        .complaint-details .text-md-end {
            text-align: center;
        }

        /* Center stats boxes */
        .complaint-details .p-3 {
            text-align: center;
        }

        @media (max-width: 768px) {
            .complaint-details .text-md-end {
                text-align: center;
                margin-top: 1rem;
            }

            .glass-modal .modal-body {
                padding: 1.5rem;
            }

            .complaint-details h5 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Navigation -->
    <?php include '../../includes/layout/admin-nav.php'; ?>

    <!-- Main Content -->
    <div class="complaints-container">
        <!-- Header -->
        <div class="page-header">
            <h1>Manage Complaints</h1>
            <p>Review, approve, reject, and resolve complaints</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-cards">
            <a href="?status=pending" class="stat-card pending <?php echo $status === 'pending' ? 'active' : ''; ?>">
                <div class="stat-count"><?php echo $status_counts['pending']; ?></div>
                <div class="stat-label">Pending</div>
            </a>
            
            <a href="?status=under_review" class="stat-card under_review <?php echo $status === 'under_review' ? 'active' : ''; ?>">
                <div class="stat-count"><?php echo $status_counts['under_review']; ?></div>
                <div class="stat-label">Under Review</div>
            </a>
            
            <a href="?status=published" class="stat-card published <?php echo $status === 'published' ? 'active' : ''; ?>">
                <div class="stat-count"><?php echo $status_counts['published']; ?></div>
                <div class="stat-label">Published</div>
            </a>
            
            <a href="?status=resolved" class="stat-card resolved <?php echo $status === 'resolved' ? 'active' : ''; ?>">
                <div class="stat-count"><?php echo $status_counts['resolved']; ?></div>
                <div class="stat-label">Resolved</div>
            </a>
            
            <a href="?status=rejected" class="stat-card rejected <?php echo $status === 'rejected' ? 'active' : ''; ?>">
                <div class="stat-count"><?php echo $status_counts['rejected']; ?></div>
                <div class="stat-label">Rejected</div>
            </a>
        </div>

        <!-- Filters -->
        <div class="glass-card filters-card">
            <form method="GET" action="" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="under_review" <?php echo $status === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                            <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Urgency</label>
                        <select name="urgency" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Urgency Levels</option>
                            <option value="critical" <?php echo $urgency === 'critical' ? 'selected' : ''; ?>>Critical</option>
                            <option value="high" <?php echo $urgency === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="medium" <?php echo $urgency === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="low" <?php echo $urgency === 'low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                </div>
                
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input 
                        type="search" 
                        name="search" 
                        class="search-input" 
                        placeholder="Search by title, description, or complaint code..."
                        value="<?php echo htmlspecialchars($search ?? ''); ?>"
                    >
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="complaints.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Complaints Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <?php 
                    $status_text = $status === 'all' ? 'All Complaints' : ucfirst(str_replace('_', ' ', $status));
                    echo $status_text . " (" . $total . ")";
                    ?>
                </div>
                
                <div class="export-buttons">
                    <button class="btn btn-outline" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                    <button class="btn btn-outline" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <button class="btn btn-outline" onclick="printTable()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="complaintsTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Complaint Code</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Votes</th>
                            <th>Views</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($total > 0): ?>
                            <?php 
                            // Get complaints for current page
                            $query_params = $params;
                            $query_params[] = $limit;
                            $query_params[] = $offset;
                            
                            try {
                                $query = "
                                    SELECT 
                                        c.id,
                                        c.complaint_code,
                                        c.title,
                                        c.description,
                                        c.urgency,
                                        c.status,
                                        c.view_count,
                                        c.upvotes,
                                        c.downvotes,
                                        c.created_at,
                                        c.resolved_at,
                                        cat.name as category_name,
                                        cat.color as category_color
                                    FROM complaints c
                                    JOIN categories cat ON c.category_id = cat.id
                                    $where_clause
                                    ORDER BY c.created_at DESC
                                    LIMIT ? OFFSET ?
                                ";
                                
                                $stmt = db()->prepare($query);
                                $stmt->execute($query_params);
                                $complaints = $stmt->fetchAll();
                                
                                foreach ($complaints as $complaint):
                            ?>
                                <tr class="complaint-row" data-id="<?php echo $complaint['id']; ?>">
                                    <td><?php echo $complaint['id']; ?></td>
                                    <td>
                                        <strong><?php echo $complaint['complaint_code']; ?></strong>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo truncateText(htmlspecialchars($complaint['title']), 50); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars(truncateText(strip_tags(html_entity_decode(html_entity_decode(html_entity_decode($complaint['description'])))), 70)); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: <?php echo $complaint['category_color'] . '20'; ?>; color: <?php echo $complaint['category_color']; ?>;">
                                            <?php echo htmlspecialchars($complaint['category_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="urgency-badge urgency-<?php echo $complaint['urgency']; ?>">
                                            <?php echo ucfirst($complaint['urgency']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo date('M j, Y', strtotime($complaint['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo timeAgo($complaint['created_at']); ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <span class="text-success">
                                                <i class="fas fa-arrow-up"></i> <?php echo $complaint['upvotes']; ?>
                                            </span>
                                            <span class="text-danger">
                                                <i class="fas fa-arrow-down"></i> <?php echo $complaint['downvotes']; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <i class="fas fa-eye"></i> <?php echo $complaint['view_count']; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn btn-view" onclick="viewComplaint(<?php echo $complaint['id']; ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($can_manage_complaints && ($complaint['status'] === 'pending' || $complaint['status'] === 'under_review')): ?>
                                                <button class="action-btn btn-approve" 
                                                        onclick="updateStatus(<?php echo $complaint['id']; ?>, 'published')"
                                                        title="Approve & Publish">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                
                                                <button class="action-btn btn-reject" 
                                                        onclick="rejectComplaint(<?php echo $complaint['id']; ?>)"
                                                        title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                
                                                <button class="action-btn btn-resolve" 
                                                        onclick="updateStatus(<?php echo $complaint['id']; ?>, 'resolved')"
                                                        title="Mark as Resolved">
                                                    <i class="fas fa-flag-checkered"></i>
                                                </button>
                                                <?php elseif ($can_manage_complaints && (($complaint['status'] === 'published' || $complaint['status'] === 'under_review' ) && $complaint['resolved_at'] == NULL)): ?>
                                                <button class="action-btn btn-resolve" 
                                                        onclick="updateStatus(<?php echo $complaint['id']; ?>, 'resolved')"
                                                        title="Mark as Resolved">
                                                    <i class="fas fa-flag-checkered"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($can_manage_complaints && $complaint['status'] === 'pending'): ?>
                                                <button class="action-btn btn-assign" 
                                                        onclick="assignToReview(<?php echo $complaint['id']; ?>)"
                                                        title="Assign for Review">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                                
                                                <button class="action-btn btn-delete" 
                                                        onclick="deleteComplaint(<?php echo $complaint['id']; ?>)"
                                                        title="Delete Complaint">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php 
                                endforeach;
                            } catch (PDOException $e) {
                                error_log("Error loading complaints: " . $e->getMessage());
                            }
                            ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h3>No complaints found</h3>
                                        <p>There are no complaints matching your filters.</p>
                                        <a href="complaints.php" class="btn btn-gradient mt-3">
                                            <i class="fas fa-redo"></i> Reset Filters
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total > $limit): ?>
                <div class="pagination-container">
                    <?php echo generatePagination($page, ceil($total / $limit), 'complaints.php?page=%d&status=' . $status . '&category=' . $category . '&urgency=' . $urgency . '&search=' . urlencode($search ?? '')); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Complaint Modal -->
    <div class="modal fade" id="viewComplaintModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Complaint Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="complaintDetails">
                    <!-- Details will be loaded here via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-gradient" id="takeActionBtn">Take Action</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Complaint Modal -->
    <div class="modal fade" id="rejectComplaintModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content glass-modal">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Complaint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="rejectForm">
                        <input type="hidden" name="complaint_id" id="rejectComplaintId">
                        <div class="form-group mb-3">
                            <label for="rejectionReason" class="form-label">Reason for Rejection</label>
                            <textarea class="form-control" id="rejectionReason" name="reason" 
                                      rows="4" placeholder="Please provide a reason for rejection..." 
                                      required></textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea class="form-control" name="notes" 
                                      rows="2" placeholder="Any additional notes..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="submitRejection()">Confirm Rejection</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/theme-toggle.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/datatables-config.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    
    <script>
        const CAN_MANAGE_COMPLAINTS = <?php echo $can_manage_complaints ? 'true' : 'false'; ?>;

        // Initialize DataTable
        $(document).ready(function() {
            $('#complaintsTable').DataTable({
                responsive: true,
                paging: false,
                searching: false,
                info: false,
                order: [[0, 'desc']],
                language: {
                    emptyTable: "No complaints found"
                }
            });
        });

        // View complaint details
        // async function viewComplaint(complaintId) {
        //     try {
        //         const response = await fetch(`<?php echo APP_URL; ?>/api/get_complaint.php?id=${complaintId}`);
        //         const data = await response.json();
                
        //         if (data.success) {
        //             const complaint = data.complaint;
        //             const modalBody = document.getElementById('complaintDetails');
                    
        //             let html = `
        //                 <div class="complaint-details">
        //                     <div class="row mb-4">
        //                         <div class="col-md-6">
        //                             <h6>Complaint Code</h6>
        //                             <p class="fw-bold">${complaint.complaint_code}</p>
        //                         </div>
        //                         <div class="col-md-6">
        //                             <h6>Status</h6>
        //                             <span class="status-badge status-${complaint.status}">
        //                                 ${complaint.status.replace('_', ' ').toUpperCase()}
        //                             </span>
        //                         </div>
        //                     </div>
                            
        //                     <div class="row mb-4">
        //                         <div class="col-md-6">
        //                             <h6>Urgency</h6>
        //                             <span class="urgency-badge urgency-${complaint.urgency}">
        //                                 ${complaint.urgency.toUpperCase()}
        //                             </span>
        //                         </div>
        //                         <div class="col-md-6">
        //                             <h6>Category</h6>
        //                             <span class="badge" style="background: ${complaint.category_color}20; color: ${complaint.category_color};">
        //                                 ${complaint.category_name}
        //                             </span>
        //                         </div>
        //                     </div>
                            
        //                     <div class="mb-4">
        //                         <h6>Title</h6>
        //                         <p class="fw-bold">${complaint.title}</p>
        //                     </div>
                            
        //                     <div class="mb-4">
        //                         <h6>Description</h6>
        //                         <div class="p-3 bg-light rounded">${complaint.description}</div>
        //                     </div>
                            
        //                     ${complaint.location ? `
        //                         <div class="mb-4">
        //                             <h6>Location</h6>
        //                             <p><i class="fas fa-map-marker-alt"></i> ${complaint.location}</p>
        //                         </div>
        //                     ` : ''}
                            
        //                     <div class="row mb-4">
        //                         <div class="col-md-4">
        //                             <h6>Views</h6>
        //                             <p><i class="fas fa-eye"></i> ${complaint.view_count}</p>
        //                         </div>
        //                         <div class="col-md-4">
        //                             <h6>Upvotes</h6>
        //                             <p class="text-success"><i class="fas fa-arrow-up"></i> ${complaint.upvotes}</p>
        //                         </div>
        //                         <div class="col-md-4">
        //                             <h6>Downvotes</h6>
        //                             <p class="text-danger"><i class="fas fa-arrow-down"></i> ${complaint.downvotes}</p>
        //                         </div>
        //                     </div>
                            
        //                     <div class="mb-4">
        //                         <h6>Submitted</h6>
        //                         <p>${complaint.created_at_formatted} (${complaint.time_ago})</p>
        //                     </div>
                            
        //                     ${complaint.rejection_reason ? `
        //                         <div class="alert alert-danger">
        //                             <h6><i class="fas fa-exclamation-circle"></i> Rejection Reason</h6>
        //                             <p>${complaint.rejection_reason}</p>
        //                         </div>
        //                     ` : ''}
        //                 </div>
        //             `;
                    
        //             modalBody.innerHTML = html;
                    
        //             // Update action button
        //             const actionBtn = document.getElementById('takeActionBtn');
        //             if (complaint.status === 'pending' || complaint.status === 'under_review') {
        //                 actionBtn.innerHTML = '<i class="fas fa-cogs"></i> Manage Complaint';
        //                 actionBtn.onclick = function() {
        //                     showActionMenu(complaintId);
        //                 };
        //             } else {
        //                 actionBtn.style.display = 'none';
        //             }
                    
        //             // Show modal
        //             const modal = new bootstrap.Modal(document.getElementById('viewComplaintModal'));
        //             modal.show();
        //         } else {
        //             showToast('Failed to load complaint details', 'error');
        //         }  catch (error) {
        //         console.error('Error:', error);
        //         showToast('Network error', 'error');
        //     }
        // }
        //======NEW CODE STARTS HERE======
                                // View complaint details
                async function viewComplaint(complaintId) {
                    try {
                        const response = await fetch(`<?php echo APP_URL; ?>/api/get_complaint.php?id=${complaintId}`);
                        const data = await response.json();
                        
                        if (data.success) {
                            const complaint = data.complaint;
                            const modalBody = document.getElementById('complaintDetails');
                            
                            let html = `
                                <div class="complaint-details">
                                    <div class="mb-4 pb-3 border-bottom">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h6 class="text-muted mb-1">Complaint Title</h6>
                                                <h5 class="mb-2">${complaint.title}</h5>
                                                <br>Complaint Code:
                                                <p class="text-muted mb-0">${complaint.complaint_code}</p>
                                                 <div class="col-md-4 text-md-end"><br>Current Status:</br>
                                                <span class="status-badge status-${complaint.status}">
                                                    ${complaint.status.replace('_', ' ').toUpperCase()}
                                                </span>
                                            </div>
                                                </br>
                                            </div>
                                                
                                           
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label text-muted fw-bold d-block mb-2">Category</label>
                                                <span class="badge" style="background: ${complaint.category_color}20; color: ${complaint.category_color}; padding: 0.5rem 1rem; font-size: 0.9rem;">
                                                    ${complaint.category_name}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label text-muted fw-bold d-block mb-2">Urgency Level</label>
                                                <span class="urgency-badge urgency-${complaint.urgency}">
                                                    ${complaint.urgency.toUpperCase()}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <label class="form-label text-muted fw-bold d-block mb-2">Description</label>
                                        <div class="p-3 bg-light rounded" style="line-height: 1.6; max-height: 300px; overflow-y: auto;">
                                            ${complaint.description}
                                        </div>
                                    </div>
                                    
                                    ${complaint.location ? `
                                        <div class="mb-4">
                                            <label class="form-label text-muted fw-bold d-block mb-2">Location</label>
                                            <p class="mb-0"><i class="fas fa-map-marker-alt" style="color: #667eea;"></i> ${complaint.location}</p>
                                        </div>
                                    ` : ''}
                                    
                                    <div class="row mb-4">
                                        <div class="col-md-4">
                                            <div class="p-3 rounded" style="background: rgba(102, 126, 234, 0.1);">
                                                <label class="form-label text-muted fw-bold d-block mb-2">Views</label>
                                                <h5 class="mb-0"><i class="fas fa-eye" style="color: #667eea;"></i> ${complaint.view_count}</h5>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-3 rounded" style="background: rgba(72, 187, 120, 0.1);">
                                                <label class="form-label text-muted fw-bold d-block mb-2">Upvotes</label>
                                                <h5 class="mb-0"><i class="fas fa-arrow-up" style="color: #48bb78;"></i> ${complaint.upvotes}</h5>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-3 rounded" style="background: rgba(245, 101, 101, 0.1);">
                                                <label class="form-label text-muted fw-bold d-block mb-2">Downvotes</label>
                                                <h5 class="mb-0"><i class="fas fa-arrow-down" style="color: #f56565;"></i> ${complaint.downvotes}</h5>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-4 pb-3 border-bottom">
                                        <div class="col-md-6">
                                            <label class="form-label text-muted fw-bold d-block mb-2">Submitted</label>
                                            <p class="mb-0"><strong>${complaint.created_at_formatted}</strong></p>
                                            <small class="text-muted">${complaint.time_ago}</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label text-muted fw-bold d-block mb-2">Complaint ID</label>
                                            <p class="mb-0"><code>${complaint.complaint_code}</code></p>
                                        </div>
                                    </div>
                                    
                                    ${complaint.rejection_reason ? `
                                        <div class="alert alert-danger" role="alert">
                                            <h6 class="alert-heading"><i class="fas fa-exclamation-circle"></i> Rejection Reason</h6>
                                            <p class="mb-0">${complaint.rejection_reason}</p>
                                        </div>
                                    ` : ''}
                                </div>
                            `;
                            
                            modalBody.innerHTML = html;
                            
                            // Update action button
                            const actionBtn = document.getElementById('takeActionBtn');
                            if (CAN_MANAGE_COMPLAINTS && (complaint.status === 'pending' || complaint.status === 'under_review')) {
                                actionBtn.innerHTML = '<i class="fas fa-cogs"></i> Manage Complaint';
                                actionBtn.onclick = function() {
                                    showActionMenu(complaintId);
                                };
                            } else {
                                actionBtn.style.display = 'none';
                            }
                            
                            // Show modal
                            const modal = new bootstrap.Modal(document.getElementById('viewComplaintModal'));
                            modal.show();
                        } else {
                            showToast('Failed to load complaint details', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showToast('Network error', 'error');
                    }
                }
        //=====NEW CODE ENDS HERE======
           

        // Update complaint status
        async function updateStatus(complaintId, status) {
            if (!confirm(`Are you sure you want to ${status.replace('_', ' ')} this complaint?`)) {
                return;
            }
            
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/update_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        complaint_id: complaintId,
                        status: status,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showToast(`Complaint ${status.replace('_', ' ')} successfully`, 'success');
                    
                    // Reload page after 1 second
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Failed to update status', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error', 'error');
            }
        }

        // Show reject complaint modal
        function rejectComplaint(complaintId) {
            document.getElementById('rejectComplaintId').value = complaintId;
            document.getElementById('rejectionReason').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('rejectComplaintModal'));
            modal.show();
        }

        // Submit rejection
        async function submitRejection() {
            const complaintId = document.getElementById('rejectComplaintId').value;
            const reason = document.getElementById('rejectionReason').value;
            const notes = document.querySelector('#rejectForm textarea[name="notes"]').value;
            
            if (!reason.trim()) {
                showToast('Please provide a reason for rejection', 'error');
                return;
            }
            
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/update_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        complaint_id: complaintId,
                        status: 'rejected',
                        rejection_reason: reason,
                        notes: notes,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('rejectComplaintModal'));
                    modal.hide();
                    
                    showToast('Complaint rejected successfully', 'success');
                    
                    // Reload page after 1 second
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    if (data.requires_approval) {
                        showToast('Rejection request sent to super admin for approval', 'info');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('rejectComplaintModal'));
                        modal.hide();
                    } else {
                        showToast(data.message || 'Failed to reject complaint', 'error');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error', 'error');
            }
        }

        // Assign complaint for review
        async function assignToReview(complaintId) {
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/update_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        complaint_id: complaintId,
                        status: 'under_review',
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showToast('Complaint assigned for review', 'success');
                    
                    // Reload page after 1 second
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Failed to assign complaint', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error', 'error');
            }
        }

        // Delete complaint
        async function deleteComplaint(complaintId) {
            if (!confirm('Are you sure you want to delete this complaint? This action cannot be undone.')) {
                return;
            }
            
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/delete_complaint.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        complaint_id: complaintId,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    showToast('Complaint deleted successfully', 'success');
                    
                    // Reload page after 1 second
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(data.message || 'Failed to delete complaint', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Network error', 'error');
            }
        }

        // Show action menu
        function showActionMenu(complaintId) {
            if (!CAN_MANAGE_COMPLAINTS) {
                showToast('You do not have permission to manage complaints', 'error');
                return;
            }
            const actions = [
                { label: 'Approve & Publish', icon: 'check', onclick: `updateStatus(${complaintId}, 'published')` },
                { label: 'Reject', icon: 'times', onclick: `rejectComplaint(${complaintId})` },
                { label: 'Mark as Resolved', icon: 'flag-checkered', onclick: `updateStatus(${complaintId}, 'resolved')` },
                { label: 'Assign for Review', icon: 'user-check', onclick: `assignToReview(${complaintId})` }
            ];
            
            // Create menu
            let menuHtml = '<div class="action-menu p-3">';
            menuHtml += '<h6>Select Action</h6>';
            menuHtml += '<div class="d-flex flex-column gap-2 mt-3">';
            
            actions.forEach(action => {
                menuHtml += `
                    <button class="btn btn-outline w-100 text-start" onclick="${action.onclick}">
                        <i class="fas fa-${action.icon} me-2"></i> ${action.label}
                    </button>
                `;
            });
            
            menuHtml += '</div></div>';
            
            // Show in modal (avoid duplicating the menu on repeated clicks)
            const details = document.getElementById('complaintDetails');
            const existing = details.querySelector('.action-menu');
            if (existing) existing.remove();
            details.insertAdjacentHTML('beforeend', menuHtml);
        }

        // Export functions
        function exportToExcel() {
            // Implementation for Excel export
            
            

            // showToast('Export to Excel feature coming soon', 'info');
        }

        function exportToPDF() {
            // Implementation for PDF export
            showToast('Export to PDF feature coming soon', 'info');
        }

        function printTable() {
            window.print();
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            // Check for new pending complaints
            fetch('<?php echo APP_URL; ?>/api/admin_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.stats.pending_increase > 0) {
                        // Update pending count
                        const pendingCard = document.querySelector('.stat-card.pending .stat-count');
                        if (pendingCard) {
                            const current = parseInt(pendingCard.textContent);
                            pendingCard.textContent = current + data.stats.pending_increase;
                        }
                        
                        // Show notification
                        showToast(`You have ${data.stats.pending_increase} new complaint(s) to review`, 'info');
                    }
                })
                .catch(error => console.error('Error refreshing:', error));
        }, 30000);
    </script>
</body>
</html>
