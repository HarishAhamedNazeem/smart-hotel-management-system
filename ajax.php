<?php
// Configure session cookie to expire when browser closes
session_set_cookie_params(0);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'db.php';
require_once 'includes/security.php';
require_once 'includes/auth.php';
require_once 'includes/rbac.php';

// Check for inactivity timeout
if (isset($_SESSION['user_id']) && isset($_SESSION['LAST_ACTIVITY'])) {
    // Admin/staff session - 2 hours timeout (7200 seconds)
    if (time() - $_SESSION['LAST_ACTIVITY'] > 7200) {
        session_unset();
        session_destroy();
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Session expired due to inactivity. Please log in again.']);
        exit();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
} elseif (isset($_SESSION['guest_id'])) {
    // Guest session - 30 minutes timeout (1800 seconds)
    // Use LAST_ACTIVITY_GUEST to avoid conflicts with admin session
    if (isset($_SESSION['LAST_ACTIVITY_GUEST'])) {
        if (time() - $_SESSION['LAST_ACTIVITY_GUEST'] > 1800) {
            // Only clear guest session vars, preserve admin session if exists
            unset($_SESSION['guest_id']);
            unset($_SESSION['guest_name']);
            unset($_SESSION['guest_email']);
            unset($_SESSION['LAST_ACTIVITY_GUEST']);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Guest session expired due to inactivity. Please log in again.']);
            exit();
        }
    }
    $_SESSION['LAST_ACTIVITY_GUEST'] = time();
}

// Helper function to get the correct soft delete column name
function getRoomDeleteColumn($connection) {
    static $columnName = null;
    
    if ($columnName === null) {
        $checkDeleteStatus = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'deleteStatus'");
        if (mysqli_num_rows($checkDeleteStatus) > 0) {
            $columnName = 'deleteStatus';
        } else {
            $checkIsDeleted = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'is_deleted'");
            if (mysqli_num_rows($checkIsDeleted) > 0) {
                $columnName = 'is_deleted';
            } else {
                $columnName = false; // Column doesn't exist
            }
        }
    }
    
    return $columnName;
}

// Helper function to get WHERE clause for non-deleted rooms
function getRoomDeleteFilter($connection, $tableAlias = 'r') {
    $column = getRoomDeleteColumn($connection);
    if ($column) {
        return " AND {$tableAlias}.{$column} = 0";
    }
    return "";
}

// Helper function to sync room status based on active bookings
function syncRoomStatus($connection, $room_id) {
    // Check if there are any active bookings for this room
    $checkQuery = "SELECT COUNT(*) as active_count 
                   FROM booking 
                   WHERE room_id = ? 
                   AND status IN ('confirmed', 'checked_in')";
    $stmt = mysqli_prepare($connection, $checkQuery);
    mysqli_stmt_bind_param($stmt, "i", $room_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $newStatus = ($row['active_count'] > 0) ? 1 : 0; // 1=Occupied, 0=Available
    
    // Update room status
    $updateQuery = "UPDATE room SET status = ? WHERE room_id = ?";
    $updateStmt = mysqli_prepare($connection, $updateQuery);
    mysqli_stmt_bind_param($updateStmt, "ii", $newStatus, $room_id);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);
    
    return $newStatus;
}

// AJAX endpoint to get current room statuses (for auto-refresh)
if (isset($_POST['get_room_statuses'])) {
    header('Content-Type: application/json');
    
    $response = ['done' => false, 'rooms' => []];
    
    try {
        // Get current user's branch if they're a branch admin
        $user = getCurrentUser();
        $userBranchId = null;
        
        if (hasRole('administrator') && !hasRole('super_admin')) {
            $staffQuery = "SELECT branch_id FROM staff WHERE user_id = ? LIMIT 1";
            $staffStmt = mysqli_prepare($connection, $staffQuery);
            if ($staffStmt) {
                mysqli_stmt_bind_param($staffStmt, "i", $user['id']);
                mysqli_stmt_execute($staffStmt);
                $staffResult = mysqli_stmt_get_result($staffStmt);
                if ($staff = mysqli_fetch_assoc($staffResult)) {
                    $userBranchId = $staff['branch_id'];
                }
                mysqli_stmt_close($staffStmt);
            }
        }
        
        // Build query
        $deleteFilter = getRoomDeleteFilter($connection, 'r');
        $query = "SELECT r.room_id, r.room_no, r.status 
                  FROM room r 
                  WHERE 1=1" . $deleteFilter;
        
        if ($userBranchId && !hasRole('super_admin')) {
            $query .= " AND r.branch_id = " . intval($userBranchId);
        }
        
        $query .= " ORDER BY r.room_no";
        
        $result = mysqli_query($connection, $query);
        
        if ($result) {
            $rooms = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rooms[] = [
                    'room_id' => $row['room_id'],
                    'room_no' => $row['room_no'],
                    'status' => $row['status']
                ];
            }
            $response['done'] = true;
            $response['rooms'] = $rooms;
        }
    } catch (Exception $e) {
        $response['done'] = false;
        $response['error'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// AJAX endpoint to sync all room statuses (for fixing inconsistencies)
if (isset($_POST['sync_all_room_statuses'])) {
    header('Content-Type: application/json');
    
    $response = ['done' => false, 'data' => ''];
    
    try {
        // Get all rooms
        $deleteFilter = getRoomDeleteFilter($connection, 'r');
        $query = "SELECT r.room_id FROM room r WHERE 1=1" . $deleteFilter;
        $result = mysqli_query($connection, $query);
        
        $synced = 0;
        while ($room = mysqli_fetch_assoc($result)) {
            syncRoomStatus($connection, $room['room_id']);
            $synced++;
        }
        
        $response['done'] = true;
        $response['data'] = "Synced $synced room statuses successfully";
        $response['synced_count'] = $synced;
        
    } catch (Exception $e) {
        $response['done'] = false;
        $response['data'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// AJAX endpoint to get available room counts by type (for customer-facing pages)
if (isset($_POST['get_available_room_counts'])) {
    header('Content-Type: application/json');
    
    $response = ['done' => false, 'room_types' => []];
    
    try {
        $deleteFilter = getRoomDeleteFilter($connection, 'r');
        
        // Get all active branches
        $branchesQuery = "SELECT branch_id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name";
        $branchesResult = mysqli_query($connection, $branchesQuery);
        $branches = [];
        if ($branchesResult) {
            while ($branchRow = mysqli_fetch_assoc($branchesResult)) {
                $branches[$branchRow['branch_id']] = $branchRow['branch_name'];
            }
        }
        
        // Get all room types
        $roomTypesQuery = "SELECT room_type_id, room_type FROM room_type ORDER BY room_type";
        $roomTypesResult = mysqli_query($connection, $roomTypesQuery);
        
        if ($roomTypesResult) {
            $room_types = [];
            while ($roomTypeRow = mysqli_fetch_assoc($roomTypesResult)) {
                $room_type_id = $roomTypeRow['room_type_id'];
                $branch_availability = [];
                $total_available = 0;
                
                // Get availability per branch for this room type
                foreach ($branches as $branch_id => $branch_name) {
                    $countQuery = "SELECT COUNT(r.room_id) as available 
                                   FROM room r 
                                   WHERE r.room_type_id = ? AND r.branch_id = ? AND r.status = 0";
                    
                    if (strpos($deleteFilter, 'deleteStatus') !== false) {
                        $countQuery .= " AND r.deleteStatus = 0";
                    } elseif (strpos($deleteFilter, 'is_deleted') !== false) {
                        $countQuery .= " AND r.is_deleted = 0";
                    }
                    
                    $countStmt = mysqli_prepare($connection, $countQuery);
                    if ($countStmt) {
                        mysqli_stmt_bind_param($countStmt, "ii", $room_type_id, $branch_id);
                        mysqli_stmt_execute($countStmt);
                        $countResult = mysqli_stmt_get_result($countStmt);
                        if ($countRow = mysqli_fetch_assoc($countResult)) {
                            $branch_count = (int)$countRow['available'];
                            if ($branch_count > 0) {
                                $branch_availability[$branch_name] = $branch_count;
                                $total_available += $branch_count;
                            }
                        }
                        mysqli_stmt_close($countStmt);
                    }
                }
                
                $room_types[] = [
                    'room_type_id' => $room_type_id,
                    'room_type' => $roomTypeRow['room_type'],
                    'available' => $total_available,
                    'branch_availability' => $branch_availability
                ];
            }
            $response['done'] = true;
            $response['room_types'] = $room_types;
        }
    } catch (Exception $e) {
        $response['done'] = false;
        $response['error'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// AJAX endpoint to modify guest booking
if (isset($_POST['modify_guest_booking'])) {
    header('Content-Type: application/json');
    ob_start();
    
    $response = ['done' => false, 'data' => ''];
    
    try {
        // Verify guest is logged in
        if (!isset($_SESSION['guest_id'])) {
            $response['data'] = 'You must be logged in to modify bookings.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        $guest_id = intval($_SESSION['guest_id']);
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $check_in = isset($_POST['check_in']) ? trim($_POST['check_in']) : '';
        $check_out = isset($_POST['check_out']) ? trim($_POST['check_out']) : '';
        
        // Validate inputs
        if ($booking_id <= 0) {
            $response['data'] = 'Invalid booking ID.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        if (empty($check_in) || empty($check_out)) {
            $response['data'] = 'Please provide both check-in and check-out dates.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        // Validate date format and logic
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) {
            $response['data'] = 'Invalid date format. Please use YYYY-MM-DD format.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        if (strtotime($check_out) <= strtotime($check_in)) {
            $response['data'] = 'Check-out date must be after check-in date.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        if (strtotime($check_in) < strtotime(date('Y-m-d'))) {
            $response['data'] = 'Check-in date cannot be in the past.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        // Verify booking exists and belongs to this guest
        $checkQuery = "SELECT b.*, r.room_type_id, rt.price 
                       FROM booking b
                       LEFT JOIN room r ON b.room_id = r.room_id
                       LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id
                       WHERE b.booking_id = ? AND b.guest_id = ?";
        $checkStmt = mysqli_prepare($connection, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, "ii", $booking_id, $guest_id);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($checkResult) == 0) {
            mysqli_stmt_close($checkStmt);
            $response['data'] = 'Booking not found or does not belong to you.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        $booking = mysqli_fetch_assoc($checkResult);
        mysqli_stmt_close($checkStmt);
        
        // Check if booking can be modified
        $allowed_statuses = ['confirmed', 'pending'];
        if (!in_array($booking['status'], $allowed_statuses)) {
            $response['data'] = 'This booking cannot be modified. Status: ' . $booking['status'];
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        // Parse existing check-in date to verify it's in the future
        $existing_check_in = $booking['check_in'];
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $existing_check_in)) {
            $parts = explode('-', $existing_check_in);
            $existing_check_in = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
        
        if (strtotime($existing_check_in) < strtotime(date('Y-m-d'))) {
            $response['data'] = 'Cannot modify past bookings.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        // Calculate new total price
        $check_in_date = new DateTime($check_in);
        $check_out_date = new DateTime($check_out);
        $interval = $check_in_date->diff($check_out_date);
        $days = $interval->days;
        
        if ($days <= 0) {
            $response['data'] = 'Invalid date range.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        $price_per_night = floatval($booking['price']);
        $new_total_price = $price_per_night * $days;
        
        // Format dates based on booking table column type
        $checkColumnQuery = "SHOW COLUMNS FROM booking LIKE 'check_in'";
        $columnResult = mysqli_query($connection, $checkColumnQuery);
        $columnInfo = mysqli_fetch_assoc($columnResult);
        $isDateType = (stripos($columnInfo['Type'], 'date') !== false);
        
        if ($isDateType) {
            $check_in_formatted = $check_in;
            $check_out_formatted = $check_out;
        } else {
            $check_in_formatted = date('d-m-Y', strtotime($check_in));
            $check_out_formatted = date('d-m-Y', strtotime($check_out));
        }
        
        // Begin transaction
        mysqli_begin_transaction($connection);
        
        try {
            // Update booking with new dates and price
            $updateQuery = "UPDATE booking 
                           SET check_in = ?, check_out = ?, total_price = ?, 
                               remaining_price = total_price - (total_price - remaining_price)
                           WHERE booking_id = ? AND guest_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "ssdii", $check_in_formatted, $check_out_formatted, 
                                  $new_total_price, $booking_id, $guest_id);
            
            if (!mysqli_stmt_execute($updateStmt)) {
                throw new Exception('Failed to update booking: ' . mysqli_stmt_error($updateStmt));
            }
            mysqli_stmt_close($updateStmt);
            
            // Commit transaction
            mysqli_commit($connection);
            
            $response['done'] = true;
            $response['data'] = 'Booking modified successfully!';
            $response['new_total_price'] = number_format($new_total_price, 2);
            $response['days'] = $days;
            
        } catch (Exception $e) {
            mysqli_rollback($connection);
            throw $e;
        }
        
    } catch (Exception $e) {
        $response['data'] = 'Error: ' . $e->getMessage();
    }
    
    ob_end_clean();
    echo json_encode($response);
    exit;
}

// AJAX endpoint to cancel guest booking
if (isset($_POST['cancel_guest_booking'])) {
    header('Content-Type: application/json');
    ob_start();
    
    $response = ['done' => false, 'data' => ''];
    
    try {
        // Verify guest is logged in
        if (!isset($_SESSION['guest_id'])) {
            $response['data'] = 'You must be logged in to cancel bookings.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        $guest_id = intval($_SESSION['guest_id']);
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        // Validate booking ID
        if ($booking_id <= 0) {
            $response['data'] = 'Invalid booking ID.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        // Verify booking exists and belongs to this guest
        $checkQuery = "SELECT b.*, r.room_id 
                       FROM booking b
                       LEFT JOIN room r ON b.room_id = r.room_id
                       WHERE b.booking_id = ? AND b.guest_id = ?";
        $checkStmt = mysqli_prepare($connection, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, "ii", $booking_id, $guest_id);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($checkResult) == 0) {
            mysqli_stmt_close($checkStmt);
            $response['data'] = 'Booking not found or does not belong to you.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        $booking = mysqli_fetch_assoc($checkResult);
        mysqli_stmt_close($checkStmt);
        
        // Check if booking can be cancelled
        $allowed_statuses = ['confirmed', 'pending'];
        if (!in_array($booking['status'], $allowed_statuses)) {
            $response['data'] = 'This booking cannot be cancelled. Status: ' . $booking['status'];
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        // Parse check-in date to verify it's in the future
        $check_in = $booking['check_in'];
        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_in)) {
            $parts = explode('-', $check_in);
            $check_in = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
        
        if (strtotime($check_in) < strtotime(date('Y-m-d'))) {
            $response['data'] = 'Cannot cancel past bookings.';
            ob_end_clean();
            echo json_encode($response);
            exit;
        }
        
        // Begin transaction
        mysqli_begin_transaction($connection);
        
        try {
            // Update booking status to 'cancelled'
            $updateQuery = "UPDATE booking SET status = 'cancelled' WHERE booking_id = ? AND guest_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "ii", $booking_id, $guest_id);
            
            if (!mysqli_stmt_execute($updateStmt)) {
                throw new Exception('Failed to cancel booking: ' . mysqli_stmt_error($updateStmt));
            }
            mysqli_stmt_close($updateStmt);
            
            // Sync room status after cancellation
            if ($booking['room_id']) {
                syncRoomStatus($connection, $booking['room_id']);
            }
            
            // Commit transaction
            mysqli_commit($connection);
            
            $response['done'] = true;
            $response['data'] = 'Booking cancelled successfully!';
            
        } catch (Exception $e) {
            mysqli_rollback($connection);
            throw $e;
        }
        
    } catch (Exception $e) {
        $response['data'] = 'Error: ' . $e->getMessage();
    }
    
    ob_end_clean();
    echo json_encode($response);
    exit;
}

if (isset($_POST['login'])) {
    // Verify CSRF token
    // If token is missing or invalid, regenerate and show error
    if (!isset($_POST['csrf_token'])) {
        // Token not provided - regenerate for next attempt
        generateCSRFToken();
        header('Location:login.php?error=csrf');
        exit();
    }
    
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        // Token mismatch - regenerate for next attempt
        generateCSRFToken();
        header('Location:login.php?error=csrf');
        exit();
    }
    
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password']; // Don't sanitize password
    
    if (empty($email) || empty($password)) {
        header('Location:login.php?empty');
        exit();
    }
    
    // Rate limiting check
    if (!checkRateLimit('login_' . $email, 5, 300)) {
        header('Location:login.php?error=rate_limit');
        exit();
    }
    
    // Check if account is locked
    $query = "SELECT * FROM user WHERE (username = ? OR email = ?) AND status = 'active'";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "ss", $email, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Check if account is locked
        if (isAccountLocked($user['id'])) {
            logSecurityEvent('login_attempt_locked_account', ['email' => $email]);
            header('Location:login.php?error=locked');
            exit();
        }
        
        // Verify password - support both old MD5 and new bcrypt
        $passwordValid = false;
        if (password_verify($password, $user['password'])) {
            $passwordValid = true;
        } elseif (md5($password) === $user['password']) {
            // Migrate old MD5 password to bcrypt
            $passwordValid = true;
            $newHash = hashPassword($password);
            $updateQuery = "UPDATE user SET password = ? WHERE id = ?";
            $updateStmt = mysqli_prepare($connection, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "si", $newHash, $user['id']);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
        }
        
        if ($passwordValid) {
            // Reset failed login attempts
            resetFailedLoginAttempts($user['id']);
            
            // Update last login
            updateLastLogin($user['id']);
            
            // IMPORTANT: Clear guest session variables to prevent conflicts
            // Admin/staff and guest portals must be completely separate
            unset($_SESSION['guest_id']);
            unset($_SESSION['guest_name']);
            unset($_SESSION['guest_email']);
            
            // Set admin/staff session variables
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['LAST_ACTIVITY'] = time(); // Initialize inactivity tracking
            
            // Clear RBAC cache to reload roles/permissions
            clearRBACCache();
            
            // Log successful login
            logSecurityEvent('login_success', ['user_id' => $user['id'], 'email' => $email]);
            
            // Redirect to main dashboard
            // All staff (housekeeping_staff, concierge) use main dashboard like Receptionist
            header('Location:index.php?dashboard');
            exit();
        } else {
            // Increment failed login attempts
            incrementFailedLoginAttempts($user['id']);
            logSecurityEvent('login_failed', ['email' => $email, 'reason' => 'invalid_password']);
            header('Location:login.php?loginE');
            exit();
        }
    } else {
        logSecurityEvent('login_failed', ['email' => $email, 'reason' => 'user_not_found']);
        header('Location:login.php?loginE');
        exit();
    }
    
    mysqli_stmt_close($stmt);
}

// User Registration Handler
if (isset($_POST['register'])) {
    require_once 'includes/auth.php';
    
    // Check if this is an AJAX request
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    $isAjax = $isAjax || (isset($_POST['ajax']) || isset($_GET['ajax']));
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => 'Security token mismatch.']);
            exit();
        }
        header('Location:register.php?error=csrf');
        exit();
    }
    
    $name = sanitizeInput($_POST['name']);
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => 'Please fill all required fields.']);
            exit();
        }
        header('Location:register.php?empty');
        exit();
    }
    
    if (!validateEmail($email)) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
            exit();
        }
        header('Location:register.php?invalid_email');
        exit();
    }
    
    if (strlen($password) < 8) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => 'Password must be at least 8 characters long.']);
            exit();
        }
        header('Location:register.php?weak_password');
        exit();
    }
    
    if ($password !== $confirm_password) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
            exit();
        }
        header('Location:register.php?password_mismatch');
        exit();
    }
    
    // Check for duplicate email
    if (emailExists($email)) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => 'Email already exists.']);
            exit();
        }
        header('Location:register.php?email_exists');
        exit();
    }
    
    // Check for duplicate username
    if (usernameExists($username)) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => 'Username already exists.']);
            exit();
        }
        header('Location:register.php?username_exists');
        exit();
    }
    
    // Hash password
    $hashedPassword = hashPassword($password);
    
    // Insert user
    $query = "INSERT INTO user (name, username, email, phone, password, status, created_at) 
              VALUES (?, ?, ?, ?, ?, 'active', NOW())";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "sssss", $name, $username, $email, $phone, $hashedPassword);
    
    if (mysqli_stmt_execute($stmt)) {
        $user_id = mysqli_insert_id($connection);
        
        // Log registration
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent('user_registered', ['user_id' => $user_id, 'email' => $email]);
        }
        
        mysqli_stmt_close($stmt);
        
        if ($isAjax) {
            echo json_encode(['success' => true, 'message' => 'User registered successfully!', 'user_id' => $user_id]);
            exit();
        }
        header('Location:register.php?success');
        exit();
    } else {
        mysqli_stmt_close($stmt);
        if ($isAjax) {
            echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
            exit();
        }
        header('Location:register.php?error=database');
        exit();
    }
}

// Guest Login Handler
if (isset($_POST['guest_login'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        header('Location:guest_login.php?error=csrf');
        exit();
    }
    
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        header('Location:guest_login.php?empty');
        exit();
    }
    
    // Rate limiting check
    if (!checkRateLimit('guest_login_' . $email, 5, 300)) {
        header('Location:guest_login.php?error=rate_limit');
        exit();
    }
    
    // Check if guest account exists and is active in guests table
    $query = "SELECT * FROM guests WHERE (username = ? OR email = ?) AND status = 'active'";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "ss", $email, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 1) {
        $guest = mysqli_fetch_assoc($result);
        
        // Check if account is locked
        if (isGuestAccountLocked($guest['guest_id'])) {
            logSecurityEvent('guest_login_attempt_locked_account', ['email' => $email]);
            header('Location:guest_login.php?error=locked');
            exit();
        }
        
        // Verify password
        $passwordValid = false;
        if (password_verify($password, $guest['password'])) {
            $passwordValid = true;
        } elseif (md5($password) === $guest['password']) {
            // Migrate old MD5 password to bcrypt
            $passwordValid = true;
            $newHash = hashPassword($password);
            $updateQuery = "UPDATE guests SET password = ? WHERE guest_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "si", $newHash, $guest['guest_id']);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
        }
        
        if ($passwordValid) {
            // Reset failed login attempts
            resetGuestFailedLoginAttempts($guest['guest_id']);
            
            // Update last login
            updateLastLoginGuest($guest['guest_id']);
            
            // IMPORTANT: Clear admin/staff session variables to prevent conflicts
            // Admin/staff and guest portals must be completely separate
            unset($_SESSION['user_id']);
            unset($_SESSION['current_user_data']);
            unset($_SESSION['user_roles']);
            unset($_SESSION['user_permissions']);
            
            // Set guest session variables (NO user table connection)
            $_SESSION['guest_id'] = $guest['guest_id'];
            $_SESSION['guest_name'] = $guest['name'];
            $_SESSION['guest_email'] = $guest['email'];
            $_SESSION['username'] = $guest['username'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['LAST_ACTIVITY_GUEST'] = time(); // Initialize guest-specific inactivity tracking
            
            // Log successful login
            logSecurityEvent('guest_login_success', ['guest_id' => $guest['guest_id'], 'email' => $email]);
            
            // Always redirect to home page (logged-in guest view)
            header('Location:home.php');
            exit();
        } else {
            incrementGuestFailedLoginAttempts($guest['guest_id']);
            logSecurityEvent('guest_login_failed', ['email' => $email, 'reason' => 'invalid_password']);
            header('Location:guest_login.php?loginE');
            exit();
        }
    } else {
        logSecurityEvent('guest_login_failed', ['email' => $email, 'reason' => 'guest_not_found']);
        header('Location:guest_login.php?loginE');
        exit();
    }
    
    mysqli_stmt_close($stmt);
}

// Guest Registration Handler
if (isset($_POST['guest_register'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        header('Location:guest_login.php?action=register&error=csrf');
        exit();
    }
    
    $name = sanitizeInput($_POST['name']);
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
    $password = $_POST['password'];
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    
    // Validation
    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        header('Location:guest_login.php?action=register&empty');
        exit();
    }
    
    if ($password !== $password_confirm) {
        header('Location:guest_login.php?action=register&error=password_mismatch');
        exit();
    }
    
    if (strlen($password) < 6) {
        header('Location:guest_login.php?action=register&error=weak_password');
        exit();
    }
    
    if (!validateEmail($email)) {
        header('Location:guest_login.php?action=register&error=invalid_email');
        exit();
    }
    
    // Check for duplicate email in guests table
    $emailCheckQuery = "SELECT guest_id FROM guests WHERE email = ?";
    $emailCheckStmt = mysqli_prepare($connection, $emailCheckQuery);
    mysqli_stmt_bind_param($emailCheckStmt, "s", $email);
    mysqli_stmt_execute($emailCheckStmt);
    $emailCheckResult = mysqli_stmt_get_result($emailCheckStmt);
    if (mysqli_num_rows($emailCheckResult) > 0) {
        mysqli_stmt_close($emailCheckStmt);
        header('Location:guest_login.php?action=register&error=email_exists');
        exit();
    }
    mysqli_stmt_close($emailCheckStmt);
    
    // Also check user table to prevent conflicts
    if (emailExists($email)) {
        header('Location:guest_login.php?action=register&error=email_exists');
        exit();
    }
    
    // Check for duplicate username in guests table
    $usernameCheckQuery = "SELECT guest_id FROM guests WHERE username = ?";
    $usernameCheckStmt = mysqli_prepare($connection, $usernameCheckQuery);
    mysqli_stmt_bind_param($usernameCheckStmt, "s", $username);
    mysqli_stmt_execute($usernameCheckStmt);
    $usernameCheckResult = mysqli_stmt_get_result($usernameCheckStmt);
    if (mysqli_num_rows($usernameCheckResult) > 0) {
        mysqli_stmt_close($usernameCheckStmt);
        header('Location:guest_login.php?action=register&error=username_exists');
        exit();
    }
    mysqli_stmt_close($usernameCheckStmt);
    
    // Also check user table to prevent conflicts
    if (usernameExists($username)) {
        header('Location:guest_login.php?action=register&error=username_exists');
        exit();
    }
    
    // Hash password
    $hashedPassword = hashPassword($password);
    
    // Remove non-numeric characters from phone for bigint storage
    $contact_no = !empty($phone) ? preg_replace('/[^0-9]/', '', $phone) : null;
    $contact_no = !empty($contact_no) && is_numeric($contact_no) ? intval($contact_no) : null;
    
    // Insert into guests table (NOT user table)
    $query = "INSERT INTO guests (name, username, email, phone, contact_no, password, status, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "ssssis", $name, $username, $email, $phone, $contact_no, $hashedPassword);
    
    if (mysqli_stmt_execute($stmt)) {
        $guest_id = mysqli_insert_id($connection);
        
        // Log registration
        logSecurityEvent('guest_registered', ['guest_id' => $guest_id, 'email' => $email]);
        
        mysqli_stmt_close($stmt);
        
        // Redirect to guest login page with success message
        // Do NOT auto-login - guest must login manually
        header('Location:guest_login.php?action=register&registerS=1');
        exit();
    } else {
        mysqli_stmt_close($stmt);
        header('Location:guest_login.php?action=register&error=database');
        exit();
    }
}

