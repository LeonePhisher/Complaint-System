-- HTU Anonymous Complaint System Database Schema
-- Version: 1.0.0

CREATE DATABASE IF NOT EXISTS htu_complaint_system 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE htu_complaint_system;

-- Users Table (Students)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    index_number VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    department VARCHAR(100),
    level VARCHAR(20),
    avatar_color VARCHAR(7) DEFAULT '#667eea',
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(100),
    verification_expires DATETIME,
    reset_token VARCHAR(100),
    reset_expires DATETIME,
    login_attempts INT DEFAULT 0,
    last_login DATETIME,
    account_status ENUM('active', 'suspended', 'inactive') DEFAULT 'inactive',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (account_status)
);

-- Administrators Table
CREATE TABLE admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'category_admin', 'support_admin') DEFAULT 'category_admin',
    permissions TEXT,
    avatar_color VARCHAR(7) DEFAULT '#764ba2',
    is_active BOOLEAN DEFAULT TRUE,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    last_login DATETIME,
    login_attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_active (is_active)
);

-- Categories Table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    color VARCHAR(7) DEFAULT '#667eea',
    admin_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_slug (slug)
);

-- Complaints Table
CREATE TABLE complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_code VARCHAR(20) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(200),
    urgency ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    attachments TEXT,
    status ENUM('pending', 'under_review', 'published', 'resolved', 'rejected') DEFAULT 'pending',
    published_at DATETIME,
    resolved_at DATETIME,
    rejection_reason TEXT,
    rejected_by INT,
    assigned_to INT,
    view_count INT DEFAULT 0,
    upvotes INT DEFAULT 0,
    downvotes INT DEFAULT 0,
    is_anonymous BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (rejected_by) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_category (category_id),
    INDEX idx_user (user_id),
    INDEX idx_urgency (urgency),
    INDEX idx_created (created_at)
);

-- Votes Table
CREATE TABLE votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_id INT NOT NULL,
    user_id INT NOT NULL,
    vote_type ENUM('upvote', 'downvote') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (complaint_id, user_id),
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_complaint (complaint_id)
);

-- Comments Table
CREATE TABLE comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_id INT NOT NULL,
    user_id INT,
    admin_id INT,
    content TEXT NOT NULL,
    is_anonymous BOOLEAN DEFAULT TRUE,
    is_private BOOLEAN DEFAULT FALSE,
    parent_id INT,
    status ENUM('active', 'deleted', 'hidden') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_complaint (complaint_id),
    INDEX idx_status (status)
);

-- Status History Table
CREATE TABLE status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complaint_id INT NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by INT,
    changed_by_type ENUM('admin', 'system', 'user') DEFAULT 'admin',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    INDEX idx_complaint (complaint_id)
);

-- Notifications Table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    admin_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error', 'system') DEFAULT 'info',
    related_id INT,
    related_type VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
    INDEX idx_user (user_id, is_read),
    INDEX idx_admin (admin_id, is_read)
);

-- Audit Log Table
CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    admin_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_action (action)
);

-- Sessions Table
CREATE TABLE sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT,
    user_type ENUM('user', 'admin') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_user (user_id, user_type),
    INDEX idx_expires (expires_at)
);

-- Settings Table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json', 'array') DEFAULT 'string',
    category VARCHAR(50) DEFAULT 'general',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
);

-- Insert Default Super Admin (Password: Admin@123)
INSERT INTO admins (username, email, password_hash, full_name, role) VALUES
('superadmin', 'superadmin@htu.edu.gh', '$2y$10$YourHashedPasswordHere', 'Super Administrator', 'super_admin');

-- Insert Default Categories
INSERT INTO categories (name, slug, description, icon, color) VALUES
('Hostel', 'hostel', 'Complaints related to hostel facilities and accommodation', 'home', '#667eea'),
('Campus', 'campus', 'General campus facilities and environment', 'university', '#764ba2'),
('Department', 'department', 'Academic department related issues', 'book', '#f56565'),
('Library', 'library', 'Library services and resources', 'book-open', '#ed8936'),
('Cafeteria', 'cafeteria', 'Food services and dining facilities', 'utensils', '#48bb78'),
('Security', 'security', 'Security and safety concerns', 'shield', '#4299e1'),
('Transport', 'transport', 'Transportation services', 'bus', '#9f7aea'),
('Health', 'health', 'Health center and medical services', 'heart', '#f687b3'),
('ICT', 'ict', 'ICT and computer lab issues', 'monitor', '#4fd1c7'),
('Other', 'other', 'Other miscellaneous complaints', 'more-horizontal', '#a0aec0');

-- Insert Default Settings
INSERT INTO settings (setting_key, setting_value, setting_type, category, description) VALUES
('site_name', 'HTU Anonymous Complaint System', 'string', 'general', 'Website name'),
('site_description', 'A platform for students to anonymously submit complaints', 'string', 'general', 'Website description'),
('allow_registration', '1', 'boolean', 'registration', 'Allow new user registration'),
('require_verification', '1', 'boolean', 'registration', 'Require email verification'),
('complaint_auto_publish', '0', 'boolean', 'complaints', 'Auto-publish complaints after approval'),
('max_complaints_per_day', '5', 'integer', 'complaints', 'Maximum complaints a user can submit per day'),
('theme_mode', 'auto', 'string', 'ui', 'Default theme mode (light/dark/auto)'),
('maintenance_mode', '0', 'boolean', 'system', 'Enable maintenance mode');

-- Create Trigger for Complaint Code Generation
DELIMITER $$
CREATE TRIGGER before_complaint_insert
BEFORE INSERT ON complaints
FOR EACH ROW
BEGIN
    DECLARE year_prefix CHAR(2);
    DECLARE seq_num INT;
    
    SET year_prefix = DATE_FORMAT(NOW(), '%y');
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(complaint_code, 6) AS UNSIGNED)), 0) + 1 
    INTO seq_num 
    FROM complaints 
    WHERE complaint_code COLLATE utf8mb4_unicode_ci LIKE CONCAT('HTU', year_prefix, '%') COLLATE utf8mb4_unicode_ci;
    
    SET NEW.complaint_code = CONCAT('HTU', year_prefix, LPAD(seq_num, 6, '0'));
END$$
DELIMITER ;