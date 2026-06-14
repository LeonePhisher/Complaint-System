<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../includes/utilities/helpers.php';

// Check if user is logged in as student
if (!isStudent()) {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$student_index = $_SESSION['student_index'];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? min(max(5, intval($_GET['limit'])), 100) : 10;
$offset = ($page - 1) * $limit;

// Filters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$urgency = isset($_GET['urgency']) ? sanitizeInput($_GET['urgency']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$sort = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'created_at_desc';

// Build query

$where = ["c.user_id = ?"];
$params = [$student_id];
$count_params = [$student_id];

// Filters
if (!empty($status) && $status !== 'all') {
    $where[] = "c.status = ?";
    $params[] = $status;
    $count_params[] = $status;
}

if (!empty($category) && $category > 0) {
    $where[] = "c.category_id = ?";
    $params[] = $category;
    $count_params[] = $category;
}

if (!empty($urgency) && $urgency !== 'all') {
    $where[] = "c.urgency = ?";
    $params[] = $urgency;
    $count_params[] = $urgency;
}

if (!empty($search)) {
    $where[] = "(c.title LIKE ? OR c.description LIKE ? OR c.complaint_code LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $count_params[] = $search_term;
    $count_params[] = $search_term;
    $count_params[] = $search_term;
}

$where_clause = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// Sorting
$sort_map = [
    'created_at_desc' => 'c.created_at DESC',
    'created_at_asc' => 'c.created_at ASC',
    'title_asc' => 'c.title ASC',
    'title_desc' => 'c.title DESC',
    'status' => 'c.status ASC, c.created_at DESC',
    'urgency' => 'FIELD(c.urgency, "critical", "high", "medium", "low"), c.created_at DESC',
    'votes' => '(c.upvotes - c.downvotes) DESC',
    'views' => 'c.view_count DESC'
];
$order_by = $sort_map[$sort] ?? 'c.created_at DESC';

// Load categories for filter (only categories with complaints by this user)
$categories = [];
try {
    $stmt = db()->prepare("
        SELECT DISTINCT cat.id, cat.name, cat.color
        FROM categories cat
        JOIN complaints c ON cat.id = c.category_id
        WHERE c.user_id = ?
        ORDER BY cat.name
    ");
    $stmt->execute([$student_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error loading categories: " . $e->getMessage());
}

// Get total complaints count
$total_complaints = 0;
$total_pages = 1;

try {
    $count_query = "SELECT COUNT(*) as total FROM complaints c $where_clause";
    $stmt = db()->prepare($count_query);
    $stmt->execute($count_params);
    $total_complaints = $stmt->fetchColumn() ?: 0;
    $total_pages = ceil($total_complaints / $limit);

    // Fetch complaints
    $query = "
        SELECT 
            c.id,c.complaint_code, c.user_id, c.category_id, c.title, c.description, c.status, c.urgency,c.resolved_at, c.created_at, c.upvotes, c.downvotes, c.view_count,
            c.attachments AS attachment_files,c.rejected_by,
            cat.name AS category_name,
            cat.color AS category_color,
            a.full_name AS rejected_by_name,
            (c.upvotes - c.downvotes) AS vote_score,
            (SELECT COUNT(*) FROM comments co WHERE co.complaint_id = c.id) AS comment_count
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN admins a ON c.rejected_by = a.id
        $where_clause
        ORDER BY $order_by
        LIMIT ? OFFSET ?
    ";

    $params[] = $limit;
    $params[] = $offset;

    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error loading complaints: " . $e->getMessage());
    $complaints = [];
    $_SESSION['error'] = 'Unable to load complaints. Please try again.';
}


// --- Build WHERE conditions ---
// $where = ["c.user_id = ?"];
// $params = [$student_id];

// // Filters
// if (!empty($status) && $status !== 'all') {
//     $where[] = "c.status = ?";
//     $params[] = $status;
// }

// if (!empty($category) && $category > 0) {
//     $where[] = "c.category_id = ?";
//     $params[] = $category;
// }

// if (!empty($urgency) && $urgency !== 'all') {
//     $where[] = "c.urgency = ?";
//     $params[] = $urgency;
// }

// if (!empty($search)) {
//     $where[] = "(c.title LIKE ? OR c.description LIKE ? OR c.complaint_code LIKE ?)";
//     $search_term = "%{$search}%";
//     $params[] = $search_term;
//     $params[] = $search_term;
//     $params[] = $search_term;
// }

// $where_clause = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// // Sorting
// $sort_map = [
//     'created_at_desc' => 'c.created_at DESC',
//     'created_at_asc' => 'c.created_at ASC',
//     'title_asc' => 'c.title ASC',
//     'title_desc' => 'c.title DESC',
//     'status' => 'c.status ASC, c.created_at DESC',
//     'urgency' => 'FIELD(c.urgency, "critical", "high", "medium", "low"), c.created_at DESC',
//     'votes' => '(c.upvotes - c.downvotes) DESC',
//     'views' => 'c.view_count DESC'
// ];
// $order_by = $sort_map[$sort] ?? 'c.created_at DESC';

// // Total count
// $count_query = "SELECT COUNT(*) AS total FROM complaints c $where_clause";
// $stmt = db()->prepare($count_query);
// $stmt->execute($params);
// $total_complaints = $stmt->fetchColumn();
// $total_pages = ceil($total_complaints / $limit);

// // Fetch complaints
// $query = "
// SELECT 
//     c.*,
//     cat.name AS category_name,
//     cat.color AS category_color,
//     (c.upvotes - c.downvotes) AS vote_score,
//     (SELECT COUNT(*) FROM comments co WHERE co.complaint_id = c.id) AS comment_count
// FROM complaints c
// JOIN categories cat ON c.category_id = cat.id
// $where_clause
// ORDER BY $order_by
// LIMIT ? OFFSET ?
// ";

// $params[] = $limit;
// $params[] = $offset;

// $stmt = db()->prepare($query);
// $stmt->execute($params);
// $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics for filters
$stats = [];
try {
    $query = "
        SELECT 
            c.status,
            COUNT(*) as count
        FROM complaints c
        WHERE c.user_id = ?
        GROUP BY c.status
        ORDER BY FIELD(c.status, 'pending', 'under_review', 'published', 'resolved', 'rejected')
    ";
    $stmt = db()->prepare($query);
    $stmt->execute([$student_id]);
    $status_stats = $stmt->fetchAll();
    
    $query = "
        SELECT 
            c.urgency,
            COUNT(*) as count
        FROM complaints c
        WHERE c.user_id = ?
        GROUP BY c.urgency
        ORDER BY FIELD(c.urgency, 'critical', 'high', 'medium', 'low')
    ";
    $stmt = db()->prepare($query);
    $stmt->execute([$student_id]);
    $urgency_stats = $stmt->fetchAll();
    
    $stats = [
        'status' => array_column($status_stats, 'count', 'status'),
        'urgency' => array_column($urgency_stats, 'count', 'urgency')
    ];
    
} catch (PDOException $e) {
    error_log("Error loading stats: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta name="app-url" content="/complaint-system">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Complaints - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/media-carousel.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <style>
        .my-complaints-container {
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

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-badge {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-badge:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-badge.active {
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .filters-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--glass-border);
            margin-bottom: 2rem;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .filter-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .filter-grid {
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

        .filter-select, .filter-input {
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }

        .results-info {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .complaints-table-container {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--glass-border);
            margin-bottom: 2rem;
        }
/*=============== DARK THEME CODE HERE========== */
        
/* Dark Theme Variables */
* {
    --primary-color: #7c93fb;
    --primary-dark: #667eea;
    --secondary-color: #9f7aea;
    
    /* Background Colors */
    --bg-primary: #1a202c;
    --bg-secondary: #2d3748;
    --bg-tertiary: #4a5568;
    
    /* Text Colors */
    --text-primary: #f7fafc;
    --text-secondary: #e2e8f0;
    --text-muted: #a0aec0;
    
    /* Border Colors */
    --border-color: #4a5568;
    --border-light: #2d3748;
    
    /* Shadow */
    --shadow-sm: 0 1px 3px rgba(0,0,0,0.3);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.25);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.25);
    --shadow-xl: 0 20px 25px rgba(0,0,0,0.3);
    
    /* Glassmorphism */
    --glass-bg: rgba(26, 32, 44, 0.95);
    --glass-border: rgba(255, 255, 255, 0.1);
}



        .table-responsive {
            overflow-x: auto;
        }

        .complaints-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .complaints-table th {
            background: var(--bg-secondary);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        .complaints-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }

        .complaints-table tbody tr {
            transition: all 0.2s ease;
        }

        .complaints-table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .complaint-code {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--primary-color);
        }

        .complaint-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--text-primary);
        }

        .complaint-preview {
            font-size: 0.875rem;
            color: var(--text-secondary);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .complaint-category {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .complaint-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
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

        .vote-score {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 600;
        }

        .vote-score.positive {
            color: #48bb78;
        }

        .vote-score.negative {
            color: #f56565;
        }

        .vote-score.neutral {
            color: var(--text-secondary);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-secondary);
            color: var(--text-secondary);
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-icon:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .btn-icon.view {
            background: rgba(66, 153, 225, 0.1);
            color: #4299e1;
        }

        .btn-icon.view:hover {
            background: rgba(66, 153, 225, 0.2);
        }

        .btn-icon.edit {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .btn-icon.edit:hover {
            background: rgba(102, 126, 234, 0.2);
        }

        .btn-icon.delete {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
        }

        .btn-icon.delete:hover {
            background: rgba(245, 101, 101, 0.2);
        }

        .btn-icon.attach {
            background: rgba(159, 122, 234, 0.1);
            color: #9f7aea;
        }

        .btn-icon.attach:hover {
            background: rgba(159, 122, 234, 0.2);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background: var(--bg-primary);
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .pagination-btn:hover:not(:disabled) {
            border-color: var(--primary-color);
            background: var(--bg-secondary);
        }

        .pagination-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .empty-state p {
            margin-bottom: 2rem;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .export-options {
            display: flex;
            gap: 0.5rem;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-toggle {
            padding: 0.5rem 1rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            color: var(--text-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            min-width: 200px;
            z-index: 1000;
            display: none;
        }

        .dropdown-menu.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        .dropdown-item {
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            text-decoration: none;
            display: block;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: var(--bg-secondary);
        }

        .dropdown-divider {
            height: 1px;
            background: var(--border-color);
            margin: 0.5rem 0;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .my-complaints-container {
                padding: 1rem;
            }
            
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .action-buttons {
                justify-content: center;
            }
            
            .complaint-meta {
                flex-direction: column;
                gap: 0.25rem;
            }
        }

        @media (max-width: 640px) {
            .stats-overview {
                grid-template-columns: 1fr;
            }
            
            .export-options {
                flex-direction: column;
            }
        }
    
        /* Modal (used by View/Edit/Attachments) */
        .modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.6);
            z-index: 2000;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            width: 100%;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
        }

        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-primary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .modal-body {
            padding: 1.25rem;
            overflow: auto;
        }

        @media (max-width: 768px) {
            .modal-body { padding: 1rem; }
        }
</style>
</head>
<body>
    <!-- Student Navigation -->
    <?php include '../../includes/layout/student-nav.php'; ?>

    <!-- Main Content -->
    <div class="my-complaints-container">
        <!-- Header -->
        <div class="page-header">
            <h1>My Complaints</h1>
            <p>Track and manage all your submitted complaints</p>
        </div>

        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-badge <?php echo $status === '' ? 'active' : ''; ?>" 
                 onclick="window.location.href='?status='">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $total_complaints; ?></div>
                    <div class="stat-label">Total Complaints</div>
                </div>
            </div>
            
            <div class="stat-badge <?php echo $status === 'pending' ? 'active' : ''; ?>" 
                 onclick="window.location.href='?status=pending'">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ed8936, #f56565);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['status']['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            
            <div class="stat-badge <?php echo $status === 'published' ? 'active' : ''; ?>" 
                 onclick="window.location.href='?status=published'">
                <div class="stat-icon" style="background: linear-gradient(135deg, #48bb78, #38a169);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['status']['published'] ?? 0; ?></div>
                    <div class="stat-label">Published</div>
                </div>
            </div>
            
            <div class="stat-badge <?php echo $status === 'resolved' ? 'active' : ''; ?>" 
                 onclick="window.location.href='?status=resolved'">
                <div class="stat-icon" style="background: linear-gradient(135deg, #4299e1, #3182ce);">
                    <i class="fas fa-flag-checkered"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['status']['resolved'] ?? 0; ?></div>
                    <div class="stat-label">Resolved</div>
                </div>
            </div>
            
            <div class="stat-badge <?php echo $status === 'rejected' ? 'active' : ''; ?>" 
                 onclick="window.location.href='?status=rejected'">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f56565, #ed64a6);">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $stats['status']['rejected'] ?? 0; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <div class="filter-header">
                <h3>Filter Complaints</h3>
                <div class="export-options">
                    <div class="dropdown">
                        <button class="dropdown-toggle" onclick="toggleDropdown('exportDropdown')">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <div class="dropdown-menu" id="exportDropdown">
                            <a href="#" class="dropdown-item" onclick="exportComplaints('csv')">
                                <i class="fas fa-file-csv"></i> CSV Format
                            </a>
                            <a href="#" class="dropdown-item" onclick="exportComplaints('excel')">
                                <i class="fas fa-file-excel"></i> Excel Format
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item" onclick="exportComplaints('pdf')">
                                <i class="fas fa-file-pdf"></i> PDF Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="GET" id="filterForm">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
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
                            <option value="0">All Categories</option>
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
                    
                    <div class="filter-group">
                        <label class="filter-label">Sort By</label>
                        <select name="sort" class="filter-select" onchange="this.form.submit()">
                            <option value="created_at_desc" <?php echo $sort === 'created_at_desc' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="created_at_asc" <?php echo $sort === 'created_at_asc' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="title_asc" <?php echo $sort === 'title_asc' ? 'selected' : ''; ?>>Title A-Z</option>
                            <option value="title_desc" <?php echo $sort === 'title_desc' ? 'selected' : ''; ?>>Title Z-A</option>
                            <option value="status" <?php echo $sort === 'status' ? 'selected' : ''; ?>>Status</option>
                            <option value="urgency" <?php echo $sort === 'urgency' ? 'selected' : ''; ?>>Urgency</option>
                            <option value="votes" <?php echo $sort === 'votes' ? 'selected' : ''; ?>>Most Votes</option>
                            <option value="views" <?php echo $sort === 'views' ? 'selected' : ''; ?>>Most Views</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-grid">
                    <div class="filter-group" style="grid-column: 1 / -1;">
                        <label class="filter-label">Search</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" name="search" class="filter-input" 
                                   placeholder="Search by title, description, or complaint code" 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-gradient">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <?php if ($search || $status || $category || $urgency): ?>
                                <button type="button" class="btn btn-outline" onclick="clearFilters()">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <div class="results-info">
                        Showing <?php echo count($complaints); ?> of <?php echo $total_complaints; ?> complaints
                        <?php if ($page > 1): ?> | Page <?php echo $page; ?> of <?php echo $total_pages; ?><?php endif; ?>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Items per page:</label>
                        <select name="limit" class="filter-select" style="width: auto;" onchange="this.form.submit()">
                            <option value="5" <?php echo $limit == 5 ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Complaints Table -->
        <div class="complaints-table-container">
            <?php if (!empty($complaints)): ?>
                <div class="table-responsive">
                    <table class="complaints-table">
                        <thead>
                            <tr>
                                <th>Complaint</th>
                                <th>Category</th>
                                <th>Urgency</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Engagement</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $complaint): ?>
                                <tr>
                                    <td>
                                        <div class="complaint-code">#<?php echo $complaint['complaint_code']; ?></div>
                                        <div class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></div>
                                        <div class="complaint-preview">
                                            <?php echo htmlspecialchars(truncateText(strip_tags(html_entity_decode(html_entity_decode(html_entity_decode($complaint['description'])))), 100)); ?>
                                        </div>
                                        <div class="complaint-meta">
                                            <span class="meta-item">
                                                <i class="fas fa-clock"></i> <?php echo timeAgo($complaint['created_at']); ?>
                                            </span>
                                            <?php if ($complaint['attachment_files']): ?>
                                                <span class="meta-item">
                                                    <i class="fas fa-paperclip"></i> Attachments
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($complaint['comment_count'] > 0): ?>
                                                <span class="meta-item">
                                                    <i class="fas fa-comment"></i> <?php echo $complaint['comment_count']; ?> comments
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="complaint-category" style="background: <?php echo $complaint['category_color']; ?>20; color: <?php echo $complaint['category_color']; ?>; border: 1px solid <?php echo $complaint['category_color']; ?>40;">
                                            <?php echo htmlspecialchars($complaint['category_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="urgency-badge urgency-<?php echo strtolower($complaint['urgency']); ?>">
                                            <?php echo $complaint['urgency']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo getStatusBadge($complaint['status']); ?>
                                        <?php if ($complaint['status'] === 'rejected' && $complaint['rejected_by']): ?>
                                            <div class="text-xs text-gray-500 mt-1">
                                                By: <?php echo htmlspecialchars($complaint['rejected_by_name'] ?? 'Admin #' . $complaint['rejected_by']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-sm">
                                            <?php echo date('M j, Y', strtotime($complaint['created_at'])); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo date('g:i A', strtotime($complaint['created_at'])); ?>
                                        </div>
                                        <?php if ($complaint['status'] === 'resolved' && $complaint['resolved_at']): ?>
                                            <div class="text-xs text-green-500 mt-1">
                                                Resolved: <?php echo date('M j', strtotime($complaint['resolved_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="vote-score <?php 
                                            echo ($complaint['vote_score'] > 0) ? 'positive' : 
                                                 (($complaint['vote_score'] < 0) ? 'negative' : 'neutral');
                                        ?>">
                                            <i class="fas fa-arrow-<?php echo $complaint['vote_score'] >= 0 ? 'up' : 'down'; ?>"></i>
                                            <?php echo abs($complaint['vote_score']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-eye"></i> <?php echo $complaint['view_count']; ?> views
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-thumbs-up"></i> <?php echo $complaint['upvotes']; ?> /
                                            <i class="fas fa-thumbs-down"></i> <?php echo $complaint['downvotes']; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon view" onclick="viewComplaint(<?php echo $complaint['id']; ?>)"
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if ($complaint['status'] === 'pending' && ($_SESSION['allow_complaint_editing'] ?? true)): ?>
                                                <button class="btn-icon edit" onclick="editComplaint(<?php echo $complaint['id']; ?>)"
                                                        title="Edit Complaint">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($complaint['status'] === 'pending'): ?>
                                                <button class="btn-icon delete" onclick="deleteComplaint(<?php echo $complaint['id']; ?>)"
                                                        title="Delete Complaint">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Complaints Found</h3>
                    <p>
                        <?php if ($search || $status || $category || $urgency): ?>
                            No complaints match your filters. Try adjusting your search criteria.
                        <?php else: ?>
                            You haven't submitted any complaints yet. Submit your first complaint to get started.
                        <?php endif; ?>
                    </p>
                    <div class="flex gap-3 justify-center mt-6">
                        <?php if ($search || $status || $category || $urgency): ?>
                            <button class="btn btn-outline" onclick="clearFilters()">
                                <i class="fas fa-times"></i> Clear Filters
                            </button>
                        <?php endif; ?>
                        <a href="submit.php" class="btn btn-gradient">
                            <i class="fas fa-plus"></i> Submit Complaint
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <button class="pagination-btn" 
                        onclick="goToPage(1)" 
                        <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                    <i class="fas fa-angle-double-left"></i>
                </button>
                
                <button class="pagination-btn" 
                        onclick="goToPage(<?php echo $page - 1; ?>)" 
                        <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                    <i class="fas fa-angle-left"></i>
                </button>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<button class="pagination-btn" onclick="goToPage(1)">1</button>';
                    if ($start_page > 2) echo '<span class="px-2">...</span>';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active = $i == $page ? 'active' : '';
                    echo "<button class=\"pagination-btn $active\" onclick=\"goToPage($i)\">$i</button>";
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span class="px-2">...</span>';
                    echo "<button class=\"pagination-btn\" onclick=\"goToPage($total_pages)\">$total_pages</button>";
                }
                ?>
                
                <button class="pagination-btn" 
                        onclick="goToPage(<?php echo $page + 1; ?>)" 
                        <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                    <i class="fas fa-angle-right"></i>
                </button>
                
                <button class="pagination-btn" 
                        onclick="goToPage(<?php echo $total_pages; ?>)" 
                        <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                    <i class="fas fa-angle-double-right"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal for Viewing Complaint -->
    <div class="modal" id="viewModal" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="modal-content" style="max-width: 800px; max-height: 90vh;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 class="modal-title">Complaint Details</h3>
                <button type="button" class="modal-close" onclick="document.getElementById('viewModal').classList.remove('active')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="viewContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <!-- Modal for Editing Complaint -->
    <div class="modal" id="editModal" onclick="if(event.target===this)this.classList.remove('active')">
        <div class="modal-content" style="max-width: 800px; max-height: 90vh;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h3 class="modal-title">Edit Complaint</h3>
                <button type="button" class="modal-close" onclick="document.getElementById('editModal').classList.remove('active')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="editContent">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>

    <script src="<?php echo APP_URL; ?>/assets/js/media-carousel.js"></script>

    <script>
        // Dropdown Toggle
        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            dropdown.classList.toggle('show');
            
            // Close other dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu.id !== dropdownId) {
                    menu.classList.remove('show');
                }
            });
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
        
        // Clear filters
        function clearFilters() {
            window.location.href = 'my-complaints.php';
        }
        
        // Go to page
        function goToPage(pageNum) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', pageNum);
            window.location.href = url.toString();
        }
        
        // Export complaints
        function exportComplaints(format) {
            const url = new URL('<?php echo APP_URL; ?>/api/export_complaints.php', window.location.origin);
            url.searchParams.set('format', format);
            url.searchParams.set('student_id', '<?php echo $student_id; ?>');
            
            // Add current filters
            const params = new URLSearchParams(window.location.search);
            params.forEach((value, key) => {
                if (key !== 'page' && key !== 'limit') {
                    url.searchParams.set(key, value);
                }
            });
            
            window.open(url.toString(), '_blank');
        }
        

        function escapeAttr(s) {
            return String(s || '').replace(/"/g, '&quot;');
        }

        function buildMediaCarouselHtml(images) {
            if (!Array.isArray(images) || images.length === 0) return '';

            const itemsHtml = images.map(img => `
                <div class="media-item">
                    <img src="${img.url}" alt="${escapeAttr(img.alt)}" loading="lazy" data-attachment-idx="${img.idx}" class="attachment-thumb">
                </div>`).join('');

            if (images.length === 1) {
                return `
                    <div class="media-carousel" data-media-carousel>
                        <div class="media-track">${itemsHtml}
                        </div>
                    </div>`;
            }

            const dotsHtml = images.map((_, i) => `
                <button class="media-dot${i === 0 ? ' active' : ''}" type="button" aria-label="Go to slide ${i + 1}"></button>`).join('');

            return `
                <div class="media-carousel" data-media-carousel>
                    <div class="media-track">${itemsHtml}
                    </div>
                    <button class="media-nav prev" type="button" aria-label="Previous">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="media-nav next" type="button" aria-label="Next">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <div class="media-dots">${dotsHtml}
                    </div>
                </div>`;
        }

        async function loadAttachmentsCarousel(complaintId, sectionId, mountId) {
            const section = document.getElementById(sectionId);
            const mount = document.getElementById(mountId);
            if (!section || !mount) return;

            section.style.display = 'none';
            mount.innerHTML = '';

            try {
                const resp = await fetch(`<?php echo APP_URL; ?>/api/get_attachments.php?complaint_id=${complaintId}`);
                const data = await resp.json();

                if (!data.success || !Array.isArray(data.attachments)) return;

                const images = data.attachments
                    .map((a, idx) => ({ a, idx }))
                    .filter(x => x.a && x.a.is_image && x.a.url)
                    .map(x => ({ url: x.a.url, alt: x.a.original_name || 'Attachment', idx: x.idx }));

                if (!images.length) return;

                mount.innerHTML = buildMediaCarouselHtml(images);

                // Clicking an attachment opens a dedicated download page
                mount.querySelectorAll('img[data-attachment-idx]').forEach(img => {
                    img.style.cursor = 'pointer';
                    img.addEventListener('click', () => {
                        const idx = img.dataset.attachmentIdx;
                        if (idx === undefined || idx === null || idx === '') return;
                        const url = `<?php echo APP_URL; ?>/pages/student/attachment.php?complaint_id=${encodeURIComponent(complaintId)}&idx=${encodeURIComponent(idx)}`;
                        window.location.href = url;
                    });
                });
                section.style.display = '';

                try {
                    if (window.initMediaCarousels) window.initMediaCarousels(section);
                } catch (e) {}
            } catch (e) {
                console.error('Error loading attachments:', e);
            }
        }

        // View complaint details
        function viewComplaint(complaintId) {
            fetch(`<?php echo APP_URL; ?>/api/get_student_complaint.php?id=${complaintId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const complaint = data.complaint;
                        
                        const html = `
                            <div class="complaint-details">
                                <div class="mb-4">
                                    <span class="text-sm font-medium px-3 py-1 rounded-full" style="background: ${complaint.category_color}20; color: ${complaint.category_color}; border: 1px solid ${complaint.category_color}40;">
                                        ${complaint.category_name}
                                    </span>
                                    <span class="ml-2 text-sm font-medium px-3 py-1 rounded-full ${complaint.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : complaint.status === 'published' ? 'bg-green-100 text-green-800' : complaint.status === 'resolved' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800'}">
                                        ${complaint.status.replace('_', ' ').toUpperCase()}
                                    </span>
                                </div>
                                
                                <h3 class="text-xl font-bold mb-2">${complaint.title}</h3>
                                <div class="text-sm text-gray-500 mb-4">
                                    <span class="mr-4"><i class="fas fa-code"></i> #${complaint.complaint_code}</span>
                                    <span class="mr-4"><i class="fas fa-calendar"></i> ${new Date(complaint.created_at).toLocaleDateString()}</span>
                                    <span><i class="fas fa-map-marker-alt"></i> ${complaint.location || 'Not specified'}</span>
                                </div>
                                
                                <div class="mb-6">
                                    <h4 class="font-semibold mb-2">Description:</h4>
                                    <div class="bg-gray-50 p-4 rounded-lg">
                                        ${complaint.description}
                                    </div>

                                <div class="mb-6" id="viewAttachmentsSection" style="display:none;">
                                    <h4 class="font-semibold mb-2"><i class="fas fa-images"></i> Attachments</h4>
                                    <div id="viewAttachmentsCarousel"></div>
                                </div>
                                
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 mb-6">
                                    <div>
                                        <h4 class="font-semibold mb-2">Urgency:</h4>
                                        <span class="px-3 py-1 rounded-full ${complaint.urgency === 'critical' ? 'bg-red-100 text-red-800' : complaint.urgency === 'high' ? 'bg-orange-100 text-orange-800' : complaint.urgency === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'}">
                                            ${complaint.urgency.toUpperCase()}
                                        </span>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold mb-2">Visibility:</h4>
                                        <span class="px-3 py-1 rounded-full ${complaint.is_anonymous ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}">
                                            ${complaint.is_anonymous ? 'Anonymous' : 'Visible to Admins'}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mb-6">
                                    <h4 class="font-semibold mb-2">Engagement:</h4>
                                    <div class="flex gap-6">
                                        <div class="text-center">
                                            <div class="text-2xl font-bold ${complaint.upvotes > complaint.downvotes ? 'text-green-600' : complaint.upvotes < complaint.downvotes ? 'text-red-600' : 'text-gray-600'}">
                                                ${complaint.upvotes - complaint.downvotes}
                                            </div>
                                            <div class="text-sm text-gray-500">Net Votes</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-2xl font-bold">${complaint.view_count}</div>
                                            <div class="text-sm text-gray-500">Views</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="text-2xl font-bold">${complaint.comment_count || 0}</div>
                                            <div class="text-sm text-gray-500">Comments</div>
                                        </div>
                                    </div>
                                </div>
                                
                                ${complaint.resolved_at ? `
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                                    <h4 class="font-semibold text-green-800 mb-1"><i class="fas fa-check-circle"></i> Resolved</h4>
                                    <p class="text-green-700">This complaint was resolved on ${new Date(complaint.resolved_at).toLocaleDateString()}</p>
                                    ${complaint.resolution_notes ? `
                                    <div class="mt-2">
                                        <h5 class="font-semibold text-green-800">Resolution Notes:</h5>
                                        <p class="text-green-700">${complaint.resolution_notes}</p>
                                    </div>
                                    ` : ''}
                                </div>
                                ` : ''}
                                
                                ${complaint.rejection_reason ? `
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                                    <h4 class="font-semibold text-red-800 mb-1"><i class="fas fa-times-circle"></i> Rejected</h4>
                                    <p class="text-red-700"><strong>Reason:</strong> ${complaint.rejection_reason}</p>
                                    <p class="text-red-700 text-sm mt-1">Rejected by Admin #${complaint.rejected_by}</p>
                                </div>
                                ` : ''}
                                
                                <div class="border-t pt-4 mt-4">
                                    <h4 class="font-semibold mb-2">Timeline:</h4>
                                    <div class="space-y-2">
                                        <div class="flex items-center">
                                            <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                                            <div>
                                                <div class="font-medium">Submitted</div>
                                                <div class="text-sm text-gray-500">${new Date(complaint.created_at).toLocaleString()}</div>
                                            </div>
                                        </div>
                                        ${complaint.last_status_change ? `
                                        <div class="flex items-center">
                                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                            <div>
                                                <div class="font-medium">Status Updated</div>
                                                <div class="text-sm text-gray-500">${new Date(complaint.last_status_change).toLocaleString()}</div>
                                            </div>
                                        </div>
                                        ` : ''}
                                        ${complaint.resolved_at ? `
                                        <div class="flex items-center">
                                            <div class="w-3 h-3 bg-purple-500 rounded-full mr-3"></div>
                                            <div>
                                                <div class="font-medium">Resolved</div>
                                                <div class="text-sm text-gray-500">${new Date(complaint.resolved_at).toLocaleString()}</div>
                                            </div>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        `;
                        
                        document.getElementById('viewContent').innerHTML = html;
                        document.getElementById('viewModal').classList.add('active');
                        loadAttachmentsCarousel(complaintId, 'viewAttachmentsSection', 'viewAttachmentsCarousel');
                    } else {
                        showNotification(data.message || 'Failed to load complaint', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to load complaint details', 'error');
                });
        }
        
        // Edit complaint
        function editComplaint(complaintId) {
            fetch(`<?php echo APP_URL; ?>/api/get_complaint_edit.php?id=${complaintId}&student_id=<?php echo $student_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Load edit form via AJAX
                        document.getElementById('editContent').innerHTML = data.html;
                        document.getElementById('editModal').classList.add('active');
                    } else {
                        showNotification(data.message || 'Cannot edit this complaint', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to load edit form', 'error');
                });
        }
        

        // Submit complaint edits
        async function submitComplaintEdit(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);

            try {
                const response = await fetch(`<?php echo APP_URL; ?>/api/update_complaint.php`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message || 'Complaint updated successfully', 'success');
                    closeModal('editModal');
                    setTimeout(() => location.reload(), 900);
                } else {
                    showNotification(data.message || 'Failed to update complaint', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Network error', 'error');
            }

            return false;
        }

        // Delete complaint
        function deleteComplaint(complaintId) {
            if (confirm('Are you sure you want to delete this complaint? This action cannot be undone.')) {
                fetch(`<?php echo APP_URL; ?>/api/delete_complaint.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        complaint_id: complaintId,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Complaint deleted successfully', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showNotification(data.message || 'Failed to delete complaint', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to delete complaint', 'error');
                });
            }
        }
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Preview image
        function previewImage(imageUrl) {
            const img = new Image();
            img.src = imageUrl;
            const width = img.width;
            const height = img.height;
            
            const w = window.open('', 'Image Preview', `width=${Math.min(width + 50, 800)},height=${Math.min(height + 100, 600)}`);
            w.document.write(`
                <html>
                <head>
                    <title>Image Preview</title>
                    <style>
                        body { margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f5f5f5; }
                        img { max-width: 100%; max-height: 90vh; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                    </style>
                </head>
                <body>
                    <img src="${imageUrl}" alt="Preview">
                </body>
                </html>
            `);
        }
        
        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `toast toast-${type}`;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>
