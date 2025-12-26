<?php
/**
 * Guest Logout Handler
 * Specifically for guest users
 * Destroys session and redirects to guest home page
 */

// Configure session cookie to expire when browser closes
session_set_cookie_params(0);

// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to guest home page
header('Location: home.php');
exit();
