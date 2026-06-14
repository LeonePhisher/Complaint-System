<?php
// Prevent direct access
defined('ROOT_PATH') or die('Direct access not permitted');

$current_page = basename($_SERVER['PHP_SELF']);
$admin_role = $_SESSION['admin_role'] ?? '';
?>

<style>
.admin-navbar {
    position: sticky;
    top: 0;
    z-index: 1000;
    backdrop-filter: blur(12px);
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.85), rgba(118, 75, 162, 0.85));
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding: 0 2rem;
}

.admin-nav-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 70px;
    max-width: 1400px;
    margin: 0 auto;
}

.admin-logo {
    font-size: 1.4rem;
    font-weight: 600;
    color: #fff;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.admin-menu {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.admin-menu a {
    color: rgba(255,255,255,0.85);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    position: relative;
}

.admin-menu a:hover {
    color: #fff;
}

.admin-menu a.active::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 0;
    width: 100%;
    height: 3px;
    background: #fff;
    border-radius: 3px;
}

.admin-user {
    display: flex;
    align-items: center;
    gap: 1rem;
    color: #fff;
    font-size: 0.9rem;
}

.logout-btn {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    background: rgba(255,255,255,0.15);
    color: #fff;
    text-decoration: none;
    font-weight: 500;
    transition: 0.3s;
}

.logout-btn:hover {
    background: rgba(255,255,255,0.25);
}

@media (max-width: 900px) {
    .admin-menu {
        display: none;
    }
}
</style>

<nav class="admin-navbar">
    <div class="admin-nav-container">

        <!-- Logo -->
        <a href="<?php echo APP_URL; ?>/pages/admin/dashboard.php" class="admin-logo">
            <i class="fas fa-shield-alt"></i>
            <?php echo APP_NAME; ?> Admin
        </a>

        <!-- Menu -->
        <div class="admin-menu">
            <a href="<?php echo APP_URL; ?>/pages/admin/dashboard.php"
               class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>

            <a href="<?php echo APP_URL; ?>/pages/admin/complaints.php"
               class="<?php echo $current_page == 'complaints.php' ? 'active' : ''; ?>">
                <i class="fas fa-inbox"></i> Complaints
            </a>

            <?php if ($admin_role === 'super_admin'): ?>
            <a href="<?php echo APP_URL; ?>/pages/admin/users.php"
               class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Users
            </a>

            <a href="<?php echo APP_URL; ?>/pages/admin/categories.php"
               class="<?php echo $current_page == 'categories.php' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i> Categories
            </a>
            <?php endif; ?>

            <a href="<?php echo APP_URL; ?>/pages/admin/reports.php"
               class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
        </div>

        <!-- User + Logout -->
        <div class="admin-user">
            <span>
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
            </span>
            <a href="<?php echo APP_URL; ?>/includes/auth/admin-logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

    </div>
</nav>
