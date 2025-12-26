<?php
/**
 * Facility Management Helper Functions
 * Includes conflict detection and availability checking
 */

/**
 * Check if a facility is available for a given date and time range
 * 
 * @param int $facility_id
 * @param string $booking_date Format: YYYY-MM-DD
 * @param string $start_time Format: HH:MM:SS
 * @param string $end_time Format: HH:MM:SS
 * @param int|null $exclude_booking_id Booking ID to exclude (for updates)
 * @return array ['available' => bool, 'conflicts' => array]
 */
function checkFacilityAvailability($facility_id, $booking_date, $start_time, $end_time, $exclude_booking_id = null) {
    global $connection;
    
    $conflicts = [];
    
    // Check for booking conflicts
    $query = "SELECT fb.*, f.facility_name 
              FROM facility_bookings fb
              JOIN facilities f ON fb.facility_id = f.facility_id
              WHERE fb.facility_id = ? 
              AND fb.booking_date = ? 
              AND fb.status IN ('pending', 'confirmed', 'in_progress')
              AND (
                  (fb.start_time < ? AND fb.end_time > ?) OR
                  (fb.start_time < ? AND fb.end_time > ?) OR
                  (fb.start_time >= ? AND fb.end_time <= ?)
              )";
    
    // Parameters: facility_id (i), booking_date (s), then 6 time comparisons (s each)
    // Total: 1 integer + 7 strings = 8 parameters
    $params = [$facility_id, $booking_date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time];
    
    if ($exclude_booking_id) {
        $query .= " AND fb.booking_id != ?";
        $params[] = $exclude_booking_id;
    }
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        return ['available' => false, 'conflicts' => [], 'error' => 'Database error: ' . mysqli_error($connection)];
    }
    
    // Build type string: facility_id is integer (i), rest are strings (s)
    // Base: 1 integer + 7 strings = 'isssssss'
    $types = 'i' . str_repeat('s', 7);
    if ($exclude_booking_id) {
        $types .= 'i'; // exclude_booking_id is integer
    }
    
    if (!mysqli_stmt_bind_param($stmt, $types, ...$params)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return ['available' => false, 'conflicts' => [], 'error' => 'Bind error: ' . $error];
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        return ['available' => false, 'conflicts' => [], 'error' => 'Execute error: ' . $error];
    }
    
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $conflicts[] = [
            'booking_id' => $row['booking_id'],
            'booking_reference' => $row['booking_reference'],
            'event_name' => $row['event_name'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'status' => $row['status']
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    // Maintenance conflict checking removed - facility_maintenance table is not used
    
    return [
        'available' => empty($conflicts),
        'conflicts' => $conflicts
    ];
}

/**
 * Get facility availability for a date range
 * 
 * @param int $facility_id
 * @param string $start_date
 * @param string $end_date
 * @return array
 */
function getFacilityAvailabilityCalendar($facility_id, $start_date, $end_date) {
    global $connection;
    
    $bookings = [];
    
    $query = "SELECT fb.*, 
              CONCAT(fb.booking_date, ' ', fb.start_time) as start_datetime,
              CONCAT(fb.booking_date, ' ', fb.end_time) as end_datetime
              FROM facility_bookings fb
              WHERE fb.facility_id = ? 
              AND fb.booking_date BETWEEN ? AND ?
              AND fb.status IN ('pending', 'confirmed', 'in_progress')
              ORDER BY fb.booking_date, fb.start_time";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, 'iss', $facility_id, $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $bookings[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    
    return $bookings;
}

/**
 * Generate unique booking reference
 * Format: FH-YYYY-NNNNN
 */
function generateBookingReference() {
    global $connection;
    
    $year = date('Y');
    $prefix = 'FH-' . $year . '-';
    
    // Get the latest booking number for this year
    $query = "SELECT booking_reference FROM facility_bookings 
              WHERE booking_reference LIKE ? 
              ORDER BY booking_id DESC LIMIT 1";
    
    $stmt = mysqli_prepare($connection, $query);
    $search = $prefix . '%';
    mysqli_stmt_bind_param($stmt, 's', $search);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Extract number and increment
        $last_ref = $row['booking_reference'];
        $number = intval(substr($last_ref, -5)) + 1;
    } else {
        $number = 1;
    }
    
    mysqli_stmt_close($stmt);
    
    return $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
}

/**
 * Calculate total cost based on hours and hourly rate
 */
function calculateFacilityCost($facility_id, $start_time, $end_time) {
    global $connection;
    
    $query = "SELECT hourly_rate FROM facilities WHERE facility_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, 'i', $facility_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $hourly_rate = $row['hourly_rate'];
        
        // Calculate hours difference
        $start = new DateTime($start_time);
        $end = new DateTime($end_time);
        $diff = $start->diff($end);
        $hours = $diff->h + ($diff->days * 24) + ($diff->i / 60);
        
        mysqli_stmt_close($stmt);
        
        return [
            'hours' => round($hours, 2),
            'hourly_rate' => $hourly_rate,
            'total_cost' => round($hours * $hourly_rate, 2)
        ];
    }
    
    mysqli_stmt_close($stmt);
    return null;
}

/**
 * Get upcoming bookings for a facility
 */
function getUpcomingBookings($facility_id, $limit = 10) {
    global $connection;
    
    $today = date('Y-m-d');
    $now = date('H:i:s');
    
    $query = "SELECT fb.*, f.facility_name
              FROM facility_bookings fb
              JOIN facilities f ON fb.facility_id = f.facility_id
              WHERE fb.facility_id = ? 
              AND (
                  (fb.booking_date > ?) OR 
                  (fb.booking_date = ? AND fb.end_time > ?)
              )
              AND fb.status IN ('pending', 'confirmed')
              ORDER BY fb.booking_date, fb.start_time
              LIMIT ?";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, 'isssi', $facility_id, $today, $today, $now, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $bookings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $bookings[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    
    return $bookings;
}

/**
 * Get facility utilization rate for a date range
 */
function getFacilityUtilization($facility_id, $start_date, $end_date) {
    global $connection;
    
    // Get total available hours (assuming 8 AM to 10 PM = 14 hours per day)
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $days = $start->diff($end)->days + 1;
    $total_available_hours = $days * 14;
    
    // Get total booked hours
    $query = "SELECT SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time))/3600) as total_hours
              FROM facility_bookings
              WHERE facility_id = ? 
              AND booking_date BETWEEN ? AND ?
              AND status IN ('confirmed', 'completed', 'in_progress')";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, 'iss', $facility_id, $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $row = mysqli_fetch_assoc($result);
    $booked_hours = $row['total_hours'] ?? 0;
    
    mysqli_stmt_close($stmt);
    
    $utilization_rate = $total_available_hours > 0 
        ? round(($booked_hours / $total_available_hours) * 100, 2) 
        : 0;
    
    return [
        'total_available_hours' => $total_available_hours,
        'booked_hours' => round($booked_hours, 2),
        'utilization_rate' => $utilization_rate
    ];
}
