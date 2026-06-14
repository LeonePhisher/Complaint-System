# OTP Registration Process - Fix Summary

## Problem
When users registered, the page would keep loading indefinitely and no OTP email would be sent. The registration was hanging instead of redirecting to the verification page.

## Root Cause
The original flow tried to generate and send OTP **after** redirecting to verify.php, which caused timing issues and made the redirect appear to hang.

## Solution
OTP is now generated and sent **DURING registration**, before the redirect happens. This eliminates the hanging issue completely.

## New Registration Flow

### Step 1: User Submits Registration Form
- Form contains: index_number, email, password, full_name, phone, department, level
- All validation occurs as before

### Step 2: Generate OTP During Registration (includes/auth/register.inc.php)
```php
// Generate OTP code (6 digits)
$otp_code = generateOTP(6);

// Get OTP expiry time from settings (default 10 minutes)
$otp_expires_minutes = intval(getSetting('otp_expiry_minutes', 10));
$otp_expires = date('Y-m-d H:i:s', strtotime("+$otp_expires_minutes minutes"));
```

### Step 3: Insert User WITH OTP Into Database
```php
INSERT INTO users (
    index_number, email, password_hash, full_name, phone, 
    department, level, avatar_color, verification_token, verification_expires,
    otp_code, otp_expires, otp_attempts
) VALUES (...)
```

### Step 4: Log OTP in otp_history Table
```php
INSERT INTO otp_history (user_id, otp_code, purpose, expires_at, ip_address)
VALUES (?, ?, 'verification', ?, ?)
```

### Step 5: Send OTP Email Immediately
```php
$otp_sent = sendOTPEmail($email, $full_name, $otp_code);
```

### Step 6: Redirect to verify.php with Email Parameter
- Only happens AFTER OTP is sent (or attempted)
- URL: `verify.php?email=user@example.com`
- User receives email with 6-digit code

### Step 7: Verify Page Loads OTP (NOT Generate New One)
The verification page now:
1. Checks if user already has a valid OTP from registration
2. If yes, shows countdown timer (remaining time from otp_expires)
3. If no, shows "request OTP" form
4. Never overwrites registration OTP with a new one

## Technical Changes

### Modified Files

#### 1. includes/auth/register.inc.php
**Changes:**
- Added OTP generation before user creation
- Now stores OTP code and expiry in users table during INSERT
- Calls sendOTPEmail() before redirect
- Uses database transactions to ensure all data is saved atomically

**Key Functions:**
- `generateOTP(6)` - Generates 6-digit random code
- `getSetting('otp_expiry_minutes', 10)` - Gets expiry setting
- `sendOTPEmail($email, $name, $otp)` - Sends OTP via email
- `db()->beginTransaction() / commit()` - Ensures atomic operations

#### 2. pages/auth/verify.php
**Changes:**
- Changed auto-load from "generate new OTP" to "load existing OTP"
- Now checks if OTP already exists from registration
- Never overwrites an existing valid OTP
- Calculates countdown time based on otp_expires timestamp

**Before:**
```php
// Old: Generated NEW OTP every time verify.php loaded with email param
$otp = generateOTP(6);
UPDATE users SET otp_code = ?, otp_expires = ?, otp_attempts = 0 WHERE id = ?
```

**After:**
```php
// New: Loads EXISTING OTP from registration, never generates new one in auto-load
if ($student['otp_code'] && strtotime($student['otp_expires']) > time()) {
    $otp_sent = true;
    $countdown_time = max(0, $expires_timestamp - time());
}
```

## OTP Request Logic (Two Separate Paths)

### Path 1: Auto-Load During Registration (pages/auth/verify.php lines 71-100)
- **When:** User redirected from register.inc.php with ?email= parameter
- **What:** Loads OTP that was just generated during registration
- **Countdown:** Shows time remaining (e.g., 10 minutes from registration time)
- **Never:** Generates a new OTP

### Path 2: Manual Request When No Valid OTP (pages/auth/verify.php "request_otp" form)
- **When:** User clicks "Request Another Code" or no valid OTP exists
- **What:** Generates a brand new OTP code
- **Countdown:** Starts fresh 10-minute timer
- **Frequency:** Can be requested multiple times (subject to rate limiting)

Both paths work independently and don't interfere with each other.

## Database Columns Used

### users table
- `otp_code` (varchar(6)) - The 6-digit OTP code
- `otp_expires` (datetime) - When this OTP expires
- `otp_attempts` (int) - Number of failed verification attempts with this OTP

### otp_history table
- `user_id` (int) - FK to users
- `otp_code` (varchar(6)) - The code that was sent
- `purpose` (varchar(50)) - 'verification', 'password_reset', etc.
- `is_used` (boolean) - Whether this OTP was successfully used
- `used_at` (datetime) - Timestamp when successfully used
- `expires_at` (datetime) - When this OTP expired
- `ip_address` (varchar(45)) - IP that requested the OTP
- `created_at` (timestamp) - When record was created

## Testing the Flow

### Test Case 1: New Registration with Auto OTP
1. Go to registration page
2. Fill form and click "Create Account"
3. **Expected:** Page redirects immediately (no hanging)
4. **Expected:** Shows verify.php with countdown timer
5. **Expected:** Email received with 6-digit code within 30 seconds
6. **Expected:** Can immediately enter code and verify

### Test Case 2: Request Another Code (manual path)
1. On verify.php, if countdown expired
2. Click "Request Another Code" button
3. **Expected:** New OTP generated
4. **Expected:** New countdown timer shows 10 minutes
5. **Expected:** New email sent with fresh code
6. **Expected:** Old OTP no longer works

### Test Case 3: Code Expiration
1. Complete registration (get OTP)
2. Wait 10 minutes without verifying
3. **Expected:** Countdown reaches 0 and shows expired message
4. **Expected:** "Request Another Code" button appears
5. **Expected:** Original OTP should not work

## Mail Configuration Check

The system uses PHPMailer configured in `config/mail_config.php`:
- **Host:** smtp.gmail.com
- **Port:** 587
- **Encryption:** TLS
- **Debug:** 0 (disabled)

To verify email is working:
```bash
php test_otp_email.php
```

This sends a test OTP and logs results to debug issues.

## Troubleshooting

### Issue: Page still hangs on registration
**Check:**
1. Is database connection working? (run `install/check.php`)
2. Are OTP columns in users table? (check htu_complaint_system.sql schema)
3. Is mail configuration correct? (test with test_otp_email.php)
4. Check server error logs for database/email errors

### Issue: Email not received after registration
**Check:**
1. Run `test_otp_email.php` to verify mail system works
2. Check spam/junk folder
3. Verify email address wasn't misspelled during registration
4. Check server error logs for email sending errors

### Issue: Countdown timer shows wrong time
**Check:**
1. Server timezone is correctly set
2. Database server and web server have same timezone
3. otp_expires column has recent timestamp (within 10 minutes)

### Issue: "Request Another Code" not generating new OTP
**Check:**
1. Previous OTP hasn't expired yet (auto-button only appears after expiry)
2. Click button specifically says "Request Another Code"
3. Check otp_history table for new entry

## Summary

✅ **Fixed:** Registration page no longer hangs  
✅ **Fixed:** OTP sent immediately during registration (not after redirect)  
✅ **Fixed:** Countdown timer shows from registration time, not from page load  
✅ **Fixed:** Manual "Request Another Code" works independently  
✅ **Improved:** Two OTP paths are now completely separate and don't interfere  
✅ **Improved:** Better error handling for email failures  
✅ **Improved:** Database transactions ensure atomic user creation  

All changes maintain backward compatibility with existing verification code.
