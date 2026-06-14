# Testing Guide - HTU Complaint System Fixes

## Overview
This document provides step-by-step testing procedures for all four major fixes implemented in the complaint system.

---

## 1. OTP-Based Email Verification Testing

### Pre-Test Setup
- Ensure email configuration is correct in `/config/mail_config.php`
- Check that XAMPP is running and MySQL database is active
- Have a test email account ready that can receive emails

### Test Case 1.1: Register New Student with OTP
**Steps:**
1. Navigate to registration page
2. Fill in student information (name, email, password)
3. Submit form
4. Verify page shows OTP request interface
5. Check email inbox for OTP message
6. **PASS if:** Email received with 6-digit OTP code within 30 seconds

### Test Case 1.2: OTP Countdown Timer
**Steps:**
1. From Test 1.1, note the timer value (should show ~10:00)
2. Wait 30 seconds
3. Check timer has decreased to ~9:30
4. **PASS if:** Timer counts down in real-time

### Test Case 1.3: Verify with Correct OTP
**Steps:**
1. From Test 1.1, copy OTP from email
2. Enter OTP in verification form
3. Click "Verify" button
4. **PASS if:** 
   - Page redirects to login or dashboard
   - No errors displayed
   - Account is marked as verified in database

### Test Case 1.4: Verify with Incorrect OTP
**Steps:**
1. Go back to verify page (or request new OTP)
2. Request fresh OTP if needed
3. Enter wrong OTP (e.g., if OTP is 123456, enter 654321)
4. Click "Verify"
5. **PASS if:** 
   - Error message shows: "Invalid OTP"
   - Attempts counter shows decreasing count
   - Timer continues running

### Test Case 1.5: Maximum Attempts Exceeded
**Steps:**
1. Request new OTP
2. Enter wrong OTP 5 times
3. On 6th attempt, click "Verify"
4. **PASS if:** 
   - Error message: "Too many failed attempts"
   - "Request OTP" button is disabled/grayed out
   - User must wait for OTP to expire to try again

### Test Case 1.6: OTP Expiration
**Steps:**
1. Request new OTP (timer shows 10:00)
2. Wait full 10 minutes (or reduce OTP_EXPIRY_MINUTES in settings to test faster)
3. Try to enter OTP when timer reaches 0:00
4. **PASS if:** 
   - Error: "OTP has expired"
   - "Request OTP" button becomes enabled for new request

### Test Case 1.7: Resend OTP
**Steps:**
1. Request OTP (timer at 10:00)
2. Wait ~2 minutes
3. Click "Request Another OTP" (or equivalent button)
4. Check email for NEW OTP (different from first)
5. **PASS if:** 
   - New OTP sent to email
   - Timer resets to 10:00
   - Old OTP becomes invalid

### Verification Checklist
- ✅ Email sending works (check email received)
- ✅ OTP format is 6 digits
- ✅ Countdown timer shows correct time
- ✅ Correct OTP accepted
- ✅ Wrong OTP rejected with error
- ✅ Attempt counter works
- ✅ Max attempts blocks verification
- ✅ Expired OTP rejected
- ✅ Resend creates new OTP

---

## 2. Notification Modal Display Testing

### Test Case 2.1: Bell Icon Shows Unread Count
**Steps:**
1. Create a new student account
2. Apply for a complaint from another student account
3. Log in as admin or responsible party
4. Submit a response/status change
5. Go to student dashboard
6. Check bell icon in navigation (top-right area)
7. **PASS if:** Bell icon shows number badge (e.g., "1")

### Test Case 2.2: Click Bell to Open Modal
**Steps:**
1. From Test 2.1, click on bell icon
2. Small modal/panel should drop down
3. **PASS if:** 
   - Modal appears with smooth animation
   - Modal is visible and not hidden behind other elements
   - Recent notifications displayed

### Test Case 2.3: View Recent Notifications in Modal
**Steps:**
1. From Test 2.2, modal is open
2. Check for unread notifications
3. **PASS if:** 
   - Each notification shows: icon, title, message, time
   - Unread notifications have different styling
   - Read notifications have different styling
   - Time shows relative time (e.g., "2 minutes ago")

