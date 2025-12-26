<?php
/**
 * Role-Based Access Control (RBAC) Functions
 * Provides functions for checking permissions and roles
 */

/**
 * Check if user has a specific role
 */
function hasRole($roleName) {
    global $connection;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Check cache first
    if (isset($_SESSION['user_roles'])) {
        return in_array($roleName, $_SESSION['user_roles']);
    }
    
    // Load user roles
    $query = "SELECT r.role_name 
              FROM user_roles ur 
              JOIN roles r ON ur.role_id = r.role_id 
              WHERE ur.user_id = ? AND r.is_active = 1";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $roles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $roles[] = $row['role_name'];
    }
    
    // Cache roles in session
    $_SESSION['user_roles'] = $roles;
    
    return in_array($roleName, $roles);
}

/**
 * Check if user has a specific permission
 */
function hasPermission($permissionName) {
    global $connection;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Super admin has all permissions
    if (hasRole('super_admin')) {
        return true;
    }
    
    // Check cache first
    if (isset($_SESSION['user_permissions'])) {
        return in_array($permissionName, $_SESSION['user_permissions']);
    }
    
    // Load user permissions
    $query = "SELECT DISTINCT p.permission_name 
              FROM user_roles ur 
              JOIN role_permissions rp ON ur.role_id = rp.role_id 
              JOIN permissions p ON rp.permission_id = p.permission_id 
              WHERE ur.user_id = ?";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $permissions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $permissions[] = $row['permission_name'];
    }
    
    // Cache permissions in session
    $_SESSION['user_permissions'] = $permissions;
    
    return in_array($permissionName, $permissions);
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($roleNames) {
    foreach ($roleNames as $roleName) {
        if (hasRole($roleName)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user has all of the specified roles
 */
function hasAllRoles($roleNames) {
    foreach ($roleNames as $roleName) {
        if (!hasRole($roleName)) {
            return false;
        }
    }
    return true;
}

/**
 * Check if user has any of the specified permissions
 */
function hasAnyPermission($permissionNames) {
    foreach ($permissionNames as $permissionName) {
        if (hasPermission($permissionName)) {
            return true;
        }
    }
    return false;
}

/**
 * Require role - redirect if user doesn't have role
 */
function requireRole($roleName, $redirectUrl = 'login.php') {
    if (!hasRole($roleName)) {
        $_SESSION['error_message'] = 'You do not have permission to access this page.';
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Require permission - redirect if user doesn't have permission
 */
function requirePermission($permissionName, $redirectUrl = 'index.php?dashboard') {
    // Super admin has all permissions
    if (hasRole('super_admin')) {
        return true;
    }
    
    if (!hasPermission($permissionName)) {
        $_SESSION['error_message'] = 'You do not have permission to perform this action.';
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Get user's primary role (first role assigned)
 */
function getUserPrimaryRole($user_id = null) {
    global $connection;
    
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $user_id = $_SESSION['user_id'];
    }
    
    $query = "SELECT r.role_name 
              FROM user_roles ur 
              JOIN roles r ON ur.role_id = r.role_id 
              WHERE ur.user_id = ? AND r.is_active = 1 
              ORDER BY ur.assigned_at ASC 
              LIMIT 1";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['role_name'];
    }
    
    return null;
}

/**
 * Get all user roles
 */
function getUserRoles($user_id = null) {
    global $connection;
    
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }
        $user_id = $_SESSION['user_id'];
    }
    
    // Check cache
    if ($user_id == $_SESSION['user_id'] && isset($_SESSION['user_roles'])) {
        return $_SESSION['user_roles'];
    }
    
    $query = "SELECT r.role_name 
              FROM user_roles ur 
              JOIN roles r ON ur.role_id = r.role_id 
              WHERE ur.user_id = ? AND r.is_active = 1 
              ORDER BY r.role_name";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $roles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $roles[] = $row['role_name'];
    }
    
    return $roles;
}

/**
 * Get all user permissions
 */
function getUserPermissions($user_id = null) {
    global $connection;
    
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }
        $user_id = $_SESSION['user_id'];
    }
    
    // Super admin has all permissions
    if (hasRole('super_admin')) {
        $query = "SELECT permission_name FROM permissions";
        $result = mysqli_query($connection, $query);
        $permissions = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $permissions[] = $row['permission_name'];
        }
        return $permissions;
    }
    
    // Check cache
    if ($user_id == $_SESSION['user_id'] && isset($_SESSION['user_permissions'])) {
        return $_SESSION['user_permissions'];
    }
    
    $query = "SELECT DISTINCT p.permission_name 
              FROM user_roles ur 
              JOIN role_permissions rp ON ur.role_id = rp.role_id 
              JOIN permissions p ON rp.permission_id = p.permission_id 
              WHERE ur.user_id = ? 
              ORDER BY p.permission_name";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $permissions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $permissions[] = $row['permission_name'];
    }
    
    return $permissions;
}

/**
 * Clear role/permission cache (call after role/permission changes)
 */
function clearRBACCache() {
    unset($_SESSION['user_roles']);
    unset($_SESSION['user_permissions']);
}


/**
 * Check if user is staff
 */
function isStaff() {
    return hasAnyRole(['receptionist', 'housekeeping_manager', 'housekeeping_staff', 'maintenance_staff', 'concierge']);
}

/**
 * Check if user is administrator
 */
function isAdministrator() {
    return hasAnyRole(['super_admin', 'administrator']);
}

