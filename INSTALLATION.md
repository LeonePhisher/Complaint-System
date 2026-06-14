# HTU Complaint System - Installation Guide

## 1. Database Setup
1. Create MySQL database: `complaint_system`
2. Import the provided SQL schema
3. Update `config/database.php` with your credentials

## 2. Email Configuration (Gmail SMTP)
1. Enable 2FA on your Gmail account
2. Generate an App Password:
   - Go to Google Account → Security
   - Enable 2-Step Verification
   - Generate App Password (select 'Mail' and 'Other')
   - Copy the 16-character password

3. Update `config/constants.php`:
```php
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-16-char-app-password'); // Use the App Password here!