if (isset($_POST['add_room'])) {
    // Get branch_id - handle empty string, null, or missing value
    $branch_id = null;
    if (isset($_POST['branch_id']) && $_POST['branch_id'] !== '' && $_POST['branch_id'] !== null) {
        $branch_id = intval($_POST['branch_id']);
        if ($branch_id <= 0) {
            $branch_id = null;
        }
    }
    
    $room_type_id = isset($_POST['room_type_id']) && $_POST['room_type_id'] != '' ? intval($_POST['room_type_id']) : null;
    $room_no = isset($_POST['room_no']) ? mysqli_real_escape_string($connection, trim($_POST['room_no'])) : '';

    // Validation
    if (empty($room_no)) {
        $response['done'] = false;
        $response['data'] = "Please Enter Room No";
        echo json_encode($response);
        exit;
    }
    
    if (empty($room_type_id) || $room_type_id <= 0) {
        $response['done'] = false;
        $response['data'] = "Please Select Room Type";
        echo json_encode($response);
        exit;
    }
    
    // Get price and max_person from room_type table
    $roomTypeQuery = "SELECT price, max_person FROM room_type WHERE room_type_id = ?";
    $roomTypeStmt = mysqli_prepare($connection, $roomTypeQuery);
    if (!$roomTypeStmt) {
        $response['done'] = false;
        $response['data'] = "Database Error: " . mysqli_error($connection);
        echo json_encode($response);
        exit;
    }
    mysqli_stmt_bind_param($roomTypeStmt, "i", $room_type_id);
    mysqli_stmt_execute($roomTypeStmt);
    $roomTypeResult = mysqli_stmt_get_result($roomTypeStmt);
    
    if (mysqli_num_rows($roomTypeResult) == 0) {
        $response['done'] = false;
        $response['data'] = "Invalid Room Type Selected";
        mysqli_stmt_close($roomTypeStmt);
        echo json_encode($response);
        exit;
    }
    
    $roomTypeData = mysqli_fetch_assoc($roomTypeResult);
    $price = intval($roomTypeData['price']);
    $max_person = intval($roomTypeData['max_person']);
    mysqli_stmt_close($roomTypeStmt);
    
    if ($price <= 0 || $max_person <= 0) {
        $response['done'] = false;
        $response['data'] = "Room Type has invalid price or max person value";
        echo json_encode($response);
        exit;
    }

    // Check if room number already exists
    $deleteFilter = getRoomDeleteFilter($connection, 'room');
    $checkQuery = "SELECT * FROM room WHERE room_no = ?" . str_replace('AND room.', 'AND ', $deleteFilter);
    $checkStmt = mysqli_prepare($connection, $checkQuery);
    if (!$checkStmt) {
        $response['done'] = false;
        $response['data'] = "Database Error: " . mysqli_error($connection);
        echo json_encode($response);
        exit;
    }
    
    mysqli_stmt_bind_param($checkStmt, "s", $room_no);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    
    if (mysqli_num_rows($checkResult) >= 1) {
        $response['done'] = false;
        $response['data'] = "Room No Already Exist";
        mysqli_stmt_close($checkStmt);
        echo json_encode($response);
        exit;
    }
    mysqli_stmt_close($checkStmt);
    
    // Insert room with branch_id and room_type_id (always include branch_id column, even if null)
    $deleteColumn = getRoomDeleteColumn($connection);
    if ($deleteColumn) {
        $query = "INSERT INTO room (branch_id, room_type_id, price, max_person, room_no, status, {$deleteColumn}) VALUES (?, ?, ?, ?, ?, 0, 0)";
    } else {
        $query = "INSERT INTO room (branch_id, room_type_id, price, max_person, room_no, status) VALUES (?, ?, ?, ?, ?, 0)";
    }
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        $response['done'] = false;
        $response['data'] = "Database Error: " . mysqli_error($connection);
        echo json_encode($response);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "iiiis", $branch_id, $room_type_id, $price, $max_person, $room_no);
    
    if (mysqli_stmt_execute($stmt)) {
        $response['done'] = true;
        $response['data'] = 'Successfully Added Room';
        $response['debug'] = ['branch_id' => $branch_id, 'room_type_id' => $room_type_id, 'price' => $price, 'max_person' => $max_person, 'room_no' => $room_no]; // Debug info
    } else {
        $response['done'] = false;
        $response['data'] = "DataBase Error: " . mysqli_error($connection);
        $response['debug'] = ['branch_id' => $branch_id, 'room_type_id' => $room_type_id, 'price' => $price, 'max_person' => $max_person, 'room_no' => $room_no]; // Debug info
    }
    mysqli_stmt_close($stmt);

    echo json_encode($response);
}

if (isset($_POST['room'])) {
    $room_id = intval($_POST['room_id']);

    $sql = "SELECT r.*, rt.room_type 
            FROM room r 
            LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id 
            WHERE r.room_id = ?";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, "i", $room_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $room = mysqli_fetch_assoc($result);
        $response['done'] = true;
        $response['room_no'] = $room['room_no'];
        $response['room_type_id'] = $room['room_type_id'];
        $response['room_type'] = $room['room_type']; // For display purposes
        $response['price'] = $room['price'];
        $response['max_person'] = $room['max_person'];
        $response['branch_id'] = $room['branch_id'];
    } else {
        $response['done'] = false;
        $response['data'] = "DataBase Error";
    }
    mysqli_stmt_close($stmt);

    echo json_encode($response);
}

if (isset($_POST['edit_room'])) {
    $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] != '' ? intval($_POST['branch_id']) : null;
    $room_type_id = isset($_POST['room_type_id']) && $_POST['room_type_id'] != '' ? intval($_POST['room_type_id']) : null;
    $price = isset($_POST['price']) ? intval($_POST['price']) : 0;
    $max_person = isset($_POST['max_person']) ? intval($_POST['max_person']) : 0;
    $room_no = isset($_POST['room_no']) ? mysqli_real_escape_string($connection, trim($_POST['room_no'])) : '';
    $room_id = intval($_POST['room_id']);

    if ($room_no != '' && $room_type_id > 0 && $price > 0 && $max_person > 0) {
        // Update room with branch_id and room_type_id
        if ($branch_id) {
            $query = "UPDATE room SET branch_id = ?, room_no = ?, room_type_id = ?, price = ?, max_person = ? WHERE room_id = ?";
            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "isiiii", $branch_id, $room_no, $room_type_id, $price, $max_person, $room_id);
        } else {
            $query = "UPDATE room SET branch_id = NULL, room_no = ?, room_type_id = ?, price = ?, max_person = ? WHERE room_id = ?";
            $stmt = mysqli_prepare($connection, $query);
            mysqli_stmt_bind_param($stmt, "siiii", $room_no, $room_type_id, $price, $max_person, $room_id);
        }
        
        if (mysqli_stmt_execute($stmt)) {
            $response['done'] = true;
            $response['data'] = 'Successfully Edit Room';
        } else {
            $response['done'] = false;
            $response['data'] = "DataBase Error: " . mysqli_error($connection);
        }
        mysqli_stmt_close($stmt);
    } else {
        $response['done'] = false;
        $response['data'] = "Please Enter All Required Fields";
    }

    echo json_encode($response);
}

// Add Room Type
// Note: Since room types are stored in the room table, this function just validates
// that the room type doesn't already exist in the room table
if (isset($_POST['add_room_type'])) {
    $room_type_name = isset($_POST['room_type_name']) ? mysqli_real_escape_string($connection, trim($_POST['room_type_name'])) : '';
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $max_person = isset($_POST['max_person']) ? intval($_POST['max_person']) : 0;

    // Validation
    if (empty($room_type_name)) {
        $response['done'] = false;
        $response['data'] = "Please Enter Room Type Name";
        echo json_encode($response);
        exit;
    }
    
    if ($price <= 0) {
        $response['done'] = false;
        $response['data'] = "Please Enter Valid Price";
        echo json_encode($response);
        exit;
    }
    
    if ($max_person <= 0) {
        $response['done'] = false;
        $response['data'] = "Please Enter Valid Maximum Persons";
        echo json_encode($response);
        exit;
    }

    // Check if room type name already exists in room_type table
    $checkQuery = "SELECT room_type_id FROM room_type WHERE room_type = ? LIMIT 1";
    $checkStmt = mysqli_prepare($connection, $checkQuery);
    if (!$checkStmt) {
        $response['done'] = false;
        $response['data'] = "Database Error: " . mysqli_error($connection);
        echo json_encode($response);
        exit;
    }
    
    mysqli_stmt_bind_param($checkStmt, "s", $room_type_name);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    
    if (mysqli_num_rows($checkResult) >= 1) {
        $response['done'] = false;
        $response['data'] = "Room Type Already Exists";
        mysqli_stmt_close($checkStmt);
        echo json_encode($response);
        exit;
    }
    mysqli_stmt_close($checkStmt);
    
    // Room type is valid and doesn't exist yet
    // Note: Room types are created when rooms are added, not as separate entities
    // This function just validates the room type name
    $response['done'] = true;
    $response['data'] = 'Room Type Name is Valid (You can now use this type when adding rooms)';
    $response['room_type_name'] = $room_type_name;
    $response['price'] = $price;
    $response['max_person'] = $max_person;

    echo json_encode($response);
}

