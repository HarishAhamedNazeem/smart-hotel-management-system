<?php
/**
 * Authentication Helper Functions
 */

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin($redirectUrl = 'login.php') {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Update last login timestamp
 */
function updateLastLogin($user_id) {
    global $connection;
    
    $query = "UPDATE user SET last_login = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Check if email already exists (checks both user and guests tables)
 */
function emailExists($email, $excludeUserId = null) {
    global $connection;
    
    if ($excludeUserId) {
        $query = "(SELECT id FROM user WHERE email = ? AND id != ?) UNION (SELECT guest_id FROM guests WHERE email = ?)";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "sis", $email, $excludeUserId, $email);
    } else {
        $query = "(SELECT id FROM user WHERE email = ?) UNION (SELECT guest_id FROM guests WHERE email = ?)";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "ss", $email, $email);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return mysqli_num_rows($result) > 0;
}

/**
 * Check if username already exists (checks both user and guests tables)
 */
function usernameExists($username, $excludeUserId = null) {
    global $connection;
    
    if ($excludeUserId) {
        $query = "(SELECT id FROM user WHERE username = ? AND id != ?) UNION (SELECT guest_id FROM guests WHERE username = ?)";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "sis", $username, $excludeUserId, $username);
    } else {
        $query = "(SELECT id FROM user WHERE username = ?) UNION (SELECT guest_id FROM guests WHERE username = ?)";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "ss", $username, $username);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return mysqli_num_rows($result) > 0;
}

/**
 * Hash password using bcrypt
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate random password
 */
function generateRandomPassword($length = 12) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

/**
 * Generate email verification token
 */
function generateVerificationToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Store verification token
 */
function storeVerificationToken($user_id, $token) {
    global $connection;
    
    // You might want to create a separate table for email verification tokens
    // For now, we'll use a simple approach with user table
    $query = "UPDATE user SET email_verified = 0 WHERE id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Store token in session or separate table
    $_SESSION['verification_token_' . $user_id] = $token;
}

/**
 * Verify email token
 */
function verifyEmailToken($user_id, $token) {
    if (isset($_SESSION['verification_token_' . $user_id])) {
        if (hash_equals($_SESSION['verification_token_' . $user_id], $token)) {
            // Mark email as verified
            global $connection;
            $query = "UPDATE user SET email_verified = 1 WHERE id = ?";
            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            unset($_SESSION['verification_token_' . $user_id]);
            return true;
        }
    }
    return false;
}

/**
 * Get current user data
 */
function getCurrentUser() {
    global $connection;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Check cache (but verify it's still valid)
    if (isset($_SESSION['current_user_data']) && is_array($_SESSION['current_user_data'])) {
        // Verify cached user still exists in database (quick check)
        // Only use cache if user_id matches
        if (isset($_SESSION['current_user_data']['id']) && $_SESSION['current_user_data']['id'] == $user_id) {
            return $_SESSION['current_user_data'];
        } else {
            // Cache is invalid, clear it
            unset($_SESSION['current_user_data']);
        }
    }
    
    // Query database for user
    $query = "SELECT * FROM user WHERE id = ? AND status = 'active'";
    $stmt = mysqli_prepare($connection, $query);
    
    if (!$stmt) {
        // Database error - return null but don't destroy session
        // This allows retry on next request
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        mysqli_stmt_close($stmt);
        return null;
    }
    
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Only cache if user was found
    if ($user) {
        $_SESSION['current_user_data'] = $user;
    } else {
        // User not found - clear cache
        unset($_SESSION['current_user_data']);
    }
    
    return $user;
}

/**
 * Clear user session cache
 */
function clearUserCache() {
    unset($_SESSION['current_user_data']);
    unset($_SESSION['user_roles']);
    unset($_SESSION['user_permissions']);
}

/**
 * Update last login timestamp for guest
 */
function updateLastLoginGuest($guest_id) {
    global $connection;
    
    $query = "UPDATE guests SET last_login = NOW() WHERE guest_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $guest_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Check if guest account is locked
 */
function isGuestAccountLocked($guest_id) {
    global $connection;
    
    $query = "SELECT account_locked_until FROM guests WHERE guest_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $guest_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $guest = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($guest && $guest['account_locked_until']) {
        return strtotime($guest['account_locked_until']) > time();
    }
    
    return false;
}

/**
 * Increment failed login attempts for guest
 */
function incrementGuestFailedLoginAttempts($guest_id) {
    global $connection;
    
    $query = "UPDATE guests SET failed_login_attempts = failed_login_attempts + 1 WHERE guest_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $guest_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Lock account after 5 failed attempts
    $query = "UPDATE guests SET account_locked_until = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE guest_id = ? AND failed_login_attempts >= 5";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $guest_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Reset failed login attempts for guest
 */
function resetGuestFailedLoginAttempts($guest_id) {
    global $connection;
    
    $query = "UPDATE guests SET failed_login_attempts = 0, account_locked_until = NULL WHERE guest_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $guest_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

