<?php
/**
 * Staff/Admin Logout Handler
 * Specifically for staff and admin users
 * Destroys session and redirects to staff login page
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

// Redirect to staff login page
header('Location: login.php');
exit();