if (isset($_GET['delete_room'])) {
    $room_id = intval($_GET['delete_room']);
    $deleteColumn = getRoomDeleteColumn($connection);
    
    if ($deleteColumn) {
        $sql = "UPDATE room SET {$deleteColumn} = 1 WHERE room_id = ? AND status IS NULL";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $room_id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } else {
        // If no soft delete column, do hard delete
        $sql = "DELETE FROM room WHERE room_id = ? AND status IS NULL";
        $stmt = mysqli_prepare($connection, $sql);
        mysqli_stmt_bind_param($stmt, "i", $room_id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    if ($result) {
        header("Location:index.php?room_mang&success");
    } else {
        header("Location:index.php?room_mang&error");
    }
}

// Old room_type handler - only execute if NOT a search_available_rooms request
// This prevents HTML output from breaking JSON responses
if (isset($_POST['room_type']) && !isset($_POST['search_available_rooms'])) {
    $room_type = mysqli_real_escape_string($connection, $_POST['room_type']);

    // Modified query to show rooms from all branches
    // Removed r.status IS NULL condition to show all available rooms
    $deleteFilter = getRoomDeleteFilter($connection, 'r');
    $sql = "SELECT r.*, br.branch_name FROM room r
            INNER JOIN room_type rt ON r.room_type_id = rt.room_type_id
            LEFT JOIN branches br ON r.branch_id = br.branch_id
            WHERE rt.room_type = '$room_type'" . $deleteFilter . "
            ORDER BY br.branch_name, r.room_no";
    $result = mysqli_query($connection, $sql);
    if ($result) {
        echo "<option selected disabled>Select Room No</option>";
        while ($room = mysqli_fetch_assoc($result)) {
            // Show room number with branch name for clarity
            $displayText = $room['room_no'];
            if (!empty($room['branch_name'])) {
                $displayText .= " - " . $room['branch_name'];
            }
            echo "<option value='" . $room['room_id'] . "'>" . htmlspecialchars($displayText) . "</option>";
        }
    } else {
        echo "<option>No Available</option>";
    }
    exit; // Exit to prevent further processing
}

// Old room_price handler - only execute if NOT a search_available_rooms request
if (isset($_POST['room_price']) && !isset($_POST['search_available_rooms'])) {
    $room_id = $_POST['room_id'];

    $sql = "SELECT * FROM room WHERE room_id = '$room_id'";
    $result = mysqli_query($connection, $sql);
    if ($result) {
        $room = mysqli_fetch_assoc($result);
        echo $room['price'];
    } else {
        echo "0";
    }
    exit; // Exit to prevent further processing
}

// Debug endpoint to test database and room queries
if (isset($_POST['debug_room_search'])) {
    header('Content-Type: application/json');
    
    $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
    $check_in = isset($_POST['check_in']) ? sanitizeInput($_POST['check_in']) : '';
    $check_out = isset($_POST['check_out']) ? sanitizeInput($_POST['check_out']) : '';
    
    $debug = [];
    $debug['branch_id'] = $branch_id;
    $debug['check_in'] = $check_in;
    $debug['check_out'] = $check_out;
    $debug['connection'] = isset($connection) ? 'available' : 'not available';
    
    if ($connection) {
        // Check if branch exists
        $branchQuery = "SELECT branch_id, branch_name FROM branches WHERE branch_id = ?";
        $branchStmt = mysqli_prepare($connection, $branchQuery);
        if ($branchStmt) {
            mysqli_stmt_bind_param($branchStmt, "i", $branch_id);
            mysqli_stmt_execute($branchStmt);
            $branchResult = mysqli_stmt_get_result($branchStmt);
            $branch = mysqli_fetch_assoc($branchResult);
            $debug['branch_exists'] = $branch ? $branch['branch_name'] : 'NOT FOUND';
            mysqli_stmt_close($branchStmt);
        }
        
        // Check total rooms for branch
        $deleteFilter = getRoomDeleteFilter($connection, 'room');
        $roomCountQuery = "SELECT COUNT(*) as total FROM room WHERE branch_id = ?" . str_replace('AND room.', 'AND ', $deleteFilter);
        $roomCountStmt = mysqli_prepare($connection, $roomCountQuery);
        if ($roomCountStmt) {
            mysqli_stmt_bind_param($roomCountStmt, "i", $branch_id);
            mysqli_stmt_execute($roomCountStmt);
            $roomCountResult = mysqli_stmt_get_result($roomCountStmt);
            $roomCount = mysqli_fetch_assoc($roomCountResult);
            $debug['total_rooms'] = $roomCount['total'] ?? 0;
            mysqli_stmt_close($roomCountStmt);
        }
        
        // Check if room_type_id column exists
        $checkColumnQuery = "SHOW COLUMNS FROM room LIKE 'room_type_id'";
        $columnResult = mysqli_query($connection, $checkColumnQuery);
        $debug['has_room_type_id'] = mysqli_num_rows($columnResult) > 0;
        
        // Check booking table date column type
        $checkBookingDateQuery = "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                                  WHERE TABLE_SCHEMA = DATABASE() 
                                  AND TABLE_NAME = 'booking' 
                                  AND COLUMN_NAME = 'check_in' 
                                  LIMIT 1";
        $dateTypeResult = mysqli_query($connection, $checkBookingDateQuery);
        if ($dateTypeRow = mysqli_fetch_assoc($dateTypeResult)) {
            $debug['booking_check_in_type'] = $dateTypeRow['DATA_TYPE'];
        }
    }
    
    echo json_encode(['success' => true, 'debug' => $debug]);
    exit;
}

// Get room types for a specific branch
if (isset($_POST['get_branch_room_types'])) {
    header('Content-Type: application/json');
    
    try {
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        
        if (!$branch_id) {
            echo json_encode(['success' => false, 'message' => 'Branch ID is required']);
            exit;
        }
        
        // Check if room table has room_type_id column
        $checkColumnQuery = "SHOW COLUMNS FROM room LIKE 'room_type_id'";
        $columnResult = mysqli_query($connection, $checkColumnQuery);
        $hasRoomTypeId = mysqli_num_rows($columnResult) > 0;
        
        $room_types = [];
        
        if ($hasRoomTypeId) {
            // Use JOIN with room_type table
            $deleteFilter = getRoomDeleteFilter($connection, 'r');
            $query = "SELECT DISTINCT rt.room_type_id, rt.room_type, rt.price, rt.max_person 
                      FROM room r 
                      INNER JOIN room_type rt ON r.room_type_id = rt.room_type_id 
                      WHERE r.branch_id = ?" . $deleteFilter . " 
                      ORDER BY rt.price ASC";
        } else {
            // Use room_type column directly
            $deleteFilter = getRoomDeleteFilter($connection, 'r');
            $query = "SELECT DISTINCT r.room_type as room_type, r.price, r.max_person, 
                             NULL as room_type_id
                      FROM room r 
                      WHERE r.branch_id = ?" . $deleteFilter . " AND r.room_type IS NOT NULL AND r.room_type != ''
                      ORDER BY r.price ASC";
        }
        
        $stmt = mysqli_prepare($connection, $query);
        if (!$stmt) {
            throw new Exception("Failed to prepare query: " . mysqli_error($connection));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $branch_id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to execute query: " . mysqli_stmt_error($stmt));
        }
        
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            throw new Exception("Failed to get result: " . mysqli_error($connection));
        }
        
        while ($row = mysqli_fetch_assoc($result)) {
            $room_types[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        
        echo json_encode(['success' => true, 'room_types' => $room_types]);
        exit;
    } catch (Exception $e) {
        error_log("get_branch_room_types error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error loading room types.',
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

// Search available rooms for guest booking
if (isset($_POST['search_available_rooms'])) {
    // Start output buffering to catch any unwanted output
    ob_start();
    
    // Set JSON header first to ensure proper response
    header('Content-Type: application/json');
    
    // Enable error reporting for debugging (remove in production)
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Don't display, but log
    
    try {
        // Log the incoming request
        error_log("search_available_rooms called with: " . json_encode($_POST));
        
        // Check if availability.php exists
        if (!file_exists('includes/availability.php')) {
            throw new Exception("availability.php file not found");
        }
        
        require_once 'includes/availability.php';
        
        // Check if required functions exist
        if (!function_exists('getAvailableRooms')) {
            throw new Exception("getAvailableRooms function not found");
        }
        if (!function_exists('getAvailableRoomTypes')) {
            throw new Exception("getAvailableRoomTypes function not found");
        }
        if (!function_exists('validateBookingDates')) {
            throw new Exception("validateBookingDates function not found");
        }
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        $check_in = isset($_POST['check_in']) ? sanitizeInput($_POST['check_in']) : '';
        $check_out = isset($_POST['check_out']) ? sanitizeInput($_POST['check_out']) : '';
        $room_type = isset($_POST['room_type']) && !empty($_POST['room_type']) ? sanitizeInput($_POST['room_type']) : null;
        
        error_log("Parsed params - branch_id: $branch_id, check_in: $check_in, check_out: $check_out, room_type: " . ($room_type ?? 'null'));
        
        // Validate branch
        if (!$branch_id) {
            error_log("Validation failed: Branch ID is missing");
            echo json_encode(['success' => false, 'message' => 'Please select a branch.']);
            exit;
        }
        
        // Validate dates format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) {
            error_log("Validation failed: Invalid date format - check_in: $check_in, check_out: $check_out");
            echo json_encode(['success' => false, 'message' => 'Invalid date format. Please use the date picker.']);
            exit;
        }
        
        // Validate dates
        $dateValidation = validateBookingDates($check_in, $check_out);
        if (!$dateValidation['valid']) {
            error_log("Date validation failed: " . ($dateValidation['error'] ?? 'Unknown error'));
            echo json_encode(['success' => false, 'message' => $dateValidation['error']]);
            exit;
        }
        
        // Check database connection
        if (!isset($connection) || !$connection) {
            error_log("Database connection not available");
            throw new Exception("Database connection error");
        }
        
        $rooms = [];
        
        try {
            if ($room_type) {
                error_log("Getting available rooms for room type: $room_type, branch: $branch_id");
                // Get available rooms for specific room type and branch
                $availableRooms = getAvailableRooms($room_type, $check_in, $check_out, $branch_id);
                error_log("getAvailableRooms returned: " . (is_array($availableRooms) ? count($availableRooms) . " rooms" : gettype($availableRooms)));
                
                if (!is_array($availableRooms)) {
                    error_log("getAvailableRooms returned non-array: " . var_export($availableRooms, true));
                    throw new Exception("Failed to retrieve available rooms: " . (is_string($availableRooms) ? $availableRooms : 'Unknown error'));
                }
                foreach ($availableRooms as $room) {
                    // Ensure promotions array exists and is valid
                    $promotions = [];
                    if (isset($room['promotions']) && is_array($room['promotions'])) {
                        $promotions = $room['promotions'];
                    }
                    
                    $rooms[] = [
                        'room_id' => isset($room['room_id']) ? $room['room_id'] : 0,
                        'room_no' => isset($room['room_no']) ? $room['room_no'] : '',
                        'room_type' => isset($room['room_type']) ? $room['room_type'] : $room_type,
                        'room_type_id' => isset($room['room_type_id']) ? $room['room_type_id'] : null,
                        'price' => isset($room['price']) ? $room['price'] : 0,
                        'max_person' => isset($room['max_person']) ? $room['max_person'] : 2,
                        'promotions' => $promotions
                    ];
                }
            } else {
                error_log("Getting all rooms (available and unavailable) for branch: $branch_id");
                // Get all rooms with availability status
                $allRooms = getAllRoomsWithAvailability($check_in, $check_out, $branch_id);
                error_log("getAllRoomsWithAvailability returned: " . (is_array($allRooms) ? count($allRooms) . " rooms" : gettype($allRooms)));
                
                if (!is_array($allRooms)) {
                    error_log("getAllRoomsWithAvailability returned non-array: " . var_export($allRooms, true));
                    throw new Exception("Failed to retrieve rooms: " . (is_string($allRooms) ? $allRooms : 'Unknown error'));
                }
                
                foreach ($allRooms as $room) {
                    // Ensure promotions array exists and is valid
                    $promotions = [];
                    if (isset($room['promotions']) && is_array($room['promotions'])) {
                        $promotions = $room['promotions'];
                    }
                    
                    $rooms[] = [
                        'room_id' => isset($room['room_id']) ? $room['room_id'] : 0,
                        'room_no' => isset($room['room_no']) ? $room['room_no'] : '',
                        'room_type' => isset($room['room_type']) ? $room['room_type'] : '',
                        'room_type_id' => isset($room['room_type_id']) ? $room['room_type_id'] : null,
                        'price' => isset($room['price']) ? $room['price'] : 0,
                        'max_person' => isset($room['max_person']) ? $room['max_person'] : 2,
                        'is_available' => isset($room['is_available']) ? $room['is_available'] : false,
                        'promotions' => $promotions
                    ];
                }
            }
            
            error_log("Total rooms found: " . count($rooms));
        } catch (Exception $e) {
            error_log("Error in room search: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
            throw $e;
        }
        
        // Check if branch has any rooms at all (for debugging and user feedback)
        $deleteFilter = getRoomDeleteFilter($connection, 'room');
        $checkRoomsQuery = "SELECT COUNT(*) as room_count FROM room WHERE branch_id = ?" . str_replace('AND room.', 'AND ', $deleteFilter);
        $checkStmt = mysqli_prepare($connection, $checkRoomsQuery);
        if ($checkStmt) {
            mysqli_stmt_bind_param($checkStmt, "i", $branch_id);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            if ($checkRow = mysqli_fetch_assoc($checkResult)) {
                error_log("Branch $branch_id has " . $checkRow['room_count'] . " total room(s)");
                if ($checkRow['room_count'] == 0 && count($rooms) == 0) {
                    // No rooms in branch at all - provide helpful message
                    ob_clean();
                    echo json_encode([
                        'success' => false, 
                        'message' => 'No rooms are configured for the selected branch. Please contact the hotel or select a different branch.',
                        'rooms' => []
                    ]);
                    exit;
                }
            }
            mysqli_stmt_close($checkStmt);
        }
        
        // Clear any output that might have been generated
        ob_clean();
        
        echo json_encode(['success' => true, 'rooms' => $rooms]);
        exit;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        $errorTrace = $e->getTraceAsString();
        error_log("search_available_rooms error: " . $errorMsg);
        error_log("Stack trace: " . $errorTrace);
        
        // Clear any output
        ob_clean();
        
        // Return detailed error in development (remove in production)
        echo json_encode([
            'success' => false, 
            'message' => 'Error searching for rooms. Please try again.',
            'error' => $errorMsg,
            'debug' => $errorTrace
        ]);
        exit;
    } catch (Error $e) {
        $errorMsg = $e->getMessage();
        $errorTrace = $e->getTraceAsString();
        error_log("search_available_rooms fatal error: " . $errorMsg);
        error_log("Stack trace: " . $errorTrace);
        
        // Clear any output
        ob_clean();
        
        echo json_encode([
            'success' => false, 
            'message' => 'Fatal error searching for rooms. Please contact support.',
            'error' => $errorMsg
        ]);
        exit;
    } finally {
        // End output buffering
        ob_end_flush();
    }
}

if (isset($_POST['booking'])) {
    // Start output buffering to catch any unwanted output
    ob_start();
    
    // Set JSON header first
    header('Content-Type: application/json');
    
    // Initialize response
    $response = ['done' => false, 'data' => ''];
    
    try {
        // Log the incoming booking request
        error_log("Booking request received: " . json_encode(array_intersect_key($_POST, array_flip(['room_id', 'check_in', 'check_out', 'name', 'email']))));
        
        require_once 'includes/availability.php';
        
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            $response['done'] = false;
            $response['data'] = 'Security token mismatch. Please refresh the page and try again.';
            echo json_encode($response);
            exit;
        }
        
        // Check if guest is logged in (booking page requires login)
        if (!isset($_SESSION['guest_id']) || empty($_SESSION['guest_id'])) {
            $response['done'] = false;
            $response['data'] = 'You must be logged in to make a booking. Please login and try again.';
            echo json_encode($response);
            exit;
        }
        
        $room_id = intval($_POST['room_id']);
        $check_in = sanitizeInput($_POST['check_in']);
        $check_out = sanitizeInput($_POST['check_out']);
        $total_price = floatval($_POST['total_price']);
        $name = sanitizeInput($_POST['name']);
        $contact_no = sanitizeInput($_POST['contact_no']);
        $email = sanitizeInput($_POST['email']);
        $id_card_id = isset($_POST['id_card_id']) && !empty($_POST['id_card_id']) ? intval($_POST['id_card_id']) : null;
        $id_card_no = isset($_POST['id_card_no']) ? sanitizeInput($_POST['id_card_no']) : '';
        $address = isset($_POST['address']) ? sanitizeInput($_POST['address']) : '';
        
        // Validate required fields
        if (empty($room_id) || empty($check_in) || empty($check_out) || empty($name) || empty($email)) {
            $response['done'] = false;
            $response['data'] = 'Please fill in all required fields.';
            echo json_encode($response);
            exit;
        }

        // Validate dates
        $dateValidation = validateBookingDates($check_in, $check_out);
        if (!$dateValidation['valid']) {
            $response['done'] = false;
            $response['data'] = $dateValidation['error'];
            echo json_encode($response);
            exit;
        }

        // CRITICAL: Check room availability BEFORE booking
        $isAvailable = isRoomAvailable($room_id, $check_in, $check_out);
        if ($isAvailable === false) {
            $response['done'] = false;
            $response['data'] = 'This room is no longer available for the selected dates. Please select different dates or another room.';
            echo json_encode($response);
            exit;
        }

        $guest_id = null;

        // Get guest_id from session (already verified at start of handler)
        $guest_id = intval($_SESSION['guest_id']);
    
        // Verify guest exists and is active
        $verifyGuestQuery = "SELECT guest_id FROM guests WHERE guest_id = ? AND status = 'active' LIMIT 1";
        $verifyStmt = mysqli_prepare($connection, $verifyGuestQuery);
        if (!$verifyStmt) {
            throw new Exception("Failed to prepare guest verification query: " . mysqli_error($connection));
        }
        mysqli_stmt_bind_param($verifyStmt, "i", $guest_id);
        if (!mysqli_stmt_execute($verifyStmt)) {
            $error = mysqli_stmt_error($verifyStmt);
            mysqli_stmt_close($verifyStmt);
            throw new Exception("Failed to verify guest: " . $error);
        }
        $verifyResult = mysqli_stmt_get_result($verifyStmt);
        
        if (mysqli_num_rows($verifyResult) == 0) {
            // Guest ID in session but not found in database - invalid session
            mysqli_stmt_close($verifyStmt);
            $response['done'] = false;
            $response['data'] = 'Your session is invalid. Please login again.';
            echo json_encode($response);
            exit;
        }
        mysqli_stmt_close($verifyStmt);
        
        // Update guest information with booking form data
        // Check if guests table has id_card_type_id column (new) or id_card_type (old)
        $checkIdCardColumnQuery = "SHOW COLUMNS FROM guests LIKE 'id_card_type_id'";
        $idCardColumnResult = mysqli_query($connection, $checkIdCardColumnQuery);
        $hasIdCardTypeIdColumn = mysqli_num_rows($idCardColumnResult) > 0;
        
        if ($hasIdCardTypeIdColumn) {
            // Use id_card_type_id (new schema)
            $updateGuestQuery = "UPDATE guests SET name = ?, contact_no = ?, email = ?, id_card_type_id = ?, id_card_no = ?, address = ? WHERE guest_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateGuestQuery);
            if (!$updateStmt) {
                error_log("Failed to prepare guest update query: " . mysqli_error($connection));
                // Continue anyway - guest update is not critical for booking
            } else {
                $contact_no_int = !empty($contact_no) ? preg_replace('/[^0-9]/', '', $contact_no) : null;
                $contact_no_int = !empty($contact_no_int) && is_numeric($contact_no_int) ? intval($contact_no_int) : null;
                // Allow null for id_card_id if not provided
                $id_card_id_value = !empty($id_card_id) ? intval($id_card_id) : null;
                mysqli_stmt_bind_param($updateStmt, "sssissi", $name, $contact_no_int, $email, $id_card_id_value, $id_card_no, $address, $guest_id);
                if (!mysqli_stmt_execute($updateStmt)) {
                    error_log("Failed to update guest info: " . mysqli_stmt_error($updateStmt));
                    // Continue anyway - guest update is not critical for booking
                }
                mysqli_stmt_close($updateStmt);
            }
        } else {
            // Use id_card_type (old schema - VARCHAR)
            // Map id_card_id to id_card_type name if possible
            $id_card_type_name = null;
            if (!empty($id_card_id)) {
                $mapQuery = "SELECT id_card_type FROM id_card_type WHERE id_card_type_id = ? LIMIT 1";
                $mapStmt = mysqli_prepare($connection, $mapQuery);
                if ($mapStmt) {
                    mysqli_stmt_bind_param($mapStmt, "i", $id_card_id);
                    mysqli_stmt_execute($mapStmt);
                    $mapResult = mysqli_stmt_get_result($mapStmt);
                    if ($mapRow = mysqli_fetch_assoc($mapResult)) {
                        $id_card_type_name = $mapRow['id_card_type'];
                    }
                    mysqli_stmt_close($mapStmt);
                }
            }
            
            $updateGuestQuery = "UPDATE guests SET name = ?, contact_no = ?, email = ?, id_card_type = ?, id_card_no = ?, address = ? WHERE guest_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateGuestQuery);
            if (!$updateStmt) {
                error_log("Failed to prepare guest update query: " . mysqli_error($connection));
                // Continue anyway - guest update is not critical for booking
            } else {
                $contact_no_int = !empty($contact_no) ? preg_replace('/[^0-9]/', '', $contact_no) : null;
                $contact_no_int = !empty($contact_no_int) && is_numeric($contact_no_int) ? intval($contact_no_int) : null;
                mysqli_stmt_bind_param($updateStmt, "ssssssi", $name, $contact_no_int, $email, $id_card_type_name, $id_card_no, $address, $guest_id);
                if (!mysqli_stmt_execute($updateStmt)) {
                    error_log("Failed to update guest info: " . mysqli_stmt_error($updateStmt));
                    // Continue anyway - guest update is not critical for booking
                }
                mysqli_stmt_close($updateStmt);
            }
        }

        // Create booking - guest_id should always be set at this point
        if ($guest_id) {
            // Start transaction to ensure atomicity
            mysqli_begin_transaction($connection);
            
            try {
                // Check availability one more time right before booking (race condition protection)
                // This prevents double-booking if two users try to book the same room simultaneously
                $isAvailable = isRoomAvailable($room_id, $check_in, $check_out);
                if ($isAvailable === false) {
                    mysqli_rollback($connection);
                    $response['done'] = false;
                    $response['data'] = 'This room was just booked by another guest. Please select a different room or dates.';
                    echo json_encode($response);
                    exit;
                }

        // Check if columns are DATE type or VARCHAR (for backward compatibility)
        $checkColumnType = "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                            WHERE TABLE_SCHEMA = DATABASE() 
                            AND TABLE_NAME = 'booking' 
                            AND COLUMN_NAME = 'check_in' 
                            LIMIT 1";
        $typeResult = mysqli_query($connection, $checkColumnType);
        $isDateType = false;
        if ($typeResult && $row = mysqli_fetch_assoc($typeResult)) {
            $isDateType = (strtolower($row['DATA_TYPE']) == 'date' || strtolower($row['DATA_TYPE']) == 'datetime');
        }
        
        // Format dates based on column type
        if ($isDateType) {
            // Use DATE format directly (YYYY-MM-DD) - no conversion needed!
            $check_in_formatted = $check_in;
            $check_out_formatted = $check_out;
        } else {
            // Convert from YYYY-MM-DD to dd-mm-yyyy format for VARCHAR columns (backward compatibility)
            $check_in_formatted = $check_in;
            $check_out_formatted = $check_out;
            
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in)) {
                $parts = explode('-', $check_in);
                $check_in_formatted = $parts[2] . '-' . $parts[1] . '-' . $parts[0]; // dd-mm-yyyy
            }
            
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) {
                $parts = explode('-', $check_out);
                $check_out_formatted = $parts[2] . '-' . $parts[1] . '-' . $parts[0]; // dd-mm-yyyy
            }
        }

        // Insert booking using guest_id (guests should NOT use customer_id)
        // Check if guest_id column exists in booking table
        $checkColumnQuery = "SHOW COLUMNS FROM booking LIKE 'guest_id'";
        $columnResult = mysqli_query($connection, $checkColumnQuery);
        $hasGuestIdColumn = mysqli_num_rows($columnResult) > 0;
        
        // At this point, $guest_id should always be set (we check at the start)
        if (!$guest_id) {
            $response['done'] = false;
            $response['data'] = "Error: Guest session invalid. Please login again.";
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        // Require guest_id column - guests should NOT use customer_id
        if (!$hasGuestIdColumn) {
            $response['done'] = false;
            $response['data'] = "Database configuration error: guest_id column is missing from booking table. Please run the database migration script to add it.";
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
            // Get advance payment and payment method from request
            $advance_payment = isset($_POST['advance_payment']) && !empty($_POST['advance_payment']) ? floatval($_POST['advance_payment']) : 0;
            $payment_method = isset($_POST['payment_method']) && !empty($_POST['payment_method']) ? sanitizeInput($_POST['payment_method']) : 'pending';
            
            // Validate advance payment
            if ($advance_payment < 0) {
                throw new Exception("Advance payment cannot be negative");
            }
            if ($advance_payment > $total_price) {
                throw new Exception("Advance payment cannot exceed total price");
            }
            
            // Calculate remaining price
            $remaining_price = $total_price - $advance_payment;
            
            // Determine advance payment method
            // Check if payment method is provided in the request
            $advance_payment_method = 'none';
            if ($advance_payment > 0) {
                // Check if advance_payment_method column exists and if value is provided
                $checkAdvancePaymentMethodColumn = "SHOW COLUMNS FROM booking LIKE 'advance_payment_method'";
                $advancePaymentMethodResult = mysqli_query($connection, $checkAdvancePaymentMethodColumn);
                $hasAdvancePaymentMethodColumn = mysqli_num_rows($advancePaymentMethodResult) > 0;
                
                if ($hasAdvancePaymentMethodColumn && isset($_POST['advance_payment_method']) && !empty($_POST['advance_payment_method'])) {
                    $advance_payment_method = sanitizeInput($_POST['advance_payment_method']);
                    // Validate payment method
                    if (!in_array($advance_payment_method, ['cash', 'card', 'none'])) {
                        $advance_payment_method = 'card'; // Default to card if invalid
                    }
                } else {
                    $advance_payment_method = 'card'; // Default to card if not specified
                }
            }
            
            // Determine overall payment status and method
            $payment_status = ($advance_payment >= $total_price) ? 1 : 0; // Fully paid or not
            if ($payment_status == 1) {
                $payment_method = 'card';
            } elseif ($advance_payment > 0) {
                $payment_method = 'card'; // Partial payment made via card
            } else {
                $payment_method = 'pending';
            }
            
            // Get meal package ID if selected (convert empty string to NULL)
            $meal_package_id = null;
            if (isset($_POST['meal_package_id']) && $_POST['meal_package_id'] !== '' && $_POST['meal_package_id'] !== null) {
                $meal_package_id = intval($_POST['meal_package_id']);
                if ($meal_package_id <= 0) {
                    $meal_package_id = null;
                }
            }
            
            // Check which columns exist in booking table
            $checkMealPackageColumn = "SHOW COLUMNS FROM booking LIKE 'meal_package_id'";
            $mealPackageColumnResult = mysqli_query($connection, $checkMealPackageColumn);
            $hasMealPackageColumn = mysqli_num_rows($mealPackageColumnResult) > 0;
            
            $checkAdvancePaymentColumn = "SHOW COLUMNS FROM booking LIKE 'advance_payment'";
            $advancePaymentResult = mysqli_query($connection, $checkAdvancePaymentColumn);
            $hasAdvancePaymentColumn = mysqli_num_rows($advancePaymentResult) > 0;
            
            $checkPaymentMethodColumn = "SHOW COLUMNS FROM booking LIKE 'payment_method'";
            $paymentMethodResult = mysqli_query($connection, $checkPaymentMethodColumn);
            $hasPaymentMethodColumn = mysqli_num_rows($paymentMethodResult) > 0;
            
            // Build INSERT query based on existing columns
            // Base columns that always exist
            $columns = ['guest_id', 'room_id', 'check_in', 'check_out', 'total_price', 'remaining_price', 'payment_status'];
            $placeholders = ['?', '?', '?', '?', '?', '?', '?'];
            
            if ($hasMealPackageColumn) {
                array_splice($columns, 2, 0, 'meal_package_id'); // Insert after room_id
                array_splice($placeholders, 2, 0, '?');
            }
            
            // Check if advance_payment_method column exists
            $checkAdvancePaymentMethodColumn = "SHOW COLUMNS FROM booking LIKE 'advance_payment_method'";
            $advancePaymentMethodResult = mysqli_query($connection, $checkAdvancePaymentMethodColumn);
            $hasAdvancePaymentMethodColumn = mysqli_num_rows($advancePaymentMethodResult) > 0;
            
            if ($hasAdvancePaymentColumn) {
                $columns[] = 'advance_payment';
                $placeholders[] = '?';
            }
            
            if ($hasAdvancePaymentMethodColumn) {
                $columns[] = 'advance_payment_method';
                $placeholders[] = '?';
            }
            
            if ($hasPaymentMethodColumn) {
                $columns[] = 'payment_method';
                $placeholders[] = '?';
            }
            
            $booking_sql = "INSERT INTO booking (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $bookingStmt = mysqli_prepare($connection, $booking_sql);
            if (!$bookingStmt) {
                $error = mysqli_error($connection);
                error_log("Booking prepare failed: " . $error . " | SQL: " . $booking_sql);
                throw new Exception("Failed to prepare booking query: " . $error);
            }
            
            // Log for debugging
            error_log("Booking params: guest_id=$guest_id, room_id=$room_id, meal_package_id=" . ($meal_package_id ?? 'NULL') . ", check_in=$check_in_formatted, check_out=$check_out_formatted, total_price=$total_price");
            
            // Bind parameters based on which columns exist
            // Order: guest_id(i), room_id(i), [meal_package_id(i)], check_in(s), check_out(s), total_price(d), remaining_price(d), payment_status(i), [advance_payment(d)], [advance_payment_method(s)], [payment_method(s)]
            // Build type string and parameters array dynamically
            $typeString = "iissddi"; // Base: guest_id, room_id, check_in, check_out, total_price, remaining_price, payment_status
            $params = [&$guest_id, &$room_id, &$check_in_formatted, &$check_out_formatted, &$total_price, &$remaining_price, &$payment_status];
            
            // Add meal_package_id if exists (after room_id)
            if ($hasMealPackageColumn) {
                $typeString = substr_replace($typeString, "i", 2, 0); // Insert 'i' after second 'i'
                array_splice($params, 2, 0, [&$meal_package_id]);
            }
            
            // Add advance_payment if exists (after payment_status)
            if ($hasAdvancePaymentColumn) {
                $typeString .= "d";
                $params[] = &$advance_payment;
            }
            
            // Add advance_payment_method if exists (after advance_payment)
            if ($hasAdvancePaymentMethodColumn) {
                $typeString .= "s";
                $params[] = &$advance_payment_method;
            }
            
            // Add payment_method if exists (last)
            if ($hasPaymentMethodColumn) {
                $typeString .= "s";
                $params[] = &$payment_method;
            }
            
            // Call bind_param with dynamic parameters
            mysqli_stmt_bind_param($bookingStmt, $typeString, ...$params);
            
            if (!mysqli_stmt_execute($bookingStmt)) {
                $error = mysqli_stmt_error($bookingStmt);
                mysqli_stmt_close($bookingStmt);
                error_log("Booking execute failed: " . $error . " | SQL: " . $booking_sql);
                throw new Exception("Failed to create booking: " . $error);
            }
            
            $booking_id = mysqli_insert_id($connection);
            mysqli_stmt_close($bookingStmt);
            
            if (!$booking_id) {
                error_log("Booking insert succeeded but no booking_id returned");
                throw new Exception("Booking was created but could not retrieve booking ID");
            }
            
            error_log("Booking created successfully: booking_id=$booking_id, room_id=$room_id, check_in=$check_in_formatted, check_out=$check_out_formatted");
            
            // Update room status to booked (optional - availability is determined by booking table)
            // This is just for general room status tracking
            $room_stats_sql = "UPDATE room SET status = '1' WHERE room_id = ?";
            $roomStmt = mysqli_prepare($connection, $room_stats_sql);
            if ($roomStmt) {
                mysqli_stmt_bind_param($roomStmt, "i", $room_id);
                if (!mysqli_stmt_execute($roomStmt)) {
                    error_log("Failed to update room status: " . mysqli_stmt_error($roomStmt));
                    // Don't fail the booking if room status update fails - availability is based on booking table
                } else {
                    error_log("Room status updated to booked for room_id=$room_id");
                }
                mysqli_stmt_close($roomStmt);
            }
            
            // Commit transaction - booking is now permanent and visible to other queries
            mysqli_commit($connection);
            error_log("Booking transaction committed successfully: booking_id=$booking_id");
            
            // Small delay to ensure transaction is fully committed
            usleep(100000); // 0.1 second
            
            // Verify the booking was created and room is now unavailable (after commit)
            // Use the original check_in/check_out dates (YYYY-MM-DD format) for verification
            // The isRoomAvailable function will handle date format conversion
            error_log("Verifying availability after booking: room_id=$room_id, check_in=$check_in, check_out=$check_out");
            $verifyAvailable = isRoomAvailable($room_id, $check_in, $check_out);
            if ($verifyAvailable === true) {
                error_log("WARNING: Room $room_id is still showing as available after booking $booking_id was created and committed!");
                error_log("WARNING: Booking details - check_in_formatted='$check_in_formatted', check_out_formatted='$check_out_formatted'");
                error_log("WARNING: This suggests the booking might not be detected by the availability check. Check date formats and overlap logic.");
            } else {
                error_log("Confirmed: Room $room_id is now unavailable after booking $booking_id (as expected)");
            }
                
                // Send booking confirmation email notification (optional - won't break booking if it fails)
                error_log("=== BOOKING EMAIL DEBUG: Starting email notification process for booking_id=$booking_id ===");
                try {
                    // Check if email notification tables exist before attempting to send
                    error_log("BOOKING EMAIL DEBUG: Checking if notification_settings table exists...");
                    $tableCheck = mysqli_query($connection, "SHOW TABLES LIKE 'notification_settings'");
                    if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
                        error_log("BOOKING EMAIL DEBUG: notification_settings table EXISTS");
                        
                        // Tables exist, try to send email
                        $emailFilePath = __DIR__ . '/includes/email_notifications.php';
                        error_log("BOOKING EMAIL DEBUG: Checking if email_notifications.php exists at: $emailFilePath");
                        
                        if (file_exists($emailFilePath)) {
                            error_log("BOOKING EMAIL DEBUG: email_notifications.php file EXISTS - loading it now");
                            require_once $emailFilePath;
                            
                            error_log("BOOKING EMAIL DEBUG: Calling sendBookingConfirmationEmail() for booking_id=$booking_id");
                            $emailResult = sendBookingConfirmationEmail($booking_id);
                            
                            error_log("BOOKING EMAIL DEBUG: Email function returned - Success: " . ($emailResult['success'] ? 'YES' : 'NO'));
                            
                            if ($emailResult['success']) {
                                error_log("✓ Booking confirmation email sent successfully for booking_id=$booking_id");
                            } else {
                                error_log("✗ Failed to send booking confirmation email: " . $emailResult['message']);
                            }
                        } else {
                            error_log("BOOKING EMAIL DEBUG: email_notifications.php file NOT FOUND at: $emailFilePath");
                        }
                    } else {
                        error_log("BOOKING EMAIL DEBUG: notification_settings table DOES NOT EXIST - skipping email");
                        error_log("Email notification system not yet configured - skipping confirmation email");
                    }
                } catch (Exception $e) {
                    error_log("✗ BOOKING EMAIL DEBUG: Exception caught - " . $e->getMessage());
                    error_log("Error sending booking confirmation email: " . $e->getMessage());
                    // Don't fail the booking if email fails - continue with success response
                } catch (Error $e) {
                    error_log("✗ BOOKING EMAIL DEBUG: Fatal Error caught - " . $e->getMessage());
                    error_log("Fatal error in email notification: " . $e->getMessage());
                    // Catch fatal errors too - don't break booking
                }
                error_log("=== BOOKING EMAIL DEBUG: Email notification process completed ===");
                
                $response['done'] = true;
                $response['data'] = 'Booking confirmed successfully!';
                
            } catch (Exception $e) {
                // Rollback transaction on any error
                mysqli_rollback($connection);
                error_log("Booking transaction rolled back due to error: " . $e->getMessage());
                throw $e;
            }
        } else {
            // This should never be reached since we check for guest_id at the start
            // But keeping it as a safety net
            $response['done'] = false;
            $response['data'] = "Error: Guest session invalid. Please login again.";
        }

        // Clear any output
        ob_clean();
        
        // Ensure JSON response
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        $errorTrace = $e->getTraceAsString();
        error_log("Booking Error: " . $errorMsg);
        error_log("Booking Error Trace: " . $errorTrace);
        error_log("Booking POST data: " . json_encode($_POST));
        
        $response['done'] = false;
        // Show detailed error in development mode (DISPLAY_ERRORS is 1 in config.php)
        if (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS) {
            $response['data'] = 'Error: ' . $errorMsg;
            $response['error'] = $errorMsg;
            $response['debug'] = substr($errorTrace, 0, 500); // Limit trace length
        } else {
            $response['data'] = 'An error occurred while processing your booking. Please try again.';
        }
        
        // Clear any output
        ob_clean();
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } catch (Error $e) {
        $errorMsg = $e->getMessage();
        $errorTrace = $e->getTraceAsString();
        error_log("Booking Fatal Error: " . $errorMsg);
        error_log("Booking Fatal Error Trace: " . $errorTrace);
        error_log("Booking POST data: " . json_encode($_POST));
        
        $response['done'] = false;
        $response['data'] = 'A fatal error occurred while processing your booking. Please contact support.';
        
        // Clear any output
        ob_clean();
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } finally {
        // End output buffering
        ob_end_flush();
    }
}

if (isset($_POST['cutomerDetails'])) {
    //$customer_result='';
    $room_id = $_POST['room_id'];

    if ($room_id != '') {
        $sql = "SELECT b.*, r.*, g.* FROM booking b 
                JOIN room r ON b.room_id = r.room_id 
                JOIN guests g ON b.guest_id = g.guest_id 
                WHERE r.room_id = '$room_id' AND b.payment_status = '0'";
        $result = mysqli_query($connection, $sql);
        if ($result) {
            $guest_details = mysqli_fetch_assoc($result);
            $id_type = $guest_details['id_card_type_id'];
            $query = "select id_card_type from id_card_type where id_card_type_id = '$id_type'";
            $result = mysqli_query($connection, $query);
            $id_type_name = mysqli_fetch_assoc($result);
            $response['done'] = true;
            $response['guest_id'] = $guest_details['guest_id'];
            $response['customer_name'] = $guest_details['name'];
            $response['contact_no'] = $guest_details['contact_no'];
            $response['email'] = $guest_details['email'];
            $response['id_card_no'] = $guest_details['id_card_no'];
            $response['id_card_type_id'] = $id_type_name['id_card_type'];
            $response['address'] = $guest_details['address'];
            $response['remaining_price'] = $guest_details['remaining_price'];
        } else {
            $response['done'] = false;
            $response['data'] = "DataBase Error";
        }

        echo json_encode($response);
    }
}

if (isset($_POST['booked_room'])) {
    $room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
    
    if (!$room_id) {
        $response['done'] = false;
        $response['data'] = "Invalid room ID";
        echo json_encode($response);
        exit;
    }

    // Get the most recent booking for this room (regardless of payment status)
    // Check if booking table has guest_id column
    $checkGuestIdQuery = "SHOW COLUMNS FROM booking LIKE 'guest_id'";
    $guestIdResult = mysqli_query($connection, $checkGuestIdQuery);
    $hasGuestId = mysqli_num_rows($guestIdResult) > 0;
    
    // Check if room table has room_type_id column
    $checkRoomTypeIdQuery = "SHOW COLUMNS FROM room LIKE 'room_type_id'";
    $roomTypeIdResult = mysqli_query($connection, $checkRoomTypeIdQuery);
    $hasRoomTypeId = mysqli_num_rows($roomTypeIdResult) > 0;
    
    if ($hasGuestId && $hasRoomTypeId) {
        // Use guest_id and room_type_id (new schema)
        $sql = "SELECT b.*, r.room_no, r.room_id, rt.room_type, g.name, g.guest_id
                FROM booking b 
                JOIN room r ON b.room_id = r.room_id 
                JOIN room_type rt ON r.room_type_id = rt.room_type_id
                JOIN guests g ON b.guest_id = g.guest_id 
                WHERE r.room_id = ? 
                ORDER BY b.booking_date DESC 
                LIMIT 1";
    } elseif ($hasGuestId) {
        // Use guest_id but room_type from room table (mixed schema)
        $sql = "SELECT b.*, r.room_no, r.room_id, r.room_type, g.name, g.guest_id
                FROM booking b 
                JOIN room r ON b.room_id = r.room_id 
                JOIN guests g ON b.guest_id = g.guest_id 
                WHERE r.room_id = ? 
                ORDER BY b.booking_date DESC 
                LIMIT 1";
    } else {
        // Fallback: old schema (might not have guest_id)
        $sql = "SELECT b.*, r.room_no, r.room_id, r.room_type
                FROM booking b 
                JOIN room r ON b.room_id = r.room_id 
                WHERE r.room_id = ? 
                ORDER BY b.booking_date DESC 
                LIMIT 1";
    }
    
    $stmt = mysqli_prepare($connection, $sql);
    if (!$stmt) {
        $response['done'] = false;
        $response['data'] = "Database Error: " . mysqli_error($connection);
        echo json_encode($response);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $room_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $room = mysqli_fetch_assoc($result);
        $response['done'] = true;
        $response['booking_id'] = $room['booking_id'];
        $response['name'] = isset($room['name']) ? $room['name'] : 'Guest';
        $response['room_no'] = $room['room_no'];
        $response['room_type'] = $room['room_type'];
        
        // Format dates - handle both DATE and VARCHAR formats
        $check_in = $room['check_in'];
        $check_out = $room['check_out'];
        
        // Try to parse date - handle both YYYY-MM-DD and dd-mm-yyyy formats
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in)) {
            // DATE format (YYYY-MM-DD)
            $check_in_timestamp = strtotime($check_in);
        } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_in)) {
            // VARCHAR format (dd-mm-yyyy)
            $parts = explode('-', $check_in);
            $check_in_timestamp = strtotime($parts[2] . '-' . $parts[1] . '-' . $parts[0]);
        } else {
            $check_in_timestamp = strtotime($check_in);
        }
        
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) {
            // DATE format (YYYY-MM-DD)
            $check_out_timestamp = strtotime($check_out);
        } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_out)) {
            // VARCHAR format (dd-mm-yyyy)
            $parts = explode('-', $check_out);
            $check_out_timestamp = strtotime($parts[2] . '-' . $parts[1] . '-' . $parts[0]);
        } else {
            $check_out_timestamp = strtotime($check_out);
        }
        
        $response['check_in'] = $check_in_timestamp ? date('M j, Y', $check_in_timestamp) : $check_in;
        $response['check_out'] = $check_out_timestamp ? date('M j, Y', $check_out_timestamp) : $check_out;
        $response['total_price'] = $room['total_price'];
        $response['remaining_price'] = isset($room['remaining_price']) ? $room['remaining_price'] : $room['total_price'];
    } else {
        $response['done'] = false;
        $response['data'] = "No booking found for this room";
    }
    
    mysqli_stmt_close($stmt);
    echo json_encode($response);
    exit;
}

