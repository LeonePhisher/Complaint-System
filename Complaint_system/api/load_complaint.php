<?php
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';

header('Content-Type: application/json');

// Get query parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$category = isset($_GET['category']) ? intval($_GET['category']) : null;
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : null;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : null;
$user_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : null;

$offset = ($page - 1) * $limit;

// Build query
$where = [];
$params = [];

// For students, only show published or resolved complaints
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'student') {
    $where[] = "c.status IN ('published', 'resolved')";
} elseif (isset($_SESSION['admin_role'])) {
    // Admins can see more based on their role
    if ($_SESSION['admin_role'] !== 'super_admin') {
        $where[] = "cat.admin_id = ?";
        $params[] = $_SESSION['admin_id'];
    }
}

// Filter by category
if ($category) {
    $where[] = "c.category_id = ?";
    $params[] = $category;
}

// Filter by status
if ($status && in_array($status, ['pending', 'under_review', 'published', 'resolved', 'rejected'])) {
    $where[] = "c.status = ?";
    $params[] = $status;
}

// Search
if ($search) {
    $where[] = "(c.title LIKE ? OR c.description LIKE ? OR c.complaint_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Build WHERE clause
$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

try {
    // Get total count
    $count_query = "
        SELECT COUNT(*) as total
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        $where_clause
    ";
    
    $stmt = db()->prepare($count_query);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get complaints
    $query = "
        SELECT 
            c.id,
            c.user_id,
            c.attachments,
            c.complaint_code,
            c.title,
            c.description,
            c.location,
            c.urgency,
            c.status,
            c.view_count,
            c.upvotes,
            c.downvotes,
            c.created_at,
            cat.name as category_name,
            cat.color as category_color,
            cat.icon as category_icon,
            u.avatar_color as user_avatar,
            CONCAT(SUBSTR(u.full_name, 1, 1), '****') as user_name,
            CASE 
                WHEN v.user_id IS NOT NULL THEN v.vote_type
                ELSE NULL
            END as user_vote
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        JOIN users u ON c.user_id = u.id
        LEFT JOIN votes v ON c.id = v.complaint_id AND v.user_id = ?
        $where_clause
        ORDER BY 
            CASE c.urgency 
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            c.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    // Add user_id to params for vote check
    array_unshift($params, $user_id);
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = db()->prepare($query);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();
    
    // Format response
    $formatted_complaints = [];
    foreach ($complaints as $complaint) {
        // Build image URLs from attachments (Twitter-like carousel)
        $images = [];
        $attachments = [];
        if (!empty($complaint['attachments'])) {
            $decoded = json_decode($complaint['attachments'], true);
            if (is_array($decoded)) {
                $attachments = $decoded;
            }
        }

        $owner_user_id = (int)($complaint['user_id'] ?? 0);
        $base_url = rtrim(APP_URL, '/') . '/assets/uploads/complaints/' . $owner_user_id . '/';

        foreach ($attachments as $att) {
            if (!is_array($att)) continue;
            $fileType = (string)($att['file_type'] ?? '');
            $filename = (string)($att['filename'] ?? '');
            if ($filename == '' || strpos($fileType, 'image/') !== 0) continue;
            $images[] = [
                'url' => $base_url . rawurlencode($filename),
                'alt' => (string)($att['original_name'] ?? 'Attachment')
            ];
        }

        $formatted_complaints[] = [
            'id' => $complaint['id'],
            'complaint_code' => $complaint['complaint_code'],
            'title' => $complaint['title'],
            'description' => truncateText($complaint['description'], 200),
            'full_description' => $complaint['description'],
            'images' => $images,
            'location' => $complaint['location'],
            'urgency' => $complaint['urgency'],
            'status' => $complaint['status'],
            'view_count' => $complaint['view_count'],
            'upvotes' => $complaint['upvotes'],
            'downvotes' => $complaint['downvotes'],
            'time_ago' => timeAgo($complaint['created_at']),
            'created_at' => $complaint['created_at'],
            'category_name' => $complaint['category_name'],
            'category_color' => $complaint['category_color'],
            'category_icon' => $complaint['category_icon'],
            'user_avatar' => $complaint['user_avatar'],
            'user_name' => $complaint['user_name'],
            'initials' => substr($complaint['user_name'], 0, 2),
            'user_vote' => $complaint['user_vote'],
            'comments_count' => getCommentsCount($complaint['id'])
        ];
    }
    
    // Increment view count for each complaint
    foreach ($complaints as $complaint) {
        incrementViewCount($complaint['id']);
    }
    
    echo json_encode([
        'success' => true,
        'complaints' => $formatted_complaints,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error loading complaints: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load complaints'
    ]);
}

function getCommentsCount($complaint_id) {
    try {
        $stmt = db()->prepare("
            SELECT COUNT(*) as count 
            FROM comments 
            WHERE complaint_id = ? AND status = 'active'
        ");
        $stmt->execute([$complaint_id]);
        return $stmt->fetch()['count'];
    } catch (PDOException $e) {
        return 0;
    }
}

function incrementViewCount($complaint_id) {
    try {
        $stmt = db()->prepare("
            UPDATE complaints 
            SET view_count = view_count + 1 
            WHERE id = ?
        ");
        $stmt->execute([$complaint_id]);
    } catch (PDOException $e) {
        // Silently fail
    }
}
?>