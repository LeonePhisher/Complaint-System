<?php
require_once '../../config/constants.php';
require_once '../../includes/utilities/helpers.php';
require_once '../../includes/utilities/notifications.php';

$error = '';
$success = '';
$show_form = true;

// Get token from URL
$token = $_GET['token'] ?? '';

if ($token) {
    try {
        // Check token in users table
        $stmt = db()->prepare("
            SELECT * 
            FROM users 
            WHERE verification_token = ? 
            AND verification_expires > NOW()
            AND is_verified = 0
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Update user as verified
            $stmt = db()->prepare("
                UPDATE users SET 
                is_verified = 1,
                verification_token = NULL,
                verification_expires = NULL,
                updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$user['id']]);

            logActivity('email_verified', 'Email verified', $user['id'], 'student');

            $success = 'Email verified successfully! You can now log in.';
            $show_form = false;
        } else {
            $error = 'Invalid or expired verification link.';
        }
    } catch (PDOException $e) {
        error_log("Verification error: " . $e->getMessage());
        $error = 'Verification failed. Please try again.';
    }
}

// Handle resend verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_verification'])) {
    $email = sanitizeInput($_POST['email']);

    try {
        $stmt = db()->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['is_verified']) {
                $error = 'Email is already verified. You can log in.';
            } else {
                // Generate new token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

                // Update user with new token
                $stmt = db()->prepare("
                    UPDATE users 
                    SET verification_token = ?, verification_expires = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$token, $expires, $user['id']]);

                // Send email
                $verification_link = APP_URL . "/pages/auth/verify.php?token=" . $token;
                $mail_sent = sendVerificationEmail($email, $user['full_name'], $token);

                if ($mail_sent) {
                    $success = 'Verification email sent! Please check your inbox.';
                } else {
                    $error = 'Failed to send verification email. Please try again.';
                }
            }
        } else {
            $error = 'No account found with this email address.';
        }
    } catch (PDOException $e) {
        error_log("Resend verification error: " . $e->getMessage());
        $error = 'Failed to resend verification. Please try again.';
    }
}
?>
