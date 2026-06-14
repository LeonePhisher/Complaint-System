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
$student_name = $_SESSION['student_name'];

// Load student stats
$stats = [];
$recent_complaints = [];
$announcements = [];
$recent_activity = [];

try {
    // Get student statistics
    $stmt = db()->prepare("
        SELECT 
            COUNT(*) as total_complaints,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(upvotes) as total_upvotes,
            SUM(downvotes) as total_downvotes,
            SUM(view_count) as total_views,
            MAX(created_at) as last_complaint_date
        FROM complaints 
        WHERE user_id = ?
    ");
    $stmt->execute([$student_id]);
    $stats = $stmt->fetch() ?: [];
    
    // Get recent complaints (5 most recent)
    $stmt = db()->prepare("
        SELECT c.*, cat.name as category_name, cat.color as category_color,
               (c.upvotes - c.downvotes) as vote_score
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$student_id]);
    $recent_complaints = $stmt->fetchAll();
    
    // Get recent announcements (3 most recent)
    $stmt = db()->prepare("
        SELECT a.*, ad.full_name as admin_name,
               CONCAT(LEFT(a.content, 100), '...') as preview
        FROM announcements a
        JOIN admins ad ON a.admin_id = ad.id
        WHERE a.is_published = 1
        AND a.target_audience IN ('all', 'students')
        ORDER BY a.created_at DESC
        LIMIT 3
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll();
    
    // Get recent activity (complaints with recent status changes)
    $stmt = db()->prepare("
        SELECT 
            c.complaint_code,
            c.title,
            c.status,
            c.last_status_change,
            cat.name as category_name,
            TIMESTAMPDIFF(HOUR, c.last_status_change, NOW()) as hours_ago
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        WHERE c.user_id = ?
        AND c.last_status_change IS NOT NULL
        ORDER BY c.last_status_change DESC
        LIMIT 10
    ");
    $stmt->execute([$student_id]);
    $recent_activity = $stmt->fetchAll();
    
    // Get popular categories for this student
    $stmt = db()->prepare("
        SELECT 
            cat.name,
            cat.color,
            COUNT(c.id) as complaint_count,
            ROUND(COUNT(c.id) * 100.0 / (SELECT COUNT(*) FROM complaints WHERE user_id = ?), 1) as percentage
        FROM categories cat
        LEFT JOIN complaints c ON cat.id = c.category_id AND c.user_id = ?
        GROUP BY cat.id, cat.name, cat.color
        ORDER BY complaint_count DESC
        LIMIT 5
    ");
    $stmt->execute([$student_id, $student_id]);
    $category_stats = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    $_SESSION['error'] = 'Unable to load dashboard data. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta name="app-url" content="/complaint-system">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    
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

        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .welcome-text h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: rgba(102, 126, 234, 0.3);
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

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-icon {
            width: 56px;
            height: 56px;
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
        .stat-icon.published { background: linear-gradient(135deg, #48bb78, #38a169); }
        .stat-icon.resolved { background: linear-gradient(135deg, #4299e1, #3182ce); }
        .stat-icon.rejected { background: linear-gradient(135deg, #f56565, #ed64a6); }
        .stat-icon.engagement { background: linear-gradient(135deg, #9f7aea, #805ad5); }

        .stat-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .stat-numbers {
            flex: 1;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, var(--text-primary), var(--text-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-trend {
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
        }

        .trend-up {
            color: #48bb78;
            background: rgba(72, 187, 120, 0.1);
        }

        .trend-down {
            color: #f56565;
            background: rgba(245, 101, 101, 0.1);
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        .section-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--glass-border);
            margin-bottom: 2rem;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border-color);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--primary-color);
        }

        .complaint-list, .announcement-list, .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .complaint-item, .announcement-item, .activity-item {
            padding: 1rem;
            border-radius: var(--radius-md);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .complaint-item:hover, .announcement-item:hover, .activity-item:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
            transform: translateX(5px);
        }

        .complaint-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .complaint-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
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
        }

        .complaint-status {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .announcement-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .announcement-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .category-chart-container {
            height: 300px;
            margin-top: 1rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem 1rem;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            border: 1px solid var(--glass-border);
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-color: var(--primary-color);
        }

        .action-btn i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .action-label {
            font-weight: 500;
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 1rem;
            }
            
            .welcome-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 640px) {
            .quick-stats {
                grid-template-columns: 1fr;
            }
            
            .complaint-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Student Navigation -->
    <?php include '../../includes/layout/student-nav.php'; ?>

    <!-- Main Content -->
    <div class="dashboard-container">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <div class="welcome-header">
                <div class="welcome-text">
                    <h1>Welcome back, <?php echo htmlspecialchars($student_name); ?>!</h1>
                    <p>Index: <?php echo htmlspecialchars($student_index); ?> | <?php echo date('l, F j, Y'); ?></p>
                </div>
                <div class="welcome-actions">
                    <a href="submit.php" class="btn btn-light">
                        <i class="fas fa-plus"></i> Submit New Complaint
                    </a>
                </div>
            </div>
            <div class="welcome-stats">
                <div class="flex gap-4 flex-wrap">
                    <div class="text-sm">
                        <span class="opacity-75">Last Login:</span>
                        <span class="font-medium"><?php echo date('M j, g:i A', strtotime($_SESSION['last_login'] ?? 'now')); ?></span>
                    </div>
                    <div class="text-sm">
                        <span class="opacity-75">Account Created:</span>
                        <span class="font-medium"><?php echo date('M j, Y', strtotime($_SESSION['created_at'] ?? 'now')); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-inbox"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-numbers">
                        <div class="stat-number"><?php echo $stats['total_complaints'] ?? 0; ?></div>
                        <div class="stat-label">Total Complaints</div>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> 12%
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-numbers">
                        <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-trend trend-down">
                        <i class="fas fa-arrow-down"></i> 5%
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon published">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-numbers">
                        <div class="stat-number"><?php echo $stats['published'] ?? 0; ?></div>
                        <div class="stat-label">Published</div>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> 8%
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon resolved">
                    <i class="fas fa-flag-checkered"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-numbers">
                        <div class="stat-number"><?php echo $stats['resolved'] ?? 0; ?></div>
                        <div class="stat-label">Resolved</div>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> 15%
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-numbers">
                        <div class="stat-number"><?php echo $stats['rejected'] ?? 0; ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                    <div class="stat-trend trend-down">
                        <i class="fas fa-arrow-down"></i> 3%
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon engagement">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-numbers">
                        <div class="stat-number"><?php echo number_format($stats['total_upvotes'] ?? 0); ?></div>
                        <div class="stat-label">Total Upvotes</div>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i> 20%
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Left Column -->
            <div>
                <!-- Recent Complaints -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-history"></i>
                            Recent Complaints
                        </h3>
                        <a href="my-complaints.php" class="btn btn-outline btn-sm">
                            View All <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    
                    <?php if (!empty($recent_complaints)): ?>
                        <div class="complaint-list">
                            <?php foreach ($recent_complaints as $complaint): ?>
                                <div class="complaint-item">
                                    <div class="complaint-header">
                                        <div>
                                            <div class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></div>
                                            <div class="complaint-category" style="background: <?php echo $complaint['category_color']; ?>20; color: <?php echo $complaint['category_color']; ?>; border: 1px solid <?php echo $complaint['category_color']; ?>40;">
                                                <?php echo htmlspecialchars($complaint['category_name']); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <?php //echo getStatusBadge($complaint['status']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="complaint-meta">
                                        <span><i class="fas fa-code"></i> <?php echo $complaint['complaint_code']; ?></span>
                                        <span><i class="fas fa-calendar"></i> <?php echo timeAgo($complaint['created_at']); ?></span>
                                        <span><i class="fas fa-thumbs-up"></i> <?php echo $complaint['upvotes']; ?></span>
                                        <span><i class="fas fa-eye"></i> <?php echo $complaint['view_count']; ?></span>
                                    </div>
                                    
                                    <?php if (!empty($complaint['description'])): ?>
                                        <div class="complaint-preview mt-2">
                                            <p class="text-sm text-gray-600"><?php echo truncateText(strip_tags($complaint['description']), 100); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No Complaints Yet</h3>
                            <p>Submit your first complaint to get started</p>
                            <a href="submit.php" class="btn btn-gradient mt-4">
                                <i class="fas fa-plus"></i> Submit Complaint
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-bolt"></i>
                            Recent Activity
                        </h3>
                    </div>
                    
                    <?php if (!empty($recent_activity)): ?>
                        <div class="activity-list">
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php switch($activity['status']):
                                            case 'published': ?>
                                                <i class="fas fa-check"></i>
                                                <?php break;
                                            case 'resolved': ?>
                                                <i class="fas fa-flag-checkered"></i>
                                                <?php break;
                                            case 'rejected': ?>
                                                <i class="fas fa-times"></i>
                                                <?php break;
                                            default: ?>
                                                <i class="fas fa-sync-alt"></i>
                                        <?php endswitch; ?>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-text">
                                            <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                                            status changed to 
                                            <span class="font-medium"><?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?></span>
                                        </div>
                                        <div class="activity-time">
                                            <span class="text-gray-500"><?php echo $activity['category_name']; ?></span> • 
                                            <span><?php echo $activity['hours_ago']; ?> hours ago</span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-stream"></i>
                            <h3>No Recent Activity</h3>
                            <p>Your complaint status updates will appear here</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Announcements -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-bullhorn"></i>
                            Announcements
                        </h3>
                    </div>
                    
                    <?php if (!empty($announcements)): ?>
                        <div class="announcement-list">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="announcement-item">
                                    <div class="announcement-title">
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                    </div>
                                    <div class="announcement-preview text-sm text-gray-600">
                                        <?php echo htmlspecialchars($announcement['preview']); ?>
                                    </div>
                                    <div class="announcement-meta">
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($announcement['admin_name']); ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo timeAgo($announcement['created_at']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bullhorn"></i>
                            <h3>No Announcements</h3>
                            <p>Check back later for updates</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Category Distribution -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-chart-pie"></i>
                            Complaint Categories
                        </h3>
                    </div>
                    
                    <?php if (!empty($category_stats)): ?>
                        <div class="category-chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                        
                        <div class="category-stats mt-4">
                            <?php foreach ($category_stats as $category): ?>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <div style="width: 12px; height: 12px; border-radius: 50%; background: <?php echo $category['color']; ?>;"></div>
                                        <span class="text-sm"><?php echo htmlspecialchars($category['name']); ?></span>
                                    </div>
                                    <div class="text-sm font-medium">
                                        <?php echo $category['complaint_count']; ?> 
                                        <span class="text-gray-500">(<?php echo $category['percentage']; ?>%)</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-pie"></i>
                            <h3>No Category Data</h3>
                            <p>Submit complaints to see distribution</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h3>
                    </div>
                    
                    <div class="quick-actions">
                        <a href="submit.php" class="action-btn">
                            <i class="fas fa-plus-circle"></i>
                            <span class="action-label">Submit Complaint</span>
                        </a>
                        
                        <a href="my-complaints.php" class="action-btn">
                            <i class="fas fa-list"></i>
                            <span class="action-label">My Complaints</span>
                        </a>
                        
                        <a href="feed.php" class="action-btn">
                            <i class="fas fa-newspaper"></i>
                            <span class="action-label">View Feed</span>
                        </a>
                        
                        <a href="profile.php" class="action-btn">
                            <i class="fas fa-user-cog"></i>
                            <span class="action-label">Profile</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Category Chart
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($category_stats)): ?>
            const categoryCtx = document.getElementById('categoryChart').getContext('2d');
            const categoryLabels = <?php echo json_encode(array_column($category_stats, 'name')); ?>;
            const categoryData = <?php echo json_encode(array_column($category_stats, 'complaint_count')); ?>;
            const categoryColors = <?php echo json_encode(array_column($category_stats, 'color')); ?>;
            
            new Chart(categoryCtx, {
                type: 'doughnut',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryData,
                        backgroundColor: categoryColors,
                        borderWidth: 0,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            <?php endif; ?>
            
            // Auto-refresh dashboard every 60 seconds
            setInterval(() => {
                fetch('<?php echo APP_URL; ?>/api/dashboard_stats.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update stats counters with animation
                            updateCounter('total-complaints', data.stats.total_complaints);
                            updateCounter('pending-complaints', data.stats.pending);
                            updateCounter('published-complaints', data.stats.published);
                            updateCounter('resolved-complaints', data.stats.resolved);
                        }
                    });
            }, 60000);
        });
        
        function updateCounter(elementId, targetValue) {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            const currentValue = parseInt(element.textContent);
            const duration = 1000; // 1 second
            const step = (targetValue - currentValue) / (duration / 16); // 60fps
            
            let current = currentValue;
            const timer = setInterval(() => {
                current += step;
                if ((step > 0 && current >= targetValue) || (step < 0 && current <= targetValue)) {
                    current = targetValue;
                    clearInterval(timer);
                }
                element.textContent = Math.round(current);
            }, 16);
        }
    </script>
</body>
</html>