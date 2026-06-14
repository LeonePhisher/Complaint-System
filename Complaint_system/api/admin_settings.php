<?php
/**
 * Admin Settings API
 * Unified API for managing all system settings
 */
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/utilities/helpers.php';
require_once '../includes/auth/session.inc.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Only super admins can manage settings
if (!isAdmin() || $_SESSION['admin_role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        // ===== GET ALL SETTINGS =====
        case 'get_all':
            $stmt = db()->prepare("SELECT * FROM settings ORDER BY category, setting_key");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $organized = [];
            foreach ($settings as $s) {
                if (!isset($organized[$s['category']])) {
                    $organized[$s['category']] = [];
                }
                
                // Convert types
                $value = $s['setting_value'];
                if ($s['setting_type'] === 'boolean') {
                    $value = (bool)$value;
                } elseif ($s['setting_type'] === 'integer') {
                    $value = (int)$value;
                } elseif ($s['setting_type'] === 'json') {
                    $value = json_decode($value, true);
                }
                
                $organized[$s['category']][$s['setting_key']] = $value;
            }
            
            echo json_encode([
                'success' => true,
                'settings' => $organized
            ]);
            exit;

        // ===== GET SINGLE SETTING =====
        case 'get':
            $key = $_GET['key'] ?? $_POST['key'] ?? null;
            
            if (!$key) {
                echo json_encode(['success' => false, 'message' => 'No setting key provided']);
                exit;
            }
            
            $stmt = db()->prepare("SELECT * FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $setting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$setting) {
                echo json_encode(['success' => false, 'message' => 'Setting not found']);
                exit;
            }
            
            // Convert type
            $value = $setting['setting_value'];
            if ($setting['setting_type'] === 'boolean') {
                $value = (bool)$value;
            } elseif ($setting['setting_type'] === 'integer') {
                $value = (int)$value;
            } elseif ($setting['setting_type'] === 'json') {
                $value = json_decode($value, true);
            }
            
            echo json_encode([
                'success' => true,
                'key' => $key,
                'value' => $value,
                'type' => $setting['setting_type'],
                'category' => $setting['category']
            ]);
            exit;

        // ===== UPDATE SETTING =====
        case 'update':
            $key = $_POST['key'] ?? null;
            $value = $_POST['value'] ?? null;
            $type = $_POST['type'] ?? 'string';
            
            if (!$key) {
                echo json_encode(['success' => false, 'message' => 'No setting key provided']);
                exit;
            }
            
            // Convert value based on type
            if ($type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            } elseif ($type === 'integer') {
                $value = intval($value);
            } elseif ($type === 'json') {
                $value = is_array($value) ? json_encode($value) : $value;
            }
            
            // Check if setting exists
            $stmt = db()->prepare("SELECT id FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Update
                $stmt = db()->prepare("
                    UPDATE settings SET 
                    setting_value = ?,
                    updated_at = NOW()
                    WHERE setting_key = ?
                ");
                $result = $stmt->execute([$value, $key]);
            } else {
                // Insert (get category from request or use 'general')
                $category = $_POST['category'] ?? 'general';
                $stmt = db()->prepare("
                    INSERT INTO settings (setting_key, setting_value, setting_type, category, updated_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $result = $stmt->execute([$key, $value, $type, $category]);
            }
            
            if ($result) {
                // Log activity
                logActivity('setting_updated', "Setting '{$key}' updated", $_SESSION['admin_id'], 'admin');
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Setting updated successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update setting'
                ]);
            }
            exit;

        // ===== UPDATE MULTIPLE SETTINGS =====
        case 'update_multiple':
            $settings_data = $_POST['settings'] ?? json_decode(file_get_contents('php://input'), true)['settings'] ?? [];
            
            if (!is_array($settings_data) || empty($settings_data)) {
                echo json_encode(['success' => false, 'message' => 'No settings provided']);
                exit;
            }
            
            $updated = 0;
            $errors = [];
            
            foreach ($settings_data as $key => $data) {
                try {
                    if (!isset($data['value'])) {
                        $errors[] = "No value for setting: $key";
                        continue;
                    }
                    
                    $value = $data['value'];
                    $type = $data['type'] ?? 'string';
                    
                    // Convert value
                    if ($type === 'boolean') {
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                    } elseif ($type === 'integer') {
                        $value = intval($value);
                    } elseif ($type === 'json') {
                        $value = is_array($value) ? json_encode($value) : $value;
                    }
                    
                    $stmt = db()->prepare("
                        UPDATE settings SET 
                        setting_value = ?,
                        updated_at = NOW()
                        WHERE setting_key = ?
                    ");
                    
                    if ($stmt->execute([$value, $key])) {
                        $updated++;
                    } else {
                        $errors[] = "Failed to update setting: $key";
                    }
                } catch (Exception $e) {
                    $errors[] = "Error updating $key: {$e->getMessage()}";
                }
            }
            
            logActivity('multiple_settings_updated', "Updated $updated settings", $_SESSION['admin_id'], 'admin');
            
            echo json_encode([
                'success' => true,
                'message' => "Successfully updated $updated settings",
                'updated' => $updated,
                'errors' => $errors
            ]);
            exit;

        // ===== RESET SETTING TO DEFAULT =====
        case 'reset':
            $key = $_POST['key'] ?? null;
            
            if (!$key) {
                echo json_encode(['success' => false, 'message' => 'No setting key provided']);
                exit;
            }
            
            // Map defaults
            $defaults = [
                'otp_expiry_minutes' => '10',
                'otp_length' => '6',
                'otp_max_attempts' => '5',
                'notifications_per_page' => '7',
                'allow_registration' => '1',
                'require_verification' => '1',
                'theme_mode' => 'auto'
            ];
            
            if (!isset($defaults[$key])) {
                echo json_encode(['success' => false, 'message' => 'No default value for this setting']);
                exit;
            }
            
            $stmt = db()->prepare("
                UPDATE settings SET 
                setting_value = ?,
                updated_at = NOW()
                WHERE setting_key = ?
            ");
            
            if ($stmt->execute([$defaults[$key], $key])) {
                logActivity('setting_reset', "Setting '{$key}' reset to default", $_SESSION['admin_id'], 'admin');
                echo json_encode([
                    'success' => true,
                    'message' => 'Setting reset to default'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to reset setting'
                ]);
            }
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
            exit;
    }
} catch (PDOException $e) {
    error_log("Settings API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
} catch (Exception $e) {
    error_log("Settings API exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
?>