### Test Case 2.4: Mark Single Notification as Read
**Steps:**
1. Modal open with unread notification
2. Click on notification item
3. **PASS if:** 
   - Styling changes to "read" state
   - If notification has related_url, navigates to it
   - Bell icon count decreases by 1

### Test Case 2.5: Mark All Notifications as Read
**Steps:**
1. Modal open with multiple unread notifications
2. Look for "Mark all as read" or similar button
3. Click it
4. **PASS if:** 
   - All notifications change to "read" styling
   - Bell icon count becomes "0"

### Test Case 2.6: View All Notifications Button
**Steps:**
1. Modal open
2. Look for "View all" or similar button
3. Click it
4. **PASS if:** 
   - Navigates to full notifications page
   - Full notification list shown with pagination

### Test Case 2.7: Modal Closes on Click Away
**Steps:**
1. Modal open
2. Click outside modal area
3. **PASS if:** 
   - Modal closes smoothly
   - Bell icon is clickable again

### Verification Checklist
- ✅ Bell icon displays unread count
- ✅ Modal opens when bell clicked
- ✅ Modal shows recent notifications
- ✅ Can mark single notification as read
- ✅ Can mark all as read
- ✅ "View all" button navigates correctly
- ✅ Modal closes on click away
- ✅ No modal positioning issues

---

## 3. Notification Pagination Testing

### Pre-Test Setup
- Create ~20+ notifications for a test account
- Can create test notifications by triggering multiple complaint events
- Or insert test data directly into database:
  ```sql
  INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
  VALUES (1, 'Test', 'Message', 'info', 0, NOW());
  ```

### Test Case 3.1: Pagination Displays Correctly
**Steps:**
1. Navigate to notifications page as student
2. If >7 notifications exist, should see pagination controls
3. **PASS if:** 
   - Shows "Page 1 of X" indicator
   - Shows correct total count at top
   - Shows range "Showing 1 to 7 of 20" etc.

### Test Case 3.2: First Page Display
**Steps:**
1. On notifications page (first page)
2. Check "First" and "Previous" buttons
3. **PASS if:** 
   - "First" button disabled (grayed out)
   - "Previous" button disabled
   - "Next" button enabled
   - Correct items displayed (1-7)

### Test Case 3.3: Next Page Navigation
**Steps:**
1. Click "Next" button
2. Page should change to page 2
3. **PASS if:** 
   - URL changes to `?page=2`
   - Items 8-14 displayed
   - "Previous" button now enabled
   - Page indicator shows "Page 2"

### Test Case 3.4: Previous Page Navigation
**Steps:**
1. From Test 3.3 (on page 2)
2. Click "Previous" button
3. **PASS if:** 
   - Returns to page 1
   - Shows items 1-7
   - "First" and "Previous" disabled again

### Test Case 3.5: Last Page Navigation
**Steps:**
1. From page 1
2. Click "Last" button
3. **PASS if:** 
   - Navigates to last page (page X)
   - Shows remaining items (may be <7)
   - "Next" and "Last" buttons disabled

### Test Case 3.6: First Button Navigation
**Steps:**
1. From last page or middle page
2. Click "First" button
3. **PASS if:** 
   - Returns to page 1
   - Shows first 7 notifications

### Test Case 3.7: Direct Page URL
**Steps:**
1. Manually navigate to `notifications.php?page=2`
2. **PASS if:** 
   - Page loads correctly
   - Shows page 2 items
   - Pagination controls reflect page 2
   - No errors displayed

### Test Case 3.8: Admin Notification Pagination
**Steps:**
1. Repeat Test 3.1-3.7 for admin notifications page
2. Navigate to admin panel notifications page
3. **PASS if:** 
   - Same pagination behavior as student page
   - Uses admin_id instead of student_id
   - Shows admin-relevant notifications

