<?php
require_once '../../config/constants.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../includes/utilities/helpers.php';

// Check if user is admin
if (!isAdmin()) {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'];

// Get dashboard statistics
$stats = [];
try {
    // Total complaints
    if ($admin_role === 'super_admin') {
        $stmt = db()->prepare("SELECT COUNT(*) as total FROM complaints");
        $stmt->execute();
    } else {
        $stmt = db()->prepare("
            SELECT COUNT(*) as total 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE cat.admin_id = ?
        ");
        $stmt->execute([$admin_id]);
    }
    $stats['total'] = $stmt->fetch()['total'];

    // Pending complaints
    if ($admin_role === 'super_admin') {
        $stmt = db()->prepare("SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'");
        $stmt->execute();
    } else {
        $stmt = db()->prepare("
            SELECT COUNT(*) as pending 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'pending' AND cat.admin_id = ?
        ");
        $stmt->execute([$admin_id]);
    }
    $stats['pending'] = $stmt->fetch()['pending'];

    // Under review complaints
    if ($admin_role === 'super_admin') {
        $stmt = db()->prepare("SELECT COUNT(*) as under_review FROM complaints WHERE status = 'under_review'");
        $stmt->execute();
    } else {
        $stmt = db()->prepare("
            SELECT COUNT(*) as under_review 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'under_review' AND cat.admin_id = ?
        ");
        $stmt->execute([$admin_id]);
    }
    $stats['under_review'] = $stmt->fetch()['under_review'];

    // Published complaints
    if ($admin_role === 'super_admin') {
        $stmt = db()->prepare("SELECT COUNT(*) as published FROM complaints WHERE status = 'published'");
        $stmt->execute();
    } else {
        $stmt = db()->prepare("
            SELECT COUNT(*) as published 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'published' AND cat.admin_id = ?
        ");
        $stmt->execute([$admin_id]);
    }
    $stats['published'] = $stmt->fetch()['published'];

    // Resolved complaints
    if ($admin_role === 'super_admin') {
        $stmt = db()->prepare("SELECT COUNT(*) as resolved FROM complaints WHERE status = 'resolved'");
        $stmt->execute();
    } else {
        $stmt = db()->prepare("
            SELECT COUNT(*) as resolved 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'resolved' AND cat.admin_id = ?
        ");
        $stmt->execute([$admin_id]);
    }
    $stats['resolved'] = $stmt->fetch()['resolved'];

    // Rejected complaints
    if ($admin_role === 'super_admin') {
        $stmt = db()->prepare("SELECT COUNT(*) as rejected FROM complaints WHERE status = 'rejected'");
        $stmt->execute();
    } else {
        $stmt = db()->prepare("
            SELECT COUNT(*) as rejected 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'rejected' AND cat.admin_id = ?
        ");
        $stmt->execute([$admin_id]);
    }
    $stats['rejected'] = $stmt->fetch()['rejected'];

    // Total users
    if ($admin_role === 'super_admin') {
        $stmt = db()->prepare("SELECT COUNT(*) as total_users FROM users WHERE is_verified = 1");
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch()['total_users'];
    }

    // Recent activity
    if ($admin_role === 'super_admin') {
        $stmt = db()->prepare("
            SELECT 
                a.action,
                a.created_at,
                u.full_name as user_name,
                c.complaint_code,
                c.title
            FROM audit_log a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN complaints c ON a.record_id = c.id AND a.table_name = 'complaints'
            WHERE a.action IN ('COMPLAINT_SUBMITTED', 'COMPLAINT_APPROVED', 'COMPLAINT_REJECTED', 'COMPLAINT_RESOLVED')
            ORDER BY a.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
    } else {
        $stmt = db()->prepare("
            SELECT 
                a.action,
                a.created_at,
                u.full_name as user_name,
                c.complaint_code,
                c.title
            FROM audit_log a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN complaints c ON a.record_id = c.id AND a.table_name = 'complaints'
            WHERE a.action IN ('COMPLAINT_SUBMITTED', 'COMPLAINT_APPROVED', 'COMPLAINT_REJECTED', 'COMPLAINT_RESOLVED')
            AND EXISTS (
                SELECT 1 FROM categories cat
                WHERE cat.id = c.category_id AND cat.admin_id = ?
            )
            ORDER BY a.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$admin_id]);
    }
    $stats['recent_activity'] = $stmt->fetchAll();

    // Category distribution
    if ($admin_role === 'super_admin') {
        $stmt = db()->prepare("
            SELECT 
                cat.name,
                cat.color,
                COUNT(c.id) as count
            FROM categories cat
            LEFT JOIN complaints c ON cat.id = c.category_id
            GROUP BY cat.id, cat.name, cat.color
            ORDER BY count DESC
        ");
        $stmt->execute();
    } else {
        $stmt = db()->prepare("
            SELECT 
                cat.name,
                cat.color,
                COUNT(c.id) as count
            FROM categories cat
            LEFT JOIN complaints c ON cat.id = c.category_id
            WHERE cat.admin_id = ?
            GROUP BY cat.id, cat.name, cat.color
            ORDER BY count DESC
        ");
        $stmt->execute([$admin_id]);
    }
    $stats['category_distribution'] = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    
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
        .dashboard-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .dashboard-header {
            margin-bottom: 2rem;
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .dashboard-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
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
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--card-color), transparent);
        }

        .stat-card.total::before { background: linear-gradient(90deg, #667eea, transparent); }
        .stat-card.pending::before { background: linear-gradient(90deg, #ed8936, transparent); }
        .stat-card.review::before { background: linear-gradient(90deg, #ecc94b, transparent); }
        .stat-card.published::before { background: linear-gradient(90deg, #48bb78, transparent); }
        .stat-card.resolved::before { background: linear-gradient(90deg, #4299e1, transparent); }
        .stat-card.rejected::before { background: linear-gradient(90deg, #f56565, transparent); }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: white;
        }

        .stat-icon.total { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-icon.pending { background: linear-gradient(135deg, #ed8936, #f56565); }
        .stat-icon.review { background: linear-gradient(135deg, #ecc94b, #ed8936); }
        .stat-icon.published { background: linear-gradient(135deg, #48bb78, #38a169); }
        .stat-icon.resolved { background: linear-gradient(135deg, #4299e1, #3182ce); }
        .stat-icon.rejected { background: linear-gradient(135deg, #f56565, #ed64a6); }

        .stat-content h3 {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--text-primary), var(--text-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-change {
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.positive {
            color: #48bb78;
        }

        .stat-change.negative {
            color: #f56565;
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

        .recent-activity {
            margin-bottom: 3rem;
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            border-radius: var(--radius-md);
            background: var(--bg-secondary);
            transition: all 0.2s ease;
        }

        .activity-item:hover {
            background: var(--bg-tertiary);
            transform: translateX(5px);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: white;
        }

        .activity-icon.submitted { background: linear-gradient(135deg, #ed8936, #f56565); }
        .activity-icon.approved { background: linear-gradient(135deg, #48bb78, #38a169); }
        .activity-icon.rejected { background: linear-gradient(135deg, #f56565, #ed64a6); }
        .activity-icon.resolved { background: linear-gradient(135deg, #4299e1, #3182ce); }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .activity-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .activity-complaint {
            color: var(--primary-color);
            font-weight: 500;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 3rem;
        }

        .action-card {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: 1px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .action-card:hover {
            transform: translateY(-5px);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.2), rgba(118, 75, 162, 0.2));
            border-color: rgba(102, 126, 234, 0.3);
        }

        .action-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: white;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .action-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .action-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-container {
                padding: 1rem;
            }
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Navigation -->
    <?php include '../../includes/layout/admin-nav.php'; ?>

    <!-- Main Content -->
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
            <p>Welcome back, <?php echo $_SESSION['admin_name']; ?>! Here's what's happening today.</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <!-- Total Complaints -->
            <div class="glass-card stat-card total">
                <div class="stat-icon total">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Complaints</h3>
                    <div class="stat-value animate-count" data-target="<?php echo $stats['total']; ?>">0</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>12% from last month</span>
                    </div>
                </div>
            </div>

            <!-- Pending -->
            <div class="glass-card stat-card pending">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3>Pending Review</h3>
                    <div class="stat-value animate-count" data-target="<?php echo $stats['pending']; ?>">0</div>
                    <div class="stat-change negative">
                        <i class="fas fa-arrow-up"></i>
                        <span>5 new today</span>
                    </div>
                </div>
            </div>

            <!-- Under Review -->
            <div class="glass-card stat-card review">
                <div class="stat-icon review">
                    <i class="fas fa-search"></i>
                </div>
                <div class="stat-content">
                    <h3>Under Review</h3>
                    <div class="stat-value animate-count" data-target="<?php echo $stats['under_review']; ?>">0</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-down"></i>
                        <span>3 resolved today</span>
                    </div>
                </div>
            </div>

            <!-- Published -->
            <div class="glass-card stat-card published">
                <div class="stat-icon published">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Published</h3>
                    <div class="stat-value animate-count" data-target="<?php echo $stats['published']; ?>">0</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>8% from last week</span>
                    </div>
                </div>
            </div>

            <!-- Resolved -->
            <div class="glass-card stat-card resolved">
                <div class="stat-icon resolved">
                    <i class="fas fa-flag-checkered"></i>
                </div>
                <div class="stat-content">
                    <h3>Resolved</h3>
                    <div class="stat-value animate-count" data-target="<?php echo $stats['resolved']; ?>">0</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>15% increase</span>
                    </div>
                </div>
            </div>

            <!-- Rejected -->
            <div class="glass-card stat-card rejected">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>Rejected</h3>
                    <div class="stat-value animate-count" data-target="<?php echo $stats['rejected']; ?>">0</div>
                    <div class="stat-change negative">
                        <i class="fas fa-arrow-down"></i>
                        <span>2% decrease</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="charts-grid">
            <!-- Status Distribution -->
            <div class="glass-card chart-card">
                <div class="chart-header">
                    <h3>Complaint Status Distribution</h3>
                    <p>Overview of complaints by current status</p>
                </div>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

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
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <?php if (hasPermission('view_complaints')): ?>
            <div class="action-card" onclick="window.location.href='<?php echo APP_URL; ?>/pages/admin/complaints.php'">
                <div class="action-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="action-title">Manage Complaints</div>
                <div class="action-description">Review, approve, or reject complaints</div>
            </div>
            <?php endif; ?>

            <?php if ($admin_role === 'super_admin'): ?>
            <div class="action-card" onclick="window.location.href='<?php echo APP_URL; ?>/pages/admin/users.php'">
                <div class="action-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="action-title">Manage Users</div>
                <div class="action-description">Manage students and admin users</div>
            </div>

            <div class="action-card" onclick="window.location.href='<?php echo APP_URL; ?>/pages/admin/categories.php'">
                <div class="action-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="action-title">Manage Categories</div>
                <div class="action-description">Add or edit complaint categories</div>
            </div>
            <?php endif; ?>

            <div class="action-card" onclick="window.location.href='<?php echo APP_URL; ?>/pages/admin/reports.php'">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="action-title">Generate Reports</div>
                <div class="action-description">Create detailed system reports</div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <div class="glass-card">
                <div class="chart-header">
                    <h3>Recent Activity</h3>
                    <p>Latest actions in the system</p>
                </div>
                <div class="activity-list">
                    <?php if (empty($stats['recent_activity'])): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-history text-4xl mb-4"></i>
                            <p>No recent activity</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($stats['recent_activity'] as $activity): ?>
                            <?php 
                            $icon_class = '';
                            $icon = '';
                            switch ($activity['action']) {
                                case 'COMPLAINT_SUBMITTED':
                                    $icon_class = 'submitted';
                                    $icon = 'fa-plus';
                                    $title = 'New complaint submitted';
                                    break;
                                case 'COMPLAINT_APPROVED':
                                    $icon_class = 'approved';
                                    $icon = 'fa-check';
                                    $title = 'Complaint approved';
                                    break;
                                case 'COMPLAINT_REJECTED':
                                    $icon_class = 'rejected';
                                    $icon = 'fa-times';
                                    $title = 'Complaint rejected';
                                    break;
                                case 'COMPLAINT_RESOLVED':
                                    $icon_class = 'resolved';
                                    $icon = 'fa-flag-checkered';
                                    $title = 'Complaint resolved';
                                    break;
                                default:
                                    $icon_class = 'info';
                                    $icon = 'fa-info';
                                    $title = 'System activity';
                            }
                            ?>
                            <div class="activity-item animate-fade-in-up">
                                <div class="activity-icon <?php echo $icon_class; ?>">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo $title; ?></div>
                                    <div class="activity-meta">
                                        <span><i class="far fa-clock"></i> <?php echo timeAgo($activity['created_at']); ?></span>
                                        <?php if (!empty($activity['user_name'])): ?>
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($activity['user_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($activity['complaint_code'])): ?>
                                            <span class="activity-complaint">
                                                <i class="fas fa-hashtag"></i> <?php echo $activity['complaint_code']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/theme-toggle.js"></script>
    
    <script>
        // Animated counters
        document.addEventListener('DOMContentLoaded', function() {
            const counters = document.querySelectorAll('.animate-count');
            
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-target'));
                const duration = 2000; // 2 seconds
                const increment = target / (duration / 16); // 60fps
                let current = 0;
                
                const updateCounter = () => {
                    current += increment;
                    if (current < target) {
                        counter.textContent = Math.floor(current);
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.textContent = target;
                    }
                };
                
                // Start counter when in viewport
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            updateCounter();
                            observer.unobserve(entry.target);
                        }
                    });
                });
                
                observer.observe(counter);
            });

            // Initialize charts
            initCharts();
        });

        // Initialize Charts
        function initCharts() {
            // Status Distribution Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Under Review', 'Published', 'Resolved', 'Rejected'],
                    datasets: [{
                        data: [
                            <?php echo $stats['pending']; ?>,
                            <?php echo $stats['under_review']; ?>,
                            <?php echo $stats['published']; ?>,
                            <?php echo $stats['resolved']; ?>,
                            <?php echo $stats['rejected']; ?>
                        ],
                        backgroundColor: [
                            '#ed8936',
                            '#ecc94b',
                            '#48bb78',
                            '#4299e1',
                            '#f56565'
                        ],
                        borderColor: 'transparent',
                        borderWidth: 0,
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
                                usePointStyle: true,
                                font: {
                                    family: 'Inter'
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'var(--glass-bg)',
                            titleColor: 'var(--text-primary)',
                            bodyColor: 'var(--text-primary)',
                            borderColor: 'var(--glass-border)',
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });

            // Category Distribution Chart
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            const categoryChart = new Chart(categoryCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($stats['category_distribution'], 'name')); ?>,
                    datasets: [{
                        label: 'Complaints',
                        data: <?php echo json_encode(array_column($stats['category_distribution'], 'count')); ?>,
                        backgroundColor: <?php echo json_encode(array_column($stats['category_distribution'], 'color')); ?>,
                        borderColor: 'transparent',
                        borderWidth: 0,
                        borderRadius: 8,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'var(--glass-bg)',
                            titleColor: 'var(--text-primary)',
                            bodyColor: 'var(--text-primary)',
                            borderColor: 'var(--glass-border)',
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 12
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                color: 'var(--text-secondary)',
                                font: {
                                    family: 'Inter'
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'var(--border-color)',
                                drawBorder: false
                            },
                            ticks: {
                                color: 'var(--text-secondary)',
                                font: {
                                    family: 'Inter'
                                },
                                padding: 10
                            }
                        }
                    }
                }
            });
        }

        // Refresh dashboard data every 30 seconds
        setInterval(async () => {
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/admin_stats.php');
                const data = await response.json();
                
                if (data.success) {
                    // Update counters
                    document.querySelectorAll('.animate-count').forEach(counter => {
                        const statType = counter.closest('.stat-card').classList[1];
                        if (data[statType] !== undefined) {
                            counter.setAttribute('data-target', data[statType]);
                            counter.textContent = data[statType];
                        }
                    });
                    
                    // Show notification if there are new pending complaints
                    if (data.pending_increase > 0) {
                        showNotification(`You have ${data.pending_increase} new complaints to review`);
                    }
                }
            } catch (error) {
                console.error('Error refreshing dashboard:', error);
            }
        }, 30000);

        // Show notification
        function showNotification(message) {
            if (!('Notification' in window)) return;
            
            if (Notification.permission === 'granted') {
                new Notification('HTU Complaint System', {
                    body: message,
                    icon: '/assets/images/logo.png'
                });
            } else if (Notification.permission !== 'denied') {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        new Notification('HTU Complaint System', {
                            body: message,
                            icon: '/assets/images/logo.png'
                        });
                    }
                });
            }
        }

        // Request notification permission on page load
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    </script>
</body>
</html>
