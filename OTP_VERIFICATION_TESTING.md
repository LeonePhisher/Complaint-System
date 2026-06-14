# Quick Verification Guide - OTP Registration Fix

## What Was Fixed
✅ Registration process now generates and sends OTP **immediately** during registration  
✅ No more page hanging or infinite loading  
✅ Email sent before redirect to verify page  
✅ Countdown timer starts from OTP generation time  

## Step-by-Step Test

### 1. Login to Admin Panel First (Optional)
- Go to http://localhost/complaint-system/pages/admin/login.php
- Or skip to Register if no admin account exists

### 2. Test Registration Process
1. Go to: **http://localhost/complaint-system/pages/auth/register.php**
2. Fill in the registration form:
   - Index Number: `TEST001`
   - Email: `your_email@gmail.com` (use a real email to receive OTP)
   - Password: `Test@1234`
   - Full Name: `Test User`
   - Phone: `0701234567`
   - Department: Select one
   - Level: Select one
3. Click **"Create Account"** button

### 3. Expected Behavior (FIXED)
- ✅ Page should redirect immediately (NO LOADING)
- ✅ Should see verify page with countdown timer
- ✅ Should show message: "Welcome! Check your email for your 6-digit verification code"
- ✅ Countdown timer should show ~10 minutes (e.g., "09:58")

### 4. Check Email
- Open your email inbox (Gmail, Yahoo, etc.)
- Look for email from: **jaytechmobilemoney@gmail.com**
- Subject: **Your Verification Code**
- Body should contain 6-digit code

**If Email Not Received:**
- Check spam/junk folder
- If nothing there, run: http://localhost/complaint-system/test_otp_email.php
- This tests if mail system is working

### 5. Verify the Code
1. Copy the 6-digit code from email
2. Paste it into the "Enter verification code:" field on verify page
3. Click **"Verify Account"** button
4. Should see: "Email verified successfully!"
5. Should be redirected to login page automatically

### 6. Test "Request Another Code"
1. Go back to registration
2. Register with a different index number (TEST002)
3. Wait for countdown to reach 0 minutes (or click "Request Another Code" if visible)
4. Verify new code is sent to email
5. New countdown should say "10:00" (fresh 10 minutes)

---

## Verification Checklist

| Check | Expected | Status |
|-------|----------|--------|
| Registration page loads | Yes | ☐ |
| Form fills without errors | Yes | ☐ |
| Submit button works | Yes | ☐ |
| Page redirects immediately | Yes (NO HANGING) | ☐ |
| Verify page shows countdown | Yes | ☐ |
| Email received within 1 min | Yes | ☐ |
| Email has valid 6-digit code | Yes | ☐ |
| Code verification works | Yes | ☐ |
| Countdown timer is accurate | ~10 min | ☐ |
| "Request Another Code" works | Yes (if expired) | ☐ |
| Can verify with new code | Yes | ☐ |
| "Resend OTP" via AJAX works | Yes | ☐ |

---

## Debug Commands (if needed)

### Check Database Schema
```sql
DESC users;
-- Should show: otp_code, otp_expires, otp_attempts columns
```

### Check if OTP Was Generated
```sql
SELECT id, email, otp_code, otp_expires, otp_attempts 
FROM users 
WHERE email = 'your_email@gmail.com';
```

### Check OTP History
```sql
SELECT * FROM otp_history 
WHERE user_id = (SELECT id FROM users WHERE email = 'your_email@gmail.com')
ORDER BY created_at DESC;
```

### Test Mail Directly
Run this file: `http://localhost/complaint-system/test_otp_email.php`

It will:
1. Test Mail configuration
2. Send a test OTP email
3. Display success/error messages
4. Log results to console

---

## Common Issues & Solutions

### Issue: "Page keeps loading after clicking Create Account"
**Solution:**
1. Check if email is valid format (must have @domain.com)
2. Check database connection (test in install/check.php)
3. Check mail configuration (run test_otp_email.php)
4. Look at browser console (F12) for error messages

### Issue: "Verify page not showing countdown timer"
**Solution:**
1. Refresh the page (Ctrl+F5)
2. Check browser cache is cleared
3. Check if otp_expires column exists in database

### Issue: "Email not received"
**Solution:**
1. Check spam/junk folder
2. Run test_otp_email.php to verify mail system
3. Check email address was typed correctly
4. Look at server error logs

### Issue: "Code shows as expired immediately"
**Solution:**
1. Check server time is correct
2. Check database server and web server have same timezone
3. Verify otp_expires timestamp in database

---

## Files Modified

📝 **includes/auth/register.inc.php**
- Now generates OTP during registration
- Sends email before redirect
- Uses database transactions

📝 **pages/auth/verify.php**
- Changed auto-load to skip generating new OTP
- Only loads existing OTP from registration
- Manual request path unchanged

---

## Success Indicators

You'll know the fix is working when:

1. **Registration finishes immediately** - No hanging/loading wheel
2. **Verify page appears instantly** - With countdown timer showing ~10:00
3. **Email arrives within 1 minute** - With valid 6-digit code
4. **Code verification works** - Enter code and it's accepted
5. **Request Another Code works** - After expiry, get new code
6. **Can complete full registration** - Email verified → login successfully

---

## Next Steps

After successful registration verification:

1. Test login with verified account
2. Submit a complaint (test main functionality)
3. Test notifications (if implemented)
4. Check admin panel (if applicable)

---

**Having Issues?**

1. Check the "Debug Commands" section above
2. Review browser console (F12) for JavaScript errors
3. Check server error logs in `/logs/` folder
4. Run test_otp_email.php to verify mail system
5. Verify database has all required columns (check htu_complaint_system.sql)
