<?php
// config/env.example.php
// Copy this to config/env.php and update values

return [
    // Database Configuration
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'complaint_system',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    
    // Application
    'APP_NAME' => 'HTU Complaint System',
    'APP_URL' => 'http://localhost/complaint-system',
    'APP_ENV' => 'development', // development, production
    'DEBUG' => true,
    
    // Security
    'ENCRYPTION_KEY' => 'your-32-character-encryption-key-here',
    'JWT_SECRET' => 'your-jwt-secret-key-here',
    
    // Email
    'MAIL_DRIVER' => 'smtp',
    'MAIL_HOST' => 'smtp.gmail.com',
    'MAIL_PORT' => 587,
    'MAIL_USERNAME' => 'your-email@gmail.com',
    'MAIL_PASSWORD' => 'your-app-password',
    'MAIL_ENCRYPTION' => 'tls',
    'MAIL_FROM_ADDRESS' => 'noreply@htu.edu.gh',
    'MAIL_FROM_NAME' => 'HTU Complaint System',
    
    // File Upload
    'UPLOAD_MAX_SIZE' => 5242880, // 5MB
    'ALLOWED_FILE_TYPES' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
    'UPLOAD_PATH' => 'assets/uploads/',
    
    // Features
    'ENABLE_REGISTRATION' => true,
    'ENABLE_EMAIL_VERIFICATION' => true,
    'ENABLE_TWO_FACTOR' => false,
    'ENABLE_FILE_UPLOAD' => true,
    'ENABLE_VOTING' => true,
    'ENABLE_COMMENTS' => true,
    
    // Limits
    'DAILY_COMPLAINT_LIMIT' => 5,
    'COMPLAINT_CHAR_LIMIT' => 5000,
    'COMMENT_CHAR_LIMIT' => 1000,
    
    // Cache
    'CACHE_ENABLED' => false,
    'CACHE_TIME' => 3600,
];
?>