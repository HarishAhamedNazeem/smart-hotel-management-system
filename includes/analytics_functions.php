<?php
/**
 * Analytics Helper Functions
 * Real-time insights for hotel operations
 */

/**
 * Get current occupancy rate
 * @return array ['occupied' => int, 'total' => int, 'rate' => float]
 */
function getCurrentOccupancy($connection, $branch_id = null) {
    $today = date('Y-m-d');
    
    // Get total rooms
    $totalQuery = "SELECT COUNT(*) as total FROM room WHERE 1=1";
    if ($branch_id) {
        $totalQuery .= " AND branch_id = " . intval($branch_id);
    }
    $totalResult = mysqli_query($connection, $totalQuery);
    $total = mysqli_fetch_assoc($totalResult)['total'];
    
    // Get occupied rooms (active bookings)
    $occupiedQuery = "SELECT COUNT(DISTINCT r.room_id) as occupied 
                      FROM room r
                      INNER JOIN booking b ON r.room_id = b.room_id
                      WHERE b.status IN ('confirmed', 'checked_in')
                      AND b.check_in <= '$today'
                      AND b.check_out > '$today'";
    if ($branch_id) {
        $occupiedQuery .= " AND r.branch_id = " . intval($branch_id);
    }
    
    $occupiedResult = mysqli_query($connection, $occupiedQuery);
    $occupied = mysqli_fetch_assoc($occupiedResult)['occupied'];
    
    $rate = $total > 0 ? ($occupied / $total) * 100 : 0;
    
    return [
        'occupied' => $occupied,
        'total' => $total,
        'available' => $total - $occupied,
        'rate' => round($rate, 2)
    ];
}

/**
 * Get revenue summary for a date range
 */
function getRevenueSummary($connection, $start_date, $end_date, $branch_id = null) {
    $query = "SELECT 
                SUM(total_price) as total_revenue,
                COUNT(*) as total_bookings,
                AVG(total_price) as avg_booking_value,
                SUM(CASE WHEN payment_status = 1 THEN total_price ELSE 0 END) as paid_revenue,
                SUM(CASE WHEN payment_status = 0 THEN total_price ELSE 0 END) as pending_revenue
              FROM booking b
              INNER JOIN room r ON b.room_id = r.room_id
              WHERE b.check_in BETWEEN '$start_date' AND '$end_date'
              AND b.status NOT IN ('cancelled')";
    
    if ($branch_id) {
        $query .= " AND r.branch_id = " . intval($branch_id);
    }
    
    $result = mysqli_query($connection, $query);
    return mysqli_fetch_assoc($result);
}

/**
 * Get booking trends by day (last 30 days)
 */
function getBookingTrends($connection, $days = 30, $branch_id = null) {
    $query = "SELECT 
                DATE(check_in) as day,
                COUNT(*) as bookings,
                SUM(total_price) as revenue,
                AVG(total_price) as avg_value
              FROM booking b
              INNER JOIN room r ON b.room_id = r.room_id
              WHERE b.check_in >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
              AND b.status NOT IN ('cancelled')";
    
    if ($branch_id) {
        $query .= " AND r.branch_id = " . intval($branch_id);
    }
    
    $query .= " GROUP BY DATE(check_in)
                ORDER BY day ASC";
    
    $result = mysqli_query($connection, $query);
    $trends = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $trends[] = $row;
    }
    
    // Fill in missing days with zero values
    $allDays = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $allDays[$date] = [
            'day' => $date,
            'bookings' => 0,
            'revenue' => 0,
            'avg_value' => 0
        ];
    }
    
    // Merge actual data
    foreach ($trends as $trend) {
        if (isset($allDays[$trend['day']])) {
            $allDays[$trend['day']] = $trend;
        }
    }
    
    return array_values($allDays);
}

/**
 * Get top performing room types
 */
function getTopRoomTypes($connection, $limit = 5, $branch_id = null) {
    $query = "SELECT 
                rt.room_type,
                COUNT(b.booking_id) as bookings,
                SUM(b.total_price) as revenue,
                AVG(b.total_price) as avg_rate
              FROM booking b
              INNER JOIN room r ON b.room_id = r.room_id
              INNER JOIN room_type rt ON r.room_type_id = rt.room_type_id
              WHERE b.check_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND b.status NOT IN ('cancelled')";
    
    if ($branch_id) {
        $query .= " AND r.branch_id = " . intval($branch_id);
    }
    
    $query .= " GROUP BY rt.room_type
                ORDER BY revenue DESC
                LIMIT $limit";
    
    $result = mysqli_query($connection, $query);
    $types = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $types[] = $row;
    }
    return $types;
}

/**
 * Get service performance metrics
 */
function getServicePerformance($connection, $branch_id = null) {
    $query = "SELECT 
                s.status,
                COUNT(*) as count,
                0 as avg_response_time
              FROM service_requests s
              LEFT JOIN booking b ON s.booking_id = b.booking_id
              LEFT JOIN room r ON b.room_id = r.room_id
              WHERE s.request_id IS NOT NULL";
    
    if ($branch_id) {
        $query .= " AND r.branch_id = " . intval($branch_id);
    }
    
    $query .= " GROUP BY s.status";
    
    $result = mysqli_query($connection, $query);
    $performance = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $performance[$row['status']] = $row;
    }
    return $performance;
}

/**
 * Get guest demographics
 */