if (isset($_POST['check_in_room'])) {
    // Start output buffering
    ob_start();
    header('Content-Type: application/json');
    
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $advance_payment = isset($_POST['advance_payment']) ? floatval($_POST['advance_payment']) : 0;
    $advance_payment_method = isset($_POST['advance_payment_method']) ? sanitizeInput($_POST['advance_payment_method']) : 'none';
    
    $response = ['done' => false, 'data' => ''];

    if ($booking_id <= 0) {
        $response['done'] = false;
        $response['data'] = "Error With Booking: Invalid booking ID";
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    // Check if advance_payment and payment_method columns exist
    $checkAdvanceColumn = mysqli_query($connection, "SHOW COLUMNS FROM booking LIKE 'advance_payment'");
    $hasAdvancePaymentColumn = mysqli_num_rows($checkAdvanceColumn) > 0;
    
    $checkAdvanceMethodColumn = mysqli_query($connection, "SHOW COLUMNS FROM booking LIKE 'advance_payment_method'");
    $hasAdvancePaymentMethodColumn = mysqli_num_rows($checkAdvanceMethodColumn) > 0;
    
    $checkPaymentMethodColumn = mysqli_query($connection, "SHOW COLUMNS FROM booking LIKE 'payment_method'");
    $hasPaymentMethodColumn = mysqli_num_rows($checkPaymentMethodColumn) > 0;
    
    // Build query based on available columns
    if ($hasAdvancePaymentColumn && $hasAdvancePaymentMethodColumn) {
        $query = "SELECT booking_id, room_id, total_price, remaining_price, advance_payment, advance_payment_method FROM booking WHERE booking_id = ?";
    } else {
        // Fallback if columns don't exist
        $query = "SELECT booking_id, room_id, total_price, remaining_price FROM booking WHERE booking_id = ?";
    }
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        $response['done'] = false;
        $response['data'] = "Database Error: " . mysqli_error($connection);
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        mysqli_stmt_close($stmt);
        $response['done'] = false;
        $response['data'] = "Error With Booking: Booking not found";
        ob_clean();
        echo json_encode($response);
        exit;
    }
    
    $booking_details = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $room_id = $booking_details['room_id'];
    $current_remaining = floatval($booking_details['remaining_price']);
    $total_price = floatval($booking_details['total_price']);
    
    // Get advance payment info if columns exist
    if ($hasAdvancePaymentColumn && $hasAdvancePaymentMethodColumn) {
        $existing_advance = floatval($booking_details['advance_payment'] ?? 0);
        $existing_advance_method = $booking_details['advance_payment_method'] ?? 'none';
    } else {
        // Columns don't exist - assume no advance payment
        $existing_advance = 0;
        $existing_advance_method = 'none';
    }
    
    // Normalize existing_advance_method (handle NULL, empty string, etc.)
    if (empty($existing_advance_method) || $existing_advance_method == '') {
        $existing_advance_method = 'none';
    }
    
    // Initialize variables
    $final_advance_method = 'none';
    $new_remaining_price = $total_price;
    
    // IMPORTANT: If guest already paid advance during booking, use that payment
    // Do NOT ask for additional advance payment at check-in
    if ($existing_advance > 0 && $existing_advance_method != 'none' && $existing_advance_method != '') {
        // Guest already paid advance - use existing payment, don't collect more
        $advance_payment = $existing_advance;
        $final_advance_method = $existing_advance_method;
        $new_remaining_price = $total_price - $existing_advance;
        
        // Log for debugging
        error_log("Check-in: Guest already paid advance of LKR " . $existing_advance . " via " . $existing_advance_method);
    } else {
        // No existing advance - admin can collect advance payment at check-in (optional)
        // Validate new advance payment if provided
        if ($advance_payment < 0) {
            $response['done'] = false;
            $response['data'] = "Advance payment cannot be negative";
            ob_clean();
            echo json_encode($response);
            exit;
        }
        
        if ($advance_payment > $total_price) {
            $response['done'] = false;
            $response['data'] = "Advance payment cannot exceed total price";
            ob_clean();
            echo json_encode($response);
            exit;
        }
        
        // Validate payment method if advance payment is provided
        if ($advance_payment > 0) {
            if (!in_array($advance_payment_method, ['cash', 'card'])) {
                $response['done'] = false;
                $response['data'] = "Invalid payment method. Please select cash or card.";
                ob_clean();
                echo json_encode($response);
                exit;
            }
            $final_advance_method = $advance_payment_method;
        } else {
            $final_advance_method = 'none';
        }
        
        // Calculate new remaining price with new advance payment
        $new_remaining_price = $total_price - $advance_payment;
    }

    // Start transaction
    mysqli_begin_transaction($connection);
    
    try {
        // Determine payment status
        // Payment is complete if remaining price is 0 or very close to 0 (accounting for floating point)
        $payment_status = ($new_remaining_price <= 0.01) ? 1 : 0;
        
        // Determine payment method
        if ($advance_payment > 0) {
            $payment_method = $final_advance_method;
            // Ensure payment_method is valid
            if (!in_array($payment_method, ['cash', 'card', 'pending'])) {
                $payment_method = 'card'; // Default to card if invalid
            }
        } else {
            $payment_method = 'pending';
        }
        
        // Ensure final_advance_method is valid for database
        if (!in_array($final_advance_method, ['cash', 'card', 'none'])) {
            $final_advance_method = 'none';
        }
        
        // Log for debugging
        error_log("Check-in: booking_id=$booking_id, existing_advance=$existing_advance, advance_used=$advance_payment, total_price=$total_price, remaining=$new_remaining_price, payment_status=$payment_status, payment_method=$payment_method, advance_method=$final_advance_method");
        
        // Update booking status to 'checked_in', remaining price, advance payment, and payment methods
        // IMPORTANT: If guest already paid advance, we preserve it; otherwise use admin's new advance
        if ($hasAdvancePaymentColumn && $hasAdvancePaymentMethodColumn && $hasPaymentMethodColumn) {
            // All columns exist - update with advance payment info
            $updateBooking = "UPDATE booking SET status = 'checked_in', remaining_price = ?, advance_payment = ?, advance_payment_method = ?, payment_method = ?, payment_status = ? WHERE booking_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateBooking);
            if (!$updateStmt) {
                throw new Exception("Failed to prepare booking update: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($updateStmt, "ddssii", $new_remaining_price, $advance_payment, $final_advance_method, $payment_method, $payment_status, $booking_id);
        } elseif ($hasAdvancePaymentColumn && $hasAdvancePaymentMethodColumn) {
            // Advance columns exist but payment_method doesn't
            $updateBooking = "UPDATE booking SET status = 'checked_in', remaining_price = ?, advance_payment = ?, advance_payment_method = ?, payment_status = ? WHERE booking_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateBooking);
            if (!$updateStmt) {
                throw new Exception("Failed to prepare booking update: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($updateStmt, "ddssi", $new_remaining_price, $advance_payment, $final_advance_method, $payment_status, $booking_id);
        } elseif ($hasPaymentMethodColumn) {
            // Only payment_method exists
            $updateBooking = "UPDATE booking SET status = 'checked_in', remaining_price = ?, payment_method = ?, payment_status = ? WHERE booking_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateBooking);
            if (!$updateStmt) {
                throw new Exception("Failed to prepare booking update: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($updateStmt, "dsii", $new_remaining_price, $payment_method, $payment_status, $booking_id);
        } else {
            // Basic columns only
            $updateBooking = "UPDATE booking SET status = 'checked_in', remaining_price = ?, payment_status = ? WHERE booking_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateBooking);
            if (!$updateStmt) {
                throw new Exception("Failed to prepare booking update: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($updateStmt, "dii", $new_remaining_price, $payment_status, $booking_id);
        }
        if (!mysqli_stmt_execute($updateStmt)) {
            throw new Exception("Failed to update booking: " . mysqli_stmt_error($updateStmt));
        }
        mysqli_stmt_close($updateStmt);
        
        // Sync room status based on all active bookings
        syncRoomStatus($connection, $room_id);
        
        // Commit transaction
        mysqli_commit($connection);
        
        $response['done'] = true;
        $response['data'] = "Check-in successful";
        
    } catch (Exception $e) {
        mysqli_rollback($connection);
        $errorMsg = $e->getMessage();
        error_log("Check-in error: " . $errorMsg);
        error_log("Check-in error trace: " . $e->getTraceAsString());
        $response['done'] = false;
        $response['data'] = "Error With Booking: " . $errorMsg;
    } catch (Error $e) {
        mysqli_rollback($connection);
        $errorMsg = $e->getMessage();
        error_log("Check-in fatal error: " . $errorMsg);
        error_log("Check-in fatal error trace: " . $e->getTraceAsString());
        $response['done'] = false;
        $response['data'] = "Fatal error during check-in. Please contact support.";
    }
    
    ob_clean();
    echo json_encode($response);
    ob_end_flush();
    exit;
}

if (isset($_POST['check_out_room'])) {
    // Start output buffering
    ob_start();
    header('Content-Type: application/json');
    
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $remaining_amount = isset($_POST['remaining_amount']) ? floatval($_POST['remaining_amount']) : 0;
    $balance_payment_method = isset($_POST['balance_payment_method']) ? sanitizeInput($_POST['balance_payment_method']) : '';

    $response = ['done' => false, 'data' => ''];
    
    if ($booking_id <= 0) {
        ob_clean();
        $response['data'] = "Invalid booking ID";
        echo json_encode($response);
        exit;
    }
    
    // Validate payment method
    if (!in_array($balance_payment_method, ['cash', 'card'])) {
        ob_clean();
        $response['data'] = "Invalid payment method. Please select cash or card.";
        echo json_encode($response);
        exit;
    }
    
    // Check if advance_payment_method column exists
    $checkAdvanceMethodColumn = mysqli_query($connection, "SHOW COLUMNS FROM booking LIKE 'advance_payment_method'");
    $hasAdvancePaymentMethodColumn = mysqli_num_rows($checkAdvanceMethodColumn) > 0;
    
    $checkBalancePaymentMethodColumn = mysqli_query($connection, "SHOW COLUMNS FROM booking LIKE 'balance_payment_method'");
    $hasBalancePaymentMethodColumn = mysqli_num_rows($checkBalancePaymentMethodColumn) > 0;
    
    $checkPaymentMethodColumn = mysqli_query($connection, "SHOW COLUMNS FROM booking LIKE 'payment_method'");
    $hasPaymentMethodColumn = mysqli_num_rows($checkPaymentMethodColumn) > 0;
    
    // Build query based on available columns
    if ($hasAdvancePaymentMethodColumn) {
        $query = "SELECT booking_id, room_id, remaining_price, advance_payment_method FROM booking WHERE booking_id = ?";
    } else {
        $query = "SELECT booking_id, room_id, remaining_price FROM booking WHERE booking_id = ?";
    }
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        ob_clean();
        $response['data'] = "Database error: " . mysqli_error($connection);
        echo json_encode($response);
        exit;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        ob_clean();
        $response['data'] = "Database error: " . mysqli_stmt_error($stmt);
        echo json_encode($response);
        exit;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result || mysqli_num_rows($result) == 0) {
        mysqli_stmt_close($stmt);
        ob_clean();
        $response['data'] = "Booking not found";
        echo json_encode($response);
        exit;
    }
    
    $booking_details = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    $room_id = $booking_details['room_id'];
    $remaining_price = floatval($booking_details['remaining_price']);
    $advance_payment_method = $hasAdvancePaymentMethodColumn ? ($booking_details['advance_payment_method'] ?? 'none') : 'none';
    
    // Validate full payment (allow small floating point differences)
    if (abs($remaining_price - $remaining_amount) > 0.01) {
        ob_clean();
        $response['data'] = "Please Enter Full Payment. Expected: LKR " . number_format($remaining_price, 2);
        echo json_encode($response);
        exit;
    }

    mysqli_begin_transaction($connection);
    
    try {
        // Determine overall payment method
        // If advance was paid, combine both methods
        $overall_payment_method = $balance_payment_method;
        if ($advance_payment_method != 'none' && $advance_payment_method != '' && !empty($advance_payment_method)) {
            // If both methods are the same, use that method
            // If different, indicate mixed payment
            if ($advance_payment_method == $balance_payment_method) {
                $overall_payment_method = $balance_payment_method;
            } else {
                $overall_payment_method = 'card'; // Default to card if mixed
            }
        }
        
        // Ensure payment method is valid
        if (!in_array($overall_payment_method, ['cash', 'card', 'pending'])) {
            $overall_payment_method = 'card';
        }
        
        // Log for debugging
        error_log("Check-out: booking_id=$booking_id, remaining_price=$remaining_price, balance_method=$balance_payment_method, overall_method=$overall_payment_method");
        
        // Update booking status to 'checked_out' and finalize payment
        // Build UPDATE query based on available columns
        if ($hasBalancePaymentMethodColumn && $hasPaymentMethodColumn) {
            // All columns exist
            $updateBooking = "UPDATE booking 
                              SET status = 'checked_out',
                                  payment_status = 1,
                                  remaining_price = 0,
                                  balance_payment_method = ?,
                                  payment_method = ?
                              WHERE booking_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateBooking);
            if (!$updateStmt) {
                throw new Exception("Failed to prepare booking update: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($updateStmt, "ssi", $balance_payment_method, $overall_payment_method, $booking_id);
        } elseif ($hasPaymentMethodColumn) {
            // Only payment_method exists
            $updateBooking = "UPDATE booking 
                              SET status = 'checked_out',
                                  payment_status = 1,
                                  remaining_price = 0,
                                  payment_method = ?
                              WHERE booking_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateBooking);
            if (!$updateStmt) {
                throw new Exception("Failed to prepare booking update: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($updateStmt, "si", $overall_payment_method, $booking_id);
        } else {
            // Basic columns only
            $updateBooking = "UPDATE booking 
                              SET status = 'checked_out',
                                  payment_status = 1,
                                  remaining_price = 0
                              WHERE booking_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateBooking);
            if (!$updateStmt) {
                throw new Exception("Failed to prepare booking update: " . mysqli_error($connection));
            }
            mysqli_stmt_bind_param($updateStmt, "i", $booking_id);
        }
        if (!mysqli_stmt_execute($updateStmt)) {
            throw new Exception("Failed to update booking");
        }
        mysqli_stmt_close($updateStmt);
        
        // Sync room status based on all active bookings
        syncRoomStatus($connection, $room_id);
        
        mysqli_commit($connection);
        
        $response['done'] = true;
        $response['data'] = "Check-out successful";
        
    } catch (Exception $e) {
        mysqli_rollback($connection);
        $errorMsg = $e->getMessage();
        error_log("Check-out error: " . $errorMsg);
        error_log("Check-out error trace: " . $e->getTraceAsString());
        $response['done'] = false;
        $response['data'] = "Error: " . $errorMsg;
    } catch (Error $e) {
        mysqli_rollback($connection);
        $errorMsg = $e->getMessage();
        error_log("Check-out fatal error: " . $errorMsg);
        error_log("Check-out fatal error trace: " . $e->getTraceAsString());
        $response['done'] = false;
        $response['data'] = "Fatal error during check-out. Please contact support.";
    }

    ob_clean();
    echo json_encode($response);
    ob_end_flush();
    exit;
}


if (isset($_POST['add_employee'])) {
    require_once "includes/security.php";
    require_once "includes/auth.php";
    require_once "includes/audit.php";

    $staff_type_id = isset($_POST['staff_type']) ? intval($_POST['staff_type']) : 0;
    $shift = $_POST['shift'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $name = trim($first_name . ' ' . $last_name);
    $contact_no = $_POST['contact_no'];
    $id_card_id = $_POST['id_card_id'];
    $id_card_no = $_POST['id_card_no'];
    $address = $_POST['address'];
    $salary = $_POST['salary'];
    $create_user_account = isset($_POST['create_user_account']) && $_POST['create_user_account'] == '1';

    $response = array('done' => false, 'success' => false, 'message' => '', 'data' => '');

    // Validate required fields
    if (empty($staff_type_id) || $shift == '' || $salary == '' || empty($first_name) || empty($contact_no)) {
        $response['done'] = false;
        $response['data'] = "Please fill all required fields";
        echo json_encode($response);
        exit;
    }
    
    // Get staff_type name from staff_type table for role mapping
    $staff_type_name = '';
    $staffTypeQuery = "SELECT staff_type FROM staff_type WHERE staff_type_id = ? AND is_active = 1 LIMIT 1";
    $staffTypeStmt = mysqli_prepare($connection, $staffTypeQuery);
    if ($staffTypeStmt) {
        mysqli_stmt_bind_param($staffTypeStmt, "i", $staff_type_id);
        mysqli_stmt_execute($staffTypeStmt);
        $staffTypeResult = mysqli_stmt_get_result($staffTypeStmt);
        if ($staffTypeRow = mysqli_fetch_assoc($staffTypeResult)) {
            $staff_type_name = $staffTypeRow['staff_type'];
        }
        mysqli_stmt_close($staffTypeStmt);
    }
    
    if (empty($staff_type_name)) {
        $response['done'] = false;
        $response['data'] = "Invalid staff type selected";
        echo json_encode($response);
        exit;
    }

    // If creating user account, validate user fields and permissions
    if ($create_user_account) {
        if (!hasRole('super_admin')) {
            $response['done'] = false;
            $response['message'] = 'Access denied. Only super admin can create employee accounts.';
            echo json_encode($response);
            exit;
        }

        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            $response['done'] = false;
            $response['message'] = 'Security token mismatch.';
            echo json_encode($response);
            exit;
        }

        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $branch_id = isset($_POST['branch_id']) && !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;

        // Validate user account fields
        if (empty($username) || empty($email) || empty($password)) {
            $response['done'] = false;
            $response['message'] = 'Please fill all required user account fields.';
            echo json_encode($response);
            exit;
        }

        if ($password !== $confirm_password) {
            $response['done'] = false;
            $response['message'] = 'Passwords do not match.';
            echo json_encode($response);
            exit;
        }

        if (strlen($password) < 8) {
            $response['done'] = false;
            $response['message'] = 'Password must be at least 8 characters long.';
            echo json_encode($response);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['done'] = false;
            $response['message'] = 'Invalid email address.';
            echo json_encode($response);
            exit;
        }

        // Check if email or username exists
        if (emailExists($email)) {
            $response['done'] = false;
            $response['message'] = 'Email already exists.';
            echo json_encode($response);
            exit;
        }

        if (usernameExists($username)) {
            $response['done'] = false;
            $response['message'] = 'Username already exists.';
            echo json_encode($response);
            exit;
        }
    }

    // Parse shift (format: "shift|shift_timing")
    $shift_parts = explode('|', $shift);
    $shift_name = isset($shift_parts[0]) ? mysqli_real_escape_string($connection, trim($shift_parts[0])) : '';
    $shift_timing = isset($shift_parts[1]) ? mysqli_real_escape_string($connection, trim($shift_parts[1])) : '';
    
    if (empty($shift_name) || empty($shift_timing)) {
        $response['done'] = false;
        $response['data'] = "Invalid shift format";
        echo json_encode($response);
        exit;
    }
    
    // Get role_id from roles table based on staff_type
    // Map staff_type to role_name
    $staffTypeToRoleName = [
        'Branch Admin' => 'administrator',
        'Receptionist' => 'receptionist',
        'Housekeeping Attendant' => 'housekeeping_staff',
        'Concierge' => 'concierge'
    ];
    
    $role_id = null;
    if (isset($staffTypeToRoleName[$staff_type])) {
        $role_name = $staffTypeToRoleName[$staff_type];
        $roleQuery = "SELECT role_id FROM roles WHERE role_name = ? AND is_active = 1 LIMIT 1";
        $roleStmt = mysqli_prepare($connection, $roleQuery);
        if ($roleStmt) {
            mysqli_stmt_bind_param($roleStmt, "s", $role_name);
            mysqli_stmt_execute($roleStmt);
            $roleResult = mysqli_stmt_get_result($roleStmt);
            if ($roleRow = mysqli_fetch_assoc($roleResult)) {
                $role_id = $roleRow['role_id'];
            }
            mysqli_stmt_close($roleStmt);
        }
    }
    
    // Insert employee into staff table
    $name_escaped = mysqli_real_escape_string($connection, $name);
    $address_escaped = mysqli_real_escape_string($connection, $address);
    $id_card_no_escaped = mysqli_real_escape_string($connection, $id_card_no);
    
    $staff_sql = "INSERT INTO staff (staff_name, staff_type_id, shift, shift_timing, role_id, id_card_type, id_card_no, address, contact_no, salary" . 
                    ($create_user_account && $branch_id ? ", branch_id" : "") . 
                    ") VALUES ('$name_escaped', '$staff_type_id', '$shift_name', '$shift_timing', " . 
                    ($role_id ? "'$role_id'" : "NULL") . ", '$id_card_id', '$id_card_no_escaped', '$address_escaped', '$contact_no', '$salary'" . 
                    ($create_user_account && $branch_id ? ", '$branch_id'" : "") . ")";
    
    $staff_result = mysqli_query($connection, $staff_sql);
    $staff_id = mysqli_insert_id($connection);
    
    if (!$staff_result || !$staff_id) {
        $response['done'] = false;
        $response['data'] = "Database error: Failed to add employee to staff table";
        echo json_encode($response);
        exit;
    }

    // If creating user account, create it now
    if ($create_user_account) {
        $hashedPassword = hashPassword($password);
        
        $insertQuery = "INSERT INTO user (name, username, email, phone, password, status, created_at) 
                       VALUES (?, ?, ?, ?, ?, 'active', NOW())";
        $insertStmt = mysqli_prepare($connection, $insertQuery);
        mysqli_stmt_bind_param($insertStmt, "sssss", $name, $username, $email, $contact_no, $hashedPassword);
        
        if (mysqli_stmt_execute($insertStmt)) {
            $user_id = mysqli_insert_id($connection);
            
            // Get role_id from staff table to check if super_admin
            $staffQuery = "SELECT role_id FROM staff WHERE emp_id = ?";
            $staffStmt = mysqli_prepare($connection, $staffQuery);
            mysqli_stmt_bind_param($staffStmt, "i", $staff_id);
            mysqli_stmt_execute($staffStmt);
            $staffResult = mysqli_stmt_get_result($staffStmt);
            $staff = mysqli_fetch_assoc($staffResult);
            mysqli_stmt_close($staffStmt);
            
            // Check if role is super_admin - if so, set branch_id to NULL
            $isSuperAdmin = false;
            if ($staff && $staff['role_id']) {
                $roleCheckQuery = "SELECT role_name FROM roles WHERE role_id = ? AND role_name = 'super_admin' LIMIT 1";
                $roleCheckStmt = mysqli_prepare($connection, $roleCheckQuery);
                mysqli_stmt_bind_param($roleCheckStmt, "i", $staff['role_id']);
                mysqli_stmt_execute($roleCheckStmt);
                $roleCheckResult = mysqli_stmt_get_result($roleCheckStmt);
                $isSuperAdmin = mysqli_num_rows($roleCheckResult) > 0;
                mysqli_stmt_close($roleCheckStmt);
            }
            
            // Link staff to user and update branch if provided (set to NULL for super_admin)
            $finalBranchId = $isSuperAdmin ? null : $branch_id;
            if ($finalBranchId) {
                $updateStaffQuery = "UPDATE staff SET user_id = ?, branch_id = ? WHERE emp_id = ?";
                $updateStaffStmt = mysqli_prepare($connection, $updateStaffQuery);
                mysqli_stmt_bind_param($updateStaffStmt, "iii", $user_id, $finalBranchId, $staff_id);
            } else {
                $updateStaffQuery = "UPDATE staff SET user_id = ?, branch_id = NULL WHERE emp_id = ?";
                $updateStaffStmt = mysqli_prepare($connection, $updateStaffQuery);
                mysqli_stmt_bind_param($updateStaffStmt, "ii", $user_id, $staff_id);
            }
            mysqli_stmt_execute($updateStaffStmt);
            mysqli_stmt_close($updateStaffStmt);
            
            if ($staff && $staff['role_id']) {
                // Check if role already assigned to avoid duplicates
                $checkRoleQuery = "SELECT user_id, role_id FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1";
                $checkRoleStmt = mysqli_prepare($connection, $checkRoleQuery);
                mysqli_stmt_bind_param($checkRoleStmt, "ii", $user_id, $staff['role_id']);
                mysqli_stmt_execute($checkRoleStmt);
                $checkRoleResult = mysqli_stmt_get_result($checkRoleStmt);
                
                if (mysqli_num_rows($checkRoleResult) == 0) {
                    // Assign role from staff table to user_roles table
                    $assignRoleQuery = "INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)";
                    $assignRoleStmt = mysqli_prepare($connection, $assignRoleQuery);
                    $assigned_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
                    mysqli_stmt_bind_param($assignRoleStmt, "iii", $user_id, $staff['role_id'], $assigned_by);
                    mysqli_stmt_execute($assignRoleStmt);
                    mysqli_stmt_close($assignRoleStmt);
                }
                mysqli_stmt_close($checkRoleStmt);
            }
            
            // Log audit event
            if (function_exists('logAuditEvent')) {
                logAuditEvent('employee_user_created', 'users', 'user', $user_id, null, [
                    'emp_id' => $staff_id,
                    'staff_type_id' => $staff_type_id,
                    'staff_type' => $staff_type_name,
                    'username' => $username,
                    'branch_id' => $finalBranchId
                ]);
            }
            
            mysqli_stmt_close($insertStmt);
            $response['done'] = true;
            $response['success'] = true;
            $response['message'] = 'Staff and user account created successfully! Username: ' . htmlspecialchars($username);
            $response['data'] = 'Successfully Added';
        } else {
            mysqli_stmt_close($insertStmt);
            $response['done'] = false;
            $response['message'] = 'Error creating user account.';
            $response['data'] = 'Employee added but user account creation failed';
        }
    } else {
        $response['done'] = true;
        $response['success'] = true;
        $response['data'] = 'Employee Successfully Added';
    }
    
    echo json_encode($response);
}

// Update Employee (with optional user account creation)
if (isset($_POST['update_employee'])) {
    require_once "includes/security.php";
    require_once "includes/auth.php";
    require_once "includes/audit.php";

    $staff_id = intval($_POST['emp_id']);
    $staff_type_id = isset($_POST['staff_type']) ? intval($_POST['staff_type']) : 0;
    $shift = isset($_POST['shift']) ? sanitizeInput($_POST['shift']) : '';
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $name = trim($first_name . ' ' . $last_name);
    $id_card_type = sanitizeInput($_POST['id_card_type']);
    $id_card_no = sanitizeInput($_POST['id_card_no']);
    $address = sanitizeInput($_POST['address']);
    $contact_no = sanitizeInput($_POST['contact_no']);
    $salary = floatval($_POST['salary']);
    $branch_id = isset($_POST['branch_id']) && !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
    $create_user_account = isset($_POST['create_user_account']) && $_POST['create_user_account'] == '1';

    $response = array('done' => false, 'success' => false, 'message' => '', 'data' => '');

    // Parse shift (format: "shift|shift_timing")
    $shift_parts = explode('|', $shift);
    $shift_name = isset($shift_parts[0]) ? mysqli_real_escape_string($connection, trim($shift_parts[0])) : '';
    $shift_timing = isset($shift_parts[1]) ? mysqli_real_escape_string($connection, trim($shift_parts[1])) : '';

    // Get staff_type name from staff_type table for role mapping
    $staff_type_name = '';
    $staffTypeQuery = "SELECT staff_type FROM staff_type WHERE staff_type_id = ? AND is_active = 1 LIMIT 1";
    $staffTypeStmt = mysqli_prepare($connection, $staffTypeQuery);
    if ($staffTypeStmt) {
        mysqli_stmt_bind_param($staffTypeStmt, "i", $staff_type_id);
        mysqli_stmt_execute($staffTypeStmt);
        $staffTypeResult = mysqli_stmt_get_result($staffTypeStmt);
        if ($staffTypeRow = mysqli_fetch_assoc($staffTypeResult)) {
            $staff_type_name = $staffTypeRow['staff_type'];
        }
        mysqli_stmt_close($staffTypeStmt);
    }
    
    // Get role_id from roles table based on staff_type
    // Map staff_type to role_name
    $staffTypeToRoleName = [
        'Branch Admin' => 'administrator',
        'Receptionist' => 'receptionist',
        'Housekeeping Attendant' => 'housekeeping_staff',
        'Concierge' => 'concierge'
    ];
    
    $role_id = null;
    if (!empty($staff_type_name) && isset($staffTypeToRoleName[$staff_type_name])) {
        $role_name = $staffTypeToRoleName[$staff_type_name];
        $roleQuery = "SELECT role_id FROM roles WHERE role_name = ? AND is_active = 1 LIMIT 1";
        $roleStmt = mysqli_prepare($connection, $roleQuery);
        if ($roleStmt) {
            mysqli_stmt_bind_param($roleStmt, "s", $role_name);
            mysqli_stmt_execute($roleStmt);
            $roleResult = mysqli_stmt_get_result($roleStmt);
            if ($roleRow = mysqli_fetch_assoc($roleResult)) {
                $role_id = $roleRow['role_id'];
            }
            mysqli_stmt_close($roleStmt);
        }
    }

    // Validate required fields
    if (empty($staff_id) || empty($staff_type_id) || empty($shift_name) || empty($shift_timing) || empty($first_name) || empty($contact_no) || empty($salary)) {
        $response['done'] = false;
        $response['message'] = "Please fill all required fields";
        echo json_encode($response);
        exit;
    }
    
    if (empty($staff_type_name)) {
        $response['done'] = false;
        $response['message'] = "Invalid staff type selected";
        echo json_encode($response);
        exit;
    }

    // If creating user account, validate user fields and permissions
    if ($create_user_account) {
        if (!hasRole('super_admin')) {
            $response['done'] = false;
            $response['message'] = 'Access denied. Only super admin can create employee accounts.';
            echo json_encode($response);
            exit;
        }

        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            $response['done'] = false;
            $response['message'] = 'Security token mismatch.';
            echo json_encode($response);
            exit;
        }

        // Check if employee already has a user account
        $checkUserQuery = "SELECT user_id FROM staff WHERE staff_id = ?";
        $checkUserStmt = mysqli_prepare($connection, $checkUserQuery);
        mysqli_stmt_bind_param($checkUserStmt, "i", $staff_id);
        mysqli_stmt_execute($checkUserStmt);
        $checkUserResult = mysqli_stmt_get_result($checkUserStmt);
        $checkUser = mysqli_fetch_assoc($checkUserResult);
        mysqli_stmt_close($checkUserStmt);

        if ($checkUser && $checkUser['user_id']) {
            $response['done'] = false;
            $response['message'] = 'This employee already has a user account.';
            echo json_encode($response);
            exit;
        }

        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        // Use branch_id from user account section if provided, otherwise use from main form
        $userAccountBranchId = isset($_POST['branch_id']) && !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
        if ($userAccountBranchId !== null) {
            $branch_id = $userAccountBranchId;
        }

        // Validate user account fields
        if (empty($username) || empty($email) || empty($password)) {
            $response['done'] = false;
            $response['message'] = 'Please fill all required user account fields.';
            echo json_encode($response);
            exit;
        }

        if ($password !== $confirm_password) {
            $response['done'] = false;
            $response['message'] = 'Passwords do not match.';
            echo json_encode($response);
            exit;
        }

        if (strlen($password) < 8) {
            $response['done'] = false;
            $response['message'] = 'Password must be at least 8 characters long.';
            echo json_encode($response);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['done'] = false;
            $response['message'] = 'Invalid email address.';
            echo json_encode($response);
            exit;
        }

        // Check if email or username exists
        if (emailExists($email)) {
            $response['done'] = false;
            $response['message'] = 'Email already exists.';
            echo json_encode($response);
            exit;
        }

        if (usernameExists($username)) {
            $response['done'] = false;
            $response['message'] = 'Username already exists.';
            echo json_encode($response);
            exit;
        }
    }

    // Update employee
    $name_escaped = mysqli_real_escape_string($connection, $name);
    $address_escaped = mysqli_real_escape_string($connection, $address);
    $id_card_no_escaped = mysqli_real_escape_string($connection, $id_card_no);
    
    $updateQuery = "UPDATE staff SET 
                    emp_name = '$name_escaped',
                    staff_type_id = '$staff_type_id',
                    shift = '$shift_name',
                    shift_timing = '$shift_timing',
                    role_id = " . ($role_id ? "'$role_id'" : "NULL") . ",
                    id_card_type = '$id_card_type',
                    id_card_no = '$id_card_no_escaped',
                    address = '$address_escaped',
                    contact_no = '$contact_no',
                    salary = '$salary'";
    
    // Check if role is super_admin - if so, set branch_id to NULL
    $isSuperAdmin = false;
    if ($role_id) {
        $roleCheckQuery = "SELECT role_name FROM roles WHERE role_id = ? AND role_name = 'super_admin' LIMIT 1";
        $roleCheckStmt = mysqli_prepare($connection, $roleCheckQuery);
        mysqli_stmt_bind_param($roleCheckStmt, "i", $role_id);
        mysqli_stmt_execute($roleCheckStmt);
        $roleCheckResult = mysqli_stmt_get_result($roleCheckStmt);
        $isSuperAdmin = mysqli_num_rows($roleCheckResult) > 0;
        mysqli_stmt_close($roleCheckStmt);
    }
    
    // Update branch_id if provided (whether creating user account or not)
    // Set to NULL for super_admin
    if ($isSuperAdmin) {
        $updateQuery .= ", branch_id = NULL";
    } elseif ($branch_id !== null) {
        $updateQuery .= ", branch_id = '$branch_id'";
    } else {
        // If branch_id is empty string, set it to NULL
        $updateQuery .= ", branch_id = NULL";
    }
    
    $updateQuery .= " WHERE staff_id = '$staff_id'";
    
    $updateResult = mysqli_query($connection, $updateQuery);

    if (!$updateResult) {
        $response['done'] = false;
        $response['message'] = "Database error: Failed to update employee";
        echo json_encode($response);
        exit;
    }

    // If creating user account, create it now
    if ($create_user_account) {
        $hashedPassword = hashPassword($password);
        
        $insertQuery = "INSERT INTO user (name, username, email, phone, password, status, created_at) 
                       VALUES (?, ?, ?, ?, ?, 'active', NOW())";
        $insertStmt = mysqli_prepare($connection, $insertQuery);
        mysqli_stmt_bind_param($insertStmt, "sssss", $name, $username, $email, $contact_no, $hashedPassword);
        
        if (mysqli_stmt_execute($insertStmt)) {
            $user_id = mysqli_insert_id($connection);
            
            // Get role_id from staff table to check if super_admin
            $staffQuery = "SELECT role_id FROM staff WHERE emp_id = ?";
            $staffStmt = mysqli_prepare($connection, $staffQuery);
            mysqli_stmt_bind_param($staffStmt, "i", $staff_id);
            mysqli_stmt_execute($staffStmt);
            $staffResult = mysqli_stmt_get_result($staffStmt);
            $staff = mysqli_fetch_assoc($staffResult);
            mysqli_stmt_close($staffStmt);
            
            // Check if role is super_admin - if so, set branch_id to NULL
            $isSuperAdmin = false;
            if ($staff && $staff['role_id']) {
                $roleCheckQuery = "SELECT role_name FROM roles WHERE role_id = ? AND role_name = 'super_admin' LIMIT 1";
                $roleCheckStmt = mysqli_prepare($connection, $roleCheckQuery);
                mysqli_stmt_bind_param($roleCheckStmt, "i", $staff['role_id']);
                mysqli_stmt_execute($roleCheckStmt);
                $roleCheckResult = mysqli_stmt_get_result($roleCheckStmt);
                $isSuperAdmin = mysqli_num_rows($roleCheckResult) > 0;
                mysqli_stmt_close($roleCheckStmt);
            }
            
            // Link staff to user and update branch if provided (set to NULL for super_admin)
            $finalBranchId = $isSuperAdmin ? null : $branch_id;
            if ($finalBranchId) {
                $updateStaffQuery = "UPDATE staff SET user_id = ?, branch_id = ? WHERE emp_id = ?";
                $updateStaffStmt = mysqli_prepare($connection, $updateStaffQuery);
                mysqli_stmt_bind_param($updateStaffStmt, "iii", $user_id, $finalBranchId, $staff_id);
            } else {
                $updateStaffQuery = "UPDATE staff SET user_id = ?, branch_id = NULL WHERE emp_id = ?";
                $updateStaffStmt = mysqli_prepare($connection, $updateStaffQuery);
                mysqli_stmt_bind_param($updateStaffStmt, "ii", $user_id, $staff_id);
            }
            mysqli_stmt_execute($updateStaffStmt);
            mysqli_stmt_close($updateStaffStmt);
            
            if ($staff && $staff['role_id']) {
                // Check if role already assigned to avoid duplicates
                $checkRoleQuery = "SELECT user_id, role_id FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1";
                $checkRoleStmt = mysqli_prepare($connection, $checkRoleQuery);
                mysqli_stmt_bind_param($checkRoleStmt, "ii", $user_id, $staff['role_id']);
                mysqli_stmt_execute($checkRoleStmt);
                $checkRoleResult = mysqli_stmt_get_result($checkRoleStmt);
                
                if (mysqli_num_rows($checkRoleResult) == 0) {
                    // Assign role from staff table to user_roles table
                    $assignRoleQuery = "INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)";
                    $assignRoleStmt = mysqli_prepare($connection, $assignRoleQuery);
                    $assigned_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
                    mysqli_stmt_bind_param($assignRoleStmt, "iii", $user_id, $staff['role_id'], $assigned_by);
                    mysqli_stmt_execute($assignRoleStmt);
                    mysqli_stmt_close($assignRoleStmt);
                }
                mysqli_stmt_close($checkRoleStmt);
            }
            
            // Log audit event
            if (function_exists('logAuditEvent')) {
                logAuditEvent('employee_user_created', 'users', 'user', $user_id, null, [
                    'emp_id' => $staff_id,
                    'staff_type_id' => $staff_type_id,
                    'staff_type' => $staff_type_name,
                    'username' => $username,
                    'branch_id' => $finalBranchId
                ]);
            }
            
            mysqli_stmt_close($insertStmt);
            $response['done'] = true;
            $response['success'] = true;
            $response['message'] = 'Staff updated and user account created successfully! Username: ' . htmlspecialchars($username);
            $response['data'] = 'Successfully Updated';
        } else {
            mysqli_stmt_close($insertStmt);
            $response['done'] = false;
            $response['message'] = 'Error creating user account.';
            $response['data'] = 'Employee updated but user account creation failed';
        }
    }
    
    // If updating existing user account
    $update_user_account = isset($_POST['update_user_account']) && $_POST['update_user_account'] == '1';
    if ($update_user_account) {
        if (!hasRole('super_admin')) {
            $response['done'] = false;
            $response['message'] = 'Access denied. Only super admin can update employee accounts.';
            echo json_encode($response);
            exit;
        }

        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            $response['done'] = false;
            $response['message'] = 'Security token mismatch.';
            echo json_encode($response);
            exit;
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $edit_username = isset($_POST['edit_username']) ? sanitizeInput($_POST['edit_username']) : '';
        $edit_email = isset($_POST['edit_email']) ? sanitizeInput($_POST['edit_email']) : '';
        $edit_user_branch_id = isset($_POST['edit_user_branch_id']) && !empty($_POST['edit_user_branch_id']) ? intval($_POST['edit_user_branch_id']) : null;
        $change_password = isset($_POST['change_password']) && $_POST['change_password'] == '1';
        
        if (empty($user_id) || empty($edit_username) || empty($edit_email)) {
            $response['done'] = false;
            $response['message'] = 'Please fill all required user account fields.';
            echo json_encode($response);
            exit;
        }

        // Check if username or email already exists (excluding current user)
        $checkUsernameQuery = "SELECT id FROM user WHERE username = ? AND id != ? LIMIT 1";
        $checkUsernameStmt = mysqli_prepare($connection, $checkUsernameQuery);
        mysqli_stmt_bind_param($checkUsernameStmt, "si", $edit_username, $user_id);
        mysqli_stmt_execute($checkUsernameStmt);
        $checkUsernameResult = mysqli_stmt_get_result($checkUsernameStmt);
        if (mysqli_num_rows($checkUsernameResult) > 0) {
            mysqli_stmt_close($checkUsernameStmt);
            $response['done'] = false;
            $response['message'] = 'Username already exists.';
            echo json_encode($response);
            exit;
        }
        mysqli_stmt_close($checkUsernameStmt);

        $checkEmailQuery = "SELECT id FROM user WHERE email = ? AND id != ? LIMIT 1";
        $checkEmailStmt = mysqli_prepare($connection, $checkEmailQuery);
        mysqli_stmt_bind_param($checkEmailStmt, "si", $edit_email, $user_id);
        mysqli_stmt_execute($checkEmailStmt);
        $checkEmailResult = mysqli_stmt_get_result($checkEmailStmt);
        if (mysqli_num_rows($checkEmailResult) > 0) {
            mysqli_stmt_close($checkEmailStmt);
            $response['done'] = false;
            $response['message'] = 'Email already exists.';
            echo json_encode($response);
            exit;
        }
        mysqli_stmt_close($checkEmailStmt);

        // Update user account
        if ($change_password) {
            $edit_password = $_POST['edit_password'];
            $edit_confirm_password = $_POST['edit_confirm_password'];
            
            if ($edit_password !== $edit_confirm_password) {
                $response['done'] = false;
                $response['message'] = 'Passwords do not match.';
                echo json_encode($response);
                exit;
            }
            
            if (strlen($edit_password) < 8) {
                $response['done'] = false;
                $response['message'] = 'Password must be at least 8 characters long.';
                echo json_encode($response);
                exit;
            }
            
            $hashedPassword = hashPassword($edit_password);
            $updateUserQuery = "UPDATE user SET username = ?, email = ?, phone = ?, password = ?";
            if ($edit_user_branch_id !== null) {
                $updateUserQuery .= ", branch_id = ?";
            }
            $updateUserQuery .= " WHERE id = ?";
            
            $updateUserStmt = mysqli_prepare($connection, $updateUserQuery);
            if ($edit_user_branch_id !== null) {
                mysqli_stmt_bind_param($updateUserStmt, "ssssii", $edit_username, $edit_email, $contact_no, $hashedPassword, $edit_user_branch_id, $user_id);
            } else {
                mysqli_stmt_bind_param($updateUserStmt, "ssssi", $edit_username, $edit_email, $contact_no, $hashedPassword, $user_id);
            }
        } else {
            $updateUserQuery = "UPDATE user SET username = ?, email = ?, phone = ?";
            if ($edit_user_branch_id !== null) {
                $updateUserQuery .= ", branch_id = ?";
            }
            $updateUserQuery .= " WHERE id = ?";
            
            $updateUserStmt = mysqli_prepare($connection, $updateUserQuery);
            if ($edit_user_branch_id !== null) {
                mysqli_stmt_bind_param($updateUserStmt, "sssii", $edit_username, $edit_email, $contact_no, $edit_user_branch_id, $user_id);
            } else {
                mysqli_stmt_bind_param($updateUserStmt, "sssi", $edit_username, $edit_email, $contact_no, $user_id);
            }
        }
        
        if (mysqli_stmt_execute($updateUserStmt)) {
            // Check if user has super_admin role - if so, set branch_id to NULL
            $isSuperAdmin = false;
            $checkSuperAdminQuery = "SELECT COUNT(*) as count FROM user_roles ur 
                                     INNER JOIN roles r ON ur.role_id = r.role_id 
                                     WHERE ur.user_id = ? AND r.role_name = 'super_admin'";
            $checkSuperAdminStmt = mysqli_prepare($connection, $checkSuperAdminQuery);
            mysqli_stmt_bind_param($checkSuperAdminStmt, "i", $user_id);
            mysqli_stmt_execute($checkSuperAdminStmt);
            $checkSuperAdminResult = mysqli_stmt_get_result($checkSuperAdminStmt);
            if ($checkSuperAdminRow = mysqli_fetch_assoc($checkSuperAdminResult)) {
                $isSuperAdmin = $checkSuperAdminRow['count'] > 0;
            }
            mysqli_stmt_close($checkSuperAdminStmt);
            
            // Update staff branch_id - set to NULL for super_admin
            $finalBranchId = $isSuperAdmin ? null : $edit_user_branch_id;
            $updateStaffBranchQuery = "UPDATE staff SET branch_id = ? WHERE user_id = ?";
            $updateStaffBranchStmt = mysqli_prepare($connection, $updateStaffBranchQuery);
            mysqli_stmt_bind_param($updateStaffBranchStmt, "ii", $finalBranchId, $user_id);
            mysqli_stmt_execute($updateStaffBranchStmt);
            mysqli_stmt_close($updateStaffBranchStmt);
            
            // Log audit event
            if (function_exists('logAuditEvent')) {
                logAuditEvent('employee_user_updated', 'users', 'user', $user_id, null, [
                    'staff_id' => $staff_id,
                    'username' => $edit_username,
                    'email' => $edit_email,
                    'branch_id' => $edit_user_branch_id,
                    'password_changed' => $change_password
                ]);
            }
            
            mysqli_stmt_close($updateUserStmt);
            $response['done'] = true;
            $response['success'] = true;
            $response['message'] = 'Staff and user account updated successfully!';
            $response['data'] = 'Successfully Updated';
        } else {
            mysqli_stmt_close($updateUserStmt);
            $response['done'] = false;
            $response['message'] = 'Error updating user account.';
            $response['data'] = 'Employee updated but user account update failed';
        }
    } else {
        $response['done'] = true;
        $response['success'] = true;
        $response['message'] = 'Employee Successfully Updated';
        $response['data'] = 'Successfully Updated';
    }
    
    echo json_encode($response);
}

// ============================================
// SERVICE REQUEST ENDPOINTS
// ============================================

// Create Service Request
if (isset($_POST['action']) && $_POST['action'] == 'create_service_request') {
    // Set JSON header first to ensure proper response format
    header('Content-Type: application/json');
    
    // Start output buffering to catch any errors/warnings
    ob_start();
    
    require_once 'includes/auth.php';
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit();
    }
    
    // Verify user is logged in (either staff or guest)
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $guest_id = isset($_SESSION['guest_id']) ? $_SESSION['guest_id'] : null;
    
    if (!$user_id && !$guest_id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in again.']);
        exit();
    }
    
    // Debug logging (remove in production)
    error_log("Service Request - user_id: " . ($user_id ?? 'null') . ", guest_id: " . ($guest_id ?? 'null'));
    
    $service_type_id = intval($_POST['service_type_id']);
    $request_title = sanitizeInput($_POST['request_title']);
    $request_description = sanitizeInput($_POST['request_description']);
    $priority = sanitizeInput($_POST['priority']);
    $booking_id = isset($_POST['booking_id']) && !empty($_POST['booking_id']) ? intval($_POST['booking_id']) : null;
    
    // Validate inputs
    if (empty($service_type_id) || empty($request_title) || empty($request_description)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        exit();
    }
    
    // If guest is logged in, use their guest_id directly
    if ($guest_id) {
        // Guest is logged in - use their guest_id from session
        // Verify booking belongs to guest if provided
        if ($booking_id) {
            $bookingVerifyQuery = "SELECT booking_id FROM booking WHERE booking_id = ? AND guest_id = ?";
            $bookingVerifyStmt = mysqli_prepare($connection, $bookingVerifyQuery);
            mysqli_stmt_bind_param($bookingVerifyStmt, "ii", $booking_id, $guest_id);
            mysqli_stmt_execute($bookingVerifyStmt);
            $bookingVerifyResult = mysqli_stmt_get_result($bookingVerifyStmt);
            if (mysqli_num_rows($bookingVerifyResult) == 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid booking']);
                exit();
            }
            mysqli_stmt_close($bookingVerifyStmt);
        }
    } else {
        // Staff user - get guest ID from booking or user's email
        $guest_id = null;
        
        if ($booking_id) {
            $bookingQuery = "SELECT guest_id FROM booking WHERE booking_id = ? LIMIT 1";
            $bookingStmt = mysqli_prepare($connection, $bookingQuery);
            mysqli_stmt_bind_param($bookingStmt, "i", $booking_id);
            mysqli_stmt_execute($bookingStmt);
            $bookingResult = mysqli_stmt_get_result($bookingStmt);
            $booking = mysqli_fetch_assoc($bookingResult);
            if ($booking) {
                $guest_id = $booking['guest_id'];
            }
            mysqli_stmt_close($bookingStmt);
        }
        
        // If no guest_id found, try to get from user's email (for backward compatibility)
        if (!$guest_id) {
            $userQuery = "SELECT email FROM user WHERE id = ? LIMIT 1";
            $userStmt = mysqli_prepare($connection, $userQuery);
            mysqli_stmt_bind_param($userStmt, "i", $user_id);
            mysqli_stmt_execute($userStmt);
            $userResult = mysqli_stmt_get_result($userStmt);
            $user = mysqli_fetch_assoc($userResult);
            if ($user) {
                $guestQuery = "SELECT guest_id FROM guests WHERE email = ? LIMIT 1";
                $guestStmt = mysqli_prepare($connection, $guestQuery);
                mysqli_stmt_bind_param($guestStmt, "s", $user['email']);
                mysqli_stmt_execute($guestStmt);
                $guestResult = mysqli_stmt_get_result($guestStmt);
                $guest = mysqli_fetch_assoc($guestResult);
                if ($guest) {
                    $guest_id = $guest['guest_id'];
                }
                mysqli_stmt_close($guestStmt);
            }
            mysqli_stmt_close($userStmt);
        }
        
        if (!$guest_id) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Guest record not found. Please make a booking first.']);
            exit();
        }
        
        // Verify booking belongs to guest if provided
        if ($booking_id) {
            $bookingVerifyQuery = "SELECT booking_id FROM booking WHERE booking_id = ? AND guest_id = ?";
            $bookingVerifyStmt = mysqli_prepare($connection, $bookingVerifyQuery);
            mysqli_stmt_bind_param($bookingVerifyStmt, "ii", $booking_id, $guest_id);
            mysqli_stmt_execute($bookingVerifyStmt);
            $bookingVerifyResult = mysqli_stmt_get_result($bookingVerifyStmt);
            if (mysqli_num_rows($bookingVerifyResult) == 0) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Invalid booking']);
                exit();
            }
            mysqli_stmt_close($bookingVerifyStmt);
        }
    }
    
    // Get guest's branch_id from booking's room (guests and booking tables don't have branch_id)
    // Branch is stored in room table, so we join booking -> room to get branch_id
    $guest_branch_id = null;
    
    // First try to get from the booking_id if provided
    if ($booking_id) {
        $bookingBranchQuery = "SELECT r.branch_id FROM booking b
                               INNER JOIN room r ON b.room_id = r.room_id
                               WHERE b.booking_id = ? AND b.guest_id = ? LIMIT 1";
        $bookingBranchStmt = mysqli_prepare($connection, $bookingBranchQuery);
        mysqli_stmt_bind_param($bookingBranchStmt, "ii", $booking_id, $guest_id);
        mysqli_stmt_execute($bookingBranchStmt);
        $bookingBranchResult = mysqli_stmt_get_result($bookingBranchStmt);
        if ($bookingBranch = mysqli_fetch_assoc($bookingBranchResult)) {
            $guest_branch_id = $bookingBranch['branch_id'];
        }
        mysqli_stmt_close($bookingBranchStmt);
    }
    
    // If no branch from booking, try to get from most recent active booking
    if (!$guest_branch_id) {
        $recentBookingQuery = "SELECT r.branch_id FROM booking b
                               INNER JOIN room r ON b.room_id = r.room_id
                               WHERE b.guest_id = ? AND b.payment_status = 0
                               ORDER BY b.booking_id DESC LIMIT 1";
        $recentBookingStmt = mysqli_prepare($connection, $recentBookingQuery);
        mysqli_stmt_bind_param($recentBookingStmt, "i", $guest_id);
        mysqli_stmt_execute($recentBookingStmt);
        $recentBookingResult = mysqli_stmt_get_result($recentBookingStmt);
        if ($recentBooking = mysqli_fetch_assoc($recentBookingResult)) {
            $guest_branch_id = $recentBooking['branch_id'];
        }
        mysqli_stmt_close($recentBookingStmt);
    }
    
    // Verify service type exists and is available for guest's branch
    $serviceTypeQuery = "SELECT service_type_id, branch_id, is_active FROM service_types WHERE service_type_id = ?";
    $serviceTypeStmt = mysqli_prepare($connection, $serviceTypeQuery);
    mysqli_stmt_bind_param($serviceTypeStmt, "i", $service_type_id);
    mysqli_stmt_execute($serviceTypeStmt);
    $serviceTypeResult = mysqli_stmt_get_result($serviceTypeStmt);
    $serviceType = mysqli_fetch_assoc($serviceTypeResult);
    mysqli_stmt_close($serviceTypeStmt);
    
    if (!$serviceType) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid service type selected']);
        exit();
    }
    
    if ($serviceType['is_active'] != 1) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'This service type is currently inactive']);
        exit();
    }
    
    // Check if service type is available for guest's branch
    // Service is available if: branch_id IS NULL (global) OR branch_id matches guest's branch
    // If guest has no branch (no booking), only allow global services (branch_id IS NULL)
    if ($serviceType['branch_id'] !== null) {
        if ($guest_branch_id === null) {
            // Guest has no booking/branch - only allow global services
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'This service type is not available. Please make a booking first.']);
            exit();
        } elseif ($serviceType['branch_id'] != $guest_branch_id) {
            // Service is branch-specific but doesn't match guest's branch
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'This service type is not available for your branch']);
            exit();
        }
    }
    
    // Insert service request
    // Handle NULL booking_id properly
    if ($booking_id === null || $booking_id === '') {
        $insertQuery = "INSERT INTO service_requests (guest_id, booking_id, service_type_id, 
                       request_title, request_description, priority, status) 
                       VALUES (?, NULL, ?, ?, ?, ?, 'pending')";
        $insertStmt = mysqli_prepare($connection, $insertQuery);
        if (!$insertStmt) {
            error_log("Service request prepare failed: " . mysqli_error($connection));
            echo json_encode(['success' => false, 'message' => 'Error preparing query: ' . mysqli_error($connection)]);
            exit();
        }
        mysqli_stmt_bind_param($insertStmt, "iisss", $guest_id, $service_type_id, 
                             $request_title, $request_description, $priority);
    } else {
        $insertQuery = "INSERT INTO service_requests (guest_id, booking_id, service_type_id, 
                       request_title, request_description, priority, status) 
                       VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $insertStmt = mysqli_prepare($connection, $insertQuery);
        if (!$insertStmt) {
            error_log("Service request prepare failed: " . mysqli_error($connection));
            echo json_encode(['success' => false, 'message' => 'Error preparing query: ' . mysqli_error($connection)]);
            exit();
        }
        mysqli_stmt_bind_param($insertStmt, "iiisss", $guest_id, $booking_id, $service_type_id, 
                             $request_title, $request_description, $priority);
    }
    
    // Clear any output before sending JSON
    ob_end_clean();
    
    if (mysqli_stmt_execute($insertStmt)) {
        $request_id = mysqli_insert_id($connection);
        
        // Log service request creation (only if user_id exists for staff)
        if ($user_id && function_exists('logSecurityEvent')) {
            try {
                logSecurityEvent('service_request_created', [
                    'request_id' => $request_id,
                    'user_id' => $user_id,
                    'service_type_id' => $service_type_id
                ]);
            } catch (Exception $e) {
                error_log("Failed to log service request: " . $e->getMessage());
            }
        } elseif ($guest_id && function_exists('logSecurityEvent')) {
            // For guests, log with guest_id
            try {
                logSecurityEvent('service_request_created', [
                    'request_id' => $request_id,
                    'guest_id' => $guest_id,
                    'service_type_id' => $service_type_id
                ]);
            } catch (Exception $e) {
                error_log("Failed to log service request: " . $e->getMessage());
            }
        }
        
        // AUTO-ASSIGN: Automatically route request to appropriate staff based on category
        $auto_assigned = false;
        if (file_exists('includes/service_routing.php')) {
            require_once 'includes/service_routing.php';
            if (function_exists('autoAssignServiceRequest')) {
                try {
                    error_log("Attempting auto-assignment for request_id: $request_id");
                    $auto_assigned = autoAssignServiceRequest($connection, $request_id);
                    if ($auto_assigned) {
                        error_log("Auto-assignment successful for request_id: $request_id");
                    } else {
                        error_log("Auto-assignment failed for request_id: $request_id - no eligible staff found or assignment failed");
                    }
                } catch (Exception $e) {
                    error_log("Auto-assign exception for request_id $request_id: " . $e->getMessage());
                    error_log("Stack trace: " . $e->getTraceAsString());
                }
            } else {
                error_log("autoAssignServiceRequest function not found in service_routing.php");
            }
        } else {
            error_log("service_routing.php file not found");
        }
        
        // Send service request notification (if function exists)
        if (file_exists('includes/email.php')) {
            require_once 'includes/email.php';
            if (function_exists('sendServiceRequestNotification')) {
                try {
                    sendServiceRequestNotification($request_id, 'created');
                } catch (Exception $e) {
                    error_log("Notification send failed: " . $e->getMessage());
                }
            }
        }
        
        $message = 'Service request submitted successfully! Request ID: #' . $request_id;
        if ($auto_assigned) {
            $message .= ' Request has been automatically routed to the appropriate staff member.';
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'request_id' => $request_id,
            'auto_assigned' => $auto_assigned
        ]);
    } else {
        $error = mysqli_stmt_error($insertStmt);
        $mysql_error = mysqli_error($connection);
        error_log("Service request insert failed: " . $error . " | MySQL Error: " . $mysql_error . " | Query: " . $insertQuery);
        
        // Provide user-friendly error message
        $error_message = 'Error creating service request.';
        if (strpos($error, 'foreign key') !== false) {
            $error_message = 'Invalid service type or guest information. Please try again.';
        } elseif (strpos($error, 'Duplicate entry') !== false) {
            $error_message = 'This service request already exists.';
        }
        
        echo json_encode([
            'success' => false, 
            'message' => $error_message
        ]);
    }
    
    mysqli_stmt_close($insertStmt);
    exit();
}

