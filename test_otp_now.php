<?php
// Set timeout to prevent hanging
set_time_limit(30);

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/constants.php';
require_once 'config/database.php';
require_once 'includes/utilities/helpers.php';

echo "=== OTP Email Test ===\n\n";

try {
    // Test 1: Load mail config
    echo "1. Loading Mail Configuration...\n";
    require_once 'config/mail_config.php';
    echo "✓ Mail config loaded\n\n";
    
    // Test 2: Get mailer instance
    echo "2. Getting Mailer Instance...\n";
    $mailer = getMailer();
    echo "✓ Mailer instance created\n";
    echo "   Mailer type: " . get_class($mailer) . "\n\n";
    
    // Test 3: Generate OTP
    echo "3. Generating OTP...\n";
    $otp = generateOTP(6);
    echo "✓ OTP generated: " . $otp . "\n\n";
    
    // Test 4: Send test email
    echo "4. Sending Test Email...\n";
    echo "   To: test@example.com\n";
    echo "   Method: sendOTPEmail()\n";
    
    $testEmail = 'test@example.com';
    $testName = 'Test User';
    $testOTP = '123456';
    
    $sendResult = sendOTPEmail($testEmail, $testName, $testOTP);
    
    if ($sendResult) {
        echo "✓ SUCCESS - Email sent!\n\n";
    } else {
        echo "✗ FAILED - Email not sent\n\n";
    }
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n5. Check Error Log:\n";
$logFile = ini_get('error_log');
if ($logFile && file_exists($logFile)) {
    echo "   Location: $logFile\n";
    exec("powershell -NoProfile -Command \"(Get-Content '$logFile' -Tail 10 | Out-String)\"", $output);
    echo implode("\n", $output);
} else {
    echo "   Error log file not found or not configured\n";
}

echo "\n=== Test Complete ===\n";
?>
