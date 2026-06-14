<?php
// Student Navigation Component
if (!isset($_SESSION['student_id'])) {
    return;
}

$user_name = $_SESSION['user_name'] ?? 'Student';
$user_avatar = $_SESSION['user_avatar'] ?? '#667eea';
$user_initials = getInitials($user_name);

// Get unread notification count
$notification_count = 0;
try {
    $stmt = db()->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$_SESSION['student_id']]);
    $result = $stmt->fetch();
    $notification_count = $result['count'] ?? 0;
} catch (PDOException $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
}
?>
<nav class="glass-nav">
    <div class="container">
        <div class="nav-content">
            <!-- Logo -->
            <div class="nav-logo">
                <a href="<?php echo APP_URL; ?>/pages/student/dashboard.php" class="logo-link">
                    <i class="fas fa-comment-dots logo-icon"></i>
                    <span class="logo-text"><?php echo APP_NAME; ?></span>
                </a>
            </div>

            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-btn" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Navigation Links -->
            <div class="nav-links" id="navLinks">
                <a href="<?php echo APP_URL; ?>/pages/student/feed.php" class="nav-link">
                    <i class="fas fa-stream"></i>
                    <span>Feed</span>
                </a>
                
                <a href="<?php echo APP_URL; ?>/pages/student/submit.php" class="nav-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>Submit</span>
                </a>
                
                <a href="<?php echo APP_URL; ?>/pages/student/my-complaints.php" class="nav-link">
                    <i class="fas fa-history"></i>
                    <span>My Complaints</span>
                </a>
                
                <div class="nav-dropdown">
                    <button id="notificationBell" class="nav-dropdown-toggle">
                        <i class="fas fa-bell"></i>
                        <?php if ($notification_count > 0): ?>
                            <span class="notification-badge"><?php echo $notification_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="notificationsPanel" class="nav-dropdown-menu">
                        <div class="dropdown-header">
                            <h4>Notifications</h4>
                            <a href="#" class="mark-all-read">Mark all as read</a>
                        </div>
                        <div class="dropdown-content" id="notificationList">
                            <!-- Notifications will be loaded via AJAX -->
                            <div class="empty-notifications">
                                <i class="fas fa-bell-slash"></i>
                                <p>No new notifications</p>
                            </div>
                        </div>
                        <div class="dropdown-footer">
                            <a href="<?php echo APP_URL; ?>/pages/student/notifications.php">View all</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Menu --> <button class="user-dropdown-toggle">
            <div class="user-menu">
                <div class="user-avatar" style="background: <?php echo $user_avatar; ?>">
                    <?php echo $user_initials; ?>
                </div>
               

                 <div class="user-dropdown">
                   
                        <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    
                    <div class="user-dropdown-menu">
                        <a href="<?php echo APP_URL; ?>/pages/student/profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a href="<?php echo APP_URL; ?>/pages/student/settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo APP_URL; ?>/includes/auth/logout.inc.php" class="dropdown-item text-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
                
            </div></button>
        </div>
    </div>
</nav>

<!-- Student complaint details modal (used by notifications for rejected/resolved/etc.) -->
<div class="sc-modal" id="studentComplaintModal" aria-hidden="true">
    <div class="sc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="studentComplaintModalTitle">
        <div class="sc-modal-header">
            <h3 class="sc-modal-title" id="studentComplaintModalTitle">Complaint Details</h3>
            <button type="button" class="sc-modal-close" id="studentComplaintModalClose" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="sc-modal-body" id="studentComplaintModalBody"></div>
    </div>
</div>

