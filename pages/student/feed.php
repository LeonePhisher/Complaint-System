<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../includes/utilities/helpers.php';

// Check if user is logged in
if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

// Get user info
$user_id = $_SESSION['student_id'];
$user_name = $_SESSION['student_name'];
$user_avatar = $_SESSION['user_avatar'];

// Get complaints for feed
$complaints = [];
try {
    $stmt = db()->prepare("
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
            u.full_name as user_name
        FROM complaints c
        JOIN categories cat ON c.category_id = cat.id
        JOIN users u ON c.user_id = u.id
        WHERE c.status IN ('published', 'resolved')
        ORDER BY 
            CASE c.urgency 
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            c.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $complaints = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching complaints: " . $e->getMessage());
}

// Get user's vote history
$user_votes = [];
try {
    $stmt = db()->prepare("
        SELECT complaint_id, vote_type 
        FROM votes 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    while ($row = $stmt->fetch()) {
        $user_votes[$row['complaint_id']] = $row['vote_type'];
    }
} catch (PDOException $e) {
    error_log("Error fetching user votes: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta name="app-url" content="/complaint-system">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Feed - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/media-carousel.css">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
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
            --radius-full: 9999px;
        }

        .feed-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .header {
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: var(--text-secondary);
        }

        .complaint-card {
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.5s ease-out;
            animation-fill-mode: both;
        }

        .complaint-card:nth-child(1) { animation-delay: 0.1s; }
        .complaint-card:nth-child(2) { animation-delay: 0.2s; }
        .complaint-card:nth-child(3) { animation-delay: 0.3s; }
        .complaint-card:nth-child(4) { animation-delay: 0.4s; }
        .complaint-card:nth-child(5) { animation-delay: 0.5s; }

        .card-header {
            display: flex;
            align-items: center;
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .post-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .post-meta i {
            margin-right: 0.25rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        .complaint-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .complaint-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .complaint-location {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .category-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
            /* Comment You badge and close button */
            .you-badge {
                display: inline-block;
                background: var(--primary-color);
                color: #fff;
                padding: 0.15rem 0.4rem;
                margin-left: 0.5rem;
                border-radius: 3px;
                font-size: 0.75rem;
                font-weight: 600;
                vertical-align: middle;
            }
        
            .close-comments-btn {
                background: transparent;
                border: none;
                font-size: 1.25rem;
                color: var(--text-secondary);
                cursor: pointer;
                margin-left: 1rem;
            }
        .view-replies-btn {
            background: transparent;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 0.85rem;
            margin-left: 0.5rem;
        /* UPPERCASE */
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-published {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
            border: 1px solid rgba(72, 187, 120, 0.2);
        }

        .status-resolved {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .card-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            color: var(--text-muted);
            background: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border-radius: var(--radius-md);
        }

        .action-btn:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .action-btn.active {
            color: var(--primary-color);
        }

        .action-btn.upvote.active {
            color: #48bb78;
        }

        .action-btn.downvote.active {
            color: #f56565;
        }

        .vote-count {
            min-width: 20px;
            text-align: center;
            font-weight: 500;
        }

        .floating-action-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: var(--shadow-xl);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 100;
        }

        .floating-action-btn:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
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

        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            gap: 1rem;
        }

        .urgency-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .urgency-critical { background: #f56565; box-shadow: 0 0 10px #f56565; }
        .urgency-high { background: #ed8936; }
        .urgency-medium { background: #ecc94b; }
        .urgency-low { background: #48bb78; }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .feed-container {
                padding: 1rem;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .user-avatar {
                margin-right: 0;
                margin-bottom: 1rem;
            }
            
            .floating-action-btn {
                bottom: 1rem;
                right: 1rem;
            }
        }

        /* ---- comments section styles ---- */
        .comments-section {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-lg);
            padding: 2rem;
            border: 1px solid var(--glass-border);
            margin-top: 2rem;
        }

        .comments-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
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

        .comment-form-container {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }

        .char-counter {
            text-align: right;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .comments-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .comment-item {
            display: flex;
            gap: 1rem;
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .comment-item:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
            transform: translateX(5px);
        }

        .comment-avatar {
            flex-shrink: 0;
        }

        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .comment-content {
            flex: 1;
        }

        .comment-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
        }

        .commenter-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .admin-badge {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 0.2rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 500;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .anonymous-badge {
            background: rgba(156, 163, 175, 0.1);
            color: #9ca3af;
            padding: 0.2rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 500;
            border: 1px solid rgba(156, 163, 175, 0.2);
        }

        .comment-time {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .edited-badge {
            color: var(--text-secondary);
            font-size: 0.7rem;
            font-style: italic;
        }

        .comment-body {
            color: var(--text-primary);
            line-height: 1.6;
            margin-bottom: 0.75rem;
        }

        .comment-actions {
            display: flex;
            gap: 1rem;
        }

        .comment-action {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 0.875rem;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }

        .comment-action:hover {
            color: var(--primary-color);
            background: rgba(102, 126, 234, 0.1);
        }

        .comment-action.delete:hover {
            color: #f56565;
            background: rgba(245, 101, 101, 0.1);
        }

        .edit-comment-form {
            margin-top: 1rem;
        }

        .edit-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
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

        .loading-spinner {
            padding: 2rem;
            text-align: center;
        }

        @media (max-width: 640px) {
            .comment-item {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .comment-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .comment-actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include '../../includes/layout/student-nav.php'; ?>

    <!-- Main Content -->
    <div class="feed-container">
        <!-- Header -->
        <div class="header">
            <h1>Complaint Feed</h1>
            <p>Latest published complaints from the HTU community</p>
        </div>

        <!-- Feed -->
        <div id="complaintFeed">
            <?php if (empty($complaints)): ?>
                <div class="empty-state">
                    <i class="fas fa-comment-slash"></i>
                    <h3>No complaints yet</h3>
                    <p>Be the first to submit a complaint or check back later</p>
                    <a href="<?php echo APP_URL; ?>/pages/student/submit.php" class="btn btn-gradient mt-4">
                        <i class="fas fa-plus mr-2"></i> Submit Complaint
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($complaints as $complaint): ?>
                    <?php 
                    // Generate initials from username
                    $initials = getInitials($complaint['user_name']);
                    $time_ago = timeAgo($complaint['created_at']);
                    $is_upvoted = isset($user_votes[$complaint['id']]) && $user_votes[$complaint['id']] === 'upvote';
                    $is_downvoted = isset($user_votes[$complaint['id']]) && $user_votes[$complaint['id']] === 'downvote';
                    ?>
                    
                    <div class="glass-card complaint-card" id="complaint-<?php echo $complaint['id']; ?>">
                        <!-- Card Header -->
                        <div class="card-header">
                            <div class="user-avatar" style="background: <?php echo $complaint['user_avatar']; ?>">
                                <?php echo $initials; ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name">Anonymous Student</div>
                                <div class="post-meta">
                                    <span><i class="far fa-clock"></i> <?php echo $time_ago; ?></span>
                                    <span class="urgency-indicator urgency-<?php echo $complaint['urgency']; ?>"></span>
                                    <span><?php echo ucfirst($complaint['urgency']); ?> Priority</span>
                                    <span class="view-count" id="viewcount-<?php echo $complaint['id']; ?>">
                                        <i class="far fa-eye"></i> <?php echo $complaint['view_count']; ?> views
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body">
                            <!-- Status Badge -->
                            <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                            </span>

                            <!-- Category Tag -->
                            <span class="category-tag" style="background: <?php echo $complaint['category_color'] . '20'; ?>; color: <?php echo $complaint['category_color']; ?>;">
                                <i class="fas fa-<?php echo $complaint['category_icon']; ?>"></i>
                                <?php echo $complaint['category_name']; ?>
                            </span>

                            <!-- Complaint Code -->
                            <div class="text-sm text-muted mb-2">
                                <i class="fas fa-hashtag"></i> <?php echo $complaint['complaint_code']; ?>
                            </div>

                            <!-- Title -->
                            <h3 class="complaint-title"><?php echo htmlspecialchars($complaint['title']); ?></h3>

                            <!-- Description -->
                            <?php $desc = html_entity_decode($complaint['description']); ?>
                            <div class="complaint-description"><?php echo $desc; ?></div>

                            <?php
                            $attachments = [];
                            $images = [];
                            if (!empty($complaint['attachments'])) {
                                $decoded = json_decode($complaint['attachments'], true);
                                if (is_array($decoded)) {
                                    $attachments = $decoded;
                                }
                            }
                            foreach ($attachments as $att) {
                                if (!is_array($att)) continue;
                                $fileType = (string)($att['file_type'] ?? '');
                                $filename = (string)($att['filename'] ?? '');
                                if ($filename === '' || strpos($fileType, 'image/') !== 0) continue;
                                $url = APP_URL . '/assets/uploads/complaints/' . (int)$complaint['user_id'] . '/' . rawurlencode($filename);
                                $alt = (string)($att['original_name'] ?? 'Attachment');
                                $images[] = ['url' => $url, 'alt' => $alt];
                            }
                            ?>
                            <?php if (!empty($images)): ?>
                                <div class="media-carousel" data-media-carousel>
                                    <div class="media-track">
                                        <?php foreach ($images as $img): ?>
                                            <div class="media-item">
                                                <img src="<?php echo htmlspecialchars($img['url']); ?>" alt="<?php echo htmlspecialchars($img['alt']); ?>" loading="lazy">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($images) > 1): ?>
                                        <button class="media-nav prev" type="button" aria-label="Previous">
                                            <i class="fas fa-chevron-left"></i>
                                        </button>
                                        <button class="media-nav next" type="button" aria-label="Next">
                                            <i class="fas fa-chevron-right"></i>
                                        </button>
                                        <div class="media-dots">
                                            <?php for ($i = 0; $i < count($images); $i++): ?>
                                                <button class="media-dot<?php echo $i === 0 ? ' active' : ''; ?>" type="button" aria-label="Go to slide <?php echo $i + 1; ?>"></button>
                                            <?php endfor; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Location -->
                            <?php if (!empty($complaint['location'])): ?>
                                <div class="complaint-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($complaint['location']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Card Footer -->
                        <div class="card-footer">
                            <!-- Upvote Button -->
                            <button 
                                class="action-btn upvote <?php echo $is_upvoted ? 'active' : ''; ?>" 
                                data-complaint-id="<?php echo $complaint['id']; ?>"
                                onclick="vote(<?php echo $complaint['id']; ?>, 'upvote')"
                            >
                                <i class="fas fa-thumbs-up"></i>
                                <span class="vote-count" id="upvotes-<?php echo $complaint['id']; ?>">
                                    <?php echo $complaint['upvotes']; ?>
                                </span>
                            </button>

                            <!-- Downvote Button -->
                            <button 
                                class="action-btn downvote <?php echo $is_downvoted ? 'active' : ''; ?>" 
                                data-complaint-id="<?php echo $complaint['id']; ?>"
                                onclick="vote(<?php echo $complaint['id']; ?>, 'downvote')"
                            >
                                <i class="fas fa-thumbs-down"></i>
                                <span class="vote-count" id="downvotes-<?php echo $complaint['id']; ?>">
                                    <?php echo $complaint['downvotes']; ?>
                                </span>
                            </button>

                            <!-- Comment Button -->
                            <button class="action-btn" onclick="showComments(<?php echo $complaint['id']; ?>)">
                                <i class="far fa-comment"></i>
                                <span>Comment</span>
                            </button>

                            <!-- Share Button -->
                            <button class="action-btn" onclick="shareComplaint(<?php echo $complaint['id']; ?>)">
                                <i class="fas fa-share"></i>
                                <span>Share</span>
                            </button>
                        </div>

                        <!-- Comments Section (Hidden by default) -->
                        <div class="comments-section" id="comments-<?php echo $complaint['id']; ?>" style="display: none;">
                            <!-- Comments will be loaded here via AJAX -->
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Loading Spinner (for infinite scroll) -->
        <div id="loadingSpinner" class="loading-spinner" style="display: none;">
            <div class="spinner"></div>
            <p>Loading more complaints...</p>
        </div>
    </div>

    <!-- Floating Action Button -->
    <a href="<?php echo APP_URL; ?>/pages/student/submit.php" class="floating-action-btn hover-lift">
        <i class="fas fa-plus"></i>
    </a>

    <!-- JavaScript -->

    <script src="<?php echo APP_URL; ?>/assets/js/ajax-handler.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/theme-toggle.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/media-carousel.js"></script>
    
    <script>
        // Vote functionality
        async function vote(complaintId, voteType) {
            try {
                const response = await fetch('<?php echo APP_URL; ?>/api/vote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        complaint_id: complaintId,
                        vote_type: voteType,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    // Update vote counts
                    document.getElementById(`upvotes-${complaintId}`).textContent = data.upvotes;
                    document.getElementById(`downvotes-${complaintId}`).textContent = data.downvotes;
                    
                    // Update button states
                    const upvoteBtn = document.querySelector(`#complaint-${complaintId} .upvote`);
                    const downvoteBtn = document.querySelector(`#complaint-${complaintId} .downvote`);
                    
                    if (voteType === 'upvote') {
                        if (data.voted) {
                            upvoteBtn.classList.add('active');
                            downvoteBtn.classList.remove('active');
                        } else {
                            upvoteBtn.classList.remove('active');
                        }
                    } else {
                        if (data.voted) {
                            downvoteBtn.classList.add('active');
                            upvoteBtn.classList.remove('active');
                        } else {
                            downvoteBtn.classList.remove('active');
                        }
                    }
                    
                    // Show notification
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showToast('Failed to submit vote', 'error');
            }
        }

        // showComments toggles and initialises comment panel, then loads page 1
        const commentState = {}; // complaintId -> {page,pages,loading}

        let openComplaintId = null;

        //  async function showComments(complaintId) {
        //     // close previously open if different
        //     if (openComplaintId && openComplaintId !== complaintId) {
        //         closeComments(openComplaintId);
        //     }

        window.showComments = async function(complaintId, highlightCommentId = null) {
            // Count a "view" when a user opens a complaint to read/discuss it.
            // Throttled server-side per session to prevent inflated counts.
            try { incrementComplaintView(complaintId); } catch (e) {}

            // close previously open if different
            if (openComplaintId && openComplaintId !== complaintId) {
                closeComments(openComplaintId);
            }

            const container = document.getElementById(`comments-${complaintId}`);
            if (container.style.display === 'block') {
                container.style.display = 'none';
                // restore other complaint cards
                document.querySelectorAll('.complaint-card').forEach(c => c.style.display = '');
                openComplaintId = null;
                return;
            }

            // hide other complaint cards to focus
            document.querySelectorAll('.complaint-card').forEach(c => {
                if (c.id !== `complaint-${complaintId}`) c.style.display = 'none';
            });
            openComplaintId = complaintId;

            if (!container.dataset.initialized) {
                container.innerHTML = buildCommentSectionHtml(complaintId);
                container.dataset.initialized = '1';
                const ta = container.querySelector('textarea');
                ta.addEventListener('input', () => updateCommentCounter(complaintId));
            }
            container.style.display = 'block';
            loadComments(complaintId, 1, highlightCommentId);
        }

            window.closeComments = function(complaintId) {
                const container = document.getElementById(`comments-${complaintId}`);
                if (container) container.style.display = 'none';
            // show all cards again
            document.querySelectorAll('.complaint-card').forEach(c => c.style.display = '');
            if (openComplaintId === complaintId) openComplaintId = null;
        }

        function buildCommentSectionHtml(complaintId) {
            return `
    <div class="comments-header">
        <h3 class="section-title">
            <i class="fas fa-comments"></i>
            Comments (<span id="commentCount-${complaintId}">0</span>)
        </h3>
        <button class="close-comments-btn" onclick="closeComments(${complaintId})" title="Close comments">&times;</button>
    </div>

    <div class="comment-form-container">
        <form id="commentForm-${complaintId}" onsubmit="return submitComment(event, ${complaintId})">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="complaint_id" value="${complaintId}">
            <input type="hidden" name="parent_id" value="">
            <div class="form-group">
                <textarea name="content" id="commentContent-${complaintId}"
                          class="form-input" rows="3"
                          placeholder="Write a comment..."
                          maxlength="200px" required></textarea>
                <div class="char-counter" id="commentCounter-${complaintId}">0/1000</div>
            </div>
            <?php if (isStudent()): ?>
           <!-- <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_anonymous" value="1">
                    <i class="fas fa-user-secret"></i> Post anonymously
                </label>-->
            </div>
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit" class="btn btn-gradient">
                    <i class="fas fa-paper-plane"></i> Post Comment
                </button>
            </div>
        </form>
    </div>

    <div id="commentsList-${complaintId}" class="comments-list">
        <div class="loading-spinner text-center py-8">
            <i class="fas fa-spinner fa-spin fa-2x" style="color: #667eea;"></i>
        </div>
    </div>

    <div id="loadMoreContainer-${complaintId}" class="text-center mt-4" style="display:none;">
        <button class="btn btn-outline" onclick="loadMoreComments(${complaintId})">
            <i class="fas fa-chevron-down"></i> Load More Comments
        </button>
    </div>

    <div class="text-center mt-4">
        <button class="btn btn-secondary" onclick="closeComments(${complaintId})">
            <i class="fas fa-times"></i> Close Comments
        </button>
    </div>
`;
        }
        

        async function loadComments(complaintId, page, highlightCommentId = null) {
            if (!commentState[complaintId]) commentState[complaintId] = {page:0,pages:0,loading:false};
            const state = commentState[complaintId];
            if (state.loading) return;
            state.loading = true;

            const list = document.getElementById(`commentsList-${complaintId}`);
            if (page === 1) {
                list.innerHTML = '<div class="loading-spinner text-center py-8"><i class="fas fa-spinner fa-spin fa-2x" style="color: #667eea;"></i></div>';
            }

            try {
                const res = await fetch(`<?php echo APP_URL; ?>/api/get_comments.php?complaint_id=${complaintId}&page=${page}`);
                const data = await res.json();
                if (data.success) {
                    if (page === 1) {
                        list.innerHTML = '';
                        document.getElementById(`commentCount-${complaintId}`).textContent = data.total;
                    }

                    // build parent/child structure
                    const commentMap = {};
                    data.comments.forEach(c => { c.replies = []; commentMap[c.id] = c; });
                    window.__commentMap = commentMap; // expose for reply toggle lookup
                    const topLevel = [];
                    data.comments.forEach(c => {
                        if (c.parent_id) {
                            if (commentMap[c.parent_id]) {
                                commentMap[c.parent_id].replies.push(c);
                            } else {
                                topLevel.push(c);
                            }
                        } else {
                            topLevel.push(c);
                        }
                    });

                    state.pages = data.pages;

                    if (topLevel.length === 0 && page === 1) {
                        list.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-comments"></i>
                                <h3>No Comments Yet</h3>
                                <p>Be the first to comment on this complaint</p>
                            </div>`;
                    } else {
                        // render tree
                        topLevel.forEach(c => list.appendChild(createCommentElement(c)));
                    }

                    // Highlight comment if specified
                    if (highlightCommentId && page === 1) {
                        setTimeout(() => {
                            const commentEl = document.getElementById(`comment-${highlightCommentId}`);
                            if (commentEl) {
                                try { commentEl.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
                                commentEl.style.backgroundColor = '#fff3cd';
                                commentEl.style.border = '2px solid #ffc107';
                                commentEl.style.borderRadius = '8px';
                                setTimeout(() => {
                                    commentEl.style.backgroundColor = '';
                                    commentEl.style.border = '';
                                    commentEl.style.borderRadius = '';
                                }, 4500);
                            }
                        }, 500);
                    }

                    state.page = page;
                    document.getElementById(`loadMoreContainer-${complaintId}`).style.display =
                        state.page < state.pages ? 'block' : 'none';
                } else {
                    showNotification(data.message,'error');
                }
            } catch(e) { console.error(e); showNotification('Failed to load comments','error'); }
            finally { state.loading = false; }
        }

        function loadMoreComments(complaintId) {
            const state = commentState[complaintId];
            if (state && state.page < state.pages) {
                loadComments(complaintId, state.page + 1);
            }
        }

        function createCommentElement(comment) {
            const div = document.createElement('div');
            div.className = 'comment-item';
            div.id = `comment-${comment.id}`;
            const editBadge = comment.is_edited ? '<span class="edited-badge">(edited)</span>' : '';

            // build replies toggle if any
            let repliesToggle = '';
            if (comment.replies && comment.replies.length) {
                repliesToggle = `<button class="view-replies-btn" data-count="${comment.replies.length}" onclick="toggleReplies(${comment.id}, this)">View replies (${comment.replies.length})</button>`;
            }

            div.innerHTML = `
                <div class="comment-avatar">
                    <div class="avatar">${comment.name.charAt(0)}</div>
                </div>
                <div class="comment-content">
                    <div class="comment-header">
                        <span class="commenter-name">${comment.name}</span>
                        ${comment.is_current_user ? '<span class="you-badge">You</span>' : ''}
                        ${comment.badge}
                        <span class="comment-time">${comment.time} ${editBadge}</span>
                    </div>
                    <div class="comment-body">${comment.content}</div>
                    <div class="comment-actions">
                        <button class="comment-action" onclick="replyToComment(${comment.id})">
                            <i class="fas fa-reply"></i> Reply
                        </button>
                        ${comment.can_edit ? `<button class="comment-action" onclick="editComment(${comment.id})"><i class="fas fa-edit"></i> Edit</button>` : ''}
                        ${comment.can_delete ? `<button class="comment-action delete" onclick="deleteComment(${comment.id})"><i class="fas fa-trash"></i> Delete</button>` : ''}
                        ${repliesToggle}
                    </div>
                </div>
                <div class="replies-container" id="replies-${comment.id}" style="display: none; margin-left: 2rem; margin-top: 0.5rem;"></div>`;

            // recursively append replies immediately for correct order
            if (comment.replies && comment.replies.length) {
                const repContainer = div.querySelector(`#replies-${comment.id}`);
                comment.replies.forEach(r => {
                    repContainer.appendChild(createCommentElement(r));
                });
            }

            return div;
        }

        function toggleReplies(commentId, btn) {
            const container = document.getElementById(`replies-${commentId}`);
            if (!container) return;
            if (container.style.display === 'none') {
                // show and populate if empty
                container.style.display = 'block';
                if (!container.dataset.populated) {
                    const parentComment = commentStateData(commentId);
                    if (parentComment && parentComment.replies) {
                        parentComment.replies.forEach(r => container.appendChild(createCommentElement(r)));
                    }
                    container.dataset.populated = '1';
                }
                btn.textContent = 'Hide replies';
            } else {
                container.style.display = 'none';
                btn.textContent = `View replies (${btn.dataset.count || ''})`;
            }
        }

        // helper to lookup comment object in current state map
        function commentStateData(commentId) {
            // reconstruct from last loaded page of comments (simplest: search DOM or maintain a map)
            // we will keep a temporary map when processing comments in loadComments
            return window.__commentMap && window.__commentMap[commentId];
        }

        function submitComment(evt, complaintId) {
            evt.preventDefault();
            const form = document.getElementById(`commentForm-${complaintId}`);
            const content = form.content.value.trim();
            if (content.length < 3) { showNotification('Comment must be at least 3 characters','error'); return false; }
            if (content.length > 1000) { showNotification('Comment must not exceed 1000 characters','error'); return false; }
            const btn = form.querySelector('button[type=submit]');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...'; btn.disabled=true;
            const payload = {complaint_id: complaintId, content: content, csrf_token: '<?php echo generateCSRFToken(); ?>'};
            if (form.is_anonymous) payload.is_anonymous = 1;
            if (form.parent_id && form.parent_id.value) payload.parent_id = parseInt(form.parent_id.value);
            fetch('<?php echo APP_URL; ?>/api/add_comment.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
                .then(r=>r.json()).then(d=>{
                    if (d.success) {
                        form.reset();
                        if (form.parent_id) form.parent_id.value = '';
                        document.getElementById(`commentCounter-${complaintId}`).textContent='0/1000';
                        loadComments(complaintId,1);
                        showNotification('Comment posted successfully!','success');
                    } else showNotification(d.message,'error');
                }).catch(e=>{console.error(e); showNotification('Failed to post comment','error');})
                .finally(()=>{ btn.innerHTML='<i class="fas fa-paper-plane"></i> Post Comment'; btn.disabled=false; });
            return false;
        }

        function deleteComment(commentId) {
            if (!confirm('Are you sure you want to delete this comment?')) return;
            const payload = {comment_id:commentId, csrf_token:'<?php echo generateCSRFToken(); ?>'};
            fetch('<?php echo APP_URL; ?>/api/delete_comment.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
                .then(r=>r.json()).then(d=>{
                    if (d.success) { document.getElementById(`comment-${commentId}`).remove(); showNotification('Comment deleted successfully','success'); }
                    else showNotification(d.message,'error');
                }).catch(e=>{console.error(e); showNotification('Failed to delete comment','error');});
        }

        function editComment(commentId) {
            const el=document.getElementById(`comment-${commentId}`);
            const body=el.querySelector('.comment-body');
            const txt=body.innerText;
            const form=document.createElement('div');
            form.className='edit-comment-form';
            form.innerHTML=`
                <textarea class="form-input" rows="3">${txt}</textarea>
                <div class="edit-actions">
                    <button class="btn btn-gradient btn-sm" onclick="saveEdit(${commentId}, this)">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button class="btn btn-outline btn-sm" onclick="cancelEdit(${commentId})">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>`;
            body.style.display='none';
            body.parentNode.insertBefore(form, body.nextSibling);
        }

        function saveEdit(commentId, btn) {
            const form=btn.closest('.edit-comment-form');
            const newContent=form.querySelector('textarea').value.trim();
            if (newContent.length<3){ showNotification('Comment must be at least 3 characters','error'); return; }
            if (newContent.length>1000){ showNotification('Comment must not exceed 1000 characters','error'); return; }
            const payload={comment_id:commentId, content:newContent, csrf_token:'<?php echo generateCSRFToken(); ?>'};
            btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving...'; btn.disabled=true;
            fetch('<?php echo APP_URL; ?>/api/edit_comment.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
                .then(r=>r.json()).then(d=>{
                    if (d.success) {
                        const el=document.getElementById(`comment-${commentId}`);
                        const body=el.querySelector('.comment-body');
                        body.innerHTML=newContent;
                        body.style.display='block';
                        form.remove();
                        const timeSpan=el.querySelector('.comment-time');
                        if (!timeSpan.innerHTML.includes('(edited)')) timeSpan.innerHTML += ' <span class="edited-badge">(edited)</span>';
                        showNotification('Comment updated successfully','success');
                    } else showNotification(d.message,'error');
                }).catch(e=>{console.error(e); showNotification('Failed to update comment','error');})
                .finally(()=>{ btn.innerHTML='<i class="fas fa-save"></i> Save'; btn.disabled=false; });
        }

        function cancelEdit(commentId) {
            const el=document.getElementById(`comment-${commentId}`);
            const form=el.querySelector('.edit-comment-form');
            const body=el.querySelector('.comment-body');
            body.style.display='block'; form.remove();
        }

        function replyToComment(commentId) {
            const commentEl = document.getElementById(`comment-${commentId}`);
            if (!commentEl) return;
            const container = commentEl.closest('.comments-section');
            if (!container) return;
            const complaintId = container.id.replace('comments-', '');
            const textarea = container.querySelector('textarea');
            textarea.value = `@user_${commentId} `;
            textarea.focus();
            // set hidden parent_id field
            if (container.querySelector('input[name="parent_id"]')) {
                container.querySelector('input[name="parent_id"]').value = commentId;
            }
            updateCommentCounter(complaintId);
        }

        function updateCommentCounter(complaintId) {
            const ta=document.getElementById(`commentContent-${complaintId}`);
            const counter=document.getElementById(`commentCounter-${complaintId}`);
            const len=ta.value.length;
            counter.textContent = `${len}/1000`;
            counter.style.color = len>900 ? '#f56565' : len>800 ? '#ecc94b' : 'var(--text-secondary)';
        }

        function showNotification(message,type) {
            const n=document.createElement('div');
            n.className=`toast toast-${type}`;
            n.innerHTML=`<i class="fas fa-${type==='success'?'check-circle':type==='error'?'exclamation-circle':'info-circle'}"></i><span>${message}</span>`;
            const c=document.createElement('div');
            c.className='toast-container';
            c.appendChild(n);
            document.body.appendChild(c);
            setTimeout(()=>n.style.display='none',3000);
        }

        // Share complaint
        function shareComplaint(complaintId) {
            const url = `${window.location.origin}/complaint/${complaintId}`;
            const title = document.querySelector(`#complaint-${complaintId} .complaint-title`).textContent;
            
            if (navigator.share) {
                navigator.share({
                    title: title,
                    text: 'Check out this complaint on HTU Complaints',
                    url: url
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(url).then(() => {
                    showToast('Link copied to clipboard', 'success');
                });
            }
        }

        // Infinite scroll
        let loading = false;
        let page = 1;

        async function incrementComplaintView(complaintId) {
            const id = parseInt(complaintId, 10);
            if (!id) return;

            try {
                const resp = await fetch('<?php echo APP_URL; ?>/api/increment_view.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        complaint_id: id,
                        csrf_token: '<?php echo generateCSRFToken(); ?>'
                    })
                });
                const data = await resp.json();
                if (data && data.success && typeof data.view_count !== 'undefined') {
                    const el = document.getElementById(`viewcount-${id}`);
                    if (el) {
                        el.innerHTML = `<i class="far fa-eye"></i> ${data.view_count} views`;
                    }
                }
            } catch (e) {
                // ignore network errors for view counting
                console.error(e);
            }
        }
        
        window.addEventListener('scroll', async () => {
            if (loading) return;
            
            const { scrollTop, scrollHeight, clientHeight } = document.documentElement;
            
            if (scrollTop + clientHeight >= scrollHeight - 100) {
                loading = true;
                document.getElementById('loadingSpinner').style.display = 'flex';
                
                try {
                    const response = await fetch(`<?php echo APP_URL; ?>/api/load_complaint.php?page=${page + 1}`);
                    const data = await response.json();
                    
                    if (data.success && data.complaints.length > 0) {
                        page++;
                        const feed = document.getElementById('complaintFeed');
                        data.complaints.forEach(complaint => {
                            feed.appendChild(createComplaintCard(complaint));
                        });
                    }
                } catch (error) {
                    console.error('Error:', error);
                } finally {
                    loading = false;
                    document.getElementById('loadingSpinner').style.display = 'none';
                }
            }
        });

        // Create complaint card element
        function createComplaintCard(complaint) {
            const card = document.createElement('div');
            card.className = 'glass-card complaint-card';
            card.id = `complaint-${complaint.id}`;

            const isUpvoted = complaint.user_vote === 'upvote';
            const isDownvoted = complaint.user_vote === 'downvote';

            const images = Array.isArray(complaint.images) ? complaint.images.filter(i => i && i.url) : [];
            const carouselHtml = images.length ? (() => {
                const itemsHtml = images.map(img => `
                    <div class="media-item">
                        <img src="${img.url}" alt="${(img.alt || 'Attachment').replace(/"/g, '&quot;')}" loading="lazy">
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
            })() : '';

            card.innerHTML = `
                <div class="card-header">
                    <div class="user-avatar" style="background: ${complaint.user_avatar}">
                        ${complaint.initials || ''}
                    </div>
                    <div class="user-info">
                        <div class="user-name">Anonymous Student</div>
                        <div class="post-meta">
                            <span><i class="far fa-clock"></i> ${complaint.time_ago || ''}</span>
                            <span class="urgency-indicator urgency-${complaint.urgency}"></span>
                            <span>${(complaint.urgency || '').charAt(0).toUpperCase() + (complaint.urgency || '').slice(1)} Priority</span>
                            <span class="view-count" id="viewcount-${complaint.id}">
                                <i class="far fa-eye"></i> ${complaint.view_count} views
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <span class="status-badge status-${complaint.status}">
                        ${(complaint.status || '').replace('_', ' ')}
                    </span>

                    <span class="category-tag" style="background: ${complaint.category_color}20; color: ${complaint.category_color};">
                        <i class="fas fa-${complaint.category_icon}"></i>
                        ${complaint.category_name}
                    </span>

                    <div class="text-sm text-muted mb-2">
                        <i class="fas fa-hashtag"></i> ${complaint.complaint_code}
                    </div>

                    <h3 class="complaint-title">${complaint.title}</h3>
                    <div class="complaint-description">${complaint.description}</div>

                    ${carouselHtml}

                    ${complaint.location ? `
                        <div class="complaint-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>${complaint.location}</span>
                        </div>
                    ` : ''}
                </div>

                <div class="card-footer">
                    <button
                        class="action-btn upvote ${isUpvoted ? 'active' : ''}"
                        data-complaint-id="${complaint.id}"
                        onclick="vote(${complaint.id}, 'upvote')"
                    >
                        <i class="fas fa-thumbs-up"></i>
                        <span class="vote-count" id="upvotes-${complaint.id}">${complaint.upvotes}</span>
                    </button>

                    <button
                        class="action-btn downvote ${isDownvoted ? 'active' : ''}"
                        data-complaint-id="${complaint.id}"
                        onclick="vote(${complaint.id}, 'downvote')"
                    >
                        <i class="fas fa-thumbs-down"></i>
                        <span class="vote-count" id="downvotes-${complaint.id}">${complaint.downvotes}</span>
                    </button>

                    <button class="action-btn" onclick="showComments(${complaint.id})">
                        <i class="far fa-comment"></i>
                        <span>Comment</span>
                    </button>

                    <button class="action-btn" onclick="shareComplaint(${complaint.id})">
                        <i class="fas fa-share"></i>
                        <span>Share</span>
                    </button>
                </div>

                <div class="comments-section" id="comments-${complaint.id}" style="display: none;"></div>
            `;

            try {
                if (window.initMediaCarousels) window.initMediaCarousels(card);
            } catch (e) {}

            return card;
        }

        // scroll to complaint specified in URL and show comments if present
        (function() {
            const params = new URLSearchParams(window.location.search);
            let cid = params.get('complaint_id');
            const commentId = params.get('comment_id');
            if (commentId) {
                // Fetch complaint_id from comment_id
                fetch(`<?php echo APP_URL; ?>/api/get_complaint_from_comment.php?comment_id=${commentId}`)
                    .then(resp => resp.json())
                    .then(data => {
                        if (data.success && data.complaint_id) {
                            cid = data.complaint_id;
                            const card = document.getElementById(`complaint-${cid}`);
                            if (card) {
                                card.scrollIntoView({behavior:'smooth', block:'center'});
                                if (typeof showComments === 'function') {
                                    showComments(cid, commentId);
                                }
                                incrementComplaintView(cid);
                            }
                        }
                    })
                    .catch(err => console.error('Error fetching complaint_id:', err));
            } else if (cid) {
                const card = document.getElementById(`complaint-${cid}`);
                if (card) {
                    card.scrollIntoView({behavior:'smooth', block:'center'});
                    try {
                        card.style.outline = '2px solid rgba(102, 126, 234, 0.9)';
                        card.style.boxShadow = '0 0 0 6px rgba(102, 126, 234, 0.15)';
                        card.style.borderRadius = '16px';
                        setTimeout(() => {
                            card.style.outline = '';
                            card.style.boxShadow = '';
                            card.style.borderRadius = '';
                        }, 3500);
                    } catch (e) {}
                    incrementComplaintView(cid);
                }
            }
        })();

        // Show toast notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type} animate-slide-in-right`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            
            const container = document.createElement('div');
            container.className = 'toast-container';
            container.appendChild(toast);
            document.body.appendChild(container);
            
            setTimeout(() => {
                toast.classList.add('animate-scale-out');
                setTimeout(() => {
                    container.remove();
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html>
