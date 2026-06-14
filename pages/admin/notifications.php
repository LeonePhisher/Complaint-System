<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../includes/auth/session.inc.php';
require_once '../../includes/utilities/helpers.php';

// Ensure admin
if (!isAdmin()) {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = intval(getSetting('notifications_per_page', 7));

try {
    // Get total count
    $stmt = db()->prepare("SELECT COUNT(*) as total FROM notifications WHERE admin_id = ?");
    $stmt->execute([$admin_id]);
    $total = $stmt->fetch()['total'] ?? 0;
    $total_pages = ceil($total / $per_page);
    
    // Get paginated notifications
    $offset = ($page - 1) * $per_page;
    $stmt = db()->prepare("
        SELECT * FROM notifications 
        WHERE admin_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$admin_id, $per_page, $offset]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching admin notifications: ' . $e->getMessage());
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
    <title>Notifications - Admin Panel</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/glassmorphism.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/animations.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/responsive.css">
    <style>
        .notifications-page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .notification-item {
            padding: 1rem;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
        }

        .notification-item.unread {
            background: rgba(124, 147, 251, 0.1);
            border-color: rgba(124, 147, 251, 0.35);
        }

        .notification-icon {
            color: var(--primary-color);
            font-size: 1.25rem;
            flex-shrink: 0;
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
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .notification-time {
            font-size: 0.85rem;
            color: var(--text-muted);
            white-space: nowrap;
            flex-shrink: 0;
        }

        .action-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: var(--primary-dark);
            box-shadow: 0 4px 6px rgba(102, 126, 234, 0.2);
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: var(--primary-dark);
        }

        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.9rem;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        h2 {
            color: var(--text-primary);
        }

        p {
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
<?php include '../../includes/layout/admin-nav.php'; ?>

<div class="notifications-page">
    <div class="glass-card" style="padding: 1.5rem;">
        <div style="display:flex; align-items:center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem;">
            <div>
                <h2 style="margin:0;">Notifications</h2>
                <p style="margin:0.25rem 0 0; color: var(--text-secondary);">Updates for your admin account</p>
            </div>
            <button id="markAllBtn" class="btn btn-secondary" type="button">Mark all as read</button>
        </div>

    <div id="notificationsContainer">
        <?php if ($total > 0): ?>
            <div style="margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.9rem;">
                Showing <?php echo (($page - 1) * $per_page) + 1; ?> to <?php echo min($page * $per_page, $total); ?> of <?php echo $total; ?> notifications
            </div>
            
            <?php foreach ($notifications as $note): ?>
                <?php
                    $url = '';
                    if (!empty($note['related_type']) && !empty($note['related_id'])) {
                        if ($note['related_type'] === 'complaint') {
                            $url = APP_URL . '/pages/admin/complaints.php?status=pending';
                        } elseif ($note['related_type'] === 'rejection_request') {
                            $url = APP_URL . '/pages/admin/complaints.php?status=pending';
                        } elseif ($note['related_type'] === 'user') {
                            $url = APP_URL . '/pages/admin/users.php?user_id=' . intval($note['related_id']);
                        } elseif ($note['related_type'] === 'admin') {
                            $url = APP_URL . '/pages/admin/users.php?admin_id=' . intval($note['related_id']);
                        }
                    }
                ?>
                <div class="notification-item <?php echo $note['is_read'] ? '' : 'unread'; ?>"
                     data-id="<?php echo $note['id']; ?>"
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
                    <?php else: ?>
                        <button class="btn btn-sm" disabled style="margin: 0.25rem;"><i class="fas fa-chevron-left"></i> First</button>
                        <button class="btn btn-sm" disabled style="margin: 0.25rem;"><i class="fas fa-chevron-left"></i> Previous</button>
                    <?php endif; ?>
                    
                    <div style="display: inline-block; margin: 0 1rem;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </div>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="btn btn-sm" style="margin: 0.25rem;">Next <i class="fas fa-chevron-right"></i></a>
                        <a href="?page=<?php echo $total_pages; ?>" class="btn btn-sm" style="margin: 0.25rem;">Last <i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <button class="btn btn-sm" disabled style="margin: 0.25rem;">Next <i class="fas fa-chevron-right"></i></button>
                        <button class="btn btn-sm" disabled style="margin: 0.25rem;">Last <i class="fas fa-chevron-right"></i></button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p>You haven&rsquo;t received any notifications yet.</p>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Notification Details Modal -->
<div class="modal fade" id="adminNotificationDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adminNotificationDetailsTitle">Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div style="display:flex; gap: 0.75rem; align-items:flex-start;">
                    <div class="notification-icon" id="adminNotificationDetailsIcon" aria-hidden="true">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div style="flex: 1;">
                        <div id="adminNotificationDetailsTime" style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 0.75rem;"></div>
                        <div id="adminNotificationDetailsMessage" style="white-space: pre-wrap; line-height: 1.7;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                <a href="#" id="adminNotificationOpenRelated" class="btn btn-outline" style="display:none;">Open related</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// clicking item opens modal with full notification details; mark read on modal close
let __pendingNotifIdToMarkRead = null;

function markNotificationRead(notificationId) {
    return fetch('<?php echo APP_URL; ?>/api/notifications_unified.php?action=mark_read', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ notification_id: notificationId })
    }).then(r => r.json()).catch(() => ({ success: false }));
}

