<?php
/**
 * Real-time Room Availability Checker
 * Smart Hotel Management System
 */

/**
 * Helper function to get the correct soft delete column name for room table
 */
function getRoomDeleteColumnName() {
    global $connection;
    static $columnName = null;
    
    if ($columnName === null) {
        $checkDeleteStatus = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'deleteStatus'");
        if ($checkDeleteStatus && mysqli_num_rows($checkDeleteStatus) > 0) {
            $columnName = 'deleteStatus';
        } else {
            $checkIsDeleted = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'is_deleted'");
            if ($checkIsDeleted && mysqli_num_rows($checkIsDeleted) > 0) {
                $columnName = 'is_deleted';
            } else {
                $columnName = false; // Column doesn't exist
            }
        }
    }
    
    return $columnName;
}

/**
 * Check if a room is available for given dates
 */
function isRoomAvailable($room_id, $check_in, $check_out, $exclude_booking_id = null) {
    global $connection;
    
    if (!$connection) {
        error_log("isRoomAvailable: Database connection not available");
        return false;
    }
    
    // Convert dates to YYYY-MM-DD format for comparison
    // Handle both YYYY-MM-DD (from frontend) and dd-mm-yyyy (from database) formats
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in)) {
        $check_in_date = $check_in; // Already in YYYY-MM-DD format
    } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_in)) {
        $parts = explode('-', $check_in);
        $check_in_date = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    } else {
        $check_in_date = date('Y-m-d', strtotime($check_in));
    }
    
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) {
        $check_out_date = $check_out; // Already in YYYY-MM-DD format
    } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_out)) {
        $parts = explode('-', $check_out);
        $check_out_date = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    } else {
        $check_out_date = date('Y-m-d', strtotime($check_out));
    }
    
    // Validate converted dates
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out_date)) {
        error_log("isRoomAvailable: Invalid date format - check_in: $check_in ($check_in_date), check_out: $check_out ($check_out_date)");
        return false;
    }
    
    // Check if room exists and is not deleted
    $deleteColumn = getRoomDeleteColumnName();
    if ($deleteColumn) {
        $query = "SELECT room_id, status, $deleteColumn as deleteStatus FROM room WHERE room_id = ? AND $deleteColumn = 0";
    } else {
        $query = "SELECT room_id, status, 0 as deleteStatus FROM room WHERE room_id = ?";
    }
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        error_log("isRoomAvailable: Prepare failed - " . mysqli_error($connection));
        return false;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $room_id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("isRoomAvailable: Execute failed - " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        error_log("isRoomAvailable: Get result failed - " . mysqli_error($connection));
        mysqli_stmt_close($stmt);
        return false;
    }
    
    if (mysqli_num_rows($result) == 0) {
        mysqli_stmt_close($stmt);
        return false; // Room doesn't exist
    }
    
    $room = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Check if the room has any bookings that overlap with requested dates
    // Dates overlap if: existing.check_in < new.check_out AND existing.check_out > new.check_in
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
        error_log("isRoomAvailable: Booking table check_in column type = " . $row['DATA_TYPE'] . " (isDateType = " . ($isDateType ? 'true' : 'false') . ")");
    } else {
        error_log("isRoomAvailable: Could not determine booking table check_in column type");
    }
    
    // Debug: Check what bookings exist for this room
    $debugBookingsQuery = "SELECT booking_id, check_in, check_out, payment_status FROM booking WHERE room_id = ? LIMIT 10";
    $debugBookingsStmt = mysqli_prepare($connection, $debugBookingsQuery);
    if ($debugBookingsStmt) {
        mysqli_stmt_bind_param($debugBookingsStmt, "i", $room_id);
        mysqli_stmt_execute($debugBookingsStmt);
        $debugBookingsResult = mysqli_stmt_get_result($debugBookingsStmt);
        $debugBookingCount = mysqli_num_rows($debugBookingsResult);
        error_log("isRoomAvailable: Found $debugBookingCount booking(s) for room_id=$room_id");
        while ($debugBooking = mysqli_fetch_assoc($debugBookingsResult)) {
            error_log("  - Booking ID: " . $debugBooking['booking_id'] . ", check_in: '" . ($debugBooking['check_in'] ?? 'NULL') . "', check_out: '" . ($debugBooking['check_out'] ?? 'NULL') . "', payment_status: " . ($debugBooking['payment_status'] ?? 'NULL'));
        }
        mysqli_stmt_close($debugBookingsStmt);
    }
    
    if ($isDateType) {
        // Use DATE columns directly (much simpler and faster!)
        // Check for ANY booking (paid or unpaid) that overlaps with requested dates
        // Dates overlap if: existing.check_in < new.check_out AND existing.check_out > new.check_in
        // Note: check_out date means the room becomes available again on that date
        // So if existing booking is Dec 17-18, and new search is Dec 18-19, they DON'T overlap
        // But if existing booking is Dec 17-18, and new search is Dec 17-18, they DO overlap
        $overlapQuery = "SELECT booking_id, check_in, check_out FROM booking 
                        WHERE room_id = ? 
                        AND check_in IS NOT NULL 
                        AND check_out IS NOT NULL
                        AND check_in < ? 
                        AND check_out > ?";
    } else {
        // Fallback for VARCHAR columns (old format: dd-mm-yyyy)
        // Check for ANY booking (paid or unpaid) that overlaps with requested dates
        // Dates overlap if: existing.check_in < new.check_out AND existing.check_out > new.check_in
        // Convert VARCHAR dates to DATE for proper comparison
        $overlapQuery = "SELECT booking_id, check_in, check_out FROM booking 
                        WHERE room_id = ? 
                        AND check_in IS NOT NULL 
                        AND check_out IS NOT NULL
                        AND check_in != '' 
                        AND check_out != ''
                        AND STR_TO_DATE(check_in, '%d-%m-%Y') IS NOT NULL
                        AND STR_TO_DATE(check_out, '%d-%m-%Y') IS NOT NULL
                        AND STR_TO_DATE(check_in, '%d-%m-%Y') < ? 
                        AND STR_TO_DATE(check_out, '%d-%m-%Y') > ?";
    }
    
    if ($exclude_booking_id) {
        $overlapQuery .= " AND booking_id != ?";
    }
    
    $stmt = mysqli_prepare($connection, $overlapQuery);
    if (!$stmt) {
        $error = mysqli_error($connection);
        error_log("isRoomAvailable: Overlap query prepare failed - " . $error);
        // If STR_TO_DATE is causing issues, try alternative approach
        return false;
    }
    
    // Log the query parameters for debugging
    error_log("isRoomAvailable: Checking room_id=$room_id, check_in_date=$check_in_date, check_out_date=$check_out_date, isDateType=" . ($isDateType ? 'true' : 'false'));
    
    if ($exclude_booking_id) {
        mysqli_stmt_bind_param($stmt, "issi", $room_id, $check_out_date, $check_in_date, $exclude_booking_id);
        error_log("isRoomAvailable: Binding params - room_id=$room_id, check_out_date=$check_out_date, check_in_date=$check_in_date, exclude_booking_id=$exclude_booking_id");
    } else {
        mysqli_stmt_bind_param($stmt, "iss", $room_id, $check_out_date, $check_in_date);
        error_log("isRoomAvailable: Binding params - room_id=$room_id, check_out_date=$check_out_date, check_in_date=$check_in_date");
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        error_log("isRoomAvailable: Overlap query execute failed - " . $error);
        error_log("isRoomAvailable: Query was: " . $overlapQuery);
        mysqli_stmt_close($stmt);
        // If query fails, assume room is not available to be safe
        return false;
    }
    
    $overlapResult = mysqli_stmt_get_result($stmt);
    if (!$overlapResult) {
        $error = mysqli_error($connection);
        error_log("isRoomAvailable: Overlap query get result failed - " . $error);
        mysqli_stmt_close($stmt);
        return false;
    }
    
    $hasOverlap = mysqli_num_rows($overlapResult) > 0;
    
    // If there are overlapping bookings, get details for logging
    if ($hasOverlap) {
        mysqli_data_seek($overlapResult, 0);
        $conflictingBookings = [];
        while ($conflict = mysqli_fetch_assoc($overlapResult)) {
            $conflictingBookings[] = [
                'booking_id' => $conflict['booking_id'],
                'check_in' => $conflict['check_in'] ?? 'N/A',
                'check_out' => $conflict['check_out'] ?? 'N/A'
            ];
        }
        error_log("isRoomAvailable: Room $room_id has " . count($conflictingBookings) . " overlapping booking(s) for dates $check_in_date to $check_out_date");
        foreach ($conflictingBookings as $conflict) {
            error_log("  - Booking ID: " . $conflict['booking_id'] . " (check_in: " . $conflict['check_in'] . ", check_out: " . $conflict['check_out'] . ")");
        }
    } else {
        // Log when no overlap is found for debugging
        error_log("isRoomAvailable: Room $room_id - No overlapping bookings found for dates $check_in_date to $check_out_date");
        
        // Debug: Check if there are ANY bookings for this room
        $debugQuery = "SELECT booking_id, check_in, check_out FROM booking WHERE room_id = ? LIMIT 5";
        $debugStmt = mysqli_prepare($connection, $debugQuery);
        if ($debugStmt) {
            mysqli_stmt_bind_param($debugStmt, "i", $room_id);
            mysqli_stmt_execute($debugStmt);
            $debugResult = mysqli_stmt_get_result($debugStmt);
            $bookingCount = mysqli_num_rows($debugResult);
            if ($bookingCount > 0) {
                error_log("isRoomAvailable: Room $room_id has $bookingCount booking(s) in database:");
                while ($booking = mysqli_fetch_assoc($debugResult)) {
                    error_log("  - Booking ID: " . $booking['booking_id'] . " (check_in: " . ($booking['check_in'] ?? 'NULL') . ", check_out: " . ($booking['check_out'] ?? 'NULL') . ")");
                }
            } else {
                error_log("isRoomAvailable: Room $room_id has no bookings in database");
            }
            mysqli_stmt_close($debugStmt);
        }
    }
    
    mysqli_stmt_close($stmt);
    
    if ($hasOverlap) {
        return false; // Room is booked for overlapping dates
    }
    
    error_log("isRoomAvailable: Room $room_id is available for dates $check_in_date to $check_out_date");
    return true; // Room is available
}

