<?php
/**
 * Audit Logging Functions
 * Smart Hotel Management System
 */

/**
 * Log an audit event
 */
function logAuditEvent($action, $module = null, $resource_type = null, $resource_id = null, $old_values = null, $new_values = null) {
    global $connection;
    
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $ip_address = getClientIP();
    $user_agent = getUserAgent();
    
    // Convert arrays/objects to JSON
    if (is_array($old_values) || is_object($old_values)) {
        $old_values = json_encode($old_values);
    }
    if (is_array($new_values) || is_object($new_values)) {
        $new_values = json_encode($new_values);
    }
    
    $query = "INSERT INTO audit_logs (user_id, action, module, resource_type, resource_id, 
             old_values, new_values, ip_address, user_agent) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "isssissss", $user_id, $action, $module, $resource_type, 
                         $resource_id, $old_values, $new_values, $ip_address, $user_agent);
    
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Get audit logs with filters
 */
function getAuditLogs($filters = []) {
    global $connection;
    
    $query = "SELECT al.*, u.username, u.name as user_name, u.email as user_email
              FROM audit_logs al
              LEFT JOIN user u ON al.user_id = u.id
              WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if (isset($filters['user_id']) && $filters['user_id']) {
        $query .= " AND al.user_id = ?";
        $params[] = $filters['user_id'];
        $types .= 'i';
    }
    
    if (isset($filters['action']) && $filters['action']) {
        $query .= " AND al.action LIKE ?";
        $params[] = '%' . $filters['action'] . '%';
        $types .= 's';
    }
    
    if (isset($filters['module']) && $filters['module']) {
        $query .= " AND al.module = ?";
        $params[] = $filters['module'];
        $types .= 's';
    }
    
    if (isset($filters['resource_type']) && $filters['resource_type']) {
        $query .= " AND al.resource_type = ?";
        $params[] = $filters['resource_type'];
        $types .= 's';
    }
    
    if (isset($filters['date_from']) && $filters['date_from']) {
        $query .= " AND DATE(al.created_at) >= ?";
        $params[] = $filters['date_from'];
        $types .= 's';
    }
    
    if (isset($filters['date_to']) && $filters['date_to']) {
        $query .= " AND DATE(al.created_at) <= ?";
        $params[] = $filters['date_to'];
        $types .= 's';
    }
    
    $query .= " ORDER BY al.created_at DESC";
    
    if (isset($filters['limit'])) {
        $query .= " LIMIT ?";
        $params[] = $filters['limit'];
        $types .= 'i';
    } else {
        $query .= " LIMIT 1000";
    }
    
    $stmt = mysqli_prepare($connection, $query);
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $logs = [];
    while ($log = mysqli_fetch_assoc($result)) {
        $logs[] = $log;
    }
    
    mysqli_stmt_close($stmt);
    
    return $logs;
}

/**
 * Get audit log statistics
 */
function getAuditStatistics($days = 30) {
    global $connection;
    
    $query = "SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT action) as unique_actions,
        COUNT(DISTINCT module) as unique_modules
        FROM audit_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $days);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Get top actions
    $actionsQuery = "SELECT action, COUNT(*) as count 
                    FROM audit_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY action 
                    ORDER BY count DESC 
                    LIMIT 10";
    $actionsStmt = mysqli_prepare($connection, $actionsQuery);
    mysqli_stmt_bind_param($actionsStmt, "i", $days);
    mysqli_stmt_execute($actionsStmt);
    $actionsResult = mysqli_stmt_get_result($actionsStmt);
    
    $topActions = [];
    while ($action = mysqli_fetch_assoc($actionsResult)) {
        $topActions[] = $action;
    }
    mysqli_stmt_close($actionsStmt);
    
    $stats['top_actions'] = $topActions;
    
    return $stats;
}

/**
 * Get client IP address (from security.php if available)
 */
if (!function_exists('getClientIP')) {
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
}

/**
 * Get user agent (from security.php if available)
 */
if (!function_exists('getUserAgent')) {
function getUserAgent() {
    return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
}
}

