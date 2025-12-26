<?php
/**
 * Service Request Routing Functions
 * Automatically routes service requests to appropriate staff based on category
 */

/**
 * Get eligible staff for a service category
 * 
 * @param mysqli $connection Database connection
 * @param string $category Service category
 * @param int $branch_id Branch ID (optional)
 * @return int|null Staff ID or null if no staff found
 */
function getEligibleStaffForCategory($connection, $category, $branch_id = null) {
    // Define category to role mapping
    // IMPORTANT: Dining and Transport services are automatically assigned to Concierge (role_id = 7)
    $categoryRoleMap = [
        'housekeeping' => 5,      // housekeeping_staff role
        'maintenance' => 5,       // housekeeping_staff role (handles maintenance too)
        'transport' => 7,         // concierge role - AUTO ASSIGNED
        'dining' => 7,            // concierge role - AUTO ASSIGNED
        'room_service' => 5,      // housekeeping_staff role
        'concierge' => 7,         // concierge role
        'other' => null           // No automatic assignment for other
    ];
    
    // Get the role ID for this category
    $role_id = $categoryRoleMap[$category] ?? null;
    
    if (!$role_id) {
        error_log("No role mapping for category: $category");
        return null; // No automatic assignment for this category
    }
    
    error_log("Looking for staff with role_id=$role_id for category=$category");
    
    // Build query to find eligible staff
    // IMPORTANT: When branch_id is provided, ONLY assign to staff in that branch
    // Super admin can manually assign across branches if needed
    
    // Query to find eligible staff with matching role_id
    // Use LEFT JOIN to include staff even if they don't have user accounts yet
    $query = "SELECT s.staff_id, 
              s.staff_name, 
              s.branch_id,
              (SELECT COUNT(*) FROM service_requests sr 
               WHERE sr.assigned_to = s.staff_id
               AND sr.status IN ('pending', 'assigned', 'in_progress')) as active_requests
              FROM staff s
              LEFT JOIN user u ON s.user_id = u.id
              WHERE s.role_id = ?
              AND (u.status = 'active' OR u.id IS NULL)";
    
    $params = [$role_id];
    $types = 'i';
    
    // CRITICAL: If branch_id is provided, ONLY assign to staff in that specific branch
    // No fallback to NULL branch_id - requests must go to the correct branch
    if ($branch_id !== null) {
        $query .= " AND s.branch_id = ?";
        $params[] = $branch_id;
        $types .= 'i';
    }
    
    // Order by: least active requests first (workload balancing)
    $query .= " ORDER BY 
                active_requests ASC,
                s.staff_id ASC
                LIMIT 1";
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        error_log("Failed to prepare eligible staff query: " . mysqli_error($connection));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Failed to execute eligible staff query: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return null;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $found_staff_id = intval($row['staff_id']);
        error_log("Found eligible staff: staff_id=$found_staff_id, name={$row['staff_name']}, role_id=$role_id, category=$category");
        mysqli_stmt_close($stmt);
        return $found_staff_id;
    }
    
    // No staff found - log for debugging
    error_log("No eligible staff found for role_id=$role_id, category=$category, branch_id=" . ($branch_id ?? 'NULL'));
    mysqli_stmt_close($stmt);
    return null;
}

/**
 * Auto-assign service request to appropriate staff
 * 
 * @param mysqli $connection Database connection
 * @param int $request_id Service request ID
 * @return bool True if assigned, false otherwise
 */