/**
 * Get available rooms for given dates and room type
 * @param string $room_type Room type name
 * @param string $check_in Check-in date
 * @param string $check_out Check-out date
 * @param int|null $branch_id Branch ID to filter by (required)
 * @param int|null $exclude_booking_id Booking ID to exclude from availability check
 */
function getAvailableRooms($room_type, $check_in, $check_out, $branch_id = null, $exclude_booking_id = null) {
    global $connection;
    
    if (!$connection) {
        error_log("getAvailableRooms: Database connection not available");
        return [];
    }
    
    if (!$branch_id) {
        error_log("getAvailableRooms: Branch ID is required");
        return [];
    }
    
    error_log("getAvailableRooms called: room_type=$room_type, branch_id=$branch_id, check_in=$check_in, check_out=$check_out");
    
    $check_in_date = date('Y-m-d', strtotime($check_in));
    $check_out_date = date('Y-m-d', strtotime($check_out));
    
    // Try to query using room_type column directly first, then fallback to JOIN with room_type table
    // Check if room table has room_type_id column
    $checkColumnQuery = "SHOW COLUMNS FROM room LIKE 'room_type_id'";
    $columnResult = mysqli_query($connection, $checkColumnQuery);
    if (!$columnResult) {
        error_log("getAvailableRooms: Failed to check for room_type_id column - " . mysqli_error($connection));
        return [];
    }
    $hasRoomTypeId = mysqli_num_rows($columnResult) > 0;
    
    $availableRooms = [];
    
    if ($hasRoomTypeId) {
        // Use JOIN with room_type table
        // Filter by branch_id
        // Use room_type price (standard price for all rooms of this type)
        $query = "SELECT r.room_id, r.room_no, 
                         rt.price as price,
                         CASE 
                             WHEN r.max_person IS NOT NULL AND r.max_person > 0 THEN r.max_person
                             ELSE rt.max_person
                         END as max_person,
                         rt.room_type, rt.room_type_id, r.branch_id
                  FROM room r 
                  INNER JOIN room_type rt ON r.room_type_id = rt.room_type_id 
                  WHERE rt.room_type = ? AND r.branch_id = ? AND r.deleteStatus = 0";
    } else {
        // Use room_type column directly
        // Filter by branch_id
        $query = "SELECT r.room_id, r.room_no, r.room_type, r.price, r.max_person, r.branch_id 
                  FROM room r 
                  WHERE r.room_type = ? AND r.branch_id = ? AND r.deleteStatus = 0";
    }
    
    error_log("getAvailableRooms: Query = $query");
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        $error = mysqli_error($connection);
        error_log("getAvailableRooms: Prepare failed - " . $error);
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "si", $room_type, $branch_id);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        error_log("getAvailableRooms: Execute failed - " . $error);
        mysqli_stmt_close($stmt);
        return [];
    }
    
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        $error = mysqli_error($connection);
        error_log("getAvailableRooms: Get result failed - " . $error);
        mysqli_stmt_close($stmt);
        return [];
    }
    
    $roomCount = mysqli_num_rows($result);
    error_log("getAvailableRooms: Found $roomCount room(s) of type '$room_type' in branch $branch_id");
    
    $checkedCount = 0;
    $availableCount = 0;
    
    while ($room = mysqli_fetch_assoc($result)) {
        $checkedCount++;
        error_log("getAvailableRooms: Checking availability for room_id=" . $room['room_id'] . " (room_no=" . $room['room_no'] . ")");
        
        $isAvailable = isRoomAvailable($room['room_id'], $check_in, $check_out, $exclude_booking_id);
        error_log("getAvailableRooms: Room " . $room['room_id'] . " availability = " . ($isAvailable ? 'true' : 'false'));
        
        if ($isAvailable) {
            // Ensure max_person is set
            if (!isset($room['max_person'])) {
                $room['max_person'] = 2;
            }
            
            // Get applicable promotions for this room type
            if (!function_exists('getApplicablePromotions')) {
                require_once __DIR__ . '/promotions.php';
            }
            $room_type_id = isset($room['room_type_id']) ? $room['room_type_id'] : null;
            $room_price = isset($room['price']) ? floatval($room['price']) : 0;
            try {
                $promotions = getApplicablePromotions($room_type_id, $branch_id, $check_in, $room_price);
                $room['promotions'] = $promotions;
            } catch (Exception $e) {
                error_log("Error getting promotions for room: " . $e->getMessage());
                $room['promotions'] = [];
            }
            
            $availableRooms[] = $room;
            $availableCount++;
        }
    }
    
    mysqli_stmt_close($stmt);
    error_log("getAvailableRooms: Checked $checkedCount rooms, $availableCount available");
    return $availableRooms;
}

