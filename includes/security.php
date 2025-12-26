<?php
/**
 * Security Utilities
 * Provides CSRF protection, input sanitization, and other security functions
 */

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Use hash_equals for timing attack protection
    $isValid = hash_equals($_SESSION['csrf_token'], $token);
    
    return $isValid;
}

/**
 * Sanitize input string
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

/**
 * Sanitize for SQL (use with prepared statements instead)
 * This is a fallback - always prefer prepared statements
 */
function sanitizeForSQL($input) {
    global $connection;
    if (is_array($input)) {
        return array_map('sanitizeForSQL', $input);
    }
    return mysqli_real_escape_string($connection, $input);
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (basic)
 */
function validatePhone($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Check if it's a reasonable length (7-15 digits)
    return strlen($phone) >= 7 && strlen($phone) <= 15;
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

/**
 * Get user agent
 */
function getUserAgent() {
    return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
}

/**
 * Rate limiting check
 */
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
    $key = 'rate_limit_' . $identifier;
    $attempts = isset($_SESSION[$key]) ? $_SESSION[$key] : [];
    
    // Remove attempts outside time window
    $currentTime = time();
    $attempts = array_filter($attempts, function($timestamp) use ($currentTime, $timeWindow) {
        return ($currentTime - $timestamp) < $timeWindow;
    });
    
    // Check if limit exceeded
    if (count($attempts) >= $maxAttempts) {
        return false;
    }
    
    // Add current attempt
    $attempts[] = $currentTime;
    $_SESSION[$key] = $attempts;
    
    return true;
}

/**
 * Log security event
 * Uses audit logging system if available
 */
function logSecurityEvent($event, $details = []) {
    if (function_exists('logAuditEvent')) {
        logAuditEvent($event, 'security', null, null, null, $details);
    } else {
        // Fallback to direct database insert
        global $connection;
        
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $ip_address = getClientIP();
        $user_agent = getUserAgent();
        
        $query = "INSERT INTO audit_logs (user_id, action, module, ip_address, user_agent, new_values) 
                  VALUES (?, ?, 'security', ?, ?, ?)";
        
        $stmt = mysqli_prepare($connection, $query);
        $details_json = json_encode($details);
        
        mysqli_stmt_bind_param($stmt, "issss", $user_id, $event, $ip_address, $user_agent, $details_json);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Check if account is locked
 */
function isAccountLocked($user_id) {
    global $connection;
    
    $query = "SELECT account_locked_until FROM user WHERE id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        if ($row['account_locked_until'] && strtotime($row['account_locked_until']) > time()) {
            return true;
        }
    }
    
    return false;
}

/**
 * Lock account
 */
function lockAccount($user_id, $duration = 1800) { // 30 minutes default
    global $connection;
    
    $lockUntil = date('Y-m-d H:i:s', time() + $duration);
    $query = "UPDATE user SET account_locked_until = ? WHERE id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "si", $lockUntil, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}



/**
 * Increment failed login attempts
 */
function incrementFailedLoginAttempts($user_id) {
    global $connection;
    
    $query = "UPDATE user SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    // Check if should lock account (after 5 failed attempts)
    $query = "SELECT failed_login_attempts FROM user WHERE id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        if ($row['failed_login_attempts'] >= 5) {
            lockAccount($user_id);
        }
    }
}

/**
 * Reset failed login attempts
 */
function resetFailedLoginAttempts($user_id) {
    global $connection;
    
    $query = "UPDATE user SET failed_login_attempts = 0, account_locked_until = NULL WHERE id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

