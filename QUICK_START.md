# Quick Start Guide - Complaint System with OTP & Permissions

## 🚀 Getting Started After Updates

This guide walks you through deploying and testing the complaint system with all new features.

---

## 📋 Prerequisites

Before starting, ensure you have:
- XAMPP running (Apache + MySQL)
- Database: `complaint_system` created
- PHP 8.0.30+
- Composer dependencies installed
- Email configuration (SMTP) working

---

## 🔧 Installation Steps

### Step 1: Database Migration (CRITICAL)

**Via CLI:**
```bash
cd c:\xampp\htdocs\complaint-system
mysql -u root -p complaint_system < OTP_MIGRATION.sql
```

**Via PhpMyAdmin:**
1. Go to http://localhost/phpmyadmin
2. Select `complaint_system` database
3. Click "Import" tab
4. Choose `OTP_MIGRATION.sql`
5. Click "Go"

**Verify Migration:**
```bash
# Run in MySQL:
DESC users;  # Should show: otp_code, otp_expires, otp_attempts
SHOW TABLES;  # Should show: otp_history
SELECT * FROM settings WHERE setting_key LIKE 'otp_%';
```

### Step 2: Verify Email Configuration

**File:** `/config/mail_config.php`

**Check these settings:**
- SMTP Host configured
- SMTP Port (usually 587 or 465)
- SMTP Username and Password
- From Email address

**Test Email Sending:**
```php
<?php
require 'config/mail_config.php';
require 'includes/utilities/helpers.php';

// Test sending an OTP
$test_sent = sendOTPEmail('your-email@example.com', 'Test User', '123456');
echo $test_sent ? "✅ Email sent successfully!" : "❌ Email failed to send";
?>
```

Save as `test-email.php` and visit: http://localhost/complaint-system/test-email.php

### Step 3: Verify New Files Are Present

**Run this command or check manually:**

```bash
# In PowerShell Windows - check if files exist
$files = @(
    "pages/auth/verify.php",
    "pages/admin/settings-new.php",
    "pages/admin/notifications.php",
    "pages/student/notifications.php",
    "api/admin_permissions.php",
    "api/get_paginated_notifications.php",
    "includes/utilities/helpers.php",
    "assets/js/main.js"
)

foreach ($file in $files) {
    $path = "c:\xampp\htdocs\complaint-system\$file"
    if (Test-Path $path) {
        Write-Host "✅ $file"
    } else {
        Write-Host "❌ $file"
    }
}
```

---

## 🧪 Quick Testing

### Test 1: Student Registration with OTP (5 minutes)

1. **Start Fresh**
   - Open http://localhost/complaint-system/pages/auth/register.php
   - Use new email address (e.g., student1@test.com)

2. **Register**
   - Fill in name, email, student ID
   - Create password
   - Click "Register"

3. **Verify OTP**
   - Should see OTP verification page
   - Countdown timer showing ~10:00
   - Check email for OTP code
   - Enter OTP and click "Verify"

4. **Success Indicators:**
   - ✅ Email received with OTP
   - ✅ OTP is 6 digits
   - ✅ Countdown timer works
   - ✅ Account verified successfully
   - ✅ Can now login

### Test 2: Admin Settings (3 minutes)

1. **Login as Admin**
   - Credentials: Check your admin user account
   - Must be `role = 'super_admin'`

2. **Navigate to Settings**
   - In admin panel, find "Settings" (or go to /pages/admin/settings-new.php)
   - Should see settings form with categories

3. **Update a Setting**
   - Change "Site Name" to something new
   - Change "Notifications Per Page" to 5
   - Click "Save Settings"

4. **Verify Changes**
   - Success message appears
   - Value persists on page reload
   - Check database: `SELECT * FROM settings WHERE setting_key = 'site_name'`

### Test 3: Notification Pagination (3 minutes)

1. **Create Test Notifications** (if few exist)
   ```sql
   -- Run in MySQL to create test notifications
   INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
   SELECT 1, CONCAT('Test Notification ', @row:=@row+1), 'Test message', 'info', 0, DATE_SUB(NOW(), INTERVAL @row MINUTE)
   FROM (SELECT 0) t, (SELECT @row:=0) r
   LIMIT 20;
   ```

2. **View Notifications Page**
   - As student: http://localhost/complaint-system/pages/student/notifications.php
   - Should see pagination controls if >7 notifications

3. **Test Pagination**
   - Click "Next" - should show different notifications
   - Click "Last" - should show final page
   - Page indicator shows "Page X of Y"

### Test 4: Permission System (3 minutes)

1. **As Super Admin:**
   - Go to Admin Users management
   - Edit a regular admin
   - Find "Permissions" section
   - Give permission: "manage_settings"
   - Save

2. **Verify Permission Works:**
   - Logout
   - Login as that admin
   - Go to Settings page - should have access
   
3. **Revoke Permission:**
   - As super admin: Remove "manage_settings"
   - Logout and login as that admin
   - Go to Settings page - should see access denied

---

## 📊 System Status Check

Run this to verify everything is working:

```php
<?php
echo "=== SYSTEM STATUS CHECK ===\n\n";

// 1. Database Connection
try {
    require 'config/database.php';
    $conn = db();
    echo "✅ Database connected\n";
} catch (Exception $e) {
    echo "❌ Database: " . $e->getMessage() . "\n";
    exit;
}

// 2. OTP Table
try {
    $stmt = db()->query("DESC otp_history");
    $result = $stmt->fetch();
    echo "✅ OTP History table exists\n";
} catch (Exception $e) {
    echo "❌ OTP History table missing\n";
}

// 3. OTP Columns
try {
    $stmt = db()->query("DESC users");
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    $otp_cols = ['otp_code', 'otp_expires', 'otp_attempts'];
    $missing = array_diff($otp_cols, $columns);
    if (empty($missing)) {
        echo "✅ OTP columns in users table\n";
    } else {
        echo "❌ Missing columns: " . implode(', ', $missing) . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error checking users table\n";
}

// 4. Settings
try {
    $stmt = db()->prepare("SELECT COUNT(*) as cnt FROM settings WHERE setting_key LIKE 'otp_%'");
    $stmt->execute();
    $result = $stmt->fetch();
    echo "✅ OTP settings exist (" . $result['cnt'] . " settings)\n";
} catch (Exception $e) {
    echo "❌ Settings table issue\n";
}

// 5. Helper Functions
require 'includes/utilities/helpers.php';
if (function_exists('generateOTP')) {
    echo "✅ Helper functions loaded\n";
} else {
    echo "❌ Helper functions missing\n";
}

// 6. Email Configuration
try {
    require 'config/mail_config.php';
    echo "✅ Email configuration loaded\n";
} catch (Exception $e) {
    echo "❌ Email configuration: " . $e->getMessage() . "\n";
}

echo "\n=== STATUS CHECK COMPLETE ===\n";
?>
```

Save as `system-check.php` in root directory
Visit: http://localhost/complaint-system/system-check.php

---

## 🐛 Troubleshooting

### Problem: OTP Email Not Sending

**Solution:**
1. Check email config in `/config/mail_config.php`
2. Verify SMTP credentials work
3. Check PHP error log: `check error_log()`
4. Test with Gmail's "App Passwords" (not regular password)

### Problem: Settings Not Saving

**Solution:**
1. Verify settings table exists: `DESC settings`
2. Check database user has INSERT/UPDATE permissions
3. Verify no SQL errors in logs
4. Check `ON DUPLICATE KEY UPDATE` syntax

### Problem: Notifications Not Showing

**Solution:**
1. Check if notifications exist in database
2. Verify JavaScript enabled in browser
3. Check browser console for errors (F12)
4. Verify API endpoint: /includes/utilities/notifications.php

### Problem: Permission Denied Errors

**Solution:**
1. Verify admin role in database: `SELECT role FROM admins WHERE id = 1`
2. Check permission stored in JSON: `SELECT permissions FROM admins WHERE id = 1`
3. Clear browser cache and re-login
4. Test with super_admin account first

---

## 📚 Documentation Files

After deployment, refer to these docs:

| File | Purpose |
|------|---------|
| `FIXES_SUMMARY.md` | Overview of all changes |
| `TESTING_GUIDE.md` | Detailed test procedures |
| `IMPLEMENTATION_CHECKLIST.md` | What's done and what's optional |
| `OTP_MIGRATION.sql` | Database schema changes |

---

## 🔐 Security Checklist

- [ ] Email configuration uses secure SMTP
- [ ] Database backups created before migration
- [ ] File permissions correct (readable by web server)
- [ ] No sensitive data in error logs
- [ ] OTP codes not logged in plain text
- [ ] Permissions table properly indexed
- [ ] Session configuration secure

---

## 📞 Support Resources

### If OTP system not working:
1. Check `TESTING_GUIDE.md` → Section 1 (OTP Testing)
2. Run system-check.php
3. Check error logs

### If notifications not working:
1. Check `TESTING_GUIDE.md` → Section 2-3 (Notifications)
2. Test API: `/includes/utilities/notifications.php?action=recent`
3. Check browser console (F12)

### If settings not persisting:
1. Check `TESTING_GUIDE.md` → Section 4 (Settings Testing)
2. Test database connection
3. Verify ON DUPLICATE KEY UPDATE syntax

### If permissions not working:
1. Check `TESTING_GUIDE.md` → Section 5 (Permissions Testing)
2. Test hasPermission() function directly
3. Verify super_admin role in database

---

## 🎯 Next Steps After Verification

1. **Apply Permission Checks** (Optional but recommended)
   - Add `requirePermission()` calls to existing admin pages
   - See `IMPLEMENTATION_CHECKLIST.md` for details

2. **Test End-to-End**
   - Follow all test cases in `TESTING_GUIDE.md`
   - Document any issues

3. **Deploy to Production**
   - Backup production database
   - Run OTP_MIGRATION.sql
   - Deploy updated files
   - Run system-check.php in production
   - Test on live system

4. **Monitor**
   - Watch error logs for issues
   - Track OTP delivery success rate
   - Monitor permission denials

---

## ✅ Success Indicators

You'll know everything is working when:

- ✅ Students can register and verify via OTP
- ✅ Notification modal appears when clicking bell icon
- ✅ Pagination works with >7 notifications
- ✅ Admin can change settings and they persist
- ✅ Permission-based access control works
- ✅ No PHP errors in error log
- ✅ No JavaScript errors in console

---

## 📋 Quick Reference Commands

```bash
# Check database connection
mysql -u root -p -e "USE complaint_system; SELECT VERSION();"

# Backup database
mysqldump -u root -p complaint_system > backup.sql

# Apply migration
mysql -u root -p complaint_system < OTP_MIGRATION.sql

# View error log (Linux/Mac)
tail -f /var/log/php-errors.log

# View error log (Windows - in XAMPP)
tail -f C:\xampp\php\logs\php_error.log
```

---

**Last Updated:** 2024-03-08  
**Version:** 1.0  
**Status:** Ready for Deployment