// Get Service Request Details
if (isset($_POST['action']) && $_POST['action'] == 'get_service_request_details') {
    require_once 'includes/auth.php';
    
    // Verify user is logged in (either staff or guest)
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $guest_id = isset($_SESSION['guest_id']) ? $_SESSION['guest_id'] : null;
    
    if (!$user_id && !$guest_id) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    $request_id = intval($_POST['request_id']);
    
    // Get request details
    $query = "SELECT sr.*, st.service_name, g.name as customer_name, 
             s.staff_name as assigned_staff_name
             FROM service_requests sr
             JOIN service_types st ON sr.service_type_id = st.service_type_id
             JOIN guests g ON sr.guest_id = g.guest_id
             LEFT JOIN staff s ON sr.assigned_to = s.staff_id
             WHERE sr.request_id = ?";
    
    // If guest is logged in, ensure they can only view their own requests
    if ($guest_id) {
        $query .= " AND sr.guest_id = ?";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "ii", $request_id, $guest_id);
    } else {
        // Staff can view any request
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "i", $request_id);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($request = mysqli_fetch_assoc($result)) {
        echo json_encode([
            'success' => true,
            'request_id' => $request['request_id'],
            'service_name' => $request['service_name'],
            'request_title' => $request['request_title'],
            'request_description' => $request['request_description'],
            'priority' => $request['priority'],
            'status' => $request['status'],
            'customer_name' => $request['customer_name'],
            'assigned_staff' => $request['assigned_staff_name'],
            'requested_at' => date('M j, Y g:i A', strtotime($request['requested_at'])),
            'notes' => $request['notes']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Service request not found']);
    }
    
    mysqli_stmt_close($stmt);
    exit();
}

// Cancel Service Request
if (isset($_POST['action']) && $_POST['action'] == 'cancel_service_request') {
    require_once 'includes/auth.php';
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit();
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    $request_id = intval($_POST['request_id']);
    $user_id = $_SESSION['user_id'];
    
    // Verify request belongs to user
    $verifyQuery = "SELECT sr.request_id FROM service_requests sr
                    JOIN guests g ON sr.guest_id = g.guest_id
                    WHERE sr.request_id = ? 
                    AND g.email = (SELECT email FROM user WHERE id = ?)
                    AND sr.status = 'pending'";
    $verifyStmt = mysqli_prepare($connection, $verifyQuery);
    mysqli_stmt_bind_param($verifyStmt, "ii", $request_id, $user_id);
    mysqli_stmt_execute($verifyStmt);
    $verifyResult = mysqli_stmt_get_result($verifyStmt);
    
    if (mysqli_num_rows($verifyResult) == 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found or cannot be cancelled']);
        exit();
    }
    
    // Update status to cancelled
    $updateQuery = "UPDATE service_requests SET status = 'cancelled' WHERE request_id = ?";
    $updateStmt = mysqli_prepare($connection, $updateQuery);
    mysqli_stmt_bind_param($updateStmt, "i", $request_id);
    
    if (mysqli_stmt_execute($updateStmt)) {
        logSecurityEvent('service_request_cancelled', [
            'request_id' => $request_id,
            'user_id' => $user_id
        ]);
        echo json_encode(['success' => true, 'message' => 'Service request cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error cancelling request']);
    }
    
    mysqli_stmt_close($updateStmt);
    exit();
}

// Assign Service Request (Staff/Admin)
if (isset($_POST['action']) && $_POST['action'] == 'assign_service_request') {
    require_once 'includes/auth.php';
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit();
    }
    
    if (!hasPermission('service.assign')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
    
    $request_id = intval($_POST['request_id']);
    $staff_id = intval($_POST['staff_id']);
    
    // Verify request exists, is pending, and references valid tables (service_requests, guests, service_types, booking)
    $verifyQuery = "SELECT sr.request_id, sr.guest_id, sr.service_type_id, sr.booking_id
                   FROM service_requests sr
                   INNER JOIN guests g ON sr.guest_id = g.guest_id
                   INNER JOIN service_types st ON sr.service_type_id = st.service_type_id
                   LEFT JOIN booking b ON sr.booking_id = b.booking_id
                   WHERE sr.request_id = ? AND sr.status = 'pending'";
    $verifyStmt = mysqli_prepare($connection, $verifyQuery);
    mysqli_stmt_bind_param($verifyStmt, "i", $request_id);
    mysqli_stmt_execute($verifyStmt);
    $verifyResult = mysqli_stmt_get_result($verifyStmt);
    
    if (mysqli_num_rows($verifyResult) == 0) {
        echo json_encode(['success' => false, 'message' => 'Request not found, already assigned, or invalid reference to guest/service type']);
        exit();
    }
    
    // Verify staff exists
    $staffQuery = "SELECT staff_id FROM staff WHERE staff_id = ?";
    $staffStmt = mysqli_prepare($connection, $staffQuery);
    mysqli_stmt_bind_param($staffStmt, "i", $staff_id);
    mysqli_stmt_execute($staffStmt);
    $staffResult = mysqli_stmt_get_result($staffStmt);
    
    if (mysqli_num_rows($staffResult) == 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid staff member selected']);
        mysqli_stmt_close($staffStmt);
        exit();
    }
    mysqli_stmt_close($staffStmt);
    
    // Update request
    $updateQuery = "UPDATE service_requests SET assigned_to = ?, status = 'assigned', assigned_at = NOW() WHERE request_id = ?";
    $updateStmt = mysqli_prepare($connection, $updateQuery);
    mysqli_stmt_bind_param($updateStmt, "ii", $staff_id, $request_id);
    
    if (mysqli_stmt_execute($updateStmt)) {
        logSecurityEvent('service_request_assigned', [
            'request_id' => $request_id,
            'staff_id' => $staff_id
        ]);
        
        // Send notification
        require_once 'includes/email.php';
        sendServiceRequestNotification($request_id, 'assigned');
        
        echo json_encode(['success' => true, 'message' => 'Service request assigned successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error assigning request']);
    }
    
    mysqli_stmt_close($updateStmt);
    exit();
}

// Update Service Request Status (Staff)
if (isset($_POST['action']) && $_POST['action'] == 'update_service_status') {
    require_once 'includes/auth.php';
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit();
    }
    
    if (!hasPermission('service.update')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
    
    $request_id = intval($_POST['request_id']);
    $status = sanitizeInput($_POST['status']);
    $notes = isset($_POST['notes']) ? sanitizeInput($_POST['notes']) : null;
    
    // Validate status
    $validStatuses = ['assigned', 'in_progress', 'completed'];
    if (!in_array($status, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    // Update request
    $updateQuery = "UPDATE service_requests SET status = ?, notes = ?";
    if ($status == 'completed') {
        $updateQuery .= ", completed_at = NOW()";
    }
    $updateQuery .= " WHERE request_id = ?";
    
    $updateStmt = mysqli_prepare($connection, $updateQuery);
    mysqli_stmt_bind_param($updateStmt, "ssi", $status, $notes, $request_id);
    
    if (mysqli_stmt_execute($updateStmt)) {
        logSecurityEvent('service_request_updated', [
            'request_id' => $request_id,
            'status' => $status
        ]);
        
        // Send notification based on status
        require_once 'includes/email.php';
        $notificationType = $status == 'in_progress' ? 'in_progress' : ($status == 'completed' ? 'completed' : 'updated');
        sendServiceRequestNotification($request_id, $notificationType);
        
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating status']);
    }
    
    mysqli_stmt_close($updateStmt);
    exit();
}

// Complete Service Request (Staff)
if (isset($_POST['action']) && $_POST['action'] == 'complete_service_request') {
    require_once 'includes/auth.php';
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit();
    }
    
    if (!hasPermission('service.complete')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
    
    $request_id = intval($_POST['request_id']);
    
    // Update request to completed
    $updateQuery = "UPDATE service_requests SET status = 'completed', completed_at = NOW() WHERE request_id = ?";
    $updateStmt = mysqli_prepare($connection, $updateQuery);
    mysqli_stmt_bind_param($updateStmt, "i", $request_id);
    
    if (mysqli_stmt_execute($updateStmt)) {
        logSecurityEvent('service_request_completed', [
            'request_id' => $request_id
        ]);
        
        // Send completion notification
        require_once 'includes/email.php';
        sendServiceRequestNotification($request_id, 'completed');
        
        echo json_encode(['success' => true, 'message' => 'Service request marked as completed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error completing request']);
    }
    
    mysqli_stmt_close($updateStmt);
    exit();
}

// Get Pending Service Requests (for dropdown)
if (isset($_POST['action']) && $_POST['action'] == 'get_pending_service_requests') {
    require_once 'includes/auth.php';
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    // Get all pending service requests with proper joins to all required tables
    // References: service_requests, guests, booking, service_types tables
    $pendingQuery = "SELECT sr.request_id, sr.request_title, sr.request_description,
                   st.service_name, st.service_type_id,
                   g.name as customer_name, g.contact_no, g.guest_id,
                   b.booking_id,
                   r.room_no, rt.room_type,
                   sr.priority, sr.requested_at
                   FROM service_requests sr
                   INNER JOIN service_types st ON sr.service_type_id = st.service_type_id
                   INNER JOIN guests g ON sr.guest_id = g.guest_id
                   LEFT JOIN booking b ON sr.booking_id = b.booking_id
                   LEFT JOIN room r ON b.room_id = r.room_id
                   LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id
                   WHERE sr.status = 'pending'
                   ORDER BY 
                       CASE sr.priority 
                           WHEN 'urgent' THEN 1 
                           WHEN 'high' THEN 2 
                           WHEN 'normal' THEN 3 
                           WHEN 'low' THEN 4 
                       END,
                       sr.requested_at DESC";
    
    $pendingResult = mysqli_query($connection, $pendingQuery);
    $requests = [];
    
    // Check for query errors
    if (!$pendingResult) {
        $error = mysqli_error($connection);
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: ' . $error,
            'requests' => []
        ]);
        exit();
    }
    
    if (mysqli_num_rows($pendingResult) > 0) {
        while ($pending = mysqli_fetch_assoc($pendingResult)) {
            $roomInfo = $pending['room_no'] ? 
                $pending['room_type'] . ' - Room #' . $pending['room_no'] : 'No Room';
            
            $requests[] = [
                'request_id' => $pending['request_id'],
                'service_name' => htmlspecialchars($pending['service_name']),
                'service_type_id' => $pending['service_type_id'],
                'customer_name' => htmlspecialchars($pending['customer_name']),
                'contact_no' => htmlspecialchars($pending['contact_no'] ?? ''),
                'guest_id' => $pending['guest_id'],
                'booking_id' => $pending['booking_id'],
                'room_info' => htmlspecialchars($roomInfo),
                'priority' => $pending['priority'],
                'request_title' => htmlspecialchars($pending['request_title']),
                'requested_at' => date('M j, Y g:i A', strtotime($pending['requested_at']))
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'requests' => $requests
    ]);
    exit();
}

// Add New Service Type
if (isset($_POST['action']) && $_POST['action'] == 'add_service_type') {
    require_once 'includes/auth.php';
    require_once 'includes/rbac.php';
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit();
    }
    
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    // Check permissions - only super admin and administrators can add service types
    if (!hasRole('super_admin') && !hasRole('administrator')) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to add service types']);
        exit();
    }
    
    // Get and sanitize input
    $service_name = sanitizeInput($_POST['service_name'] ?? '');
    $service_description = sanitizeInput($_POST['service_description'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $is_active = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;
    
    // Get branch_id - NULL for global services (super admin only), or specific branch_id
    $branch_id = null;
    if (isset($_POST['branch_id']) && $_POST['branch_id'] !== '' && $_POST['branch_id'] !== null) {
        $branch_id = intval($_POST['branch_id']);
        if ($branch_id <= 0) {
            $branch_id = null;
        }
    }
    
    // Get user's branch if they're a branch admin (not super admin)
    if (!hasRole('super_admin') && hasRole('administrator')) {
        $user = getCurrentUser();
        $staffQuery = "SELECT branch_id FROM staff WHERE user_id = ? LIMIT 1";
        $staffStmt = mysqli_prepare($connection, $staffQuery);
        if ($staffStmt) {
            mysqli_stmt_bind_param($staffStmt, "i", $user['id']);
            mysqli_stmt_execute($staffStmt);
            $staffResult = mysqli_stmt_get_result($staffStmt);
            if ($staff = mysqli_fetch_assoc($staffResult)) {
                $branch_id = $staff['branch_id']; // Force branch admin to use their branch
            }
            mysqli_stmt_close($staffStmt);
        }
    }
    
    // Validation
    if (empty($service_name) || strlen($service_name) < 3 || strlen($service_name) > 100) {
        echo json_encode(['success' => false, 'message' => 'Service name must be between 3 and 100 characters']);
        exit();
    }
    
    if (empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Category is required']);
        exit();
    }
    
    // Validate category
    $validCategories = ['room_service', 'housekeeping', 'maintenance', 'dining', 'transport', 'concierge', 'other'];
    if (!in_array($category, $validCategories)) {
        echo json_encode(['success' => false, 'message' => 'Invalid category']);
        exit();
    }
    
    // Check if service name already exists in the same branch (or globally if branch_id is NULL)
    $checkQuery = "SELECT service_type_id FROM service_types WHERE service_name = ? AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL))";
    $checkStmt = mysqli_prepare($connection, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, "sii", $service_name, $branch_id, $branch_id);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    
    if (mysqli_num_rows($checkResult) > 0) {
        $branchText = $branch_id ? "for this branch" : "globally";
        echo json_encode(['success' => false, 'message' => "A service with this name already exists $branchText"]);
        mysqli_stmt_close($checkStmt);
        exit();
    }
    mysqli_stmt_close($checkStmt);
    
    // Insert new service type
    $insertQuery = "INSERT INTO service_types (service_name, service_description, category, default_priority, is_active, branch_id) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $insertStmt = mysqli_prepare($connection, $insertQuery);
    
    if ($insertStmt) {
        mysqli_stmt_bind_param($insertStmt, "ssssii", $service_name, $service_description, $category, $default_priority, $is_active, $branch_id);
        
        if (mysqli_stmt_execute($insertStmt)) {
            $service_type_id = mysqli_insert_id($connection);
            
            logSecurityEvent('service_type_created', [
                'service_type_id' => $service_type_id,
                'service_name' => $service_name,
                'branch_id' => $branch_id
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Service type added successfully!',
                'service_type_id' => $service_type_id,
                'service_name' => $service_name,
                'branch_id' => $branch_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding service type: ' . mysqli_error($connection)]);
        }
        
        mysqli_stmt_close($insertStmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)]);
    }
    
    exit();
}

// Get Service Types (with branch filtering)
if (isset($_POST['action']) && $_POST['action'] == 'get_service_types') {
    require_once 'includes/auth.php';
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    // Get branch filter
    $branch_id = null;
    if (isset($_POST['branch_id']) && $_POST['branch_id'] !== '' && $_POST['branch_id'] !== null) {
        $branch_id = intval($_POST['branch_id']);
        if ($branch_id <= 0) {
            $branch_id = null;
        }
    }
    
    // Get user's branch if they're a branch admin
    $user = getCurrentUser();
    $userBranchId = null;
    if (hasRole('administrator') && !hasRole('super_admin')) {
        $staffQuery = "SELECT branch_id FROM staff WHERE user_id = ? LIMIT 1";
        $staffStmt = mysqli_prepare($connection, $staffQuery);
        if ($staffStmt) {
            mysqli_stmt_bind_param($staffStmt, "i", $user['id']);
            mysqli_stmt_execute($staffStmt);
            $staffResult = mysqli_stmt_get_result($staffStmt);
            if ($staff = mysqli_fetch_assoc($staffResult)) {
                $userBranchId = $staff['branch_id'];
            }
            mysqli_stmt_close($staffStmt);
        }
    }
    
    // Build query - show global services (branch_id IS NULL) and branch-specific services
    if ($userBranchId && !hasRole('super_admin')) {
        // Branch admin - only see their branch services and global services
        $query = "SELECT st.*, b.branch_name, b.branch_code 
                  FROM service_types st
                  LEFT JOIN branches b ON st.branch_id = b.branch_id
                  WHERE (st.branch_id = ? OR st.branch_id IS NULL) AND st.is_active = 1
                  ORDER BY st.branch_id IS NULL DESC, st.service_name";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "i", $userBranchId);
    } elseif ($branch_id) {
        // Filter by specific branch
        $query = "SELECT st.*, b.branch_name, b.branch_code 
                  FROM service_types st
                  LEFT JOIN branches b ON st.branch_id = b.branch_id
                  WHERE (st.branch_id = ? OR st.branch_id IS NULL) AND st.is_active = 1
                  ORDER BY st.branch_id IS NULL DESC, st.service_name";
        $stmt = mysqli_prepare($connection, $query);
        mysqli_stmt_bind_param($stmt, "i", $branch_id);
    } else {
        // Super admin - see all services
        $query = "SELECT st.*, b.branch_name, b.branch_code 
                  FROM service_types st
                  LEFT JOIN branches b ON st.branch_id = b.branch_id
                  WHERE st.is_active = 1
                  ORDER BY st.branch_id IS NULL DESC, b.branch_name, st.service_name";
        $stmt = mysqli_prepare($connection, $query);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $service_types = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $service_types[] = [
            'service_type_id' => $row['service_type_id'],
            'service_name' => $row['service_name'],
            'service_description' => $row['service_description'],
            'category' => $row['category'],
            'default_priority' => $row['default_priority'],
            'is_active' => $row['is_active'],
            'branch_id' => $row['branch_id'],
            'branch_name' => $row['branch_name'] ?? 'All Branches',
            'branch_code' => $row['branch_code'] ?? ''
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'success' => true,
        'service_types' => $service_types
    ]);
    exit();
}

// Update Service Type
if (isset($_POST['action']) && $_POST['action'] == 'update_service_type') {
    require_once 'includes/auth.php';
    require_once 'includes/rbac.php';
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit();
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    if (!hasRole('super_admin') && !hasRole('administrator')) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update service types']);
        exit();
    }
    
    $service_type_id = intval($_POST['service_type_id'] ?? 0);
    $service_name = sanitizeInput($_POST['service_name'] ?? '');
    $service_description = sanitizeInput($_POST['service_description'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $is_active = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;
    
    // Get branch_id
    $branch_id = null;
    if (isset($_POST['branch_id']) && $_POST['branch_id'] !== '' && $_POST['branch_id'] !== null) {
        $branch_id = intval($_POST['branch_id']);
        if ($branch_id <= 0) {
            $branch_id = null;
        }
    }
    
    // Get user's branch if they're a branch admin
    $user = getCurrentUser();
    if (!hasRole('super_admin') && hasRole('administrator')) {
        $staffQuery = "SELECT branch_id FROM staff WHERE user_id = ? LIMIT 1";
        $staffStmt = mysqli_prepare($connection, $staffQuery);
        if ($staffStmt) {
            mysqli_stmt_bind_param($staffStmt, "i", $user['id']);
            mysqli_stmt_execute($staffStmt);
            $staffResult = mysqli_stmt_get_result($staffStmt);
            if ($staff = mysqli_fetch_assoc($staffResult)) {
                $branch_id = $staff['branch_id'];
            }
            mysqli_stmt_close($staffStmt);
        }
    }
    
    // Validation
    if ($service_type_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid service type ID']);
        exit();
    }
    
    if (empty($service_name) || strlen($service_name) < 3 || strlen($service_name) > 100) {
        echo json_encode(['success' => false, 'message' => 'Service name must be between 3 and 100 characters']);
        exit();
    }
    
    $validCategories = ['room_service', 'housekeeping', 'maintenance', 'dining', 'transport', 'concierge', 'other'];
    if (!in_array($category, $validCategories)) {
        echo json_encode(['success' => false, 'message' => 'Invalid category']);
        exit();
    }
    
    // Check if service name already exists (excluding current service)
    $checkQuery = "SELECT service_type_id FROM service_types 
                   WHERE service_name = ? AND (branch_id = ? OR (branch_id IS NULL AND ? IS NULL))
                   AND service_type_id != ?";
    $checkStmt = mysqli_prepare($connection, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, "siii", $service_name, $branch_id, $branch_id, $service_type_id);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    
    if (mysqli_num_rows($checkResult) > 0) {
        $branchText = $branch_id ? "for this branch" : "globally";
        echo json_encode(['success' => false, 'message' => "A service with this name already exists $branchText"]);
        mysqli_stmt_close($checkStmt);
        exit();
    }
    mysqli_stmt_close($checkStmt);
    
    // Update service type
    $updateQuery = "UPDATE service_types 
                    SET service_name = ?, service_description = ?, category = ?, 
                        is_active = ?, branch_id = ?
                    WHERE service_type_id = ?";
    $updateStmt = mysqli_prepare($connection, $updateQuery);
    
    if ($updateStmt) {
        mysqli_stmt_bind_param($updateStmt, "sssiii", $service_name, $service_description, 
                             $category, $is_active, $branch_id, $service_type_id);
        
        if (mysqli_stmt_execute($updateStmt)) {
            logSecurityEvent('service_type_updated', [
                'service_type_id' => $service_type_id,
                'service_name' => $service_name,
                'branch_id' => $branch_id
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Service type updated successfully!'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating service type: ' . mysqli_error($connection)]);
        }
        
        mysqli_stmt_close($updateStmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)]);
    }
    
    exit();
}

// Delete Service Type
if (isset($_POST['action']) && $_POST['action'] == 'delete_service_type') {
    require_once 'includes/auth.php';
    require_once 'includes/rbac.php';
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit();
    }
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    if (!hasRole('super_admin') && !hasRole('administrator')) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete service types']);
        exit();
    }
    
    $service_type_id = intval($_POST['service_type_id'] ?? 0);
    
    if ($service_type_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid service type ID']);
        exit();
    }
    
    // Check if service type is being used in service requests
    $checkQuery = "SELECT COUNT(*) as count FROM service_requests WHERE service_type_id = ?";
    $checkStmt = mysqli_prepare($connection, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, "i", $service_type_id);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    $check = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);
    
    if ($check['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete service type. It is being used in ' . $check['count'] . ' service request(s).']);
        exit();
    }
    
    // Delete service type
    $deleteQuery = "DELETE FROM service_types WHERE service_type_id = ?";
    $deleteStmt = mysqli_prepare($connection, $deleteQuery);
    
    if ($deleteStmt) {
        mysqli_stmt_bind_param($deleteStmt, "i", $service_type_id);
        
        if (mysqli_stmt_execute($deleteStmt)) {
            logSecurityEvent('service_type_deleted', [
                'service_type_id' => $service_type_id
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Service type deleted successfully!'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting service type: ' . mysqli_error($connection)]);
        }
        
        mysqli_stmt_close($deleteStmt);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)]);
    }
    
    exit();
}