/**
 * Get all rooms for a branch with availability status
 * @param string $check_in Check-in date
 * @param string $check_out Check-out date
 * @param int|null $branch_id Branch ID to filter by (required)
 * @return array Array of rooms with 'is_available' flag
 */
function getAllRoomsWithAvailability($check_in, $check_out, $branch_id = null) {
    global $connection;
    
    if (!$connection) {
        error_log("getAllRoomsWithAvailability: Database connection not available");
        return [];
    }
    
    if (!$branch_id) {
        error_log("getAllRoomsWithAvailability: Branch ID is required");
        return [];
    }
    
    error_log("getAllRoomsWithAvailability called: branch_id=$branch_id, check_in=$check_in, check_out=$check_out");
    
    // Check if room table has room_type_id column
    $checkColumnQuery = "SHOW COLUMNS FROM room LIKE 'room_type_id'";
    $columnResult = mysqli_query($connection, $checkColumnQuery);
    if (!$columnResult) {
        error_log("getAllRoomsWithAvailability: Failed to check for room_type_id column - " . mysqli_error($connection));
        return [];
    }
    $hasRoomTypeId = mysqli_num_rows($columnResult) > 0;
    
    $allRooms = [];
    
    if ($hasRoomTypeId) {
        // Use JOIN with room_type table
        // Use room_type price (standard price for all rooms of this type)
        $query = "SELECT r.room_id, r.room_no, 
                         rt.price as price,
                         CASE 
                             WHEN r.max_person IS NOT NULL AND r.max_person > 0 THEN r.max_person
                             ELSE rt.max_person
                         END as max_person,
                         rt.room_type, rt.room_type_id, r.branch_id
                  FROM room r 
                  INNER JOIN room_type rt ON r.room_type_id = rt.room_type_id 
                  WHERE r.branch_id = ?";
        
        $deleteColumn = getRoomDeleteColumnName();
        if ($deleteColumn) {
            $query .= " AND r.$deleteColumn = 0";
        }
        
        $query .= " ORDER BY rt.room_type, r.room_no";
    } else {
        // Use room_type column directly
        $query = "SELECT r.room_id, r.room_no, r.room_type, r.price, r.max_person, r.branch_id 
                  FROM room r 
                  WHERE r.branch_id = ?";
        
        $deleteColumn = getRoomDeleteColumnName();
        if ($deleteColumn) {
            $query .= " AND r.$deleteColumn = 0";
        }
        
        $query .= " ORDER BY r.room_type, r.room_no";
    }
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        $error = mysqli_error($connection);
        error_log("getAllRoomsWithAvailability: Prepare failed - " . $error);
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $branch_id);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        error_log("getAllRoomsWithAvailability: Execute failed - " . $error);
        mysqli_stmt_close($stmt);
        return [];
    }
    
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        $error = mysqli_error($connection);
        error_log("getAllRoomsWithAvailability: Get result failed - " . $error);
        mysqli_stmt_close($stmt);
        return [];
    }
    
    while ($room = mysqli_fetch_assoc($result)) {
        // Check availability for each room
        error_log("getAllRoomsWithAvailability: Checking availability for room_id=" . $room['room_id'] . " (room_no=" . $room['room_no'] . ") with dates check_in=$check_in, check_out=$check_out");
        $isAvailable = isRoomAvailable($room['room_id'], $check_in, $check_out);
        
        // Log availability check for debugging
        error_log("getAllRoomsWithAvailability: Room " . $room['room_id'] . " (Room #" . $room['room_no'] . ") availability = " . ($isAvailable ? 'AVAILABLE' : 'UNAVAILABLE'));
        
        // Ensure max_person is set
        if (!isset($room['max_person'])) {
            $room['max_person'] = 2;
        }
        
        // Get applicable promotions for this room type (even if unavailable)
        if (!function_exists('getApplicablePromotions')) {
            require_once __DIR__ . '/promotions.php';
        }
        $room_type_id = isset($room['room_type_id']) ? $room['room_type_id'] : null;
        $room_price = isset($room['price']) ? floatval($room['price']) : 0;
        try {
            $promotions = getApplicablePromotions($room_type_id, $branch_id, $check_in, $room_price);
            $room['promotions'] = $promotions;
        } catch (Exception $e) {
            error_log("Error getting promotions for room: " . $e->getMessage());
            $room['promotions'] = [];
        }
        
        // Add availability status
        $room['is_available'] = $isAvailable;
        $allRooms[] = $room;
    }
    
    mysqli_stmt_close($stmt);
    error_log("getAllRoomsWithAvailability: Found " . count($allRooms) . " total room(s)");
    return $allRooms;
}