function getGuestDemographics($connection, $branch_id = null) {
    // Note: guests table doesn't have gender or birthdate columns
    $query = "SELECT 
                COUNT(DISTINCT g.guest_id) as total_guests
              FROM guests g
              INNER JOIN booking b ON g.guest_id = b.guest_id
              INNER JOIN room r ON b.room_id = r.room_id
              WHERE b.check_in >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
    
    if ($branch_id) {
        $query .= " AND r.branch_id = " . intval($branch_id);
    }
    
    $result = mysqli_query($connection, $query);
    $data = mysqli_fetch_assoc($result);
    
    // Return with placeholder values for missing demographic fields
    return [
        'total_guests' => $data['total_guests'] ?? 0,
        'male_guests' => 0,  // Not tracked in database
        'female_guests' => 0,  // Not tracked in database
        'avg_age' => 0  // Not tracked in database
    ];
}

/**
 * Get upcoming check-ins and check-outs
 */
function getUpcomingActivity($connection, $days = 7, $branch_id = null) {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+$days days"));
    
    // Check-ins
    $checkInsQuery = "SELECT COUNT(*) as count
                      FROM booking b
                      INNER JOIN room r ON b.room_id = r.room_id
                      WHERE b.check_in BETWEEN '$start_date' AND '$end_date'
                      AND b.status = 'confirmed'";
    if ($branch_id) {
        $checkInsQuery .= " AND r.branch_id = " . intval($branch_id);
    }
    
    $checkInsResult = mysqli_query($connection, $checkInsQuery);
    $checkIns = mysqli_fetch_assoc($checkInsResult)['count'];
    
    // Check-outs
    $checkOutsQuery = "SELECT COUNT(*) as count
                       FROM booking b
                       INNER JOIN room r ON b.room_id = r.room_id
                       WHERE b.check_out BETWEEN '$start_date' AND '$end_date'
                       AND b.status IN ('confirmed', 'checked_in')";
    if ($branch_id) {
        $checkOutsQuery .= " AND r.branch_id = " . intval($branch_id);
    }
    
    $checkOutsResult = mysqli_query($connection, $checkOutsQuery);
    $checkOuts = mysqli_fetch_assoc($checkOutsResult)['count'];
    
    return [
        'check_ins' => $checkIns,
        'check_outs' => $checkOuts
    ];
}

/**
 * Get average length of stay
 */
function getAverageLengthOfStay($connection, $branch_id = null) {
    $query = "SELECT 
                AVG(DATEDIFF(check_out, check_in)) as avg_los
              FROM booking b
              INNER JOIN room r ON b.room_id = r.room_id
              WHERE b.booking_id IS NOT NULL
              AND b.status NOT IN ('cancelled')";
    
    if ($branch_id) {
        $query .= " AND r.branch_id = " . intval($branch_id);
    }
    
    $result = mysqli_query($connection, $query);
    $los = mysqli_fetch_assoc($result)['avg_los'];
    return round($los, 1);
}

/**
 * Get cancellation rate
 */
function getCancellationRate($connection, $branch_id = null) {
    $query = "SELECT 
                COUNT(CASE WHEN b.status = 'cancelled' THEN 1 END) as cancelled,
                COUNT(*) as total
              FROM booking b
              INNER JOIN room r ON b.room_id = r.room_id
              WHERE b.check_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    
    if ($branch_id) {
        $query .= " AND r.branch_id = " . intval($branch_id);
    }
    
    $result = mysqli_query($connection, $query);
    $data = mysqli_fetch_assoc($result);
    $rate = $data['total'] > 0 ? ($data['cancelled'] / $data['total']) * 100 : 0;
    
    return [
        'cancelled' => $data['cancelled'],
        'total' => $data['total'],
        'rate' => round($rate, 2)
    ];
}

/**
 * Get revenue by source (direct, online, travel agency, etc.)
 */
function getRevenueBySource($connection, $branch_id = null) {
    // Note: booking table doesn't have booking_source column, returning all bookings as 'Direct'
    $query = "SELECT 
                'Direct' as booking_source,
                COUNT(*) as bookings,
                SUM(b.total_price) as revenue
              FROM booking b
              INNER JOIN room r ON b.room_id = r.room_id
              WHERE b.check_in >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND b.status NOT IN ('cancelled')";
    
    if ($branch_id) {
        $query .= " AND r.branch_id = " . intval($branch_id);
    }
    
    // No GROUP BY needed since we're returning a single hardcoded source
    
    $result = mysqli_query($connection, $query);
    $sources = [];
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $sources[] = $row;
    }
    return $sources;
}

/**
 * Get occupancy forecast
 */
function getOccupancyForecast($connection, $days = 30, $branch_id = null) {
    $forecast = [];
    
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        
        $query = "SELECT COUNT(DISTINCT b.room_id) as occupied
                  FROM booking b
                  INNER JOIN room r ON b.room_id = r.room_id
                  WHERE b.check_in <= '$date'
                  AND b.check_out > '$date'
                  AND b.status IN ('confirmed', 'checked_in')";
        
        if ($branch_id) {
            $query .= " AND r.branch_id = " . intval($branch_id);
        }
        
        $result = mysqli_query($connection, $query);
        $occupied = mysqli_fetch_assoc($result)['occupied'];
        
        $forecast[] = [
            'date' => $date,
            'occupied' => $occupied
        ];
    }
    
    return $forecast;
}
