<?php
require_once '../../config/constants.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../includes/utilities/helpers.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

requirePermission('view_reports');

$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'];

// Default date range (last 30 days)
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');
$category_id = isset($_GET['category']) ? intval($_GET['category']) : null;
$format = isset($_GET['format']) ? sanitizeInput($_GET['format']) : 'html';

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

// Build where clause for reports
$where = ["c.created_at BETWEEN ? AND ?"];
$params = ["$start_date 00:00:00", "$end_date 23:59:59"];

if ($category_id) {
    $where[] = "c.category_id = ?";
    $params[] = $category_id;
}

if ($admin_role !== 'super_admin') {
    $where[] = "cat.admin_id = ?";
    $params[] = $admin_id;
}

$where_clause = "WHERE " . implode(" AND ", $where);

// Get report data
$report_data = [];

try {
    // Overall statistics
    $query = "
        SELECT 
            COUNT(*) as total_complaints,
            SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN c.status = 'under_review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN c.status = 'published' THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN c.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            AVG(CASE WHEN c.status = 'resolved' THEN DATEDIFF(c.resolved_at, c.created_at) ELSE NULL END) as avg_resolution_days,
            SUM(c.upvotes) as total_upvotes,
            SUM(c.downvotes) as total_downvotes,
            SUM(c.view_count) as total_views
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        $where_clause
    ";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $report_data['overall'] = $stmt->fetch();
    
    // Category breakdown
    $query = "
        SELECT 
            cat.name,
            cat.color,
            COUNT(c.id) as complaint_count,
            SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
            AVG(CASE WHEN c.status = 'resolved' THEN DATEDIFF(c.resolved_at, c.created_at) ELSE NULL END) as avg_resolution_days,
            ROUND(SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) / COUNT(c.id) * 100, 1) as resolution_rate
        FROM categories cat
        LEFT JOIN complaints c ON cat.id = c.category_id AND c.created_at BETWEEN ? AND ?
        " . ($admin_role !== 'super_admin' ? "WHERE cat.admin_id = ?" : "") . "
        GROUP BY cat.id, cat.name, cat.color
        ORDER BY complaint_count DESC
    ";
    
    $category_params = ["$start_date 00:00:00", "$end_date 23:59:59"];
    if ($admin_role !== 'super_admin') {
        $category_params[] = $admin_id;
    }
    
    $stmt = db()->prepare($query);
    $stmt->execute($category_params);
    $report_data['categories'] = $stmt->fetchAll();
    
    // Daily trend
    $query = "
        SELECT 
            DATE(c.created_at) as date,
            COUNT(*) as complaint_count,
            SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        $where_clause
        GROUP BY DATE(c.created_at)
        ORDER BY date
    ";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $report_data['daily_trend'] = $stmt->fetchAll();
    
    // Urgency breakdown
    $query = "
        SELECT 
            c.urgency,
            COUNT(*) as complaint_count,
            SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
            AVG(CASE WHEN c.status = 'resolved' THEN DATEDIFF(c.resolved_at, c.created_at) ELSE NULL END) as avg_resolution_days
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        $where_clause
        GROUP BY c.urgency
        ORDER BY 
            CASE c.urgency 
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END
    ";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $report_data['urgency'] = $stmt->fetchAll();
    
    // Top complaints (by votes)
    $query = "
        SELECT 
            c.complaint_code,
            c.title,
            c.urgency,
            c.status,
            c.upvotes,
            c.downvotes,
            c.view_count,
            cat.name as category_name,
            DATEDIFF(NOW(), c.created_at) as days_old
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        $where_clause
        ORDER BY (c.upvotes - c.downvotes) DESC
        LIMIT 10
    ";
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $report_data['top_complaints'] = $stmt->fetchAll();
    
    // Admin performance (super admin only)
    if ($admin_role === 'super_admin') {
        $query = "
            SELECT 
                a.full_name,
                a.username,
                COUNT(DISTINCT c.id) as total_complaints,
                SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
                AVG(CASE WHEN c.status = 'resolved' THEN DATEDIFF(c.resolved_at, c.created_at) ELSE NULL END) as avg_resolution_days,
                ROUND(SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) / COUNT(DISTINCT c.id) * 100, 1) as resolution_rate
            FROM admins a
            LEFT JOIN categories cat ON a.id = cat.admin_id
            LEFT JOIN complaints c ON cat.id = c.category_id AND c.created_at BETWEEN ? AND ?
            WHERE a.role = 'category_admin'
            GROUP BY a.id, a.full_name, a.username
            ORDER BY total_complaints DESC
        ";
        
        $stmt = db()->prepare($query);
        $stmt->execute(["$start_date 00:00:00", "$end_date 23:59:59"]);
        $report_data['admin_performance'] = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    error_log("Error generating report: " . $e->getMessage());
    $_SESSION['error'] = 'Failed to generate report';
}