/**
 * Get all available room types for given dates and branch
 * @param string $check_in Check-in date
 * @param string $check_out Check-out date
 * @param int|null $branch_id Branch ID to filter by (required)
 */
function getAvailableRoomTypes($check_in, $check_out, $branch_id = null) {
    global $connection;
    
    if (!$connection) {
        error_log("getAvailableRoomTypes: Database connection not available");
        return [];
    }
    
    if (!$branch_id) {
        error_log("getAvailableRoomTypes: Branch ID is required");
        return [];
    }
    
    error_log("getAvailableRoomTypes called: branch_id=$branch_id, check_in=$check_in, check_out=$check_out");
    
    $check_in_date = date('Y-m-d', strtotime($check_in));
    $check_out_date = date('Y-m-d', strtotime($check_out));
    
    // Check if room table has room_type_id column
    $checkColumnQuery = "SHOW COLUMNS FROM room LIKE 'room_type_id'";
    $columnResult = mysqli_query($connection, $checkColumnQuery);
    if (!$columnResult) {
        error_log("getAvailableRoomTypes: Failed to check for room_type_id column - " . mysqli_error($connection));
        return [];
    }
    $hasRoomTypeId = mysqli_num_rows($columnResult) > 0;
    
    error_log("getAvailableRoomTypes: hasRoomTypeId = " . ($hasRoomTypeId ? 'true' : 'false'));
    
    $roomTypes = [];
    
    if ($hasRoomTypeId) {
        // Use JOIN with room_type table
        // Filter by branch_id
        $query = "SELECT DISTINCT rt.room_type, rt.price, rt.max_person,
                         COUNT(r.room_id) as total_rooms,
                         SUM(CASE WHEN r.deleteStatus = 0 THEN 1 ELSE 0 END) as active_rooms
                  FROM room r
                  INNER JOIN room_type rt ON r.room_type_id = rt.room_type_id
                  WHERE r.branch_id = ? AND r.deleteStatus = 0
                  GROUP BY rt.room_type, rt.price, rt.max_person";
    } else {
        // Use room_type column directly
        // Filter by branch_id
        $query = "SELECT DISTINCT r.room_type, r.price, r.max_person,
                         COUNT(r.room_id) as total_rooms,
                         SUM(CASE WHEN r.deleteStatus = 0 THEN 1 ELSE 0 END) as active_rooms
                  FROM room r
                  WHERE r.branch_id = ? AND r.deleteStatus = 0
                  GROUP BY r.room_type, r.price, r.max_person";
    }
    
    error_log("getAvailableRoomTypes: Query = $query");
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        $error = mysqli_error($connection);
        error_log("getAvailableRoomTypes: Prepare failed - " . $error);
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, "i", $branch_id);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        error_log("getAvailableRoomTypes: Execute failed - " . $error);
        mysqli_stmt_close($stmt);
        return [];
    }
    
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        $error = mysqli_error($connection);
        error_log("getAvailableRoomTypes: Get result failed - " . $error);
        mysqli_stmt_close($stmt);
        return [];
    }
    
    $roomTypeCount = mysqli_num_rows($result);
    error_log("getAvailableRoomTypes: Found $roomTypeCount room type(s) for branch $branch_id");
    
    if ($roomTypeCount == 0) {
        error_log("getAvailableRoomTypes: WARNING - No room types found for branch $branch_id");
        // This is not an error - just means no rooms for this branch
        mysqli_stmt_close($stmt);
        return [];
    }
    
    while ($roomType = mysqli_fetch_assoc($result)) {
        // Ensure max_person is set
        if (!isset($roomType['max_person'])) {
            $roomType['max_person'] = 2;
        }
        
        error_log("getAvailableRoomTypes: Checking availability for room type: " . $roomType['room_type']);
        
        // Count available rooms for this type
        $availableRooms = getAvailableRooms($roomType['room_type'], $check_in, $check_out, $branch_id);
        $roomType['available_count'] = count($availableRooms);
        $roomType['available_rooms'] = $availableRooms;
        
        error_log("getAvailableRoomTypes: Room type " . $roomType['room_type'] . " has " . $roomType['available_count'] . " available rooms");
        
        if ($roomType['available_count'] > 0) {
            $roomTypes[] = $roomType;
        }
    }
    
    mysqli_stmt_close($stmt);
    error_log("getAvailableRoomTypes: Returning " . count($roomTypes) . " room type(s) with available rooms");
    return $roomTypes;
}

