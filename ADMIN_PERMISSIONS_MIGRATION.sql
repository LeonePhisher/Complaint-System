-- Admin Permissions System Migration
-- This script creates the admin_permissions junction table
-- and migrates data from JSON format to the new relational structure

-- ============================================================
-- 1. Create admin_permissions Junction Table
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `permission_name` varchar(50) NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_admin_permission` (`admin_id`, `permission_name`),
  FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. Add Support for storing individual permission columns
-- ============================================================
ALTER TABLE `admin_permissions` ADD COLUMN `granted_at` timestamp NOT NULL DEFAULT current_timestamp() AFTER `permission_name`;

-- ============================================================
-- 3. Define all available permissions
-- ============================================================
-- The following are the standard permissions in the system:
-- - view_complaints: Can view complaint listings
-- - manage_complaints: Can moderate and manage complaints
-- - view_reports: Can access reports and analytics
-- - manage_users: Can manage user accounts
-- - manage_admins: Can manage other admin accounts
-- - view_settings: Can view system settings
-- - manage_settings: Can modify system settings
-- - view_audit_log: Can view audit logs

-- ============================================================
-- 4. Migrate existing permissions from JSON
-- ============================================================
-- This will parse the JSON permissions from the admins table
-- and insert them into the new admin_permissions table

INSERT IGNORE INTO `admin_permissions` (`admin_id`, `permission_name`, `granted_at`)
SELECT 
    a.id,
    JSON_UNQUOTE(JSON_EXTRACT(a.permissions, CONCAT('$[', idx.i, ']'))) as permission_name,
    a.updated_at
FROM `admins` a
CROSS JOIN (SELECT 0 as i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7) idx
WHERE a.permissions IS NOT NULL 
  AND a.permissions != 'null'
  AND a.permissions != '[]'
  AND JSON_EXTRACT(a.permissions, CONCAT('$[', idx.i, ']')) IS NOT NULL;

-- ============================================================
-- 5. Grant super admin all permissions
-- ============================================================
-- Super admins should have all permissions
INSERT IGNORE INTO `admin_permissions` (`admin_id`, `permission_name`)
SELECT a.id, p.permission_name
FROM `admins` a
CROSS JOIN (
    SELECT 'view_complaints' as permission_name UNION
    SELECT 'manage_complaints' UNION
    SELECT 'view_reports' UNION
    SELECT 'manage_users' UNION
    SELECT 'manage_admins' UNION
    SELECT 'view_settings' UNION
    SELECT 'manage_settings' UNION
    SELECT 'view_audit_log'
) p
WHERE a.role = 'super_admin'
  AND a.id NOT IN (SELECT DISTINCT admin_id FROM admin_permissions WHERE admin_id = a.id);

-- ============================================================
-- 6. Create helper view for admin permissions
-- ============================================================
CREATE OR REPLACE VIEW `admin_permissions_summary` AS
SELECT 
    a.id,
    a.username,
    a.email,
    a.full_name,
    a.role,
    COUNT(ap.permission_name) as permission_count,
    GROUP_CONCAT(ap.permission_name ORDER BY ap.permission_name SEPARATOR ',') as permissions_list
FROM `admins` a
LEFT JOIN `admin_permissions` ap ON a.id = ap.admin_id
GROUP BY a.id, a.username, a.email, a.full_name, a.role;

-- ============================================================
-- 7. Indexes for performance
-- ============================================================
CREATE INDEX IF NOT EXISTS `idx_admin_id` ON `admin_permissions` (`admin_id`);
CREATE INDEX IF NOT EXISTS `idx_permission_name` ON `admin_permissions` (`permission_name`);

-- ============================================================
-- Migration Notes
-- ============================================================
-- After running this migration:
-- 1. Verify that permissions were migrated correctly:
--    SELECT * FROM admin_permissions_summary;
--
-- 2. The JSON permissions column in admins table can be kept for backward compatibility
--    or removed after verification:
--    ALTER TABLE `admins` DROP COLUMN `permissions`;
--
-- 3. Update your PHP code to use the new query pattern:
--    SELECT permission_name FROM admin_permissions 
--    WHERE admin_id = ? AND permission_name = ?
--
-- 4. Update the hasPermission() function in helpers.php to query the new table
