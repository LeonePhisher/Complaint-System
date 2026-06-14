

<?php
/**
 * Admin Navigation Component (partial)
 * Included inside admin pages; must not output a full HTML document.
 */

// Get current page for active states
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Bootstrap is required for admin modals (e.g. reject complaint modal). -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous" defer></script>

<style>
        /* Bootstrap loads after theme.css (this file is included in <body>), so we must re-assert
           Bootstrap theme variables here; otherwise Bootstrap's defaults can force a white body bg
           even when data-theme="dark". */
        :root,
        html[data-theme="light"],
        html[data-theme="dark"] {
            --bs-body-bg: var(--bg-primary);
            --bs-body-color: var(--text-primary);
            --bs-border-color: var(--border-color);
            --bs-secondary-bg: var(--bg-secondary);
            --bs-tertiary-bg: var(--bg-tertiary);
            --bs-link-color: var(--primary-color);
            --bs-link-hover-color: var(--primary-dark);
            --bs-primary: var(--primary-color);
            --bs-success: var(--success-color);
            --bs-danger: var(--danger-color);
            --bs-warning: var(--warning-color);
            --bs-info: var(--info-color);
            --bs-modal-bg: var(--bg-secondary);
            --bs-modal-color: var(--text-primary);
            --bs-modal-border-color: var(--border-color);
            --bs-modal-header-border-color: var(--border-color);
            --bs-modal-footer-border-color: var(--border-color);
            --bs-dropdown-bg: var(--bg-secondary);
            --bs-dropdown-link-color: var(--text-primary);
            --bs-dropdown-link-hover-color: var(--text-primary);
            --bs-dropdown-link-hover-bg: var(--bg-tertiary);
        }

        html[data-theme] body {
            background-color: var(--bg-primary) !important;
            color: var(--text-primary) !important;
        }

        /* Bootstrap utility classes that often force light colors */
        html[data-theme="dark"] .text-dark { color: var(--text-primary) !important; }
        html[data-theme="dark"] .text-muted { color: var(--text-muted) !important; }
        html[data-theme="dark"] .bg-light { background-color: var(--bg-secondary) !important; color: var(--text-primary) !important; }
        html[data-theme="dark"] .bg-white { background-color: var(--bg-primary) !important; color: var(--text-primary) !important; }

        html[data-theme] .modal-content {
            background-color: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        html[data-theme] .modal-header,
        html[data-theme] .modal-footer {
            border-color: var(--border-color) !important;
        }

        /* Re-assert app button styles after Bootstrap (Bootstrap loads after theme.css on admin pages). */
        html[data-theme] .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 0.875rem;
            line-height: 1;
            text-align: center;
            cursor: pointer;
            transition: var(--transition-normal);
            border: none;
            outline: none;
            position: relative;
            overflow: hidden;
        }

        html[data-theme] .btn-primary {
            background: var(--gradient-primary);
            color: #fff;
        }

        html[data-theme] .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        html[data-theme] .btn-danger {
            background: linear-gradient(135deg, #f56565 0%, #ed64a6 100%);
            color: #fff;
        }

        html[data-theme] .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        html[data-theme] .btn-outline:hover {
            background: var(--primary-color);
            color: #fff;
        }

        /* Forms (Bootstrap can force white inputs) */
        html[data-theme] .form-control,
        html[data-theme] .form-select {
            background-color: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        html[data-theme] .form-control::placeholder {
            color: var(--text-muted) !important;
        }

        html[data-theme] .form-control:focus,
        html[data-theme] .form-select:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
        }

        /* Tables */
        html[data-theme] .table,
        html[data-theme] table {
            color: var(--text-primary);
        }

        html[data-theme="dark"] .table > :not(caption) > * > * {
            background-color: transparent;
            color: var(--text-primary);
            border-color: var(--border-color);
        }

        /* DataTables (Bootstrap 5 integration) dark-mode overrides */
        html[data-theme="dark"] .dataTables_wrapper {
            color: var(--text-secondary);
        }

        html[data-theme="dark"] .dataTables_wrapper .dataTables_length,
        html[data-theme="dark"] .dataTables_wrapper .dataTables_filter,
        html[data-theme="dark"] .dataTables_wrapper .dataTables_info,
        html[data-theme="dark"] .dataTables_wrapper .dataTables_processing,
        html[data-theme="dark"] .dataTables_wrapper .dataTables_paginate {
            color: var(--text-secondary) !important;
        }

        html[data-theme="dark"] .dataTables_wrapper .form-control,
        html[data-theme="dark"] .dataTables_wrapper .form-select,
        html[data-theme="dark"] .dataTables_wrapper input,
        html[data-theme="dark"] .dataTables_wrapper select {
            background-color: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        html[data-theme="dark"] table.dataTable.table {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-primary);
            --bs-table-border-color: var(--border-color);
        }

        html[data-theme="dark"] table.dataTable.table thead th {
            color: var(--text-secondary) !important;
            border-color: var(--border-color) !important;
        }

        html[data-theme="dark"] table.dataTable.table tbody td {
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        html[data-theme="dark"] .dataTables_wrapper .page-link {
            background-color: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }

        html[data-theme="dark"] .dataTables_wrapper .page-item.disabled .page-link {
            background-color: var(--bg-secondary) !important;
            color: var(--text-muted) !important;
            border-color: var(--border-color) !important;
            opacity: 0.7;
        }

        html[data-theme="dark"] .dataTables_wrapper .page-item.active .page-link {
            background: var(--gradient-primary) !important;
            border-color: transparent !important;
            color: #fff !important;
        }

        html[data-theme="dark"] div.dt-buttons .btn,
        html[data-theme="dark"] div.dt-buttons button,
        html[data-theme="dark"] button.dt-button,
        html[data-theme="dark"] a.dt-button,
        html[data-theme="dark"] input.dt-button {
            background-color: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            border: 1px solid var(--border-color) !important;
        }

        html[data-theme="dark"] div.dt-buttons .btn:hover,
        html[data-theme="dark"] div.dt-buttons button:hover,
        html[data-theme="dark"] button.dt-button:hover,
        html[data-theme="dark"] a.dt-button:hover,
        html[data-theme="dark"] input.dt-button:hover {
            background-color: var(--bg-tertiary) !important;
            border-color: var(--text-muted) !important;
        }

        /* Admin Navigation Styles */
        .admin-nav {
            --admin-nav-height: 90px;
            position: sticky;
            top: 0;
            z-index: 1000;
            background: var(--glass-bg-dark, rgba(17, 25, 40, 0.75));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .admin-nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 0.3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: var(--admin-nav-height);
        }

        /* Logo Section */
        .nav-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .logo-text {
            font-size: 0.875rem;
            font-weight: 600;
            background: linear-gradient(135deg, #fff, #e0e0e0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .admin-badge {
            background: rgba(102, 126, 234, 0.2);
            border: 1px solid rgba(102, 126, 234, 0.3);
            color: #667eea;
            padding: 0.15rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        /* Navigation Menu */
        .nav-menu {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            margin-left: 2rem;
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            color: var(--text-secondary, #94a3b8);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.2), transparent);
            transform: translate(-50%, -50%);
            transition: width 0.5s, height 0.5s;
            z-index: -1;
        }

        .nav-link:hover::before {
            width: 200px;
            height: 200px;
        }

        .nav-link:hover {
            color: var(--text-primary, #fff);
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .nav-link.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .nav-link.active i {
            color: white;
        }

        .nav-link i {
            font-size: 1.1rem;
            color: var(--icon-color, #667eea);
            transition: all 0.3s ease;
        }

        .nav-link:hover i {
            transform: scale(1.1);
        }

        /* Mega Menu */
        .mega-menu {
            position: absolute;
            top: calc(100% + 1rem);
            left: 0;
            width: 600px;
            background: var(--glass-bg-dark, rgba(17, 25, 40, 0.95));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
            border-radius: 16px;
            padding: 1rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 100;
        }

        .nav-item:hover .mega-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .mega-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }

        .mega-section h4 {
            color: var(--text-primary, #fff);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
            opacity: 0.7;
        }

        .mega-items {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .mega-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 8px;
            color: var(--text-secondary, #94a3b8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .mega-item:hover {
            background: rgba(102, 126, 234, 0.1);
            color: var(--text-primary, #fff);
            transform: translateX(5px);
        }

        .mega-item i {
            width: 20px;
            color: #667eea;
        }

        .mega-item span {
            font-size: 0.875rem;
        }

        .mega-item small {
            display: block;
            font-size: 0.75rem;
            opacity: 0.6;
        }

        /* Right Section */
        .nav-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Search */
        .search-container {
            position: relative;
        }

        /* .search-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            padding: 0.6rem 1.2rem 0.6rem 2.8rem;
            color: var(--text-primary, #fff);
            font-size: 0.875rem;
            width: 250px;
            transition: all 0.3s ease;
        } */

        .search-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.1);
            border-color: #667eea;
            width: 300px;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary, #94a3b8);
            font-size: 0.875rem;
        }

        /* Notifications */
        .notifications {
            position: relative;
        }

        .notification-bell {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary, #94a3b8);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-bell:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            transform: translateY(-2px);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, #f56565, #ed64a6);
            color: white;
            font-size: 0.7rem;
            font-weight: 600;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 2px;
            border: 2px solid var(--bg-primary, #1a202c);
        }

        .notification-dropdown {
            position: absolute;
            top: calc(100% + 1rem);
            right: 0;
            width: 350px;
            background: var(--glass-bg-dark, rgba(17, 25, 40, 0.95));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            display: none;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
        }

        .notification-dropdown.show {
            display: block;
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .notification-header h4 {
            color: var(--text-primary, #fff);
            font-size: 0.875rem;
            font-weight: 600;
        }

        .mark-all-read {
            color: #667eea;
            font-size: 0.75rem;
            cursor: pointer;
            text-decoration: none;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            padding: 1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .notification-item.unread {
            background: rgba(102, 126, 234, 0.05);
            border-left: 3px solid #667eea;
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            color: var(--text-primary, #fff);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .notification-time {
            color: var(--text-secondary, #94a3b8);
            font-size: 0.7rem;
        }

        .view-all {
            display: block;
            text-align: center;
            padding: 0.75rem;
            margin-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #667eea;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .view-all:hover {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 0 0 16px 16px;
        }

        /* Theme Toggle */
        .theme-toggle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary, #94a3b8);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            transform: rotate(45deg);
        }

        /* Admin Profile */
        .admin-profile {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.3rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .admin-profile:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }

        .admin-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .admin-info {
            display: flex;
            flex-direction: column;
        }

        .admin-name {
            color: var(--text-primary, #fff);
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.2;
        }

        .admin-role {
            color: var(--text-secondary, #94a3b8);
            font-size: 0.7rem;
        }

        .profile-dropdown {
            position: absolute;
            top: calc(100% + 0.5rem);
            right: 0;
            width: 240px;
            background: var(--glass-bg-dark, rgba(17, 25, 40, 0.95));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 0.5rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }

        .admin-profile:hover .profile-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            color: var(--text-secondary, #94a3b8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: rgba(102, 126, 234, 0.1);
            color: var(--text-primary, #fff);
        }

        .dropdown-item i {
            width: 20px;
            color: #667eea;
        }

        .dropdown-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 0.5rem 0;
        }

        .dropdown-item.logout {
            color: #f56565;
        }

        .dropdown-item.logout i {
            color: #f56565;
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            flex-direction: column;
            gap: 6px;
            width: 30px;
            height: 30px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
        }

        .mobile-menu-toggle span {
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        /* Mobile Menu */
        .mobile-menu {
            display: none;
            position: fixed;
            top: var(--admin-nav-height);
            left: 0;
            right: 0;
            background: var(--glass-bg-dark, rgba(17, 25, 40, 0.98));
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem;
            transform: translateY(-100%);
            transition: transform 0.3s ease;
            z-index: 999;
            max-height: calc(100vh - var(--admin-nav-height));
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            pointer-events: none;
        }

        .mobile-menu.active {
            transform: translateY(0);
            pointer-events: auto;
        }

        .mobile-menu-items {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .mobile-menu-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            color: var(--text-secondary, #94a3b8);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .mobile-menu-item:hover,
        .mobile-menu-item.active {
            background: rgba(102, 126, 234, 0.1);
            color: var(--text-primary, #fff);
        }

        .mobile-menu-item i {
            width: 20px;
            color: #667eea;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .nav-menu {
                display: none;
            }

            .search-container {
                display: none;
            }

            .mobile-menu-toggle {
                display: flex;
            }

            .mobile-menu {
                display: block;
            }

            .nav-right {
                gap: 0.5rem;
            }
        }

        @media (max-width: 768px) {
            .admin-nav {
                --admin-nav-height: 72px;
            }

            .admin-nav-container {
                padding: 0 1rem;
            }

            .logo-text {
                display: none;
            }

            .admin-info {
                display: none;
            }

            .admin-profile {
                padding: 0.5rem;
            }
        }

        @media (max-width: 480px) {
            .notification-dropdown {
                width: 300px;
                right: -100px;
            }

            .theme-toggle {
                display: none;
            }

            /* Free space so the hamburger/menu is always reachable on small phones. */
            .notifications,
            .admin-profile {
                display: none;
            }
        }
    </style>
    <!-- Admin Navigation -->
    <nav class="admin-nav">
        <div class="admin-nav-container">
            <!-- Logo Section -->
            <div class="nav-logo">
                <div class="logo-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <span class="logo-text"><?php echo APP_NAME; ?></span>
                <span class="admin-badge">
                    <?php echo $_SESSION['admin_role'] === 'super_admin' ? 'SUPER ADMIN' : 'ADMIN'; ?>
                </span>
            </div>

            <!-- Navigation Menu (Desktop) -->
            <div class="nav-menu">
                <!-- Dashboard -->
                <div class="nav-item">
                    <a href="<?php echo APP_URL; ?>/pages/admin/dashboard.php" 
                       class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <!-- Complaints with Mega Menu -->
                <?php if (hasPermission('view_complaints')): ?>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-inbox"></i>
                        <span>Complaints</span>
                        <i class="fas fa-chevron-down" style="font-size: 0.8rem;"></i>
                    </a>

                    <div class="mega-menu">
                        <div class="mega-grid">
                            <div class="mega-section">
                                <h4>By Status</h4>
                                <div class="mega-items">
                                    <a href="<?php echo APP_URL; ?>/pages/admin/complaints.php?status=pending" class="mega-item">
                                        <i class="fas fa-clock"></i>
                                        <div>
                                            <span>Pending</span>
                                            <small>Awaiting review</small>
                                        </div>
                                    </a>
                                    <a href="<?php echo APP_URL; ?>/pages/admin/complaints.php?status=under_review" class="mega-item">
                                        <i class="fas fa-search"></i>
                                        <div>
                                            <span>Under Review</span>
                                            <small>Being processed</small>
                                        </div>
                                    </a>
                                    <a href="<?php echo APP_URL; ?>/pages/admin/complaints.php?status=published" class="mega-item">
                                        <i class="fas fa-check-circle"></i>
                                        <div>
                                            <span>Published</span>
                                            <small>Publicly visible</small>
                                        </div>
                                    </a>
                                    <a href="<?php echo APP_URL; ?>/pages/admin/complaints.php?status=resolved" class="mega-item">
                                        <i class="fas fa-flag-checkered"></i>
                                        <div>
                                            <span>Resolved</span>
                                            <small>Completed</small>
                                        </div>
                                    </a>
                                    <a href="<?php echo APP_URL; ?>/pages/admin/complaints.php?status=rejected" class="mega-item">
                                        <i class="fas fa-times-circle"></i>
                                        <div>
                                            <span>Rejected</span>
                                            <small>Not approved</small>
                                        </div>
                                    </a>
                                </div>
                            </div>

                            <div class="mega-section">
                                <h4>Quick Actions</h4>
                                <div class="mega-items">
                                    <a href="<?php echo APP_URL; ?>/pages/admin/complaints.php?filter=new" class="mega-item">
                                        <i class="fas fa-star"></i>
                                        <div>
                                            <span>New Complaints</span>
                                            <small>Last 24 hours</small>
                                        </div>
                                    </a>
                                    <a href="<?php echo APP_URL; ?>/pages/admin/complaints.php?filter=urgent" class="mega-item">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <div>
                                            <span>Urgent Cases</span>
                                            <small>Critical priority</small>
                                        </div>
                                    </a>
                                    <a href="<?php echo APP_URL; ?>/pages/admin/complaints.php?filter=my" class="mega-item">
                                        <i class="fas fa-user"></i>
                                        <div>
                                            <span>My Complaints</span>
                                            <small>Assigned to me</small>
                                        </div>
                                    </a>
                                    <a href="<?php echo APP_URL; ?>/pages/admin/complaints.php?export" class="mega-item">
                                        <i class="fas fa-download"></i>
                                        <div>
                                            <span>Export Data</span>
                                            <small>CSV, Excel, PDF</small>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Categories -->
                <?php if (hasPermission('manage_categories')): ?>
                <div class="nav-item">
                    <a href="<?php echo APP_URL; ?>/pages/admin/categories.php" 
                       class="nav-link <?php echo $current_page === 'categories.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tags"></i>
                        <span>Categories</span>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Users -->
                <?php if (hasAnyPermission(['manage_users', 'manage_admins'])): ?>
                <div class="nav-item">
                    <a href="<?php echo APP_URL; ?>/pages/admin/users.php" 
                       class="nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Reports -->
                <?php if (hasPermission('view_reports')): ?>
                <div class="nav-item">
                    <a href="<?php echo APP_URL; ?>/pages/admin/reports.php" 
                       class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>Reports</span>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Settings (Super Admin Only) -->
                <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                <div class="nav-item">
                    <a href="<?php echo APP_URL; ?>/pages/admin/settings.php" 
                       class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Section -->
            <div class="nav-right">
                <!-- Search -->
              

                <!-- Notifications -->
                <div class="notifications">
                    <button type="button" id="notificationBell" class="notification-bell" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge" style="display:none;"></span>
                    </button>

                    <div id="notificationsPanel" class="notification-dropdown" role="dialog" aria-label="Notifications">
                        <div class="notification-header">
                            <h4>Notifications</h4>
                            <a href="#" class="mark-all-read">Mark all as read</a>
                        </div>

                        <div id="notificationList" style="max-height: 360px; overflow-y: auto;"></div>

                        <div class="dropdown-footer">
                            <a href="<?php echo APP_URL; ?>/pages/admin/notifications.php" class="view-all">View all notifications</a>
                        </div>
                    </div>
                </div>

                <!-- Theme Toggle -->
                <div class="theme-toggle" onclick="toggleTheme()">
                    <i class="fas fa-moon"></i>
                </div>

                <!-- Admin Profile -->
                <div class="admin-profile">
                    <div class="admin-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div class="admin-info">
                        <span class="admin-name"><?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></span>
                        <span class="admin-role">
                            <?php echo $_SESSION['admin_role'] === 'super_admin' ? 'Super Admin' : 'Category Admin'; ?>
                        </span>
                    </div>

                    <div class="profile-dropdown">
                        <a href="<?php echo APP_URL; ?>/pages/admin/profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </a>
                        <a href="<?php echo APP_URL; ?>/pages/admin/activity.php" class="dropdown-item">
                            <i class="fas fa-history"></i>
                            <span>Activity Log</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo APP_URL; ?>/pages/admin/settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo APP_URL; ?>/includes/auth/admin-logout.php" class="dropdown-item logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>

                <!-- Mobile Menu Toggle -->
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div class="mobile-menu" id="mobileMenu">
            <div class="mobile-menu-items">
                <a href="#" class="mobile-menu-item" onclick="toggleTheme(); closeMobileMenu(); return false;">
                    <i class="fas fa-circle-half-stroke"></i>
                    <span>Toggle Theme</span>
                </a>
                <a href="<?php echo APP_URL; ?>/pages/admin/dashboard.php" class="mobile-menu-item">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?php echo APP_URL; ?>/pages/admin/notifications.php" class="mobile-menu-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <?php if (hasPermission('view_complaints')): ?>
                <a href="<?php echo APP_URL; ?>/pages/admin/complaints.php" class="mobile-menu-item">
                    <i class="fas fa-inbox"></i>
                    <span>Complaints</span>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('manage_categories')): ?>
                <a href="<?php echo APP_URL; ?>/pages/admin/categories.php" class="mobile-menu-item">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
                <?php endif; ?>
                <?php if (hasAnyPermission(['manage_users', 'manage_admins'])): ?>
                <a href="<?php echo APP_URL; ?>/pages/admin/users.php" class="mobile-menu-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('view_reports')): ?>
                <a href="<?php echo APP_URL; ?>/pages/admin/reports.php" class="mobile-menu-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Reports</span>
                </a>
                <?php endif; ?>
                <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                <a href="<?php echo APP_URL; ?>/pages/admin/settings.php" class="mobile-menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="<?php echo APP_URL; ?>/pages/admin/profile.php" class="mobile-menu-item">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </a>
                <a href="<?php echo APP_URL; ?>/includes/auth/admin-logout.php" class="mobile-menu-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Complaint Details Modal (for opening from notifications on any admin page) -->
    <div class="modal fade" id="adminComplaintDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complaint Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="adminComplaintDetailsBody">
                    <div style="text-align:center; padding: 1.5rem; color: var(--text-secondary);">
                        <i class="fas fa-spinner fa-spin"></i> Loading...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme Toggle
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update icon
            const icon = document.querySelector('.theme-toggle i');
            if (icon) {
                icon.className = newTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
            }
        }

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        // Update icon based on saved theme
        const themeIcon = document.querySelector('.theme-toggle i');
        if (themeIcon) {
            themeIcon.className = savedTheme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }

        // Mobile Menu Toggle
        function closeMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const toggle = document.querySelector('.mobile-menu-toggle');
            if (menu) menu.classList.remove('active');
            if (toggle) toggle.classList.remove('active');
        }

        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            menu.classList.toggle('active');
            toggle.classList.toggle('active');
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('mobileMenu');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (!menu.contains(event.target) && !toggle.contains(event.target)) {
                menu.classList.remove('active');
                toggle.classList.remove('active');
            }
        });

        // Admin notifications: show top 5 unread in a panel (student-like),
        // open complaint-details modal on click, and mark read on modal close.
        let __pendingNotificationIdToMarkRead = null;

        function adminEscapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text ?? '').replace(/[&<>"']/g, m => map[m]);
        }

        function adminGetNotificationIcon(type) {
            const icons = {
                success: 'check-circle',
                error: 'exclamation-circle',
                warning: 'exclamation-triangle',
                info: 'info-circle',
                system: 'cog'
            };
            return icons[type] || 'bell';
        }

        function adminSetBellExpanded(isExpanded) {
            const bell = document.getElementById('notificationBell');
            if (bell) bell.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
        }

        function adminHideNotificationsPanel() {
            const panel = document.getElementById('notificationsPanel');
            if (!panel) return;
            panel.classList.remove('show');
            adminSetBellExpanded(false);
        }

        function adminShowNotificationsPanel() {
            const panel = document.getElementById('notificationsPanel');
            if (!panel) return;
            panel.classList.add('show');
            adminSetBellExpanded(true);
            adminLoadRecentUnreadNotifications();
        }

        function adminToggleNotificationsPanel() {
            const panel = document.getElementById('notificationsPanel');
            if (!panel) return;
            const willShow = !panel.classList.contains('show');
            if (willShow) adminShowNotificationsPanel();
            else adminHideNotificationsPanel();
        }

        function adminRefreshUnreadCount() {
            fetch('<?php echo APP_URL; ?>/api/notifications_unified.php?action=unread_count')
                .then(r => r.json())
                .then(d => {
                    const badge = document.querySelector('.notification-badge');
                    const count = (d && d.success) ? (parseInt(d.count, 10) || 0) : 0;
                    if (!badge) return;
                    badge.textContent = count > 9 ? '9+' : String(count);
                    badge.style.display = count > 0 ? 'flex' : 'none';
                })
                .catch(() => {});
        }

        function adminLoadRecentUnreadNotifications() {
            const list = document.getElementById('notificationList');
            if (!list) return;

            list.innerHTML = '<div style=\"text-align:center; padding: 1rem; color: var(--text-secondary);\"><i class=\"fas fa-spinner fa-spin\"></i> Loading...</div>';

            fetch('<?php echo APP_URL; ?>/api/notifications_unified.php?action=recent_unread&limit=5')
                .then(r => r.json())
                .then(d => {
                    if (!list) return;
                    if (!d || !d.success || !Array.isArray(d.notifications) || d.notifications.length === 0) {
                        list.innerHTML = '<div class=\"empty-notifications\" style=\"padding: 1.5rem; text-align:center; color: var(--text-secondary);\"><i class=\"fas fa-bell-slash\"></i><p style=\"margin:0.5rem 0 0;\">No new notifications</p></div>';
                        return;
                    }

                    list.innerHTML = '';
                    d.notifications.forEach(n => {
                        const el = document.createElement('div');
                        el.className = `notification-item ${n.is_read ? 'read' : 'unread'}`;
                        el.dataset.notificationId = n.id;
                        el.dataset.relatedType = n.related_type || '';
                        el.dataset.relatedId = n.related_id || '';

                        el.innerHTML = `
                            <div class=\"notification-icon\"><i class=\"fas fa-${adminGetNotificationIcon(n.type)}\"></i></div>
                            <div class=\"notification-content\">
                                <div class=\"notification-title\">${adminEscapeHtml(n.title)}</div>
                                <div class=\"notification-message\" style=\"color: var(--text-secondary); font-size: 0.8rem; margin-top: 0.15rem;\">${adminEscapeHtml(n.message)}</div>
                                <div class=\"notification-time\">${adminEscapeHtml(n.time_ago || '')}</div>
                            </div>
                        `;

                        el.addEventListener('click', () => {
                            const relatedType = el.dataset.relatedType;
                            const relatedId = parseInt(el.dataset.relatedId, 10);
                            const notifId = parseInt(el.dataset.notificationId, 10);

                            // Only open complaint modal for complaint-related notifications.
                            if ((relatedType === 'complaint' || relatedType === 'complaints' || relatedType === 'rejection_request') && relatedId) {
                                __pendingNotificationIdToMarkRead = notifId;
                                adminHideNotificationsPanel();
                                adminOpenComplaintDetailsModal(relatedId);
                            } else {
                                // Non-complaint notifications: mark read immediately.
                                adminMarkNotificationRead(notifId).finally(() => {
                                    adminRefreshUnreadCount();
                                    adminLoadRecentUnreadNotifications();
                                });
                            }
                        });

                        list.appendChild(el);
                    });
                })
                .catch(() => {
                    list.innerHTML = '<div style=\"padding: 1rem; color: var(--text-secondary);\">Failed to load notifications.</div>';
                });
        }

        function adminMarkNotificationRead(notificationId) {
            return fetch('<?php echo APP_URL; ?>/api/notifications_unified.php?action=mark_read', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ notification_id: notificationId })
            }).then(r => r.json()).catch(() => ({ success: false }));
        }

        function adminMarkAllNotificationsRead() {
            return fetch('<?php echo APP_URL; ?>/api/notifications_unified.php?action=mark_all_read', { method: 'POST' })
                .then(r => r.json())
                .then(d => {
                    if (d && d.success) {
                        adminRefreshUnreadCount();
                        adminLoadRecentUnreadNotifications();
                    }
                })
                .catch(() => {});
        }

        function adminOpenComplaintDetailsModal(complaintId) {
            const body = document.getElementById('adminComplaintDetailsBody');
            if (body) {
                body.innerHTML = '<div style=\"text-align:center; padding: 1.5rem; color: var(--text-secondary);\"><i class=\"fas fa-spinner fa-spin\"></i> Loading...</div>';
            }

            const modalEl = document.getElementById('adminComplaintDetailsModal');
            if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }

            fetch('<?php echo APP_URL; ?>/api/get_complaint.php?id=' + encodeURIComponent(complaintId))
                .then(r => r.json())
                .then(d => {
                    if (!body) return;
                    if (!d || !d.success || !d.complaint) {
                        body.innerHTML = '<div class=\"alert alert-error\">Failed to load complaint details.</div>';
                        return;
                    }
                    const c = d.complaint;
                    const statusLabel = adminEscapeHtml(String(c.status || '').replace('_', ' ')).toUpperCase();
                    const urgencyLabel = adminEscapeHtml(String(c.urgency || '')).toUpperCase();

                    body.innerHTML = `
                        <div class=\"glass-card\" style=\"padding: 1rem;\">
                            <div style=\"display:flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap;\">
                                <div>
                                    <div style=\"color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;\">Complaint Code</div>
                                    <div style=\"font-weight: 700; font-size: 1.1rem;\">${adminEscapeHtml(c.complaint_code)}</div>
                                </div>
                                <div style=\"text-align:right;\">
                                    <div style=\"color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;\">Status</div>
                                    <div style=\"font-weight: 700;\">${statusLabel}</div>
                                </div>
                            </div>

                            <div style=\"margin-top: 1rem; display:flex; gap: 0.75rem; flex-wrap: wrap; align-items:center;\">
                                <span class=\"badge\" style=\"background: ${adminEscapeHtml(c.category_color || '#667eea')}20; color: ${adminEscapeHtml(c.category_color || '#667eea')}; border: 1px solid ${adminEscapeHtml(c.category_color || '#667eea')}40;\">
                                    ${adminEscapeHtml(c.category_name || 'Category')}
                                </span>
                                <span class=\"badge\" style=\"background: rgba(237, 137, 54, 0.12); color: #ed8936; border: 1px solid rgba(237, 137, 54, 0.25);\">
                                    Urgency: ${urgencyLabel}
                                </span>
                                <span class=\"badge\" style=\"background: rgba(66, 153, 225, 0.12); color: #4299e1; border: 1px solid rgba(66, 153, 225, 0.25);\">
                                    Submitted: ${adminEscapeHtml(c.time_ago || '')}
                                </span>
                            </div>

                            <div style=\"margin-top: 1rem;\">
                                <div style=\"color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;\">Title</div>
                                <div style=\"font-weight: 600; margin-top: 0.25rem;\">${adminEscapeHtml(c.title || '')}</div>
                            </div>

                            <div style=\"margin-top: 1rem;\">
                                <div style=\"color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;\">Description</div>
                                <div style=\"margin-top: 0.25rem; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.75rem; line-height: 1.6;\">${adminEscapeHtml(c.description || '')}</div>
                            </div>

                            ${c.location ? `
                                <div style=\"margin-top: 1rem;\">
                                    <div style=\"color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px;\">Location</div>
                                    <div style=\"margin-top: 0.25rem;\"><i class=\"fas fa-map-marker-alt\"></i> ${adminEscapeHtml(c.location)}</div>
                                </div>
                            ` : ''}

                            ${c.rejection_reason ? `
                                <div class=\"alert alert-warning\" style=\"margin-top: 1rem;\">
                                    <strong>Rejection Reason:</strong> ${adminEscapeHtml(c.rejection_reason)}
                                </div>
                            ` : ''}

                            <div style=\"margin-top: 1rem; display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem;\">
                                <div class=\"glass-card\" style=\"padding: 0.75rem; text-align:center;\">
                                    <div style=\"color: var(--text-muted); font-size: 0.75rem;\">Views</div>
                                    <div style=\"font-weight: 700;\">${adminEscapeHtml(c.view_count ?? 0)}</div>
                                </div>
                                <div class=\"glass-card\" style=\"padding: 0.75rem; text-align:center;\">
                                    <div style=\"color: var(--text-muted); font-size: 0.75rem;\">Upvotes</div>
                                    <div style=\"font-weight: 700; color:#48bb78;\">${adminEscapeHtml(c.upvotes ?? 0)}</div>
                                </div>
                                <div class=\"glass-card\" style=\"padding: 0.75rem; text-align:center;\">
                                    <div style=\"color: var(--text-muted); font-size: 0.75rem;\">Downvotes</div>
                                    <div style=\"font-weight: 700; color:#f56565;\">${adminEscapeHtml(c.downvotes ?? 0)}</div>
                                </div>
                            </div>
                        </div>
                    `;
                })
                .catch(() => {
                    if (body) body.innerHTML = '<div class=\"alert alert-error\">Failed to load complaint details.</div>';
                });
        }

        // Wire up bell + outside click + mark-all-read
        (function initAdminNotificationsUI() {
            const bell = document.getElementById('notificationBell');
            const panel = document.getElementById('notificationsPanel');
            if (!bell || !panel) return;

            adminRefreshUnreadCount();
            setInterval(adminRefreshUnreadCount, 30000);

            bell.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                adminToggleNotificationsPanel();
            });

            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (!panel.classList.contains('show')) return;
                if (panel.contains(e.target) || bell.contains(e.target)) return;
                adminHideNotificationsPanel();
            });

            // Mark all read
            const markAll = panel.querySelector('.mark-all-read');
            if (markAll) {
                markAll.addEventListener('click', function(e) {
                    e.preventDefault();
                    adminMarkAllNotificationsRead();
                });
            }

            // When complaint modal closes, mark the related notification as read
            const complaintModalEl = document.getElementById('adminComplaintDetailsModal');
            if (complaintModalEl) {
                complaintModalEl.addEventListener('hidden.bs.modal', function() {
                    if (!__pendingNotificationIdToMarkRead) return;
                    const id = __pendingNotificationIdToMarkRead;
                    __pendingNotificationIdToMarkRead = null;
                    adminMarkNotificationRead(id).finally(() => {
                        adminRefreshUnreadCount();
                        // If panel is open, refresh list so it disappears from unread list
                        if (panel.classList.contains('show')) adminLoadRecentUnreadNotifications();
                    });
                });
            }
        })();

        // Live search functionality
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                
                searchTimeout = setTimeout(() => {
                    const query = this.value;
                    
                    if (query.length >= 3) {
                        fetch(`<?php echo APP_URL; ?>/api/search.php?q=${encodeURIComponent(query)}`)
                            .then(response => response.json())
                            .then(data => {
                                // Handle search results (could show dropdown)
                                console.log('Search results:', data);
                            })
                            .catch(error => console.error('Search error:', error));
                    }
                }, 500);
            });

            // Handle enter key
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const query = this.value;
                    window.location.href = `<?php echo APP_URL; ?>/pages/admin/search.php?q=${encodeURIComponent(query)}`;
                }
            });
        }
    </script>
