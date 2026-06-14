<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'];

try {
    $stats = [];
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $last_week = date('Y-m-d', strtotime('-7 days'));
    $last_month = date('Y-m-d', strtotime('-30 days'));
    
    // Total complaints
    if ($admin_role === 'super_admin') {
        $query = "SELECT COUNT(*) as total FROM complaints";
        $stmt = db()->prepare($query);
        $stmt->execute();
    } else {
        $query = "
            SELECT COUNT(*) as total 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE cat.admin_id = ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$admin_id]);
    }
    $stats['total'] = $stmt->fetch()['total'];
    
    // Today's complaints
    if ($admin_role === 'super_admin') {
        $query = "SELECT COUNT(*) as today FROM complaints WHERE DATE(created_at) = ?";
        $stmt = db()->prepare($query);
        $stmt->execute([$today]);
    } else {
        $query = "
            SELECT COUNT(*) as today 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE DATE(c.created_at) = ? AND cat.admin_id = ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$today, $admin_id]);
    }
    $stats['today'] = $stmt->fetch()['today'];
    
    // Yesterday's complaints
    if ($admin_role === 'super_admin') {
        $query = "SELECT COUNT(*) as yesterday FROM complaints WHERE DATE(created_at) = ?";
        $stmt = db()->prepare($query);
        $stmt->execute([$yesterday]);
    } else {
        $query = "
            SELECT COUNT(*) as yesterday 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE DATE(c.created_at) = ? AND cat.admin_id = ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$yesterday, $admin_id]);
    }
    $stats['yesterday'] = $stmt->fetch()['yesterday'];
    
    // This week's complaints
    if ($admin_role === 'super_admin') {
        $query = "SELECT COUNT(*) as week FROM complaints WHERE created_at >= ?";
        $stmt = db()->prepare($query);
        $stmt->execute([$last_week]);
    } else {
        $query = "
            SELECT COUNT(*) as week 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.created_at >= ? AND cat.admin_id = ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$last_week, $admin_id]);
    }
    $stats['week'] = $stmt->fetch()['week'];
    
    // This month's complaints
    if ($admin_role === 'super_admin') {
        $query = "SELECT COUNT(*) as month FROM complaints WHERE created_at >= ?";
        $stmt = db()->prepare($query);
        $stmt->execute([$last_month]);
    } else {
        $query = "
            SELECT COUNT(*) as month 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.created_at >= ? AND cat.admin_id = ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$last_month, $admin_id]);
    }
    $stats['month'] = $stmt->fetch()['month'];
    
    // Pending complaints
    if ($admin_role === 'super_admin') {
        $query = "SELECT COUNT(*) as pending FROM complaints WHERE status = 'pending'";
        $stmt = db()->prepare($query);
        $stmt->execute();
    } else {
        $query = "
            SELECT COUNT(*) as pending 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'pending' AND cat.admin_id = ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$admin_id]);
    }
    $stats['pending'] = $stmt->fetch()['pending'];
    
    // Under review complaints
    if ($admin_role === 'super_admin') {
        $query = "SELECT COUNT(*) as under_review FROM complaints WHERE status = 'under_review'";
        $stmt = db()->prepare($query);
        $stmt->execute();
    } else {
        $query = "
            SELECT COUNT(*) as under_review 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'under_review' AND cat.admin_id = ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$admin_id]);
    }
    $stats['under_review'] = $stmt->fetch()['under_review'];
    
    // Published complaints
    if ($admin_role === 'super_admin') {
        $query = "SELECT COUNT(*) as published FROM complaints WHERE status = 'published'";
        $stmt = db()->prepare($query);
        $stmt->execute();
    } else {
        $query = "
            SELECT COUNT(*) as published 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'published' AND cat.admin_id = ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$admin_id]);
    }
    $stats['published'] = $stmt->fetch()['published'];
    
    // Resolved complaints
    if ($admin_role === 'super_admin') {
        $query = "SELECT COUNT(*) as resolved FROM complaints WHERE status = 'resolved'";
        $stmt = db()->prepare($query);
        $stmt->execute();
    } else {
        $query = "
            SELECT COUNT(*) as resolved 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'resolved' AND cat.admin_id = ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$admin_id]);
    }
    $stats['resolved'] = $stmt->fetch()['resolved'];
    
    // Rejected complaints
    if ($admin_role === 'super_admin') {
        $query = "SELECT COUNT(*) as rejected FROM complaints WHERE status = 'rejected'";
        $stmt = db()->prepare($query);
        $stmt->execute();
    } else {
        $query = "
            SELECT COUNT(*) as rejected 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'rejected' AND cat.admin_id = ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$admin_id]);
    }
    $stats['rejected'] = $stmt->fetch()['rejected'];
    
    // Pending increase (since last check)
    $last_check = $_SESSION['last_stats_check'] ?? time() - 3600; // Default 1 hour ago
    $time_since = time() - $last_check;
    
    if ($admin_role === 'super_admin') {
        $query = "
            SELECT COUNT(*) as new_pending 
            FROM complaints 
            WHERE status = 'pending' AND created_at >= ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([date('Y-m-d H:i:s', $last_check)]);
    } else {
        $query = "
            SELECT COUNT(*) as new_pending 
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'pending' AND c.created_at >= ? AND cat.admin_id = ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([date('Y-m-d H:i:s', $last_check), $admin_id]);
    }
    $stats['pending_increase'] = $stmt->fetch()['new_pending'];
    
    // Update last check time
    $_SESSION['last_stats_check'] = time();
    
    // Category distribution
    if ($admin_role === 'super_admin') {
        $query = "
            SELECT 
                cat.name,
                cat.color,
                COUNT(c.id) as count
            FROM categories cat
            LEFT JOIN complaints c ON cat.id = c.category_id
            GROUP BY cat.id, cat.name, cat.color
            ORDER BY count DESC
            LIMIT 5
        ";
        $stmt = db()->prepare($query);
        $stmt->execute();
    } else {
        $query = "
            SELECT 
                cat.name,
                cat.color,
                COUNT(c.id) as count
            FROM categories cat
            LEFT JOIN complaints c ON cat.id = c.category_id
            WHERE cat.admin_id = ?
            GROUP BY cat.id, cat.name, cat.color
            ORDER BY count DESC
            LIMIT 5
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$admin_id]);
    }
    $stats['top_categories'] = $stmt->fetchAll();
    
    // Urgency distribution
    if ($admin_role === 'super_admin') {
        $query = "
            SELECT 
                urgency,
                COUNT(*) as count
            FROM complaints
            GROUP BY urgency
            ORDER BY 
                CASE urgency 
                    WHEN 'critical' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                END
        ";
        $stmt = db()->prepare($query);
        $stmt->execute();
    } else {
        $query = "
            SELECT 
                c.urgency,
                COUNT(*) as count
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE cat.admin_id = ?
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
        $stmt->execute([$admin_id]);
    }
    $stats['urgency_distribution'] = $stmt->fetchAll();
    
    // Resolution time (average days to resolve)
    if ($admin_role === 'super_admin') {
        $query = "
            SELECT 
                AVG(DATEDIFF(resolved_at, created_at)) as avg_resolution_time
            FROM complaints
            WHERE status = 'resolved' AND resolved_at IS NOT NULL
        ";
        $stmt = db()->prepare($query);
        $stmt->execute();
    } else {
        $query = "
            SELECT 
                AVG(DATEDIFF(c.resolved_at, c.created_at)) as avg_resolution_time
            FROM complaints c
            JOIN categories cat ON c.category_id = cat.id
            WHERE c.status = 'resolved' 
            AND c.resolved_at IS NOT NULL
            AND cat.admin_id = ?
        ";
        $stmt = db()->prepare($query);
        $stmt->execute([$admin_id]);
    }
    $result = $stmt->fetch();
    $stats['avg_resolution_time'] = round($result['avg_resolution_time'] ?? 0, 1);
    
    // Total users (super admin only)
    if ($admin_role === 'super_admin') {
        $query = "SELECT COUNT(*) as total_users FROM users WHERE is_verified = 1";
        $stmt = db()->prepare($query);
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch()['total_users'];
        
        $query = "SELECT COUNT(*) as new_users FROM users WHERE is_verified = 1 AND created_at >= ?";
        $stmt = db()->prepare($query);
        $stmt->execute([$last_week]);
        $stats['new_users'] = $stmt->fetch()['new_users'];
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'timestamp' => time()
    ]);
    
} catch (PDOException $e) {
    error_log("Admin stats error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load statistics'
    ]);
}
?>