/**
 * Calculate number of nights between two dates
 */
function calculateNights($check_in, $check_out) {
    $check_in_date = new DateTime($check_in);
    $check_out_date = new DateTime($check_out);
    $interval = $check_in_date->diff($check_out_date);
    return $interval->days;
}

/**
 * Calculate total price for booking
 */
function calculateTotalPrice($room_id, $check_in, $check_out) {
    global $connection;
    
    $nights = calculateNights($check_in, $check_out);
    
    // Get room price
    $query = "SELECT r.price FROM room r 
              WHERE r.room_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $room_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($room = mysqli_fetch_assoc($result)) {
        return $room['price'] * $nights;
    }
    
    return 0;
}

/**
 * Check if dates are valid (check-out must be after check-in)
 * Accepts dates in YYYY-MM-DD format
 */
function validateBookingDates($check_in, $check_out) {
    // Handle different date formats
    $check_in_date = null;
    $check_out_date = null;
    
    // Try YYYY-MM-DD format first (from frontend)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in)) {
        $check_in_date = strtotime($check_in);
    } 
    // Try dd-mm-yyyy format (from database)
    elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_in)) {
        $parts = explode('-', $check_in);
        $check_in_date = strtotime($parts[2] . '-' . $parts[1] . '-' . $parts[0]);
    } else {
        $check_in_date = strtotime($check_in);
    }
    
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) {
        $check_out_date = strtotime($check_out);
    } 
    elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_out)) {
        $parts = explode('-', $check_out);
        $check_out_date = strtotime($parts[2] . '-' . $parts[1] . '-' . $parts[0]);
    } else {
        $check_out_date = strtotime($check_out);
    }
    
    if ($check_in_date === false || $check_out_date === false) {
        return ['valid' => false, 'error' => 'Invalid date format'];
    }
    
    $today = strtotime('today');
    
    // Check-in must be today or in the future
    if ($check_in_date < $today) {
        return ['valid' => false, 'error' => 'Check-in date cannot be in the past'];
    }
    
    // Check-out must be after check-in
    if ($check_out_date <= $check_in_date) {
        return ['valid' => false, 'error' => 'Check-out date must be after check-in date'];
    }
    
    // Check minimum stay (if configured)
    $nights = calculateNights($check_in, $check_out);
    if ($nights < 1) {
        return ['valid' => false, 'error' => 'Minimum stay is 1 night'];
    }
    
    return ['valid' => true];
}