### Verification Checklist
- ✅ Pagination displays on page with >7 items
- ✅ First page: first + previous buttons disabled
- ✅ Next button works and increments page
- ✅ Previous button works and decrements page
- ✅ Last page: next + last buttons disabled
- ✅ First button returns to page 1
- ✅ Direct URL navigation works
- ✅ Admin pagination works same as student
- ✅ Correct item counts per page
- ✅ No duplicate items between pages

---

## 4. Admin Settings Implementation Testing

### Pre-Test Setup
- Log in as super_admin account
- Navigate to Admin Panel → Settings (new page)

### Test Case 4.1: Access Settings Page
**Steps:**
1. Log in as super_admin
2. Navigate to Admin Dashboard
3. Click "Settings" in menu
4. **PASS if:** 
   - Settings page loads (settings-new.php)
   - Should show different setting sections
   - No access denied error

### Test Case 4.2: Non-Super-Admin Cannot Access
**Steps:**
1. Log in AS a regular admin (not super_admin)
2. Try to navigate to settings page directly
3. **PASS if:** 
   - Access denied or redirected
   - Error message displayed
   - Cannot see settings form

### Test Case 4.3: Read Current Settings
**Steps:**
1. On settings page as super_admin
2. Check each setting field
3. **PASS if:** 
   - All fields populated with current values
   - Values match what's in database `settings` table

### Test Case 4.4: Update Boolean Setting
**Steps:**
1. Find a boolean setting (e.g., "Allow Registration")
2. Toggle checkbox (check if unchecked, uncheck if checked)
3. Click "Save Settings"
4. **PASS if:** 
   - No error message
   - Success confirmation shown
   - Page reloads with new value

### Test Case 4.5: Update Text Setting
**Steps:**
1. Find text setting (e.g., "Site Name")
2. Change text value
3. Click "Save Settings"
4. **PASS if:** 
   - Changes saved (check database)
   - New value appears on site
   - Previous value overwritten

### Test Case 4.6: Update Numeric Setting
**Steps:**
1. Find numeric setting (e.g., "OTP Expiry Minutes")
2. Change number (e.g., 10 to 15)
3. Click "Save Settings"
4. Request new OTP and verify countdown reflects new time
5. **PASS if:** 
   - New value saved in database
   - System behavior uses new value

### Test Case 4.7: Persistence Across Page Reload
**Steps:**
1. Change a setting (e.g., theme)
2. Save it
3. Navigate away (e.g., to dashboard)
4. Return to settings page
5. **PASS if:** 
   - Setting still shows changed value
   - Change persisted to database

### Test Case 4.8: Multiple Settings Update
**Steps:**
1. Change 3-4 different settings
2. Click "Save Settings" once
3. **PASS if:** 
   - All changes saved in one transaction
   - No partial saves
   - All changes reflected on reload

### Test Case 4.9: Database Verification
**Steps:**
1. Change a setting (e.g., OTP expiry from 10 to 20)
2. Open MySQL/database tool
3. Query: `SELECT * FROM settings WHERE setting_key = 'otp_expiry_minutes'`
4. **PASS if:** 
   - Value in database is 20
   - updated_at timestamp is recent

### Verification Checklist
- ✅ Super admin can access settings
- ✅ Non-super-admin cannot access
- ✅ Settings load with current values
- ✅ Boolean settings can be toggled
- ✅ Text settings can be changed
- ✅ Numeric settings can be changed
- ✅ Settings persist after reload
- ✅ Multiple settings saved atomically
- ✅ Database reflects changes
- ✅ System uses new settings values

---

## 5. Admin Access Control & Permissions Testing

### Pre-Test Setup
- Have at least 2 admin accounts (one super_admin, one regular)
- Log in as super_admin

### Test Case 5.1: Super Admin Has All Permissions
**Steps:**
1. Log in as super_admin
2. Try to access various admin features (complaints, users, settings, reports)
3. **PASS if:** 
   - Can access all restricted pages
   - No permission errors

