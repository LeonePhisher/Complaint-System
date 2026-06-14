<?php
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../utilities/helpers.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ' . APP_URL . '/pages/auth/login.php');
    exit();
}

// Clear session data
$_SESSION = array();

// Delete remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    // Delete the cookie from database
    $stmt = db()->prepare("DELETE FROM sessions WHERE id = ?");
    $stmt->execute([$_COOKIE['remember_token']]);
    
    // Delete the cookie from browser
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ' . APP_URL . '/pages/auth/login.php');
exit();
?>