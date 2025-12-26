<?php
/**
 * Logout Router (Backward Compatibility)
 * This file routes to the appropriate logout handler:
 * - Admin/Staff users -> staff_logout.php
 * - Guest users -> guest_logout.php
 * 
 * NOTE: For new code, use staff_logout.php or guest_logout.php directly
 * This file is kept for backward compatibility with old links/bookmarks
 */

// Configure session cookie to expire when browser closes
session_set_cookie_params(0);

// Start session
session_start();

// Check if user is admin/staff or guest before routing
$isAdminStaff = isset($_SESSION['user_id']);
$isGuest = isset($_SESSION['guest_id']);

// Route to appropriate logout handler
if ($isAdminStaff) {
    // Admin/Staff users -> use staff logout
    header('Location: staff_logout.php');
} elseif ($isGuest) {
    // Guest users -> use guest logout
    header('Location: guest_logout.php');
} else {
    // No active session -> redirect to home page
    header('Location: home.php');
}
exit();