### Test Case 5.2: Grant Permission to Admin
**Steps:**
1. Log in as super_admin
2. Navigate to Users/Admin management page
3. Find regular admin and edit their permissions
4. Check permission: "manage_settings"
5. Save/Submit
6. **PASS if:** 
   - Admin record saved with new permission
   - Verification: check database `admins.permissions` field

### Test Case 5.3: Revoke Permission from Admin
**Steps:**
1. From Test 5.2, same admin has "manage_settings"
2. Uncheck "manage_settings"
3. Save
4. **PASS if:** 
   - Permission removed from admin record
   - Database shows permission no longer in JSON array

### Test Case 5.4: Restricted Page Access with Permission
**Steps:**
1. Admin has "manage_settings" permission (from Test 5.2)
2. Log in AS that admin
3. Navigate to Settings page
4. **PASS if:** 
   - Can access settings page
   - No permission error
   - Can make changes

### Test Case 5.5: Restricted Page Access without Permission
**Steps:**
1. Admin does NOT have "manage_settings" permission
2. Log in AS that admin
3. Try to navigate to Settings page
4. **PASS if:** 
   - Access denied
   - Redirected to dashboard or error page
   - Clear permission error message

### Test Case 5.6: Permission Check Function Works
**Steps:**
1. In code/admin page, call `hasPermission('manage_settings')`
2. Test with admin who has permission
3. Test with admin who doesn't have permission
4. **PASS if:** 
   - Returns true when permission exists
   - Returns false when permission missing
   - Super admin always returns true

### Test Case 5.7: Audit Log of Permission Changes
**Steps:**
1. Grant/revoke permission from admin
2. Check audit log table
3. Query: `SELECT * FROM audit_log WHERE action = 'PERMISSION_UPDATED' ORDER BY created_at DESC LIMIT 1`
4. **PASS if:** 
   - Entry exists in audit log
   - Shows admin_id of who made change
   - Shows timestamp of change
   - Shows old and new values

### Test Case 5.8: Permission Caching
**Steps:**
1. Admin logged in with permission
2. Grant new permission while admin session active
3. Try restricted page
4. **PASS if:** 
   - Still works with old permissions (cached)
   - On new session, new permissions apply
   - (Confirm: refresh page first, then check)

### Test Case 5.9: Available Permissions List
**Steps:**
1. Call API: `GET /api/admin_permissions.php?action=get_permissions&admin_id=X`
2. Check response
3. **PASS if:** 
   - Returns list of available permissions:
     - view_complaints
     - manage_complaints
     - view_reports
     - manage_users
     - manage_admins
     - view_settings
     - manage_settings
     - view_audit_log

### Test Case 5.10: Check Permission via API
**Steps:**
1. Call API: `GET /api/admin_permissions.php?action=check_permission&permission=manage_settings`
2. Check response
3. **PASS if:** 
   - Returns success: true if admin has permission
   - Returns success: false if admin lacks permission

### Verification Checklist
- ✅ Super admin can access all features
- ✅ Can grant permissions to admin
- ✅ Can revoke permissions from admin
- ✅ Admin with permission can access restricted page
- ✅ Admin without permission denied access
- ✅ hasPermission() function works correctly
- ✅ Audit log records permission changes
- ✅ Permission caching works
- ✅ Available permissions listed
- ✅ API checks permissions correctly

---

## Integration Testing

### Test Case 6.1: Complete Registration → Verification → Login Flow
**Steps:**
1. Register new student
2. Receive OTP in email
3. Verify account with OTP
4. Log in as verified student
5. Can create complaint
6. Admin receives notification
7. **PASS if:** All steps work without errors

### Test Case 6.2: Complaint Triggers Notification to Admin
**Steps:**
1. Student submits complaint
2. Admin receives notification (check email/modal)
3. Admin clicks notification
4. Navigates to complaint details
5. **PASS if:** All steps work correctly

### Test Case 6.3: Admin Settings Affects System
**Steps:**
1. Set "require_verification" to false
2. New registration should not require OTP
3. Set back to true
4. Next registration requires OTP
5. **PASS if:** System behavior changes with setting