function autoAssignServiceRequest($connection, $request_id) {
    // Get service request details including branch_id from booking's room
    // Branch is stored in room table, not booking table
    $query = "SELECT sr.request_id, sr.booking_id, st.category, r.branch_id
              FROM service_requests sr
              INNER JOIN service_types st ON sr.service_type_id = st.service_type_id
              LEFT JOIN booking b ON sr.booking_id = b.booking_id
              LEFT JOIN room r ON b.room_id = r.room_id
              WHERE sr.request_id = ?
              LIMIT 1";
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        error_log("Failed to prepare service request query: " . mysqli_error($connection));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $request = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$request) {
        error_log("Service request not found: " . $request_id);
        return false;
    }
    
    // If no branch_id from booking, try to get from guest's most recent booking
    $branch_id = $request['branch_id'];
    if (!$branch_id) {
        // Get guest_id from the service request
        $guestQuery = "SELECT guest_id FROM service_requests WHERE request_id = ? LIMIT 1";
        $guestStmt = mysqli_prepare($connection, $guestQuery);
        if ($guestStmt) {
            mysqli_stmt_bind_param($guestStmt, "i", $request_id);
            mysqli_stmt_execute($guestStmt);
            $guestResult = mysqli_stmt_get_result($guestStmt);
            if ($guestRow = mysqli_fetch_assoc($guestResult)) {
                $guest_id = $guestRow['guest_id'];
                
                // Get branch from guest's most recent booking
                $branchQuery = "SELECT r.branch_id 
                               FROM booking b
                               INNER JOIN room r ON b.room_id = r.room_id
                               WHERE b.guest_id = ?
                               ORDER BY b.booking_id DESC
                               LIMIT 1";
                $branchStmt = mysqli_prepare($connection, $branchQuery);
                if ($branchStmt) {
                    mysqli_stmt_bind_param($branchStmt, "i", $guest_id);
                    mysqli_stmt_execute($branchStmt);
                    $branchResult = mysqli_stmt_get_result($branchStmt);
                    if ($branchRow = mysqli_fetch_assoc($branchResult)) {
                        $branch_id = $branchRow['branch_id'];
                    }
                    mysqli_stmt_close($branchStmt);
                }
            }
            mysqli_stmt_close($guestStmt);
        }
    }
    
    if (!$branch_id) {
        error_log("Auto-assign: No branch_id found for request #$request_id - cannot auto-assign without branch");
        return false;
    }
    
    // Get eligible staff for this category in the specific branch
    $staff_id = getEligibleStaffForCategory($connection, $request['category'], $branch_id);
    
    if (!$staff_id) {
        // No eligible staff found - request remains pending for manual assignment
        error_log("Auto-assign: No eligible staff found for category: " . $request['category'] . " (request_id: $request_id)");
        return false;
    }
    
    // Log the assignment attempt
    error_log("Auto-assign: Attempting to assign request #$request_id (category: {$request['category']}) to staff #$staff_id");
    
    // Assign the request to the staff member
    $updateQuery = "UPDATE service_requests 
                   SET assigned_to = ?, 
                       status = 'assigned', 
                       assigned_at = NOW() 
                   WHERE request_id = ?";
    
    $updateStmt = mysqli_prepare($connection, $updateQuery);
    if (!$updateStmt) {
        error_log("Failed to prepare update query: " . mysqli_error($connection));
        return false;
    }
    
    mysqli_stmt_bind_param($updateStmt, "ii", $staff_id, $request_id);
    $success = mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);
    
    if ($success) {
        error_log("Auto-assign: Successfully assigned request #$request_id to staff #$staff_id (category: {$request['category']})");
        
        // Log the auto-assignment
        if (function_exists('logSecurityEvent')) {
            try {
                logSecurityEvent('service_request_auto_assigned', [
                    'request_id' => $request_id,
                    'staff_id' => $staff_id,
                    'category' => $request['category']
                ]);
            } catch (Exception $e) {
                error_log("Auto-assign: Failed to log security event: " . $e->getMessage());
            }
        }
    } else {
        $error = mysqli_error($connection);
        error_log("Auto-assign: Failed to assign request #$request_id to staff #$staff_id. Error: " . $error);
    }
    
    return $success;
}

/**
 * Get service category display name
 * 
 * @param string $category Category code
 * @return string Display name
 */
function getCategoryDisplayName($category) {
    $names = [
        'room_service' => 'Room Service',
        'housekeeping' => 'Housekeeping',
        'maintenance' => 'Maintenance',
        'dining' => 'Dining',
        'transport' => 'Transport',
        'concierge' => 'Concierge',
        'other' => 'Other'
    ];
    
    return $names[$category] ?? ucfirst($category);
}

/**
 * Get staff role name by service category
 * 
 * @param string $category Service category
 * @return string Role name
 */
function getStaffRoleForCategory($category) {
    $roleMap = [
        'housekeeping' => 'Housekeeping Staff',
        'maintenance' => 'Housekeeping Staff',
        'room_service' => 'Housekeeping Staff',
        'transport' => 'Concierge',
        'dining' => 'Concierge',
        'concierge' => 'Concierge',
        'other' => 'Any Staff'
    ];
    
    return $roleMap[$category] ?? 'Staff';
}
