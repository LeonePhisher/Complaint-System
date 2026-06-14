# QUICK REFERENCE - Implementation Guide

## 🚀 QUICK START (5 Minutes)

### Step 1: Run Database Migration
```bash
cd c:\xampp\htdocs\complaint-system
mysql -u root -p"" htu_complaint_system < ADMIN_PERMISSIONS_MIGRATION.sql
```

### Step 2: Verify Files Exist
- ✅ `api/notifications_unified.php`
- ✅ `api/admin_settings.php`
- ✅ `api/admin_permissions_api.php`
- ✅ `IMPLEMENTATION_GUIDE_FIXES.md`
- ✅ `SUMMARY_OF_FIXES.md`

### Step 3: Clear Browser Cache
Press: `Ctrl + F5` (Hard refresh)

### Step 4: Test Each Feature

---

## 📋 TESTING QUICK GUIDE

### Test 1: OTP Verification (2 min)
```
1. Go to: http://localhost/complaint-system/pages/auth/register.php
2. Register new account with email
3. Check OTP in email
4. Notice countdown timer (10:00 initially)
5. Enter OTP code
6. Should verify successfully
✅ PASS: Redirects to login with success message
```

### Test 2: Notification Modal (2 min)
**Student**:
```
1. Log in as student
2. Click bell icon in navbar
3. Modal should appear with recent notifications
4. Click "Mark all as read" → Badge disappears
5. Click notification → Marks as read & navigates
✅ PASS: Modal works, notifications marked as read
```

**Admin**:
```
1. Log in as admin
2. Click bell icon in navbar
3. Modal should appear with recent notifications
4. "View all" should go to notifications page
✅ PASS: Same functionality as student
```

### Test 3: Notification Pagination (2 min)
```
1. Click "View all notifications"
2. Go to notifications page
3. Should show 7 per page
4. Try page 2, 3, etc.
5. Previous/Next should work
✅ PASS: Pagination works with 7 per page
```

### Test 4: Admin Settings (2 min)
```
1. Go to Admin → Settings
2. Change OTP expiry to 15 minutes
3. Log out, register new account
4. Countdown should show 15:00
✅ PASS: Settings take effect immediately
```

### Test 5: Admin Permissions (2 min)
```
1. Go to Admin → Users
2. Select a non-super admin
3. Grant/revoke permissions
4. Log in as that admin
5. Verify they can/cannot access restricted areas
✅ PASS: Permissions enforced immediately
```

---

## 🔌 API ENDPOINTS REFERENCE

### Notifications
```
GET  /api/notifications_unified.php?action=recent_unread&limit=5
GET  /api/notifications_unified.php?action=unread_count
POST /api/notifications_unified.php (action=mark_read&notification_id=123)
GET  /api/notifications_unified.php?action=paginated&page=1&per_page=7
POST /api/notifications_unified.php (action=mark_all_read)
```

### Settings
```
GET  /api/admin_settings.php?action=get_all
GET  /api/admin_settings.php?action=get&key=otp_expiry_minutes
POST /api/admin_settings.php (action=update&key=...&value=...&type=...)
POST /api/admin_settings.php (action=update_multiple&settings[key]=value)
POST /api/admin_settings.php (action=reset&key=...)
```

### Permissions
```
GET  /api/admin_permissions_api.php?action=get_admin_permissions&admin_id=2
GET  /api/admin_permissions_api.php?action=get_all_with_permissions
POST /api/admin_permissions_api.php (action=update_permissions&admin_id=2&permissions[]=...)
POST /api/admin_permissions_api.php (action=grant_permission&admin_id=2&permission=...)
POST /api/admin_permissions_api.php (action=revoke_permission&admin_id=2&permission=...)
POST /api/admin_permissions_api.php (action=check_permission&admin_id=2&permission=...)
GET  /api/admin_permissions_api.php?action=available_permissions
```

---

## ⚙️ COMMON CONFIGURATIONS

### OTP Settings
```php
// In database (settings table):
otp_enabled = 1                    // 0 to disable
otp_length = 6                     // OTP code length
otp_expiry_minutes = 10            // When OTP expires
otp_max_attempts = 5               // Max wrong attempts
```

### Notification Settings
```php
notifications_per_page = 7         // Per-page count
```

---

## 🔍 HOW TO USE IN CODE

### Check Permission (PHP)
```php
// At top of admin page:
requirePermission('view_settings');

// In code:
if (hasPermission('manage_complaints')) {
    // Show button/allow action
}
```