---

## Database Verification Steps

### Verify OTP Migration
```sql
-- Check OTP columns exist
DESC users;
-- Should show: otp_code, otp_expires, otp_attempts

-- Check otp_history table exists
DESC otp_history;

-- Check settings table has new settings
SELECT * FROM settings WHERE setting_key LIKE 'otp_%' OR setting_key = 'notifications_per_page';
```

### Verify Notifications Setup
```sql
-- Check notification columns
DESC notifications;
-- Should show both user_id and admin_id

-- Check indexes exist
SHOW INDEX FROM notifications;
-- Should have idx_user and idx_admin
```

### Verify Admin Permissions
```sql
-- Check admins.permissions column
DESC admins;

-- Check sample permissions data
SELECT id, username, role, permissions FROM admins LIMIT 1;
```

### Verify Settings Persistence
```sql
-- Check all settings saved
SELECT * FROM settings ORDER BY setting_key;

-- Verify specific setting
SELECT setting_value FROM settings WHERE setting_key = 'site_name';
```

---

## Troubleshooting Guide

### Issue: OTP Email Not Sending
**Diagnosis:**
- Check `/config/mail_config.php` SMTP settings
- Check error log: `error_log("OTP Email: ...")`
- Test with simple mail send first

**Solution:**
- Verify SMTP credentials
- Check firewall/port blocking
- Enable "Less secure apps" if Gmail
- Use alternative email service

### Issue: Notification Modal Not Showing
**Diagnosis:**
- Open browser console (F12)
- Check for JavaScript errors
- Verify notifications.php API returns data

**Solution:**
- Clear browser cache
- Check console for errors
- Verify API endpoint exists
- Test API with curl/Postman

### Issue: Pagination Showing Wrong Page
**Diagnosis:**
- Check database query result
- Verify LIMIT/OFFSET calculations
- Check page parameter in URL

**Solution:**
- Verify notification count in database
- Manual calculate: offset = (page-1)*per_page
- Test with known page count

### Issue: Settings Changes Not Persisting
**Diagnosis:**
- Check database for settings table
- Verify INSERT...ON DUPLICATE KEY UPDATE syntax
- Check for SQL errors in error log

**Solution:**
- Verify settings table exists
- Test SQL directly on database
- Check file permissions
- Verify transaction committed

### Issue: Permission Denied Errors
**Diagnosis:**
- Verify admin ID in session
- Check permissions JSON in admins table
- Test hasPermission() function directly

**Solution:**
- Verify admin logged in correctly
- Check permissions JSON format
- Test super_admin still has access
- Clear session and re-login

---

## Performance Testing

### Test Case 7.1: Notification Loading Time
**Steps:**
1. With 100+ notifications
2. Load notifications page
3. Measure page load time
4. **PASS if:** Loads in <2 seconds

### Test Case 7.2: Pagination Query Performance
**Steps:**
1. With 1000+ notifications
2. Navigate through pages
3. Monitor query time
4. **PASS if:** Each page loads fast (<1 sec)

### Test Case 7.3: Permission Check Performance
**Steps:**
1. Call hasPermission() 100 times
2. Measure execution time
3. **PASS if:** Completes quickly (caching works)

---

## Sign-Off Checklist

- [ ] OTP verification working end-to-end
- [ ] Notification modal displays and functions correctly  
- [ ] Pagination working for both student and admin
- [ ] Settings persisting and affecting system
- [ ] Permission system enforcing access control
- [ ] Database migrations applied successfully
- [ ] No errors in PHP error log
- [ ] No errors in browser console
- [ ] All required files created
- [ ] Code follows existing patterns
- [ ] Documentation complete

---

**Testing Date:** _____________________
**Tested By:** _____________________
**Status:** ☐ PASS ☐ FAIL ☐ PARTIAL

**Issues Found:**
1. ______________________________
2. ______________________________
3. ______________________________

**Resolution:**
1. ______________________________
2. ______________________________
3. ______________________________