if (isset($_POST['change_shift'])) {
    $staff_id = intval($_POST['emp_id']); // Note: form parameter name is 'emp_id', but column is now 'staff_id'
    $shift = sanitizeInput($_POST['shift']);
    
    // Parse shift (format: "shift|shift_timing")
    $shift_parts = explode('|', $shift);
    $shift_name = isset($shift_parts[0]) ? mysqli_real_escape_string($connection, trim($shift_parts[0])) : '';
    $shift_timing = isset($shift_parts[1]) ? mysqli_real_escape_string($connection, trim($shift_parts[1])) : '';
    
    if (empty($shift_name) || empty($shift_timing)) {
        header("Location:index.php?staff_mang&error=Invalid shift format");
        exit;
    }
    
    $query = "UPDATE staff SET shift = '$shift_name', shift_timing = '$shift_timing' WHERE staff_id = '$staff_id'";
    $result = mysqli_query($connection, $query);

    if ($result) {
        header("Location:index.php?staff_mang&success");
    } else {
        header("Location:index.php?staff_mang&error");
    }
}

// ============================================
// ADMIN MANAGEMENT ENDPOINTS
// ============================================

// Get User Roles
if (isset($_POST['action']) && $_POST['action'] == 'get_user_roles') {
    require_once 'includes/auth.php';
    
    if (!hasPermission('user.read')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
    
    $user_id = intval($_POST['user_id']);
    $roles = getUserRoles($user_id);
    
    // Get role IDs
    $roleIds = [];
    if (!empty($roles)) {
        $roleNames = "'" . implode("','", array_map(function($r) use ($connection) {
            return mysqli_real_escape_string($connection, $r);
        }, $roles)) . "'";
        $query = "SELECT role_id, role_name FROM roles WHERE role_name IN ($roleNames)";
        $result = mysqli_query($connection, $query);
        while ($role = mysqli_fetch_assoc($result)) {
            $roleIds[] = [
                'role_id' => $role['role_id'],
                'role_name' => $role['role_name']
            ];
        }
    }
    
    echo json_encode(['success' => true, 'roles' => $roleIds]);
    exit();
}

// Get Role Permissions
if (isset($_POST['action']) && $_POST['action'] == 'get_role_permissions') {
    require_once 'includes/auth.php';
    
    if (!hasPermission('user.manage_roles')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
    
    $role_id = intval($_POST['role_id']);
    
    $query = "SELECT permission_id FROM role_permissions WHERE role_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $role_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $permissions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $permissions[] = $row['permission_id'];
    }
    mysqli_stmt_close($stmt);
    
    echo json_encode(['success' => true, 'permissions' => $permissions]);
    exit();
}

// Remove User Role
if (isset($_POST['action']) && $_POST['action'] == 'remove_user_role') {
    require_once 'includes/auth.php';
    require_once 'includes/audit.php';
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit();
    }
    
    if (!hasPermission('user.manage_roles')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
    
    $user_id = intval($_POST['user_id']);
    $role_id = intval($_POST['role_id']);
    
    $removeQuery = "DELETE FROM user_roles WHERE user_id = ? AND role_id = ?";
    $removeStmt = mysqli_prepare($connection, $removeQuery);
    mysqli_stmt_bind_param($removeStmt, "ii", $user_id, $role_id);
    
    if (mysqli_stmt_execute($removeStmt)) {
        logAuditEvent('role_removed', 'users', 'user_role', $user_id, ['role_id' => $role_id], null);
        clearRBACCache();
        echo json_encode(['success' => true, 'message' => 'Role removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error removing role']);
    }
    
    mysqli_stmt_close($removeStmt);
    exit();
}

// Get Branch Rooms
if (isset($_POST['action']) && $_POST['action'] == 'get_branch_rooms') {
    require_once 'includes/auth.php';
    require_once 'includes/rbac.php';
    
    // Only super admin can manage branches
    if (!hasRole('super_admin')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
    
    $branch_id = intval($_POST['branch_id']);
    
    // Only show rooms that are specifically assigned to this branch
    // Ensure branch_id is not NULL and matches exactly
    $deleteFilter = getRoomDeleteFilter($connection, 'r');
    $query = "SELECT r.room_id, r.room_no, r.status, rt.room_type 
              FROM room r
              LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id
              WHERE r.branch_id = ? 
              AND r.branch_id IS NOT NULL" . $deleteFilter . "
              ORDER BY r.room_no";
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)]);
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "i", $branch_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $rooms = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Double-check that room belongs to the requested branch (extra safety)
        if (isset($row['room_id'])) {
            $rooms[] = $row;
        }
    }
    
    mysqli_stmt_close($stmt);
    
    // Return only rooms for this specific branch
    echo json_encode(['success' => true, 'rooms' => $rooms, 'branch_id' => $branch_id]);
    exit();
}

