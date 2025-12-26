<?php
/**
 * Sidebar Loader
 * Loads appropriate sidebar based on user role
 */

// Determine base path based on current directory
$currentScript = $_SERVER['PHP_SELF'];
$isAdminDir = (strpos($currentScript, '/admin/') !== false);
$basePath = $isAdminDir ? '../' : '';

if (!isset($connection)) {
    include_once $basePath . 'db.php';
}
require_once $basePath . 'includes/rbac.php';

// Get user's primary role
$primaryRole = getUserPrimaryRole();
$isSuperAdmin = hasRole('super_admin');

// Base path already determined above

// Load appropriate sidebar based on role
if ($isSuperAdmin) {
    $sidebarFile = $basePath . 'sidebars/super_admin_sidebar.php';
    if (file_exists($sidebarFile)) {
        include_once $sidebarFile;
    } else {
        include_once $basePath . 'sidebars/sidebar.php';
    }
} elseif ($primaryRole === 'administrator') {
    $sidebarFile = $basePath . 'sidebars/admin_sidebar.php';
    if (file_exists($sidebarFile)) {
        include_once $sidebarFile;
    } else {
        include_once $basePath . 'sidebars/sidebar.php';
    }
} elseif ($primaryRole === 'receptionist') {
    $sidebarFile = $basePath . 'sidebars/receptionist_sidebar.php';
    if (file_exists($sidebarFile)) {
        include_once $sidebarFile;
    } else {
        include_once $basePath . 'sidebars/sidebar.php';
    }
} elseif ($primaryRole === 'housekeeping_manager') {
    $sidebarFile = $basePath . 'sidebars/housekeeping_manager_sidebar.php';
    if (file_exists($sidebarFile)) {
        include_once $sidebarFile;
    } else {
        include_once $basePath . 'sidebars/sidebar.php';
    }
} elseif (in_array($primaryRole, ['housekeeping_staff', 'maintenance_staff', 'concierge'])) {
    // Staff members use their own portal with staff sidebar
    // This shouldn't be reached as they're redirected to staff portal
    $sidebarFile = $basePath . 'sidebars/staff_sidebar.php';
    if (file_exists($sidebarFile)) {
        include_once $sidebarFile;
    } else {
        include_once $basePath . 'sidebars/sidebar.php';
    }
} else {
    // Default sidebar (fallback)
    include_once $basePath . 'sidebar.php';
}