### Get Setting (PHP)
```php
$otp_minutes = getSetting('otp_expiry_minutes', 10);
$otp_enabled = getSetting('otp_enabled', true);
```

### Get Unread Count (JavaScript)
```javascript
fetch('/api/notifications_unified.php?action=unread_count')
    .then(r => r.json())
    .then(data => {
        console.log('Unread:', data.count);
    });
```

### Update Setting (JavaScript)
```javascript
fetch('/api/admin_settings.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'action=update&key=site_name&value=New Name&type=string'
})
.then(r => r.json())
.then(data => console.log('Success:', data.success));
```

---

## 🚨 TROUBLESHOOTING

### Problem: 404 on API endpoints
**Solution**: Verify files exist in `api/` folder
```bash
ls c:\xampp\htdocs\complaint-system\api\
# Should show: notifications_unified.php, admin_settings.php, admin_permissions_api.php
```

### Problem: Permissions not working
**Solution**: Run migration and clear session
```sql
mysql -u root -p"" htu_complaint_system < ADMIN_PERMISSIONS_MIGRATION.sql
```

### Problem: Settings not taking effect
**Solution**: Verify getSetting() is using correct key
```sql
SELECT * FROM settings WHERE setting_key='your_key';
```

### Problem: OTP not sending
**Solution**: Check mail configuration
```php
// Test email in config/mail_config.php
// Verify: SMTP host, port, username, password
```

### Problem: Modal not appearing
**Solution**: Check browser console for errors
```
F12 → Console → Look for errors
Verify: api/notifications_unified.php exists
Clear cache: Ctrl+F5
```

---

## 📊 PERMISSION MATRIX

| Permission | View | Confirm | Manage | Admin |
|------------|------|---------|--------|-------|
| view_complaints | ✅ | ∟ | ✅ | ✅ |
| manage_complaints | ✅ | ✅ | ✅ | ✅ |
| view_reports | ✅ | ✗ | ∟ | ✅ |
| manage_users | ∟ | ✗ | ✅ | ✅ |
| manage_admins | ✗ | ✗ | ✗ | ✅ |
| view_settings | ✅ | ✗ | ✗ | ✅ |
| manage_settings | ✗ | ✗ | ✅ | ✅ |
| view_audit_log | ✗ | ✗ | ✗ | ✅ |

*Legend: ✅ = Has permission | ✗ = No permission | ∟ = Related*

---

## 📚 DOCUMENTATION FILES

| File | Purpose |
|------|---------|
| IMPLEMENTATION_GUIDE_FIXES.md | Complete implementation guide |
| SUMMARY_OF_FIXES.md | Overview of all changes |
| QUICK_REFERENCE.md | This file |
| ADMIN_PERMISSIONS_MIGRATION.sql | Database migration |
| TESTING_GUIDE.md | Detailed testing procedures |

---

## ✨ FEATURES IMPLEMENTED

✅ **OTP System**
- 6-digit codes
- Countdown timer
- Request another code
- Max attempts

✅ **Notification Modal**
- Recent unread notifications
- Click to mark & navigate
- Mark all as read
- View all link

✅ **Notification Pagination**
- 7 per page (configurable)
- Full pagination controls
- Works for students & admins

✅ **Admin Settings**
- Centralized API
- Immediate effect
- Type validation
- Reset to defaults

✅ **Admin Permissions**
- Relational database
- 8 permission types
- Grant/revoke
- Immediate enforcement

---

## 🎯 SUCCESS CRITERIA

After implementation, verify:

- [ ] OTP countdown timer shows and counts down
- [ ] New users can register with OTP
- [ ] Admin can modify settings and changes take effect
- [ ] Admin can grant/revoke permissions
- [ ] Regular admins respect permission restrictions
- [ ] Notification modal appears on bell click
- [ ] Notifications paginate (7 per page)
- [ ] All APIs respond with proper JSON
- [ ] No console errors in browser F12
- [ ] Database migration applied successfully

---

## 📞 SUPPORT

For issues:
1. Check **IMPLEMENTATION_GUIDE_FIXES.md** (Q&A section)
2. Review **TESTING_GUIDE.md** for step-by-step tests
3. Check browser console (F12)
4. Verify database migration: `SELECT * FROM admin_permissions LIMIT 5;`
5. Review specific API file for error details

---

**Ready to implement? Start with Step 1: Run Database Migration** ✅

*Last Updated: March 9, 2026*