// Get Unassigned Rooms
if (isset($_POST['action']) && $_POST['action'] == 'get_unassigned_rooms') {
    require_once 'includes/auth.php';
    require_once 'includes/rbac.php';
    
    // Only super admin can manage branches
    if (!hasRole('super_admin')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
    
    $branch_id = intval($_POST['branch_id']);
    
    // Get rooms that are not assigned to any branch, or assigned to a different branch
    $deleteFilter = getRoomDeleteFilter($connection, 'r');
    $query = "SELECT r.room_id, r.room_no, rt.room_type 
              FROM room r
              LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id
              WHERE (r.branch_id IS NULL OR r.branch_id != ?)" . $deleteFilter . "
              ORDER BY r.room_no";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $branch_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $rooms = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rooms[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'rooms' => $rooms]);
    exit();
}

// Remove Room from Branch
if (isset($_POST['action']) && $_POST['action'] == 'remove_room_from_branch') {
    require_once 'includes/auth.php';
    require_once 'includes/rbac.php';
    require_once 'includes/audit.php';
    
    // Only super admin can manage branches
    if (!hasRole('super_admin')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
    
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit();
    }
    
    $room_id = intval($_POST['room_id']);
    
    $updateQuery = "UPDATE room SET branch_id = NULL WHERE room_id = ?";
    $updateStmt = mysqli_prepare($connection, $updateQuery);
    mysqli_stmt_bind_param($updateStmt, "i", $room_id);
    
    if (mysqli_stmt_execute($updateStmt)) {
        logAuditEvent('room.removed_from_branch', 'branches', 'room', $room_id, null, null);
        echo json_encode(['success' => true, 'message' => 'Room removed from branch successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error removing room: ' . mysqli_error($connection)]);
    }
    
    mysqli_stmt_close($updateStmt);
    exit();
}

// ==================== MEAL PACKAGES MANAGEMENT ====================

// Add Meal Package
if (isset($_POST['action']) && $_POST['action'] == 'add_meal_package') {
    require_once 'includes/auth.php';
    require_once 'includes/rbac.php';
    require_once 'includes/security.php';
    
    // Check permissions
    if (!hasPermission('package.create') && !hasRole('super_admin')) {
        echo json_encode(['done' => false, 'data' => 'Permission denied']);
        exit();
    }
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['done' => false, 'data' => 'Invalid security token']);
        exit();
    }
    
    $package_name = sanitizeInput($_POST['package_name'] ?? '');
    $package_description = sanitizeInput($_POST['package_description'] ?? '');
    $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? intval($_POST['branch_id']) : null;
    $meal_type = sanitizeInput($_POST['meal_type'] ?? 'custom');
    $number_of_meals = intval($_POST['number_of_meals'] ?? 1);
    $package_price = floatval($_POST['package_price'] ?? 0);
    $included_items = sanitizeInput($_POST['included_items'] ?? '');
    $dietary_options = sanitizeInput($_POST['dietary_options'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'active');
    
    // Validation
    if (empty($package_name)) {
        echo json_encode(['done' => false, 'data' => 'Package name is required']);
        exit();
    }
    
    if ($number_of_meals < 1 || $number_of_meals > 10) {
        echo json_encode(['done' => false, 'data' => 'Number of meals must be between 1 and 10']);
        exit();
    }
    
    if ($package_price <= 0) {
        echo json_encode(['done' => false, 'data' => 'Package price must be greater than 0']);
        exit();
    }
    
    // Check branch access for branch admins
    if ($branch_id && hasRole('administrator') && !hasRole('super_admin')) {
        $user = getCurrentUser();
        $staffQuery = "SELECT branch_id FROM staff WHERE user_id = ? LIMIT 1";
        $staffStmt = mysqli_prepare($connection, $staffQuery);
        if ($staffStmt) {
            mysqli_stmt_bind_param($staffStmt, "i", $user['id']);
            mysqli_stmt_execute($staffStmt);
            $staffResult = mysqli_stmt_get_result($staffStmt);
            if ($staff = mysqli_fetch_assoc($staffResult)) {
                if ($staff['branch_id'] != $branch_id) {
                    echo json_encode(['done' => false, 'data' => 'Permission denied: Cannot create package for other branches']);
                    exit();
                }
            }
            mysqli_stmt_close($staffStmt);
        }
    }
    
    // Insert package
    $query = "INSERT INTO meal_packages (branch_id, package_name, package_description, meal_type, number_of_meals, package_price, included_items, dietary_options, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        echo json_encode(['done' => false, 'data' => 'Database error: ' . mysqli_error($connection)]);
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "isssidsss", $branch_id, $package_name, $package_description, $meal_type, $number_of_meals, $package_price, $included_items, $dietary_options, $status);
    
    if (mysqli_stmt_execute($stmt)) {
        $package_id = mysqli_insert_id($connection);
        logAuditEvent('package.created', 'meal_packages', 'package', $package_id, null, ['package_name' => $package_name]);
        echo json_encode(['done' => true, 'data' => 'Meal package added successfully', 'package_id' => $package_id]);
    } else {
        echo json_encode(['done' => false, 'data' => 'Error adding package: ' . mysqli_error($connection)]);
    }
    
    mysqli_stmt_close($stmt);
    exit();
}

// Update Meal Package
if (isset($_POST['action']) && $_POST['action'] == 'update_meal_package') {
    require_once 'includes/auth.php';
    require_once 'includes/rbac.php';
    require_once 'includes/security.php';
    
    // Check permissions
    if (!hasPermission('package.update') && !hasRole('super_admin')) {
        echo json_encode(['done' => false, 'data' => 'Permission denied']);
        exit();
    }
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['done' => false, 'data' => 'Invalid security token']);
        exit();
    }
    
    $package_id = intval($_POST['package_id'] ?? 0);
    $package_name = sanitizeInput($_POST['package_name'] ?? '');
    $package_description = sanitizeInput($_POST['package_description'] ?? '');
    $branch_id = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? intval($_POST['branch_id']) : null;
    $meal_type = sanitizeInput($_POST['meal_type'] ?? 'custom');
    $number_of_meals = intval($_POST['number_of_meals'] ?? 1);
    $package_price = floatval($_POST['package_price'] ?? 0);
    $included_items = sanitizeInput($_POST['included_items'] ?? '');
    $dietary_options = sanitizeInput($_POST['dietary_options'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'active');
    
    // Validation
    if ($package_id <= 0) {
        echo json_encode(['done' => false, 'data' => 'Invalid package ID']);
        exit();
    }
    
    if (empty($package_name)) {
        echo json_encode(['done' => false, 'data' => 'Package name is required']);
        exit();
    }
    
    if ($number_of_meals < 1 || $number_of_meals > 10) {
        echo json_encode(['done' => false, 'data' => 'Number of meals must be between 1 and 10']);
        exit();
    }
    
    if ($package_price <= 0) {
        echo json_encode(['done' => false, 'data' => 'Package price must be greater than 0']);
        exit();
    }
    
    // Check if package exists and user has access
    $checkQuery = "SELECT branch_id FROM meal_packages WHERE package_id = ?";
    $checkStmt = mysqli_prepare($connection, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, "i", $package_id);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    
    if (mysqli_num_rows($checkResult) == 0) {
        echo json_encode(['done' => false, 'data' => 'Package not found']);
        exit();
    }
    
    $existingPackage = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);
    
    // Check branch access for branch admins
    if ($existingPackage['branch_id'] && hasRole('administrator') && !hasRole('super_admin')) {
        $user = getCurrentUser();
        $staffQuery = "SELECT branch_id FROM staff WHERE user_id = ? LIMIT 1";
        $staffStmt = mysqli_prepare($connection, $staffQuery);
        if ($staffStmt) {
            mysqli_stmt_bind_param($staffStmt, "i", $user['id']);
            mysqli_stmt_execute($staffStmt);
            $staffResult = mysqli_stmt_get_result($staffStmt);
            if ($staff = mysqli_fetch_assoc($staffResult)) {
                if ($staff['branch_id'] != $existingPackage['branch_id']) {
                    echo json_encode(['done' => false, 'data' => 'Permission denied: Cannot edit packages from other branches']);
                    exit();
                }
            }
            mysqli_stmt_close($staffStmt);
        }
    }
    
    // Update package
    $query = "UPDATE meal_packages SET 
              branch_id = ?, package_name = ?, package_description = ?, meal_type = ?, 
              number_of_meals = ?, package_price = ?, 
              included_items = ?, dietary_options = ?, status = ? 
              WHERE package_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        echo json_encode(['done' => false, 'data' => 'Database error: ' . mysqli_error($connection)]);
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "isssidsssi", $branch_id, $package_name, $package_description, $meal_type, $number_of_meals, $package_price, $included_items, $dietary_options, $status, $package_id);
    
    if (mysqli_stmt_execute($stmt)) {
        logAuditEvent('package.updated', 'meal_packages', 'package', $package_id, null, ['package_name' => $package_name]);
        echo json_encode(['done' => true, 'data' => 'Meal package updated successfully']);
    } else {
        echo json_encode(['done' => false, 'data' => 'Error updating package: ' . mysqli_error($connection)]);
    }
    
    mysqli_stmt_close($stmt);
    exit();
}

// Get Room Package Details
if (isset($_POST['action']) && $_POST['action'] == 'get_room_package_details') {
    require_once 'includes/auth.php';
    require_once 'includes/rbac.php';
    
    // Check permissions
    if (!hasPermission('package.read') && !hasRole('super_admin')) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit();
    }
    
    $package_id = intval($_POST['package_id'] ?? 0);
    
    if ($package_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid package ID']);
        exit();
    }
    
    $query = "SELECT mp.*, b.branch_name, b.branch_code 
              FROM meal_packages mp 
              LEFT JOIN branches b ON mp.branch_id = b.branch_id 
              WHERE mp.package_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)]);
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "i", $package_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        echo json_encode(['success' => false, 'message' => 'Package not found']);
        exit();
    }
    
    $package = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Check branch access for branch admins
    if ($package['branch_id'] && hasRole('administrator') && !hasRole('super_admin')) {
        $user = getCurrentUser();
        $staffQuery = "SELECT branch_id FROM staff WHERE user_id = ? LIMIT 1";
        $staffStmt = mysqli_prepare($connection, $staffQuery);
        if ($staffStmt) {
            mysqli_stmt_bind_param($staffStmt, "i", $user['id']);
            mysqli_stmt_execute($staffStmt);
            $staffResult = mysqli_stmt_get_result($staffStmt);
            if ($staff = mysqli_fetch_assoc($staffResult)) {
                if ($staff['branch_id'] != $package['branch_id']) {
                    echo json_encode(['success' => false, 'message' => 'Permission denied']);
                    exit();
                }
            }
            mysqli_stmt_close($staffStmt);
        }
    }
    
    // Format dates
    $package['created_at'] = $package['created_at'] ? date('M j, Y g:i A', strtotime($package['created_at'])) : null;
    $package['updated_at'] = $package['updated_at'] ? date('M j, Y g:i A', strtotime($package['updated_at'])) : null;
    
    echo json_encode(['success' => true] + $package);
    exit();
}

// Get all active meal packages (for guest booking)
if (isset($_POST['action']) && $_POST['action'] == 'get_meal_packages') {
    $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : 'active';
    
    $query = "SELECT package_id, package_name, package_description, meal_type, number_of_meals, 
                     package_price, included_items, dietary_options, status
              FROM meal_packages 
              WHERE status = ? 
              ORDER BY package_price ASC";
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)]);
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "s", $status);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $packages = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $packages[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'success' => true,
        'packages' => $packages
    ]);
    exit();
}

// Delete Meal Package
if (isset($_GET['delete_meal_package'])) {
    require_once 'includes/auth.php';
    require_once 'includes/rbac.php';
    
    // Check permissions
    if (!hasPermission('package.delete') && !hasRole('super_admin')) {
        header("Location:index.php?meal_packages&error");
        exit();
    }
    
    $package_id = intval($_GET['delete_meal_package']);
    
    if ($package_id <= 0) {
        header("Location:index.php?meal_packages&error");
        exit();
    }
    
    // Check if package exists and user has access
    $checkQuery = "SELECT branch_id FROM meal_packages WHERE package_id = ?";
    $checkStmt = mysqli_prepare($connection, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, "i", $package_id);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    
    if (mysqli_num_rows($checkResult) == 0) {
        header("Location:index.php?meal_packages&error");
        exit();
    }
    
    $package = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);
    
    // Check branch access for branch admins
    if ($package['branch_id'] && hasRole('administrator') && !hasRole('super_admin')) {
        $user = getCurrentUser();
        $staffQuery = "SELECT branch_id FROM staff WHERE user_id = ? LIMIT 1";
        $staffStmt = mysqli_prepare($connection, $staffQuery);
        if ($staffStmt) {
            mysqli_stmt_bind_param($staffStmt, "i", $user['id']);
            mysqli_stmt_execute($staffStmt);
            $staffResult = mysqli_stmt_get_result($staffStmt);
            if ($staff = mysqli_fetch_assoc($staffResult)) {
                if ($staff['branch_id'] != $package['branch_id']) {
                    header("Location:index.php?meal_packages&error");
                    exit();
                }
            }
            mysqli_stmt_close($staffStmt);
        }
    }
    
    // Delete package
    $query = "DELETE FROM meal_packages WHERE package_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        header("Location:index.php?meal_packages&error");
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "i", $package_id);
    
    if (mysqli_stmt_execute($stmt)) {
        logAuditEvent('package.deleted', 'meal_packages', 'package', $package_id, null, null);
        header("Location:index.php?meal_packages&success");
    } else {
        header("Location:index.php?meal_packages&error");
    }
    
    mysqli_stmt_close($stmt);
    exit();
}

// Update Guest Profile
if (isset($_POST['action']) && $_POST['action'] == 'update_guest_profile') {
    require_once 'includes/auth.php';
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Security token mismatch']);
        exit();
    }
    
    // Verify user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
    $id_card_type_id = isset($_POST['id_card_type_id']) && !empty($_POST['id_card_type_id']) ? intval($_POST['id_card_type_id']) : null;
    $id_card_no = isset($_POST['id_card_no']) ? sanitizeInput($_POST['id_card_no']) : '';
    $address = isset($_POST['address']) ? sanitizeInput($_POST['address']) : '';
    
    // Update user phone
    if (!empty($phone)) {
        $updateUserQuery = "UPDATE user SET phone = ? WHERE id = ?";
        $updateUserStmt = mysqli_prepare($connection, $updateUserQuery);
        mysqli_stmt_bind_param($updateUserStmt, "si", $phone, $user_id);
        mysqli_stmt_execute($updateUserStmt);
        mysqli_stmt_close($updateUserStmt);
    }
    
    // Get or update guest record (for staff users, find guest by email)
    $user = getCurrentUser();
    if ($user) {
        $guestQuery = "SELECT guest_id FROM guests WHERE email = ? LIMIT 1";
        $guestStmt = mysqli_prepare($connection, $guestQuery);
        mysqli_stmt_bind_param($guestStmt, "s", $user['email']);
        mysqli_stmt_execute($guestStmt);
        $guestResult = mysqli_stmt_get_result($guestStmt);
        $guest = mysqli_fetch_assoc($guestResult);
        mysqli_stmt_close($guestStmt);
        
        if ($guest) {
            // Update guest record
            $updateGuestQuery = "UPDATE guests SET contact_no = ?, id_card_type_id = ?, id_card_no = ?, address = ? WHERE guest_id = ?";
            $updateGuestStmt = mysqli_prepare($connection, $updateGuestQuery);
            $contact_no = !empty($phone) ? preg_replace('/[^0-9]/', '', $phone) : '0';
            $contact_no = !empty($contact_no) && is_numeric($contact_no) ? intval($contact_no) : 0;
            mysqli_stmt_bind_param($updateGuestStmt, "iissi", $contact_no, $id_card_type_id, $id_card_no, $address, $guest['guest_id']);
            mysqli_stmt_execute($updateGuestStmt);
            mysqli_stmt_close($updateGuestStmt);
        } else {
            // Create guest record if it doesn't exist (for staff users who also have bookings)
            $insertGuestQuery = "INSERT INTO guests (name, email, contact_no, id_card_type_id, id_card_no, address, status) VALUES (?, ?, ?, ?, ?, ?, 'active')";
            $insertGuestStmt = mysqli_prepare($connection, $insertGuestQuery);
            $contact_no = !empty($phone) ? preg_replace('/[^0-9]/', '', $phone) : '0';
            $contact_no = !empty($contact_no) && is_numeric($contact_no) ? intval($contact_no) : 0;
            mysqli_stmt_bind_param($insertGuestStmt, "ssiiss", $user['name'], $user['email'], $contact_no, $id_card_type_id, $id_card_no, $address);
            mysqli_stmt_execute($insertGuestStmt);
            mysqli_stmt_close($insertGuestStmt);
        }
    }
    
    // Clear user cache
    clearUserCache();
    
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    exit();
}

