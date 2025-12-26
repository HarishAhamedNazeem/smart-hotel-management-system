<?php
// Configure session cookie to expire after 2 hours
session_set_cookie_params(7200);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once "db.php";
require_once "includes/auth.php";
require_once "includes/rbac.php";
require_once "includes/security.php";

// Check for inactivity timeout (2 hours = 7200 seconds for admin/staff portal)
if (isset($_SESSION['user_id']) && isset($_SESSION['LAST_ACTIVITY'])) {
    if (time() - $_SESSION['LAST_ACTIVITY'] > 7200) {
        // Session expired due to inactivity
        session_unset();
        session_destroy();
        header('Location: login.php?error=session_timeout');
        exit();
    }
}

// Update last activity time for admin session
if (isset($_SESSION['user_id'])) {
    $_SESSION['LAST_ACTIVITY'] = time(); // Admin activity
}

// IMPORTANT: Prevent guest users from accessing admin/staff portal
// Don't clear guest session - preserve it so user can return to guest portal
// Just prevent access conflicts by ensuring admin session takes precedence
// Guest session will be preserved with separate LAST_ACTIVITY_GUEST tracking

// Check if user is logged in as admin/staff (NOT guest)
// Require both user_id and logged_in to be set
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Not logged in as admin/staff - redirect to login page
    // Clear any partial session data
    if (isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
        // Partial session - clear it
        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
    }
    header('Location: login.php');
    exit();
}

// Additional check: if guest_id is set but user_id is not, redirect to guest portal
if (isset($_SESSION['guest_id']) && !isset($_SESSION['user_id'])) {
    // Guest is logged in, redirect to guest home page
    header('Location: home.php');
    exit();
}

// Require login (for additional checks)
requireLogin();

// Get current user with error handling
$user = getCurrentUser();
if (!$user) {
    // User session exists but user not found in database
    // This could happen if:
    // 1. User was deleted from database
    // 2. Database connection issue
    // 3. Session cache is corrupted
    
    // Clear the session cache and try once more
    clearUserCache();
    $user = getCurrentUser();
    
    if (!$user) {
        // Still not found - destroy session and redirect
        session_destroy();
        header('Location: login.php?error=session_invalid');
        exit();
    }
}

// Check if user is active
if ($user['status'] !== 'active') {
    session_destroy();
    // Redirect to login for inactive accounts
    header('Location: login.php?error=inactive');
    exit();
}


// Note: All staff (concierge, housekeeping_staff) now use main dashboard like Receptionist

include_once "header.php";

// Load appropriate sidebar based on role
if (file_exists('includes/sidebar_loader.php')) {
    include_once 'includes/sidebar_loader.php';
} else {
    include_once "sidebars/sidebar.php";
}


if (isset($_GET['room_mang'])){
    include_once "room_mang.php";
}
elseif (isset($_GET['dashboard'])){
    // Load role-specific dashboard
    if (hasRole('super_admin') && file_exists('dashboards/super_admin_dashboard.php')) {
        include_once "dashboards/super_admin_dashboard.php";
    } else {
        include_once "dashboard.php";
    }
}
elseif (isset($_GET['reservation'])){
    include_once "reservation.php";
}
elseif (isset($_GET['staff_mang'])){
    include_once "staff_mang.php";
}
elseif (isset($_GET['add_emp'])){
    include_once "add_emp.php";
}
elseif (isset($_GET['statistics'])){
    include_once "statistics.php";
}
elseif (isset($_GET['emp_history'])){
    include_once "emp_history.php";
}
elseif (isset($_GET['users'])){
    include_once "admin/users.php";
}
elseif (isset($_GET['roles'])){
    include_once "admin/roles.php";
}
elseif (isset($_GET['audit'])){
    include_once "admin/audit_logs.php";
}
elseif (isset($_GET['create_employee_user'])){
    include_once "admin/create_employee_user.php";
}
elseif (isset($_GET['branches'])){
    include_once "admin/branches.php";
}
elseif (isset($_GET['promotions'])){
    // Allow access for super admin, administrators, and users with promotion permissions
    if (!hasRole('super_admin') && !hasRole('administrator') && !hasPermission('promotion.read')) {
        header('Location:index.php?dashboard');
        exit();
    }
    include_once "admin/promotions.php";
}
elseif (isset($_GET['services'])){
    // Allow access for staff, super admin, administrators, and users with service permissions
    if (!isStaff() && !hasRole('super_admin') && !hasRole('administrator') && !hasPermission('service.read')) {
        header('Location:index.php?dashboard');
        exit();
    }
    include_once "services.php";
}
elseif (isset($_GET['service_types'])){
    // Allow access for super admin, administrators, and users with service permissions
    if (!hasRole('super_admin') && !hasRole('administrator') && !hasPermission('service.assign')) {
        header('Location:index.php?dashboard');
        exit();
    }
    include_once "service_types_mang.php";
}
elseif (isset($_GET['meal_packages'])){
    // Allow access for super admin, administrators, and users with package permissions
    if (!hasRole('super_admin') && !hasRole('administrator') && !hasPermission('package.read')) {
        header('Location:index.php?dashboard');
        exit();
    }
    include_once "meal_packages.php";
}
elseif (isset($_GET['facilities'])){
    // Allow access for super admin, administrators, and users with facility permissions
    if (!hasRole('super_admin') && !hasRole('administrator') && !hasPermission('facility.read') && !hasPermission('facility.create')) {
        header('Location:index.php?dashboard');
        exit();
    }
    include_once "facilities.php";
}
elseif (isset($_GET['facility_bookings'])){
    // Allow access for super admin, administrators, and users with facility permissions
    if (!hasRole('super_admin') && !hasRole('administrator') && !hasPermission('facility.read') && !hasPermission('facility.create')) {
        header('Location:index.php?dashboard');
        exit();
    }
    include_once "facility_bookings.php";
}
elseif (isset($_GET['analytics'])){
    // Allow access for super admin and administrators only
    if (!hasRole('super_admin') && !hasRole('administrator')) {
        header('Location:index.php?dashboard');
        exit();
    }
    include_once "admin/analytics_dashboard.php";
}
else{
    include_once "room_mang.php";
}

include_once "footer.php";