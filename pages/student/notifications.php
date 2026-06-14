<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../includes/utilities/helpers.php';

// ensure student
if (!isLoggedIn() || $_SESSION['user_role'] !== 'student') {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

$student_id = $_SESSION['student_id'];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = intval(getSetting('notifications_per_page', 7));

try {
    // Get total count
    $stmt = db()->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $total = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $per_page);
    
    // Get paginated notifications
    $offset = ($page - 1) * $per_page;
    $stmt = db()->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$student_id, $per_page, $offset]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching notifications: ' . $e->getMessage());
    $notifications = [];
    $total = 0;
    $total_pages = 1;
}

function getNotificationIcon($type) {
    $icons = [
        'success' => 'check-circle',
        'error'   => 'exclamation-circle',
        'warning' => 'exclamation-triangle',
        'info'    => 'info-circle',
        'system'  => 'cog',
    ];
    return $icons[$type] ?? 'bell';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta name="app-url" content="<?php echo APP_URL; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - HTU Complaints</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/responsive.css">
    <style>
        .notification-item {
            padding: 1rem;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
        }

        .notification-item.read {
            opacity: 0.7;
        }

        .notification-icon {
            color: var(--primary-color);
            font-size: 1.25rem;
            min-width: 30px;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .notification-message {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.4;
        }

        .notification-time {
            color: var(--text-muted);
            font-size: 0.75rem;
            white-space: nowrap;
        }

        .action-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: var(--primary-dark);
            box-shadow: 0 4px 6px rgba(102, 126, 234, 0.2);
        }

        h2 {
            color: var(--text-primary);
        }

        p {
            color: var(--text-secondary);
        }
    </style>
<body>
<?php include '../../includes/layout/student-nav.php'; ?>

<div class="container" style="padding:2rem 1rem;">
    <h2 class="mb-4">Your Notifications</h2>
    <div id="notificationsContainer">
        <button id="markAllBtn" class="action-btn" style="margin-bottom:1rem;">Mark all as read</button>
        
        <?php if ($total > 0): ?>
            <div style="margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.9rem;">
                Showing <?php echo (($page - 1) * $per_page) + 1; ?> to <?php echo min($page * $per_page, $total); ?> of <?php echo $total; ?> notifications
            </div>
            
            <?php foreach ($notifications as $note): ?>
                <?php
                    $url = '';
                    $related_type = (string)($note['related_type'] ?? '');
                    $related_id = (int)($note['related_id'] ?? 0);

                    $title_l = strtolower((string)($note['title'] ?? ''));
                    $msg_l = strtolower((string)($note['message'] ?? ''));

                    $is_vote_notification = (strpos($title_l, 'upvote') !== false) || (strpos($title_l, 'downvote') !== false) ||
                                            (strpos($msg_l, 'upvote') !== false) || (strpos($msg_l, 'downvote') !== false);

                    // Deep-link rules:
                    // - comment notifications link by comment_id so the feed can resolve complaint_id + highlight comment
                    // - vote notifications link by complaint_id
                    // - published notifications link by complaint_id
                    if ($related_id > 0) {
                        if ($related_type === 'comment') {
                            $url = APP_URL . '/pages/student/feed.php?comment_id=' . $related_id;
                        } elseif ($related_type === 'complaint') {
                            if (stripos($note['title'] ?? '', 'published') !== false || $is_vote_notification) {
                                $url = APP_URL . '/pages/student/feed.php?complaint_id=' . $related_id;
                            }
                        }
                    }
                ?>
                <div class="notification-item <?php echo $note['is_read'] ? 'read' : 'unread'; ?>"
                      data-id="<?php echo $note['id']; ?>"
                      data-title="<?php echo htmlspecialchars($note['title'] ?? '', ENT_QUOTES); ?>"
                      data-related-type="<?php echo htmlspecialchars($note['related_type'] ?? '', ENT_QUOTES); ?>"
                      data-related-id="<?php echo htmlspecialchars((string)($note['related_id'] ?? ''), ENT_QUOTES); ?>"
                      <?php if ($url): ?>data-url="<?php echo $url; ?>"<?php endif; ?>>
                    <div class="notification-icon"><i class="fas fa-<?php echo getNotificationIcon($note['type'] ?? 'info'); ?>"></i></div>
                    <div class="notification-content">
                        <div class="notification-title"><?php echo htmlspecialchars($note['title']); ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars($note['message']); ?></div>
                    </div>
                    <div class="notification-time"><?php echo timeAgo($note['created_at']); ?></div>
                </div>
            <?php endforeach; ?>
            
            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination" style="margin-top: 2rem; text-align: center;">
                    <?php if ($page > 1): ?>
                        <a href="?page=1" class="btn btn-sm" style="margin: 0.25rem;"><i class="fas fa-chevron-left"></i> First</a>
                        <a href="?page=<?php echo $page - 1; ?>" class="btn btn-sm" style="margin: 0.25rem;"><i class="fas fa-chevron-left"></i> Previous</a>
                    <?php endif; ?>
                    
                    <div style="display: inline-block; margin: 0 1rem;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </div>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="btn btn-sm" style="margin: 0.25rem;">Next <i class="fas fa-chevron-right"></i></a>
                        <a href="?page=<?php echo $total_pages; ?>" class="btn btn-sm" style="margin: 0.25rem;">Last <i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p>You haven&rsquo;t received any notifications yet.</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const baseUrl = window.baseUrl || '<?php echo APP_URL; ?>';

    function markRead(notifId) {
        return fetch(`${baseUrl}/api/notifications_unified.php?action=mark_read`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ notification_id: notifId })
        }).then(r => r.json()).catch(() => ({success:false}));
    }

    function markAllRead() {
        return fetch(`${baseUrl}/api/notifications_unified.php?action=mark_all_read`, { method: 'POST' })
            .then(r => r.json()).catch(() => ({success:false}));
    }

    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function() {
            const notifId = this.dataset.id;
            const relatedType = (this.dataset.relatedType || '').toLowerCase();
            const relatedId = this.dataset.relatedId || '';
            const title = (this.dataset.title || '').toLowerCase();
            const url = this.dataset.url || '';

            // If a deep-link URL is provided (comments/votes/published), mark as read then navigate.
            if (url) {
                if (notifId) {
                    markRead(notifId).finally(() => {
                        this.classList.remove('unread');
                        this.classList.add('read');
                        if (typeof checkNotifications === 'function') checkNotifications();
                        window.location.href = url;
                    });
                } else {
                    window.location.href = url;
                }
                return;
            }

            // Complaint notifications: open modal with full details when not deep-linked.
            if (notifId && relatedType === 'complaint' && relatedId && !title.includes('published')) {
                if (typeof window.openStudentComplaintDetailsModal === 'function') {
                    window.openStudentComplaintDetailsModal(relatedId, notifId, this);
                } else {
                    // Fallback: just mark read
                    markRead(notifId);
                    this.classList.remove('unread');
                    this.classList.add('read');
                    if (typeof checkNotifications === 'function') checkNotifications();
                }
                return;
            }

            // Default: just mark read
            if (notifId) {
                markRead(notifId);
                this.classList.remove('unread');
                this.classList.add('read');
                if (typeof checkNotifications === 'function') checkNotifications();
            }
        });
    });

    const markAllBtn = document.getElementById('markAllBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
            markAllRead().then(d => {
                if (d && d.success) {
                    document.querySelectorAll('.notification-item').forEach(el => {
                        el.classList.remove('unread');
                        el.classList.add('read');
                    });
                    if (typeof checkNotifications === 'function') checkNotifications();
                }
            });
        });
    }
});
</script>
</body>
</html>