// Update booking (for admin/staff reservation management)
if (isset($_POST['update_booking'])) {
    // Start output buffering
    ob_start();
    header('Content-Type: application/json');
    
    $response = ['done' => false, 'data' => ''];
    
    try {
        // Check if user is logged in as admin/staff
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            $response['data'] = 'Unauthorized access. Please login as admin/staff.';
            echo json_encode($response);
            exit;
        }
        
        // Get form data
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
        $check_in = isset($_POST['check_in']) ? sanitizeInput($_POST['check_in']) : '';
        $check_out = isset($_POST['check_out']) ? sanitizeInput($_POST['check_out']) : '';
        $total_price = isset($_POST['total_price']) ? floatval($_POST['total_price']) : 0;
        $payment_status = isset($_POST['payment_status']) ? intval($_POST['payment_status']) : 0;
        $remaining_price = isset($_POST['remaining_price']) ? floatval($_POST['remaining_price']) : 0;
        $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : null;
        
        // Validate required fields
        if ($booking_id <= 0 || $room_id <= 0 || empty($check_in) || empty($check_out) || $total_price <= 0) {
            $response['data'] = 'Please fill in all required fields.';
            echo json_encode($response);
            exit;
        }
        
        // Validate dates
        $dateValidation = validateBookingDates($check_in, $check_out);
        if (!$dateValidation['valid']) {
            $response['data'] = $dateValidation['error'];
            echo json_encode($response);
            exit;
        }
        
        // Check if booking exists
        $checkBookingQuery = "SELECT * FROM booking WHERE booking_id = ?";
        $checkStmt = mysqli_prepare($connection, $checkBookingQuery);
        mysqli_stmt_bind_param($checkStmt, "i", $booking_id);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($checkResult) == 0) {
            mysqli_stmt_close($checkStmt);
            $response['data'] = 'Booking not found.';
            echo json_encode($response);
            exit;
        }
        
        $existingBooking = mysqli_fetch_assoc($checkResult);
        mysqli_stmt_close($checkStmt);
        
        // Check room availability if room or dates changed
        if ($existingBooking['room_id'] != $room_id || 
            $existingBooking['check_in'] != $check_in || 
            $existingBooking['check_out'] != $check_out) {
            
            require_once 'includes/availability.php';
            
            // Check if room is available (excluding current booking)
            $availQuery = "SELECT booking_id FROM booking 
                          WHERE room_id = ? 
                          AND booking_id != ?
                          AND (
                              (check_in <= ? AND check_out > ?) OR
                              (check_in < ? AND check_out >= ?) OR
                              (check_in >= ? AND check_out <= ?)
                          )";
            $availStmt = mysqli_prepare($connection, $availQuery);
            mysqli_stmt_bind_param($availStmt, "iissssss", 
                $room_id, $booking_id, 
                $check_in, $check_in,
                $check_out, $check_out,
                $check_in, $check_out
            );
            mysqli_stmt_execute($availStmt);
            $availResult = mysqli_stmt_get_result($availStmt);
            
            if (mysqli_num_rows($availResult) > 0) {
                mysqli_stmt_close($availStmt);
                $response['data'] = 'Room is not available for the selected dates.';
                echo json_encode($response);
                exit;
            }
            mysqli_stmt_close($availStmt);
        }
        
        // Auto-calculate remaining price if payment status is paid
        if ($payment_status == 1) {
            $remaining_price = 0;
        }
        
        // Check if status column exists in booking table
        $checkStatusColumn = mysqli_query($connection, "SHOW COLUMNS FROM booking LIKE 'status'");
        $hasStatusColumn = mysqli_num_rows($checkStatusColumn) > 0;
        
        // Validate status if provided
        $validStatuses = ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'];
        if ($hasStatusColumn && $status && !in_array($status, $validStatuses)) {
            $response['data'] = 'Invalid booking status.';
            echo json_encode($response);
            exit;
        }
        
        // Update booking with or without status field
        if ($hasStatusColumn && $status) {
            $updateQuery = "UPDATE booking SET 
                           room_id = ?, 
                           check_in = ?, 
                           check_out = ?, 
                           total_price = ?, 
                           payment_status = ?, 
                           remaining_price = ?,
                           status = ? 
                           WHERE booking_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "issdidsi", 
                $room_id, $check_in, $check_out, $total_price, 
                $payment_status, $remaining_price, $status, $booking_id
            );
        } else {
            $updateQuery = "UPDATE booking SET 
                           room_id = ?, 
                           check_in = ?, 
                           check_out = ?, 
                           total_price = ?, 
                           payment_status = ?, 
                           remaining_price = ? 
                           WHERE booking_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "issdidi", 
                $room_id, $check_in, $check_out, $total_price, 
                $payment_status, $remaining_price, $booking_id
            );
        }
        
        if (mysqli_stmt_execute($updateStmt)) {
            mysqli_stmt_close($updateStmt);
            
            // Log the update action if audit logging is available
            if (function_exists('logAuditTrail')) {
                logAuditTrail($_SESSION['user_id'], 'booking', 'update', 
                    "Updated booking #$booking_id", 
                    ['booking_id' => $booking_id, 'room_id' => $room_id, 'total_price' => $total_price]
                );
            }
            
            $response['done'] = true;
            $response['data'] = 'Booking updated successfully.';
        } else {
            mysqli_stmt_close($updateStmt);
            $response['data'] = 'Failed to update booking: ' . mysqli_error($connection);
        }
        
    } catch (Exception $e) {
        $response['data'] = 'Error: ' . $e->getMessage();
    }
    
    // Clear output buffer and send response
    ob_end_clean();
    echo json_encode($response);
    exit;
}

// Cancel booking (for admin/staff reservation management)
if (isset($_POST['cancel_booking'])) {
    // Start output buffering
    ob_start();
    header('Content-Type: application/json');
    
    $response = ['done' => false, 'data' => ''];
    
    try {
        // Check if user is logged in as admin/staff
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            $response['data'] = 'Unauthorized access. Please login as admin/staff.';
            echo json_encode($response);
            exit;
        }
        
        // Get booking ID
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        // Validate booking ID
        if ($booking_id <= 0) {
            $response['data'] = 'Invalid booking ID.';
            echo json_encode($response);
            exit;
        }
        
        // Check if booking exists
        $checkBookingQuery = "SELECT * FROM booking WHERE booking_id = ?";
        $checkStmt = mysqli_prepare($connection, $checkBookingQuery);
        mysqli_stmt_bind_param($checkStmt, "i", $booking_id);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($checkResult) == 0) {
            mysqli_stmt_close($checkStmt);
            $response['data'] = 'Booking not found.';
            echo json_encode($response);
            exit;
        }
        
        $booking = mysqli_fetch_assoc($checkResult);
        $room_id = $booking['room_id'];
        mysqli_stmt_close($checkStmt);
        
        // Update booking status to 'cancelled' instead of deleting
        $updateQuery = "UPDATE booking SET status = 'cancelled' WHERE booking_id = ?";
        $updateStmt = mysqli_prepare($connection, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, "i", $booking_id);
        
        if (mysqli_stmt_execute($updateStmt)) {
            mysqli_stmt_close($updateStmt);
            
            // Sync room status after cancellation
            syncRoomStatus($connection, $room_id);
            
            // Log the cancellation action if audit logging is available
            if (function_exists('logAuditTrail')) {
                logAuditTrail($_SESSION['user_id'], 'booking', 'cancel', 
                    "Cancelled booking #$booking_id", 
                    ['booking_id' => $booking_id, 'room_id' => $booking['room_id']]
                );
            }
            
            $response['done'] = true;
            $response['data'] = 'Booking cancelled successfully.';
        } else {
            mysqli_stmt_close($deleteStmt);
            $response['data'] = 'Failed to cancel booking: ' . mysqli_error($connection);
        }
        
    } catch (Exception $e) {
        $response['data'] = 'Error: ' . $e->getMessage();
    }
    
    // Clear output buffer and send response
    ob_end_clean();
    echo json_encode($response);
    exit;
}

// ==================== FACILITY MANAGEMENT AJAX HANDLERS ====================

// Load facility functions
require_once __DIR__ . '/includes/facility_functions.php';

// Add Facility
if (isset($_POST['action']) && $_POST['action'] == 'add_facility') {
    header('Content-Type: application/json');
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    // Check permissions
    if (!hasPermission('facility.create') && !hasRole('super_admin') && !hasRole('administrator')) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to create facilities']);
        exit;
    }
    
    // Sanitize inputs
    $facility_name = sanitizeInput($_POST['facility_name'] ?? '');
    $facility_type = sanitizeInput($_POST['facility_type'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $capacity = intval($_POST['capacity'] ?? 0);
    $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
    $full_day_rate = isset($_POST['full_day_rate']) && !empty($_POST['full_day_rate']) ? floatval($_POST['full_day_rate']) : null;
    $features = sanitizeInput($_POST['features'] ?? '');
    $branch_id = isset($_POST['branch_id']) && !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
    $status = sanitizeInput($_POST['status'] ?? 'active');
    
    // Validate required fields
    if (empty($facility_name) || empty($facility_type) || $capacity <= 0 || $hourly_rate < 0) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields correctly']);
        exit;
    }
    
    // If user is branch admin (not super admin), set their branch
    if (hasRole('administrator') && !hasRole('super_admin')) {
        $user = getCurrentUser();
        $staffQuery = "SELECT branch_id FROM staff WHERE user_id = ? LIMIT 1";
        $staffStmt = mysqli_prepare($connection, $staffQuery);
        if ($staffStmt) {
            mysqli_stmt_bind_param($staffStmt, "i", $user['id']);
            mysqli_stmt_execute($staffStmt);
            $staffResult = mysqli_stmt_get_result($staffStmt);
            if ($staff = mysqli_fetch_assoc($staffResult)) {
                $branch_id = $staff['branch_id'];
            }
            mysqli_stmt_close($staffStmt);
        }
    }
    
    // Insert facility
    $query = "INSERT INTO facilities (facility_name, facility_type, description, capacity, hourly_rate, full_day_rate, features, branch_id, status, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "sssiiddis", $facility_name, $facility_type, $description, $capacity, $hourly_rate, $full_day_rate, $features, $branch_id, $status);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Facility added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding facility: ' . mysqli_error($connection)]);
    }
    
    mysqli_stmt_close($stmt);
    exit;
}

// Update Facility
if (isset($_POST['action']) && $_POST['action'] == 'update_facility') {
    header('Content-Type: application/json');
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    // Check permissions
    if (!hasPermission('facility.update') && !hasRole('super_admin') && !hasRole('administrator')) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update facilities']);
        exit;
    }
    
    // Sanitize inputs
    $facility_id = intval($_POST['facility_id'] ?? 0);
    $facility_name = sanitizeInput($_POST['facility_name'] ?? '');
    $facility_type = sanitizeInput($_POST['facility_type'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $capacity = intval($_POST['capacity'] ?? 0);
    $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
    $full_day_rate = isset($_POST['full_day_rate']) && !empty($_POST['full_day_rate']) ? floatval($_POST['full_day_rate']) : null;
    $features = sanitizeInput($_POST['features'] ?? '');
    $branch_id = isset($_POST['branch_id']) && !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
    $status = sanitizeInput($_POST['status'] ?? 'active');
    
    // Validate
    if ($facility_id <= 0 || empty($facility_name) || empty($facility_type) || $capacity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    // Update facility
    $query = "UPDATE facilities SET 
              facility_name = ?, 
              facility_type = ?, 
              description = ?, 
              capacity = ?, 
              hourly_rate = ?, 
              full_day_rate = ?, 
              features = ?, 
              branch_id = ?, 
              status = ?, 
              updated_at = NOW()
              WHERE facility_id = ?";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "sssiiddisi", $facility_name, $facility_type, $description, $capacity, $hourly_rate, $full_day_rate, $features, $branch_id, $status, $facility_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Facility updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating facility: ' . mysqli_error($connection)]);
    }
    
    mysqli_stmt_close($stmt);
    exit;
}

// Delete Facility
if (isset($_POST['action']) && $_POST['action'] == 'delete_facility') {
    header('Content-Type: application/json');
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    // Check permissions
    if (!hasPermission('facility.delete') && !hasRole('super_admin')) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete facilities']);
        exit;
    }
    
    $facility_id = intval($_POST['facility_id'] ?? 0);
    
    if ($facility_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid facility ID']);
        exit;
    }
    
    // Check for existing bookings
    $checkQuery = "SELECT COUNT(*) as booking_count FROM facility_bookings WHERE facility_id = ? AND status NOT IN ('cancelled', 'completed')";
    $checkStmt = mysqli_prepare($connection, $checkQuery);
    mysqli_stmt_bind_param($checkStmt, "i", $facility_id);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    $row = mysqli_fetch_assoc($checkResult);
    mysqli_stmt_close($checkStmt);
    
    if ($row['booking_count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete facility with active bookings. Please cancel or complete existing bookings first.']);
        exit;
    }
    
    // Delete facility
    $query = "DELETE FROM facilities WHERE facility_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $facility_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Facility deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting facility: ' . mysqli_error($connection)]);
    }
    
    mysqli_stmt_close($stmt);
    exit;
}

// Check Facility Availability
if (isset($_POST['action']) && $_POST['action'] == 'check_facility_availability') {
    header('Content-Type: application/json');
    
    $facility_id = intval($_POST['facility_id'] ?? 0);
    $booking_date = sanitizeInput($_POST['booking_date'] ?? '');
    $start_time = sanitizeInput($_POST['start_time'] ?? '');
    $end_time = sanitizeInput($_POST['end_time'] ?? '');
    $exclude_booking_id = isset($_POST['exclude_booking_id']) ? intval($_POST['exclude_booking_id']) : null;
    
    // Validate inputs
    if ($facility_id <= 0 || empty($booking_date) || empty($start_time) || empty($end_time)) {
        echo json_encode(['available' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    // Check availability
    $result = checkFacilityAvailability($facility_id, $booking_date, $start_time, $end_time, $exclude_booking_id);
    
    // Calculate cost estimate
    if ($result['available']) {
        $cost = calculateFacilityCost($facility_id, $start_time, $end_time);
        $result['cost_estimate'] = $cost;
    }
    
    echo json_encode($result);
    exit;
}

// Create Facility Booking
if (isset($_POST['action']) && $_POST['action'] == 'create_facility_booking') {
    // Start output buffering to catch any PHP errors/warnings
    ob_start();
    header('Content-Type: application/json');
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
        exit;
    }
    
    // Check permissions
    if (!hasPermission('facility.create') && !hasRole('super_admin') && !hasRole('administrator')) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'You do not have permission to create bookings']);
        exit;
    }
    
    // Sanitize inputs
    $facility_id = intval($_POST['facility_id'] ?? 0);
    $event_name = sanitizeInput($_POST['event_name'] ?? '');
    $booking_date = sanitizeInput($_POST['booking_date'] ?? '');
    $start_time = sanitizeInput($_POST['start_time'] ?? '');
    $end_time = sanitizeInput($_POST['end_time'] ?? '');
    $customer_name = sanitizeInput($_POST['customer_name'] ?? '');
    $customer_email = sanitizeInput($_POST['customer_email'] ?? '');
    $customer_phone = sanitizeInput($_POST['customer_phone'] ?? '');
    $number_of_guests = isset($_POST['number_of_guests']) && !empty($_POST['number_of_guests']) ? intval($_POST['number_of_guests']) : null;
    $special_requirements = sanitizeInput($_POST['special_requirements'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'pending');
    
    // Get booked_by from session
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'You must be logged in to create a booking']);
        exit;
    }
    $booked_by = intval($_SESSION['user_id']);
    
    if ($booked_by <= 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid user session. Please log in again.']);
        exit;
    }
    
    // Validate
    if ($facility_id <= 0 || empty($event_name) || empty($booking_date) || empty($start_time) || empty($end_time) || empty($customer_name) || empty($customer_email) || empty($customer_phone)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit;
    }
    
    // Check availability
    if (!function_exists('checkFacilityAvailability')) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'System error: Facility functions not loaded']);
        exit;
    }
    
    $availability = checkFacilityAvailability($facility_id, $booking_date, $start_time, $end_time);
    if (!$availability) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error checking availability. Please try again.']);
        exit;
    }
    
    if (isset($availability['error'])) {
        error_log("Availability check error: " . $availability['error']);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error checking availability: ' . $availability['error']]);
        exit;
    }
    
    if (!isset($availability['available']) || !$availability['available']) {
        $conflictMsg = 'Facility is not available for the selected time. ';
        if (!empty($availability['conflicts'])) {
            $conflictMsg .= 'Conflicts found: ' . count($availability['conflicts']) . ' booking(s)';
        }
        ob_clean();
        echo json_encode(['success' => false, 'message' => $conflictMsg]);
        exit;
    }
    
    // Calculate cost
    if (!function_exists('calculateFacilityCost')) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'System error: Cost calculation function not available']);
        exit;
    }
    
    $cost = calculateFacilityCost($facility_id, $start_time, $end_time);
    if (!$cost || !isset($cost['total_cost'])) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error calculating cost. Please check facility rates.']);
        exit;
    }
    
    // Generate booking reference
    if (!function_exists('generateBookingReference')) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'System error: Booking reference generator not available']);
        exit;
    }
    
    $booking_reference = generateBookingReference();
    
    // Insert booking - handle NULL number_of_guests separately
    if ($number_of_guests === '' || $number_of_guests === null) {
        // Query without number_of_guests (will be NULL by default)
        $query = "INSERT INTO facility_bookings 
                  (facility_id, booking_reference, event_name, booking_date, start_time, end_time, 
                   customer_name, customer_email, customer_phone, 
                   special_requirements, total_cost, status, booked_by, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($connection, $query);
        if (!$stmt) {
            $error = mysqli_error($connection);
            error_log("Facility booking prepare error (without guests): " . $error);
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $error]);
            exit;
        }
        
        // 13 parameters (without number_of_guests)
        // Types: facility_id(i), booking_reference(s), event_name(s), booking_date(s), 
        //        start_time(s), end_time(s), customer_name(s), customer_email(s), 
        //        customer_phone(s), special_requirements(s), total_cost(d), status(s), booked_by(i)
        // Type string: i + 9s + d + s + i = "isssssssssdsi" (13 chars)
        $bind_result = mysqli_stmt_bind_param($stmt, "isssssssssdsi", 
            $facility_id, $booking_reference, $event_name, $booking_date, $start_time, $end_time,
            $customer_name, $customer_email, $customer_phone,
            $special_requirements, $cost['total_cost'], $status, $booked_by
        );
    } else {
        // Query with number_of_guests
        $query = "INSERT INTO facility_bookings 
                  (facility_id, booking_reference, event_name, booking_date, start_time, end_time, 
                   customer_name, customer_email, customer_phone, number_of_guests, 
                   special_requirements, total_cost, status, booked_by, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = mysqli_prepare($connection, $query);
        if (!$stmt) {
            $error = mysqli_error($connection);
            error_log("Facility booking prepare error (with guests): " . $error);
            ob_clean();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $error]);
            exit;
        }
        
        $number_of_guests = intval($number_of_guests);
        // 14 parameters (with number_of_guests)
        // Parameter order: facility_id(i), booking_reference(s), event_name(s), booking_date(s), 
        //        start_time(s), end_time(s), customer_name(s), customer_email(s), 
        //        customer_phone(s), number_of_guests(i), special_requirements(s), 
        //        total_cost(d), status(s), booked_by(i)
        // Count parameters: 
        // 1. facility_id (i), 2. booking_reference (s), 3. event_name (s), 4. booking_date (s),
        // 5. start_time (s), 6. end_time (s), 7. customer_name (s), 8. customer_email (s),
        // 9. customer_phone (s), 10. number_of_guests (i), 11. special_requirements (s),
        // 12. total_cost (d), 13. status (s), 14. booked_by (i)
        // Type string: i + 8s + i + s + d + s + i = "isssssssisdsi" (13 chars) - WRONG, need 14!
        // Actually counting: i(1) + s(8) + i(1) + s(1) + d(1) + s(1) + i(1) = 14 total
        // But "isssssssisdsi" = 13 chars. Let me count: i-s-s-s-s-s-s-s-i-s-d-s-i = 13
        // We need one more s! The correct string should be: "issssssssisdsi" (14 chars)
        // Wait, let me verify: i + 9s + i + s + d + s + i = "issssssssisdsi" = 14 ✓
        $bind_result = mysqli_stmt_bind_param($stmt, "issssssssisdsi", 
            $facility_id, $booking_reference, $event_name, $booking_date, $start_time, $end_time,
            $customer_name, $customer_email, $customer_phone, $number_of_guests,
            $special_requirements, $cost['total_cost'], $status, $booked_by
        );
    }
    
    if (!$bind_result) {
        $error = mysqli_stmt_error($stmt);
        error_log("Facility booking bind error: " . $error);
        mysqli_stmt_close($stmt);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error binding parameters: ' . $error]);
        exit;
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $booking_id = mysqli_insert_id($connection);
        if ($booking_id) {
            error_log("Facility booking created successfully: booking_id=$booking_id, reference=$booking_reference");
            ob_clean(); // Clear any output before JSON
            echo json_encode([
                'success' => true, 
                'message' => 'Booking created successfully! Reference: ' . $booking_reference,
                'booking_reference' => $booking_reference,
                'booking_id' => $booking_id
            ]);
        } else {
            error_log("Facility booking execute succeeded but no booking_id returned");
            ob_clean();
            echo json_encode([
                'success' => false, 
                'message' => 'Booking may not have been created. Please check the bookings list.'
            ]);
        }
    } else {
        $error = mysqli_error($connection);
        $stmt_error = mysqli_stmt_error($stmt);
        error_log("Facility booking execute error: " . $error . " | Stmt error: " . $stmt_error);
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Error creating booking: ' . ($stmt_error ?: $error)]);
    }
    
    mysqli_stmt_close($stmt);
    ob_end_flush();
    exit;
}

// Get Booking Details
if (isset($_POST['action']) && $_POST['action'] == 'get_booking_details') {
    header('Content-Type: application/json');
    
    $booking_id = intval($_POST['booking_id'] ?? 0);
    
    if ($booking_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
        exit;
    }
    
    $query = "SELECT fb.*, f.facility_name, f.facility_type, f.capacity, u.username as booked_by_name
              FROM facility_bookings fb
              JOIN facilities f ON fb.facility_id = f.facility_id
              LEFT JOIN user u ON fb.booked_by = u.id
              WHERE fb.booking_id = ?";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($booking = mysqli_fetch_assoc($result)) {
        $statusLabels = [
            'pending' => '<span class="label label-warning">Pending</span>',
            'confirmed' => '<span class="label label-success">Confirmed</span>',
            'in_progress' => '<span class="label label-info">In Progress</span>',
            'completed' => '<span class="label label-default">Completed</span>',
            'cancelled' => '<span class="label label-danger">Cancelled</span>'
        ];
        
        $html = '<div class="row">';
        $html .= '<div class="col-md-12">';
        $html .= '<table class="table table-bordered">';
        $html .= '<tr><th>Booking Reference</th><td><strong>' . htmlspecialchars($booking['booking_reference']) . '</strong></td></tr>';
        $html .= '<tr><th>Facility</th><td>' . htmlspecialchars($booking['facility_name']) . ' (' . ucfirst(str_replace('_', ' ', $booking['facility_type'])) . ')</td></tr>';
        $html .= '<tr><th>Event Name</th><td>' . htmlspecialchars($booking['event_name']) . '</td></tr>';
        $html .= '<tr><th>Date</th><td>' . date('F d, Y', strtotime($booking['booking_date'])) . '</td></tr>';
        $html .= '<tr><th>Time</th><td>' . date('h:i A', strtotime($booking['start_time'])) . ' - ' . date('h:i A', strtotime($booking['end_time'])) . '</td></tr>';
        $html .= '<tr><th>Customer Name</th><td>' . htmlspecialchars($booking['customer_name']) . '</td></tr>';
        $html .= '<tr><th>Customer Email</th><td>' . htmlspecialchars($booking['customer_email']) . '</td></tr>';
        $html .= '<tr><th>Customer Phone</th><td>' . htmlspecialchars($booking['customer_phone']) . '</td></tr>';
        $html .= '<tr><th>Number of Guests</th><td>' . ($booking['number_of_guests'] ?? 'N/A') . '</td></tr>';
        $html .= '<tr><th>Special Requirements</th><td>' . htmlspecialchars($booking['special_requirements'] ?? 'None') . '</td></tr>';
        $html .= '<tr><th>Total Cost</th><td><strong>$' . number_format($booking['total_cost'], 2) . '</strong></td></tr>';
        $html .= '<tr><th>Status</th><td>' . ($statusLabels[$booking['status']] ?? $booking['status']) . '</td></tr>';
        $html .= '<tr><th>Booked By</th><td>' . htmlspecialchars($booking['booked_by_name'] ?? 'N/A') . '</td></tr>';
        $html .= '<tr><th>Created At</th><td>' . date('F d, Y h:i A', strtotime($booking['created_at'])) . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
        $html .= '</div>';
        
        echo json_encode(['success' => true, 'html' => $html]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
    }
    
    mysqli_stmt_close($stmt);
    exit;
}

// Update Booking Status
if (isset($_POST['action']) && $_POST['action'] == 'update_booking_status') {
    header('Content-Type: application/json');
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    // Check permissions
    if (!hasPermission('facility.update') && !hasRole('super_admin') && !hasRole('administrator')) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update bookings']);
        exit;
    }
    
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? '');
    
    if ($booking_id <= 0 || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }
    
    $allowed_statuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
    if (!in_array($status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    $query = "UPDATE facility_bookings SET status = ?, updated_at = NOW() WHERE booking_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "si", $status, $booking_id);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Booking status updated to ' . ucfirst($status)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating status: ' . mysqli_error($connection)]);
    }
    
    mysqli_stmt_close($stmt);
    exit;
}

// ============================================
// Send Promotion Email
// ============================================
if (isset($_POST['action']) && $_POST['action'] == 'send_promotion_email') {
    header('Content-Type: application/json');
    
    // Security checks
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    // Check permissions - only admins can send promotion emails
    if (!hasRole('super_admin') && !hasRole('administrator')) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to send promotion emails']);
        exit;
    }
    
    $promotion_id = intval($_POST['promotion_id'] ?? 0);
    $recipient_type = sanitizeInput($_POST['recipient_type'] ?? 'all'); // 'all' or 'single'
    $guest_id = intval($_POST['guest_id'] ?? 0);
    
    if ($promotion_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid promotion ID']);
        exit;
    }
    
    try {
        require_once __DIR__ . '/includes/email_notifications.php';
        
        if ($recipient_type === 'single' && $guest_id > 0) {
            // Send to single guest
            $result = sendPromotionEmail($promotion_id, $guest_id);
        } else {
            // Send to all guests
            $result = sendPromotionEmail($promotion_id);
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error sending emails: ' . $e->getMessage()]);
    }
    
    exit;
}