function refreshAdminNavBadge() {
    // admin-nav defines adminRefreshUnreadCount() globally; call if present.
    if (typeof adminRefreshUnreadCount === 'function') {
        adminRefreshUnreadCount();
    }
}

const notifModalEl = document.getElementById('adminNotificationDetailsModal');
if (notifModalEl) {
    notifModalEl.addEventListener('hidden.bs.modal', function() {
        if (!__pendingNotifIdToMarkRead) return;
        const id = __pendingNotifIdToMarkRead;
        __pendingNotifIdToMarkRead = null;

        markNotificationRead(id).finally(() => {
            // Update UI state and nav badge
            document.querySelectorAll(`.notification-item[data-id="${id}"]`).forEach(el => el.classList.remove('unread'));
            refreshAdminNavBadge();
        });
    });
}

document.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', function() {
        const notifId = parseInt(this.dataset.id, 10);
        if (!notifId || !notifModalEl) return;

        const notifModal = (typeof bootstrap !== 'undefined' && bootstrap.Modal)
            ? bootstrap.Modal.getOrCreateInstance(notifModalEl)
            : null;
        if (!notifModal) return;

        __pendingNotifIdToMarkRead = notifId;

        const title = this.querySelector('.notification-title')?.innerText || 'Notification';
        const message = this.querySelector('.notification-message')?.innerText || '';
        const time = this.querySelector('.notification-time')?.innerText || '';
        const iconClass = this.querySelector('.notification-icon i')?.className || 'fas fa-bell';
        const url = this.dataset.url || '';

        const titleEl = document.getElementById('adminNotificationDetailsTitle');
        const msgEl = document.getElementById('adminNotificationDetailsMessage');
        const timeEl = document.getElementById('adminNotificationDetailsTime');
        const iconEl = document.querySelector('#adminNotificationDetailsIcon i');
        const openRelated = document.getElementById('adminNotificationOpenRelated');

        if (titleEl) titleEl.textContent = title;
        if (msgEl) msgEl.textContent = message;
        if (timeEl) timeEl.textContent = time;
        if (iconEl) iconEl.className = iconClass;

        if (openRelated) {
            if (url) {
                openRelated.style.display = '';
                openRelated.href = url;
                openRelated.onclick = function(e) {
                    e.preventDefault();
                    markNotificationRead(notifId).finally(() => {
                        refreshAdminNavBadge();
                        window.location.href = url;
                    });
                };
            } else {
                openRelated.style.display = 'none';
                openRelated.href = '#';
                openRelated.onclick = null;
            }
        }

        notifModal.show();
    });
});


// mark all button
const markAllBtn = document.getElementById('markAllBtn');
if (markAllBtn) {
    markAllBtn.addEventListener('click', function() {
        fetch('<?php echo APP_URL; ?>/api/notifications_unified.php?action=mark_all_read', {
            method: 'POST'
        }).then(r=>r.json()).then(d=>{
            if (d.success) {
                document.querySelectorAll('.notification-item').forEach(el=>el.classList.remove('unread'));
                if (typeof checkNotifications === 'function') checkNotifications();
                refreshAdminNavBadge();
            }
        });
    });
}
</script>
</body>
</html>
