<?php
/**
 * Promotions Helper Functions
 * Functions to fetch and display promotions for guests
 */

/**
 * Get active promotions for display
 * @param int|null $branch_id Optional branch ID to filter promotions
 * @return array Array of active promotions
 */
function getActivePromotions($branch_id = null) {
    global $connection;
    
    $today = date('Y-m-d');
    
    $query = "SELECT p.*, b.branch_name 
              FROM promotions p
              INNER JOIN branches b ON p.branch_id = b.branch_id
              WHERE p.status = 'active'
              AND p.start_date <= ?
              AND p.end_date >= ?
              AND (p.usage_limit IS NULL OR p.usage_count < p.usage_limit)";
    
    $params = [$today, $today];
    $types = 'ss';
    
    if ($branch_id !== null) {
        $query .= " AND p.branch_id = ?";
        $params[] = $branch_id;
        $types .= 'i';
    }
    
    $query .= " ORDER BY p.start_date DESC, p.promotion_id DESC";
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        return [];
    }
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $promotions = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $promotions[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    
    return $promotions;
}

/**
 * Format discount display text
 * @param array $promotion Promotion array
 * @return string Formatted discount text
 */
function formatPromotionDiscount($promotion) {
    if ($promotion['discount_type'] === 'percentage') {
        return number_format($promotion['discount_value'], 0) . '% OFF';
    } else {
        return 'LKR ' . number_format($promotion['discount_value'], 2) . ' OFF';
    }
}

/**
 * Format promotion dates
 * @param string $start_date Start date
 * @param string $end_date End date
 * @return string Formatted date range
 */
function formatPromotionDates($start_date, $end_date) {
    $start = date('M j, Y', strtotime($start_date));
    $end = date('M j, Y', strtotime($end_date));
    return $start . ' - ' . $end;
}

/**
 * Get applicable promotions for a room or room type
 * @param int|null $room_type_id Room type ID (optional)
 * @param int|null $branch_id Branch ID (required)
 * @param string|null $check_in_date Check-in date (optional, for date validation)
 * @param decimal|null $booking_amount Booking amount (optional, for min_purchase_amount validation)
 * @return array Array of applicable promotions
 */
function getApplicablePromotions($room_type_id = null, $branch_id = null, $check_in_date = null, $booking_amount = null) {
    global $connection;
    
    if (!$branch_id) {
        return [];
    }
    
    $today = date('Y-m-d');
    $check_date = $check_in_date ? date('Y-m-d', strtotime($check_in_date)) : $today;
    
    // Base query for active promotions
    $query = "SELECT p.*, b.branch_name 
              FROM promotions p
              INNER JOIN branches b ON p.branch_id = b.branch_id
              WHERE p.status = 'active'
              AND p.branch_id = ?
              AND p.start_date <= ?
              AND p.end_date >= ?
              AND (p.usage_limit IS NULL OR p.usage_count < p.usage_limit)
              AND (
                  p.applicable_to = 'all' 
                  OR (
                      p.applicable_to = 'room_booking' 
                      AND (p.room_type_id IS NULL" . ($room_type_id !== null ? " OR p.room_type_id = ?" : "") . ")
                  )
              )";
    
    $params = [$branch_id, $check_date, $check_date];
    $types = 'iss';  // Fixed: integer, string, string (3 params)
    
    // Add room_type_id parameter only if it's not null
    if ($room_type_id !== null) {
        $params[] = $room_type_id;
        $types .= 'i';
    }
    
    // If booking amount is provided, check min_purchase_amount
    if ($booking_amount !== null) {
        $query .= " AND (p.min_purchase_amount IS NULL OR p.min_purchase_amount = 0 OR p.min_purchase_amount <= ?)";
        $params[] = $booking_amount;
        $types .= 'd';
    }
    
    $query .= " ORDER BY p.discount_value DESC, p.promotion_id DESC";
    
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        error_log("getApplicablePromotions: Prepare failed - " . mysqli_error($connection));
        error_log("getApplicablePromotions: Query was: " . $query);
        error_log("getApplicablePromotions: Params count: " . count($params) . ", Types: " . $types);
        return [];
    }
    
    // Debug: Log parameter details
    error_log("getApplicablePromotions: Binding " . count($params) . " params with types: " . $types);
    
    if (!mysqli_stmt_bind_param($stmt, $types, ...$params)) {
        error_log("getApplicablePromotions: Bind failed - " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return [];
    }
    
    if (!mysqli_stmt_execute($stmt)) {
        error_log("getApplicablePromotions: Execute failed - " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return [];
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $promotions = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        // Calculate discount amount for display
        if ($booking_amount !== null) {
            if ($row['discount_type'] === 'percentage') {
                $discount_amount = ($booking_amount * $row['discount_value']) / 100;
                if ($row['max_discount_amount'] !== null && $discount_amount > $row['max_discount_amount']) {
                    $discount_amount = $row['max_discount_amount'];
                }
            } else {
                $discount_amount = $row['discount_value'];
            }
            $row['calculated_discount'] = $discount_amount;
            $row['discounted_price'] = $booking_amount - $discount_amount;
        }
        $promotions[] = $row;
    }
    
    mysqli_stmt_close($stmt);
    return $promotions;
}