/**
 * Get booking conflicts for a room
 */
function getBookingConflicts($room_id, $check_in, $check_out, $exclude_booking_id = null) {
    global $connection;
    
    // Convert dates to YYYY-MM-DD format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in)) {
        $check_in_date = $check_in;
    } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_in)) {
        $parts = explode('-', $check_in);
        $check_in_date = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    } else {
        $check_in_date = date('Y-m-d', strtotime($check_in));
    }
    
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) {
        $check_out_date = $check_out;
    } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_out)) {
        $parts = explode('-', $check_out);
        $check_out_date = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    } else {
        $check_out_date = date('Y-m-d', strtotime($check_out));
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
    
    if ($isDateType) {
        // Use DATE columns directly
        $query = "SELECT b.booking_id, b.check_in, b.check_out, g.name as customer_name 
                  FROM booking b
                  JOIN guests g ON b.guest_id = g.guest_id
                  WHERE b.room_id = ? 
                  AND b.payment_status = 0
                  AND (
                      (b.check_in <= ? AND b.check_out > ?)
                      OR (b.check_in < ? AND b.check_out >= ?)
                      OR (b.check_in >= ? AND b.check_out <= ?)
                  )";
    } else {
        // Fallback for VARCHAR columns (old format: dd-mm-yyyy)
        $query = "SELECT b.booking_id, b.check_in, b.check_out, g.name as customer_name 
                  FROM booking b
                  JOIN guests g ON b.guest_id = g.guest_id
                  WHERE b.room_id = ? 
                  AND b.payment_status = 0
                  AND (
                      (STR_TO_DATE(b.check_in, '%d-%m-%Y') <= ? AND STR_TO_DATE(b.check_out, '%d-%m-%Y') > ?)
                      OR (STR_TO_DATE(b.check_in, '%d-%m-%Y') < ? AND STR_TO_DATE(b.check_out, '%d-%m-%Y') >= ?)
                      OR (STR_TO_DATE(b.check_in, '%d-%m-%Y') >= ? AND STR_TO_DATE(b.check_out, '%d-%m-%Y') <= ?)
                  )";
    }
    
    if ($exclude_booking_id) {
        $query .= " AND b.booking_id != ?";
    }
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        error_log("getBookingConflicts: Prepare failed - " . mysqli_error($connection));
        return [];
    }
    
    if ($isDateType) {
        if ($exclude_booking_id) {
            mysqli_stmt_bind_param($stmt, "isssssi", $room_id, $check_in_date, $check_in_date, $check_out_date, $check_out_date, $check_in_date, $check_out_date, $exclude_booking_id);
        } else {
            mysqli_stmt_bind_param($stmt, "isssss", $room_id, $check_in_date, $check_in_date, $check_out_date, $check_out_date, $check_in_date, $check_out_date);
        }
    } else {
        if ($exclude_booking_id) {
            mysqli_stmt_bind_param($stmt, "isssssi", $room_id, $check_in_date, $check_in_date, $check_out_date, $check_out_date, $check_in_date, $check_out_date, $exclude_booking_id);
        } else {
            mysqli_stmt_bind_param($stmt, "isssss", $room_id, $check_in_date, $check_in_date, $check_out_date, $check_out_date, $check_in_date, $check_out_date);
        }
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("getBookingConflicts: Execute failed - " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return [];
    }
    
    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        error_log("getBookingConflicts: Get result failed - " . mysqli_error($connection));
        mysqli_stmt_close($stmt);
        return [];
    }
    
    $conflicts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $conflicts[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $conflicts;
}