// Export to PDF or Excel
if ($format === 'pdf' || $format === 'excel') {
    require_once '../../vendor/autoload.php';
    
    if ($format === 'pdf') {
        exportToPDF($report_data, $start_date, $end_date);
    } else {
        exportToExcel($report_data, $start_date, $end_date);
    }
    exit();
}

function exportToPDF($data, $start_date, $end_date) {
    $mpdf = new \Mpdf\Mpdf();
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            h1 { color: #333; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f4f4f4; }
            .summary { background: #f9f9f9; padding: 15px; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <h1>HTU Complaint System Report</h1>
        <div class="summary">
            <strong>Report Period:</strong> ' . $start_date . ' to ' . $end_date . '<br>
            <strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '
        </div>
    ';
    
    // Overall statistics
    $html .= '<h2>Overall Statistics</h2>';
    $html .= '<table>';
    $html .= '<tr><th>Total Complaints</th><td>' . $data['overall']['total_complaints'] . '</td></tr>';
    $html .= '<tr><th>Pending</th><td>' . $data['overall']['pending'] . '</td></tr>';
    $html .= '<tr><th>Published</th><td>' . $data['overall']['published'] . '</td></tr>';
    $html .= '<tr><th>Resolved</th><td>' . $data['overall']['resolved'] . '</td></tr>';
    $html .= '<tr><th>Rejected</th><td>' . $data['overall']['rejected'] . '</td></tr>';
    $html .= '<tr><th>Avg Resolution Time</th><td>' . round($data['overall']['avg_resolution_days'] ?? 0, 1) . ' days</td></tr>';
    $html .= '</table>';
    
    // Category breakdown
    if (!empty($data['categories'])) {
        $html .= '<h2>Category Breakdown</h2>';
        $html .= '<table>';
        $html .= '<tr><th>Category</th><th>Complaints</th><th>Resolved</th><th>Resolution Rate</th><th>Avg Days</th></tr>';
        foreach ($data['categories'] as $category) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($category['name']) . '</td>';
            $html .= '<td>' . $category['complaint_count'] . '</td>';
            $html .= '<td>' . $category['resolved_count'] . '</td>';
            $html .= '<td>' . $category['resolution_rate'] . '%</td>';
            $html .= '<td>' . round($category['avg_resolution_days'] ?? 0, 1) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
    }
    
    $html .= '</body></html>';
    
    $mpdf->WriteHTML($html);
    $mpdf->Output('HTU_Complaint_Report_' . date('Y-m-d') . '.pdf', 'D');
}

function exportToExcel($data, $start_date, $end_date) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Set headers
    $sheet->setCellValue('A1', 'HTU Complaint System Report');
    $sheet->setCellValue('A2', 'Report Period: ' . $start_date . ' to ' . $end_date);
    $sheet->setCellValue('A3', 'Generated: ' . date('Y-m-d H:i:s'));
    
    // Overall statistics
    $sheet->setCellValue('A5', 'Overall Statistics');
    $sheet->setCellValue('A6', 'Total Complaints');
    $sheet->setCellValue('B6', $data['overall']['total_complaints']);
    $sheet->setCellValue('A7', 'Pending');
    $sheet->setCellValue('B7', $data['overall']['pending']);
    $sheet->setCellValue('A8', 'Published');
    $sheet->setCellValue('B8', $data['overall']['published']);
    $sheet->setCellValue('A9', 'Resolved');
    $sheet->setCellValue('B9', $data['overall']['resolved']);
    $sheet->setCellValue('A10', 'Rejected');
    $sheet->setCellValue('B10', $data['overall']['rejected']);
    $sheet->setCellValue('A11', 'Avg Resolution Time (days)');
    $sheet->setCellValue('B11', round($data['overall']['avg_resolution_days'] ?? 0, 1));
    
    // Category breakdown
    $row = 13;
    $sheet->setCellValue('A' . $row, 'Category Breakdown');
    $row++;
    $sheet->setCellValue('A' . $row, 'Category');
    $sheet->setCellValue('B' . $row, 'Complaints');
    $sheet->setCellValue('C' . $row, 'Resolved');
    $sheet->setCellValue('D' . $row, 'Resolution Rate');
    $sheet->setCellValue('E' . $row, 'Avg Days');
    $row++;
    
    foreach ($data['categories'] as $category) {
        $sheet->setCellValue('A' . $row, $category['name']);
        $sheet->setCellValue('B' . $row, $category['complaint_count']);
        $sheet->setCellValue('C' . $row, $category['resolved_count']);
        $sheet->setCellValue('D' . $row, $category['resolution_rate']);
        $sheet->setCellValue('E' . $row, round($category['avg_resolution_days'] ?? 0, 1));
        $row++;
    }
    
    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(30);
    $sheet->getColumnDimension('B')->setWidth(15);
    $sheet->getColumnDimension('C')->setWidth(15);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    
    // Save file
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="HTU_Complaint_Report_' . date('Y-m-d') . '.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .reports-container {
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

        .export-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.total {
            border-left: 4px solid #667eea;
        }

        .stat-card.pending {
            border-left: 4px solid #ed8936;
        }

        .stat-card.published {
            border-left: 4px solid #48bb78;
        }

        .stat-card.resolved {
            border-left: 4px solid #4299e1;
        }

        .stat-card.rejected {
            border-left: 4px solid #f56565;
        }

        .stat-card.views {
            border-left: 4px solid #9f7aea;
        }

        .stat-card.resolution {
            border-left: 4px solid #4fd1c7;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
            color: white;
        }

        .stat-icon.total { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.pending { background: linear-gradient(135deg, #ed8936, #f56565); }
        .stat-icon.published { background: linear-gradient(135deg, #48bb78, #38a169); }
        .stat-icon.resolved { background: linear-gradient(135deg, #4299e1, #3182ce); }
        .stat-icon.rejected { background: linear-gradient(135deg, #f56565, #ed64a6); }
        .stat-icon.views { background: linear-gradient(135deg, #9f7aea, #805ad5); }
        .stat-icon.resolution { background: linear-gradient(135deg, #4fd1c7, #38b2ac); }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .chart-card {
            padding: 1.5rem;
            min-height: 400px;
        }

        .chart-header {
            margin-bottom: 1.5rem;
        }

        .chart-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .chart-header p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .table-container {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--glass-border);
            margin-bottom: 3rem;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
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
        }

        .table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .progress-bar {
            height: 8px;
            background: var(--bg-tertiary);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
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

        .trend-up {
            color: #48bb78;
        }

        .trend-down {
            color: #f56565;
        }

        .trend-neutral {
            color: #a0aec0;
        }

        @media (max-width: 1024px) {
            .reports-container {
                padding: 1rem;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .chart-card {
                min-height: 300px;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .export-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Navigation -->
    <?php include '../../includes/layout/admin-nav.php'; ?>

    <!-- Main Content -->
    <div class="reports-container">
        <!-- Header -->
        <div class="page-header">
            <h1>System Reports</h1>
            <p>Analyze complaint trends and performance metrics</p>
        </div>

        <!-- Filters -->
        <div class="glass-card filters-card">
            <form method="GET" action="" id="reportForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="filter-label">Start Date</label>
                        <input type="date" name="start_date" class="filter-input" 
                               value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">End Date</label>
                        <input type="date" name="end_date" class="filter-input" 
                               value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Category</label>
                        <select name="category" class="filter-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="export-buttons">
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-chart-bar"></i> Generate Report
                    </button>
                    
                    <button type="button" class="btn btn-outline" onclick="exportReport('pdf')">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                    
                    <button type="button" class="btn btn-outline" onclick="exportReport('excel')">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                    
                    <button type="button" class="btn btn-outline" onclick="printReport()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </form>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="glass-card stat-card total">
                <div class="stat-icon total">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="stat-number"><?php echo $report_data['overall']['total_complaints'] ?? 0; ?></div>
                <div class="stat-label">Total Complaints</div>
            </div>
            
            <div class="glass-card stat-card pending">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $report_data['overall']['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            
            <div class="glass-card stat-card published">
                <div class="stat-icon published">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $report_data['overall']['published'] ?? 0; ?></div>
                <div class="stat-label">Published</div>
            </div>
            
            <div class="glass-card stat-card resolved">
                <div class="stat-icon resolved">
                    <i class="fas fa-flag-checkered"></i>
                </div>
                <div class="stat-number"><?php echo $report_data['overall']['resolved'] ?? 0; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            
            <div class="glass-card stat-card rejected">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-number"><?php echo $report_data['overall']['rejected'] ?? 0; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
            
            <div class="glass-card stat-card views">
                <div class="stat-icon views">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-number"><?php echo number_format($report_data['overall']['total_views'] ?? 0); ?></div>
                <div class="stat-label">Total Views</div>
            </div>
            
            <div class="glass-card stat-card resolution">
                <div class="stat-icon resolution">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <div class="stat-number"><?php echo round($report_data['overall']['avg_resolution_days'] ?? 0, 1); ?>d</div>
                <div class="stat-label">Avg Resolution</div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="charts-grid">
            <!-- Category Distribution -->
            <div class="glass-card chart-card">
                <div class="chart-header">
                    <h3>Category Distribution</h3>
                    <p>Complaints breakdown by category</p>
                </div>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                                    </div>
            </div>
            
            <!-- Daily Trend -->
            <div class="glass-card chart-card">
                <div class="chart-header">
                    <h3>Daily Complaint Trend</h3>
                    <p>Complaints and resolutions over time</p>
                </div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            
            <!-- Urgency Distribution -->
            <div class="glass-card chart-card">
                <div class="chart-header">
                    <h3>Urgency Distribution</h3>
                    <p>Breakdown by urgency level</p>
                </div>
                <div class="chart-container">
                    <canvas id="urgencyChart"></canvas>
                </div>
            </div>
            
            <!-- Resolution Rate -->
            <div class="glass-card chart-card">
                <div class="chart-header">
                    <h3>Category Resolution Rate</h3>
                    <p>Resolution percentage by category</p>
                </div>
                <div class="chart-container">
                    <canvas id="resolutionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Category Breakdown Table -->
        <div class="table-container">
            <div class="table-header">
                <h3 class="table-title">Category Performance</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Complaints</th>
                            <th>Resolved</th>
                            <th>Resolution Rate</th>
                            <th>Avg Days to Resolve</th>
                            <th>Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($report_data['categories'])): ?>
                            <?php foreach ($report_data['categories'] as $cat): ?>
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div style="width: 12px; height: 12px; border-radius: 50%; background: <?php echo $cat['color']; ?>;"></div>
                                            <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo $cat['complaint_count']; ?></td>
                                    <td><?php echo $cat['resolved_count']; ?></td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo min($cat['resolution_rate'], 100); ?>%; background: <?php echo $cat['color']; ?>;"></div>
                                            </div>
                                            <span><?php echo $cat['resolution_rate']; ?>%</span>
                                        </div>
                                    </td>
                                    <td><?php echo round($cat['avg_resolution_days'] ?? 0, 1); ?> days</td>
                                    <td>
                                        <?php
                                        $resolution_rate = $cat['resolution_rate'];
                                        if ($resolution_rate >= 80) {
                                            echo '<span class="trend-up"><i class="fas fa-arrow-up"></i> Excellent</span>';
                                        } elseif ($resolution_rate >= 60) {
                                            echo '<span class="trend-up"><i class="fas fa-arrow-up"></i> Good</span>';
                                        } elseif ($resolution_rate >= 40) {
                                            echo '<span class="trend-neutral"><i class="fas fa-minus"></i> Average</span>';
                                        } else {
                                            echo '<span class="trend-down"><i class="fas fa-arrow-down"></i> Needs Improvement</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-8 text-gray-500">
                                    No category data available for the selected period
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Complaints -->
        <div class="table-container">
            <div class="table-header">
                <h3 class="table-title">Top Complaints by Engagement</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Complaint</th>
                            <th>Category</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Votes</th>
                            <th>Views</th>
                            <th>Age</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($report_data['top_complaints'])): ?>
                            <?php foreach ($report_data['top_complaints'] as $complaint): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($complaint['title']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo $complaint['complaint_code']; ?></div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($complaint['category_name']); ?></td>
                                    <td>
                                        <span class="urgency-badge urgency-<?php echo strtolower($complaint['urgency']); ?>">
                                            <?php echo $complaint['urgency']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo getStatusBadge($complaint['status']); ?>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span class="text-green-500"><i class="fas fa-thumbs-up"></i> <?php echo $complaint['upvotes']; ?></span>
                                            <span class="text-red-500"><i class="fas fa-thumbs-down"></i> <?php echo $complaint['downvotes']; ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo number_format($complaint['view_count']); ?></td>
                                    <td><?php echo $complaint['days_old']; ?> days</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-8 text-gray-500">
                                    No top complaints data available
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($admin_role === 'super_admin' && !empty($report_data['admin_performance'])): ?>
        <!-- Admin Performance -->
        <div class="table-container">
            <div class="table-header">
                <h3 class="table-title">Admin Performance</h3>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Username</th>
                            <th>Total Complaints</th>
                            <th>Resolved</th>
                            <th>Resolution Rate</th>
                            <th>Avg Resolution Days</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data['admin_performance'] as $admin): ?>
                            <tr>
                                <td>
                                    <div class="font-medium"><?php echo htmlspecialchars($admin['full_name']); ?></div>
                                </td>
                                <td>@<?php echo htmlspecialchars($admin['username']); ?></td>
                                <td><?php echo $admin['total_complaints']; ?></td>
                                <td><?php echo $admin['resolved_count']; ?></td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo min($admin['resolution_rate'], 100); ?>%; background: linear-gradient(135deg, #667eea, #764ba2);"></div>
                                        </div>
                                        <span><?php echo $admin['resolution_rate']; ?>%</span>
                                    </div>
                                </td>
                                <td><?php echo round($admin['avg_resolution_days'] ?? 0, 1); ?> days</td>
                                <td>
                                    <?php
                                    $performance = $admin['resolution_rate'];
                                    if ($performance >= 80) {
                                        echo '<span class="trend-up"><i class="fas fa-star"></i> Excellent</span>';
                                    } elseif ($performance >= 60) {
                                        echo '<span class="trend-up"><i class="fas fa-check-circle"></i> Good</span>';
                                    } elseif ($performance >= 40) {
                                        echo '<span class="trend-neutral"><i class="fas fa-circle"></i> Average</span>';
                                    } else {
                                        echo '<span class="trend-down"><i class="fas fa-exclamation-circle"></i> Needs Review</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script>
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Category Distribution Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            <?php if (!empty($report_data['categories'])): ?>
            const categoryLabels = <?php echo json_encode(array_column($report_data['categories'], 'name')); ?>;
            const categoryData = <?php echo json_encode(array_column($report_data['categories'], 'complaint_count')); ?>;
            const categoryColors = <?php echo json_encode(array_column($report_data['categories'], 'color')); ?>;
            
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryData,
                        backgroundColor: categoryColors,
                        borderWidth: 2,
                        borderColor: 'var(--bg-primary)',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                color: 'var(--text-primary)',
                                padding: 20,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
            
            // Daily Trend Chart
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            <?php if (!empty($report_data['daily_trend'])): ?>
            const trendDates = <?php echo json_encode(array_column($report_data['daily_trend'], 'date')); ?>;
            const complaintCounts = <?php echo json_encode(array_column($report_data['daily_trend'], 'complaint_count')); ?>;
            const resolvedCounts = <?php echo json_encode(array_column($report_data['daily_trend'], 'resolved_count')); ?>;
            
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendDates,
                    datasets: [
                        {
                            label: 'Complaints',
                            data: complaintCounts,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Resolved',
                            data: resolvedCounts,
                            borderColor: '#48bb78',
                            backgroundColor: 'rgba(72, 187, 120, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: 'var(--text-primary)'
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: 'var(--text-secondary)'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: 'var(--text-secondary)'
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
            
            // Urgency Chart
            const urgencyCtx = document.getElementById('urgencyChart').getContext('2d');
            <?php if (!empty($report_data['urgency'])): ?>
            const urgencyLabels = <?php echo json_encode(array_column($report_data['urgency'], 'urgency')); ?>;
            const urgencyData = <?php echo json_encode(array_column($report_data['urgency'], 'complaint_count')); ?>;
            const urgencyColors = {
                'critical': '#f56565',
                'high': '#ed8936',
                'medium': '#ecc94b',
                'low': '#48bb78'
            };
            
            new Chart(urgencyCtx, {
                type: 'bar',
                data: {
                    labels: urgencyLabels,
                    datasets: [{
                        label: 'Complaints by Urgency',
                        data: urgencyData,
                        backgroundColor: urgencyLabels.map(label => urgencyColors[label]),
                        borderRadius: 8,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: 'var(--text-primary)',
                                font: {
                                    weight: 'bold'
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: 'var(--text-secondary)'
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
            
            // Resolution Rate Chart
            const resolutionCtx = document.getElementById('resolutionChart').getContext('2d');
            <?php if (!empty($report_data['categories'])): ?>
            const resolutionLabels = <?php echo json_encode(array_column($report_data['categories'], 'name')); ?>;
            const resolutionRates = <?php echo json_encode(array_column($report_data['categories'], 'resolution_rate')); ?>;
            const resolutionCatColors = <?php echo json_encode(array_column($report_data['categories'], 'color')); ?>;
            
            new Chart(resolutionCtx, {
                type: 'horizontalBar',
                data: {
                    labels: resolutionLabels,
                    datasets: [{
                        label: 'Resolution Rate %',
                        data: resolutionRates,
                        backgroundColor: resolutionCatColors,
                        borderRadius: 8,
                        borderWidth: 0
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: 100,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: 'var(--text-secondary)',
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: 'var(--text-primary)'
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
        
        function exportReport(format) {
            const form = document.getElementById('reportForm');
            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'format';
            formatInput.value = format;
            form.appendChild(formatInput);
            form.submit();
            form.removeChild(formatInput);
        }
        
        function printReport() {
            window.print();
        }
    </script>
</body>
</html>