<style>
    .glass-nav {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--glass-border);
        padding: 0.75rem 0;
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .nav-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 2rem;
    }

    .nav-logo {
        flex-shrink: 0;
    }

    .logo-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none;
        color: var(--text-primary);
        font-weight: 600;
        font-size: 1.25rem;
    }

    .logo-icon {
        font-size: 1.5rem;
        background: var(--gradient-primary);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .nav-links {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        flex: 1;
        justify-content: center;
    }

    .nav-link {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-secondary);
        text-decoration: none;
        padding: 0.5rem 0.75rem;
        border-radius: var(--radius-md);
        transition: all 0.2s ease;
        position: relative;
    }

    .nav-link:hover,
    .nav-link.active {
        color: var(--primary-color);
        background: rgba(102, 126, 234, 0.1);
    }

    .nav-link i {
        font-size: 1.1rem;
    }

    .nav-dropdown {
        position: relative;
    }

    .nav-dropdown-toggle {
        background: none;
        border: none;
        color: var(--text-secondary);
        padding: 0.5rem;
        cursor: pointer;
        position: relative;
        border-radius: var(--radius-md);
        transition: all 0.2s ease;
    }

    .nav-dropdown-toggle:hover {
        color: var(--primary-color);
        background: rgba(102, 126, 234, 0.1);
    }

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
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .nav-dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        width: 320px;
        display: none;
        z-index: 1001;
        margin-top: 0.5rem;
    }

    .nav-dropdown:hover .nav-dropdown-menu {
        display: block;
    }

    /* allow toggling via JS */
    .nav-dropdown .nav-dropdown-menu.show {
        display: block;
    }

    .notification-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: background 0.2s;
    }
    .notification-item.unread {
        background: var(--bg-secondary);
    }
    .notification-item.read {
        opacity: 0.7;
    }
    .notification-item:hover {
        background: var(--bg-secondary);
    }
    .notification-icon {
        flex-shrink: 0;
        font-size: 1.2rem;
        color: var(--primary-color);
    }
    .notification-content {
        flex: 1;
    }
    .notification-time {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .dropdown-header {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .dropdown-header h4 {
        margin: 0;
        font-size: 1rem;
    }

    .mark-all-read {
        font-size: 0.875rem;
        color: var(--primary-color);
        text-decoration: none;
    }

    .dropdown-content {
        max-height: 400px;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .empty-notifications {
        text-align: center;
        padding: 2rem 1rem;
        color: var(--text-muted);
    }

    .empty-notifications i {
        font-size: 2rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .dropdown-footer {
        padding: 0.75rem 1rem;
        border-top: 1px solid var(--border-color);
        text-align: center;
    }

    .user-menu {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-shrink: 0;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: white;
        flex-shrink: 0;
    }

    .user-dropdown {
        position: relative;
    }

    .user-dropdown-toggle {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: none;
        border: none;
        color: var(--text-primary);
        cursor: pointer;
        padding: 0.5rem;
        border-radius: var(--radius-md);
        transition: all 0.2s ease;
    }

    .user-dropdown-toggle:hover {
        background: rgba(102, 126, 234, 0.1);
    }

    .user-dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        width: 200px;
        display: none;
        z-index: 1001;
        margin-top: 0.5rem;
    }

    .user-dropdown:hover .user-dropdown-menu {
        display: block;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        color: var(--text-primary);
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .dropdown-item:hover {
        background: var(--bg-secondary);
        color: var(--primary-color);
    }

    .dropdown-item.text-danger:hover {
        color: var(--danger-color);
    }

    .dropdown-divider {
        height: 1px;
        background: var(--border-color);
        margin: 0.5rem 0;
    }

    .mobile-menu-btn {
        display: none;
        background: none;
        border: none;
        color: var(--text-primary);
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0.5rem;
    }

    @media (max-width: 1024px) {
        .mobile-menu-btn {
            display: block;
        }

        .nav-links {
            position: fixed;
            top: 0;
            left: -100%;
            width: 80%;
            max-width: 300px;
            height: 100vh;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            flex-direction: column;
            align-items: flex-start;
            padding: 2rem;
            gap: 1rem;
            transition: left 0.3s ease;
            z-index: 999;
            overflow-y: auto;
        }

        .nav-links.open {
            left: 0;
        }

        .nav-link {
            width: 100%;
            justify-content: flex-start;
        }

        .nav-dropdown-menu {
            position: static;
            width: 100%;
            margin-top: 1rem;
            display: none;
        }

        /* Touch devices don't have hover; JS toggles .show */
        .nav-dropdown-menu.show {
            display: block;
        }
        
        .nav-dropdown:hover .nav-dropdown-menu {
            display: block;
        }

        .user-dropdown-menu {
            right: 0;
            left: auto;
        }
    }

    @media (max-width: 640px) {
        .nav-content {
            gap: 1rem;
        }

        .logo-text {
            display: none;
        }

        .user-name {
            display: none;
        }
    }

    /* ---- Notification complaint modal (student) ---- */
    .sc-modal {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        z-index: 2000;
    }

    .sc-modal.active {
        display: flex;
    }

    .sc-modal-dialog {
        width: min(920px, 94vw);
        max-height: 90vh;
        overflow: auto;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
    }

    .sc-modal-header {
        position: sticky;
        top: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        background: var(--bg-secondary);
        border-bottom: 1px solid var(--border-color);
        z-index: 1;
    }

    .sc-modal-title {
        margin: 0;
        font-size: 1.1rem;
        color: var(--text-primary);
    }

    .sc-modal-close {
        border: none;
        background: transparent;
        color: var(--text-primary);
        cursor: pointer;
        padding: 0.5rem;
        border-radius: var(--radius-md);
    }

    .sc-modal-close:hover {
        background: var(--bg-tertiary);
    }

    .sc-modal-body {
        padding: 1.25rem;
        color: var(--text-primary);
    }

    .sc-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .sc-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        border: 1px solid var(--border-color);
        background: rgba(255, 255, 255, 0.04);
        font-size: 0.8rem;
        color: var(--text-primary);
    }

    .sc-pill-muted {
        opacity: 0.85;
    }

    .sc-title {
        margin: 0.25rem 0 0.5rem;
        font-size: 1.25rem;
        color: var(--text-primary);
    }

    .sc-small {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem 1rem;
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin-bottom: 1rem;
    }

    .sc-small i { opacity: 0.85; }

    .sc-section {
        margin-top: 1rem;
    }

    .sc-section-title {
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }

    .sc-box {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 0.9rem;
        color: var(--text-primary);
        white-space: pre-wrap;
        word-break: break-word;
    }

    .sc-banner {
        border-radius: var(--radius-md);
        padding: 0.9rem;
        border: 1px solid var(--border-color);
        margin-top: 1rem;
    }

    .sc-banner-title {
        font-weight: 700;
        margin-bottom: 0.35rem;
    }

    .sc-banner-text {
        color: var(--text-secondary);
    }

    .sc-banner-sub {
        margin-top: 0.35rem;
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    .sc-banner-danger {
        border-color: rgba(245, 101, 101, 0.45);
        background: rgba(245, 101, 101, 0.10);
    }

    .sc-banner-success {
        border-color: rgba(72, 187, 120, 0.45);
        background: rgba(72, 187, 120, 0.10);
    }
</style>

<script>
    // Mobile menu toggle
    document.getElementById('mobileMenuToggle')?.addEventListener('click', function() {
        const navLinks = document.getElementById('navLinks');
        navLinks.classList.toggle('open');
    });

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        const navLinks = document.getElementById('navLinks');
        const mobileMenuBtn = document.getElementById('mobileMenuToggle');
        
        if (navLinks.classList.contains('open') && 
            !navLinks.contains(e.target) && 
            !mobileMenuBtn?.contains(e.target)) {
            navLinks.classList.remove('open');
        }
    });

    // notifications are handled globally in assets/js/main.js
</script>
<script src="<?php echo APP_URL; ?>/assets/js/main.js" defer></script>
