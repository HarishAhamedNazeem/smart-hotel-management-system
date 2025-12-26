<?php
/**
 * Email Notification System
 * Smart Hotel Management System
 */

require_once __DIR__ . '/../config.php';

/**
 * Send email notification
 */
function sendEmailNotification($to, $subject, $message, $isHTML = true) {
    // Check if email notifications are enabled
    if (!ENABLE_EMAIL_NOTIFICATIONS) {
        return ['success' => false, 'message' => 'Email notifications are disabled'];
    }
    
    // Use PHP's mail() function (can be replaced with PHPMailer, SendGrid, etc.)
    $headers = [];
    
    if ($isHTML) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
    }
    
    $headers[] = 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . SMTP_FROM_EMAIL;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    
    $headersString = implode("\r\n", $headers);
    
    // Send email
    $result = @mail($to, $subject, $message, $headersString);
    
    if ($result) {
        return ['success' => true, 'message' => 'Email sent successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to send email'];
    }
}

/**
 * Queue email notification in database
 */
function queueEmailNotification($user_id, $guest_id, $subject, $message) {
    global $connection;
    
    $query = "INSERT INTO notifications (user_id, guest_id, type, subject, message, status) 
              VALUES (?, ?, 'email', ?, ?, 'pending')";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "iiss", $user_id, $guest_id, $subject, $message);
    
    if (mysqli_stmt_execute($stmt)) {
        $notification_id = mysqli_insert_id($connection);
        mysqli_stmt_close($stmt);
        return ['success' => true, 'notification_id' => $notification_id];
    } else {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Failed to queue notification'];
    }
}

/**
 * Send booking confirmation email
 */
function sendBookingConfirmation($booking_id, $guest_id) {
    global $connection;
    
    // Get booking details
    $query = "SELECT b.*, r.room_no, rt.room_type, rt.price,
             g.name as customer_name, g.email, g.contact_no
             FROM booking b
             JOIN room r ON b.room_id = r.room_id
             JOIN room_type rt ON r.room_type_id = rt.room_type_id
             JOIN guests g ON b.guest_id = g.guest_id
             WHERE b.booking_id = ? AND g.guest_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "ii", $booking_id, $guest_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$booking) {
        return ['success' => false, 'message' => 'Booking not found'];
    }
    
    // Calculate nights
    $check_in = DateTime::createFromFormat('d-m-Y', $booking['check_in']);
    $check_out = DateTime::createFromFormat('d-m-Y', $booking['check_out']);
    $nights = $check_in->diff($check_out)->days;
    
    // For guests, user_id is null
    $user_id = null;
    
    // Check notification preferences (if user_id exists)
    if ($user_id && !checkNotificationPreference($user_id, 'booking_confirmation', 'email')) {
        return ['success' => false, 'message' => 'Email notifications disabled for this user'];
    }
    
    // Create email content
    $subject = "Booking Confirmation - " . APP_NAME;
    $message = getBookingConfirmationTemplate($booking, $nights);
    
    // Queue notification
    $queueResult = queueEmailNotification($user_id, $guest_id, $subject, $message);
    
    // Send immediately if queueing fails or if configured to send immediately
    if (!$queueResult['success']) {
        return sendEmailNotification($booking['email'], $subject, $message);
    }
    
    return $queueResult;
}

/**
 * Send service request notification
 */
function sendServiceRequestNotification($request_id, $notification_type = 'created') {
    global $connection;
    
    // Get service request details
    $query = "SELECT sr.*, st.service_name, g.name as customer_name, g.email, g.guest_id
             FROM service_requests sr
             JOIN service_types st ON sr.service_type_id = st.service_type_id
             JOIN guests g ON sr.guest_id = g.guest_id
             WHERE sr.request_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $request_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $request = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$request) {
        return ['success' => false, 'message' => 'Service request not found'];
    }
    
    $user_id = null; // Guests don't have user_id
    $guest_id = $request['guest_id'];
    
    // Check notification preferences (if user_id exists)
    if ($user_id && !checkNotificationPreference($user_id, 'service_request_update', 'email')) {
        return ['success' => false, 'message' => 'Email notifications disabled'];
    }
    
    // Create email content based on notification type
    $subject = "Service Request " . ucfirst($notification_type) . " - " . APP_NAME;
    $message = getServiceRequestTemplate($request, $notification_type);
    
    // Queue notification
    return queueEmailNotification($user_id, $guest_id, $subject, $message);
}

/**
 * Send check-in reminder
 */
function sendCheckInReminder($booking_id) {
    global $connection;
    
    $query = "SELECT b.*, g.name as customer_name, g.email, g.guest_id
             FROM booking b
             JOIN guests g ON b.guest_id = g.guest_id
             WHERE b.booking_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$booking) {
        return ['success' => false, 'message' => 'Booking not found'];
    }
    
    $user_id = null; // Guests don't have user_id
    $guest_id = $booking['guest_id'];
    
    // Check notification preferences (if user_id exists)
    if ($user_id && !checkNotificationPreference($user_id, 'check_in_reminder', 'email')) {
        return ['success' => false, 'message' => 'Email notifications disabled'];
    }
    
    $subject = "Check-in Reminder - " . APP_NAME;
    $message = getCheckInReminderTemplate($booking);
    
    return queueEmailNotification($user_id, $guest_id, $subject, $message);
}

/**
 * Check notification preference
 */
if (!function_exists('checkNotificationPreference')) {
function checkNotificationPreference($user_id, $notification_type, $channel) {
    global $connection;
    
    $query = "SELECT {$channel}_enabled FROM notification_preferences 
              WHERE user_id = ? AND notification_type = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "is", $user_id, $notification_type);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $preference = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Default to enabled if no preference set
    if (!$preference) {
        return true;
    }
    
    return (bool)$preference[$channel . '_enabled'];
}
}

/**
 * Get booking confirmation email template
 */
function getBookingConfirmationTemplate($booking, $nights) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }
            .content { background: #F5F5F5; padding: 20px; }
            .booking-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . APP_NAME . '</h1>
                <h2>Booking Confirmation</h2>
            </div>
            <div class="content">
                <p>Dear ' . htmlspecialchars($booking['customer_name']) . ',</p>
                <p>Thank you for your booking! We are pleased to confirm your reservation.</p>
                
                <div class="booking-details">
                    <h3>Booking Details</h3>
                    <p><strong>Booking ID:</strong> #' . $booking['booking_id'] . '</p>
                    <p><strong>Room:</strong> ' . htmlspecialchars($booking['room_type']) . ' - ' . htmlspecialchars($booking['room_no']) . '</p>
                    <p><strong>Check-in:</strong> ' . htmlspecialchars($booking['check_in']) . '</p>
                    <p><strong>Check-out:</strong> ' . htmlspecialchars($booking['check_out']) . '</p>
                    <p><strong>Duration:</strong> ' . $nights . ' night(s)</p>
                    <p><strong>Total Amount:</strong> LKR ' . number_format($booking['total_price']) . '</p>
                    <p><strong>Payment Status:</strong> ' . ($booking['payment_status'] == 1 ? 'Paid' : 'Pending') . '</p>
                </div>
                
                <p>We look forward to welcoming you!</p>
                <p>If you have any questions, please contact us.</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Get service request email template
 */
function getServiceRequestTemplate($request, $notification_type) {
    $statusMessages = [
        'created' => 'Your service request has been received and is being processed.',
        'assigned' => 'Your service request has been assigned to our staff.',
        'in_progress' => 'Our staff is currently working on your service request.',
        'completed' => 'Your service request has been completed.',
        'cancelled' => 'Your service request has been cancelled.'
    ];
    
    $message = $statusMessages[$notification_type] ?? 'Your service request status has been updated.';
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }
            .content { background: #F5F5F5; padding: 20px; }
            .request-details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . APP_NAME . '</h1>
                <h2>Service Request Update</h2>
            </div>
            <div class="content">
                <p>Dear ' . htmlspecialchars($request['customer_name']) . ',</p>
                <p>' . $message . '</p>
                
                <div class="request-details">
                    <h3>Request Details</h3>
                    <p><strong>Request ID:</strong> #' . $request['request_id'] . '</p>
                    <p><strong>Service:</strong> ' . htmlspecialchars($request['service_name']) . '</p>
                    <p><strong>Title:</strong> ' . htmlspecialchars($request['request_title']) . '</p>
                    <p><strong>Status:</strong> ' . ucfirst($request['status']) . '</p>
                    <p><strong>Priority:</strong> ' . ucfirst($request['priority']) . '</p>
                </div>
                
                <p>Thank you for choosing our hotel!</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send check-out reminder
 */
function sendCheckOutReminder($booking_id) {
    global $connection;
    
    $query = "SELECT b.*, g.name as customer_name, g.email, g.guest_id
             FROM booking b
             JOIN guests g ON b.guest_id = g.guest_id
             WHERE b.booking_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $booking_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$booking) {
        return ['success' => false, 'message' => 'Booking not found'];
    }
    
    $user_id = null; // Guests don't have user_id
    $guest_id = $booking['guest_id'];
    
    // Check notification preferences (if user_id exists)
    if ($user_id && !checkNotificationPreference($user_id, 'check_out_reminder', 'email')) {
        return ['success' => false, 'message' => 'Email notifications disabled'];
    }
    
    $subject = "Check-out Reminder - " . APP_NAME;
    $message = getCheckOutReminderTemplate($booking);
    
    return queueEmailNotification($user_id, $guest_id, $subject, $message);
}

/**
 * Send promotion notification
 */
function sendPromotionNotification($promotion_id, $guest_id = null) {
    global $connection;
    
    // Get promotion details
    $query = "SELECT * FROM promotions WHERE promotion_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $promotion_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $promotion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$promotion) {
        return ['success' => false, 'message' => 'Promotion not found'];
    }
    
    // If guest_id is provided, send to specific guest
    if ($guest_id) {
        $guestQuery = "SELECT * FROM guests WHERE guest_id = ? AND status = 'active'";
        $guestStmt = mysqli_prepare($connection, $guestQuery);
        mysqli_stmt_bind_param($guestStmt, "i", $guest_id);
        mysqli_stmt_execute($guestStmt);
        $guestResult = mysqli_stmt_get_result($guestStmt);
        $guest = mysqli_fetch_assoc($guestResult);
        mysqli_stmt_close($guestStmt);
        
        if ($guest) {
            $subject = "Special Offer: " . $promotion['title'] . " - " . APP_NAME;
            $message = getPromotionEmailTemplate($promotion, $guest['name']);
            return queueEmailNotification(null, $guest_id, $subject, $message);
        }
    } else {
        // Send to all active guests
        $guestsQuery = "SELECT guest_id, name FROM guests WHERE status = 'active'";
        $guestsResult = mysqli_query($connection, $guestsQuery);
        
        $queued = 0;
        while ($guest = mysqli_fetch_assoc($guestsResult)) {
            $subject = "Special Offer: " . ($promotion['promotion_name'] ?? $promotion['title'] ?? 'Exclusive Promotion') . " - " . APP_NAME;
            $message = getPromotionEmailTemplate($promotion, $guest['name']);
            $result = queueEmailNotification(null, $guest['guest_id'], $subject, $message);
            if ($result['success']) {
                $queued++;
            }
        }
        
        return ['success' => true, 'message' => "Queued $queued promotion emails", 'queued' => $queued];
    }
    
    return ['success' => false, 'message' => 'No recipients found'];
}

/**
 * Get check-in reminder email template
 */
function getCheckInReminderTemplate($booking) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }
            .content { background: #F5F5F5; padding: 20px; }
            .reminder { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . APP_NAME . '</h1>
                <h2>Check-in Reminder</h2>
            </div>
            <div class="content">
                <p>Dear ' . htmlspecialchars($booking['customer_name']) . ',</p>
                <p>This is a friendly reminder about your upcoming check-in.</p>
                
                <div class="reminder">
                    <h3>Check-in Details</h3>
                    <p><strong>Check-in Date:</strong> ' . htmlspecialchars($booking['check_in']) . '</p>
                    <p><strong>Check-in Time:</strong> ' . CHECK_IN_TIME . '</p>
                    <p>We look forward to welcoming you!</p>
                </div>
                
                <p>If you have any questions, please contact us.</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Get check-out reminder email template
 */
function getCheckOutReminderTemplate($booking) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }
            .content { background: #F5F5F5; padding: 20px; }
            .reminder { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3D2C8D; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . APP_NAME . '</h1>
                <h2>Check-out Reminder</h2>
            </div>
            <div class="content">
                <p>Dear ' . htmlspecialchars($booking['customer_name']) . ',</p>
                <p>This is a friendly reminder about your upcoming check-out.</p>
                
                <div class="reminder">
                    <h3>Check-out Details</h3>
                    <p><strong>Check-out Date:</strong> ' . htmlspecialchars($booking['check_out']) . '</p>
                    <p><strong>Check-out Time:</strong> ' . CHECK_OUT_TIME . '</p>
                    <p>We hope you enjoyed your stay with us!</p>
                </div>
                
                <p>Thank you for choosing our hotel. We look forward to welcoming you again!</p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Get promotion email template
 */
function getPromotionEmailTemplate($promotion, $guestName) {
    // Handle both date field names (start_date/valid_from and end_date/valid_to)
    $startDate = $promotion['start_date'] ?? $promotion['valid_from'] ?? date('Y-m-d');
    $endDate = $promotion['end_date'] ?? $promotion['valid_to'] ?? date('Y-m-d', strtotime('+30 days'));
    
    $validFrom = date('F j, Y', strtotime($startDate));
    $validTo = date('F j, Y', strtotime($endDate));
    
    // Get promotion details with field name compatibility
    $promoTitle = $promotion['promotion_name'] ?? $promotion['title'] ?? 'Special Offer';
    $promoCode = $promotion['promotion_code'] ?? $promotion['promo_code'] ?? '';
    $promoDescription = $promotion['description'] ?? '';
    $discountValue = $promotion['discount_value'] ?? $promotion['discount_percentage'] ?? 0;
    $discountType = $promotion['discount_type'] ?? 'percentage';
    $minAmount = $promotion['min_purchase_amount'] ?? $promotion['min_booking_amount'] ?? 0;
    $maxAmount = $promotion['max_discount_amount'] ?? 0;
    
    // Format discount display
    $discountDisplay = $discountType === 'percentage' 
        ? $discountValue . '% OFF' 
        : CURRENCY_SYMBOL . ' ' . number_format($discountValue) . ' OFF';
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2A1F5F 0%, #3D2C8D 50%, #5A4BCF 100%); color: white; padding: 20px; text-align: center; }
            .content { background: #F5F5F5; padding: 20px; }
            .promotion { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #5A4BCF; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
            .discount-badge { background: #FF6B6B; color: white; padding: 10px 20px; border-radius: 25px; display: inline-block; font-size: 20px; font-weight: bold; margin: 10px 0; }
            .promo-code { background: #FFF3CD; border: 2px dashed #FFC107; padding: 15px; text-align: center; margin: 15px 0; }
            .promo-code-text { font-size: 24px; font-weight: bold; color: #2A1F5F; letter-spacing: 2px; }
            .cta-button { background: #3D2C8D; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 15px 0; font-weight: bold; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . APP_NAME . '</h1>
                <h2>🎉 Special Offer Just For You!</h2>
            </div>
            <div class="content">
                <p>Dear ' . htmlspecialchars($guestName) . ',</p>
                <p>We have an exclusive offer that we think you\'ll love!</p>
                
                <div class="promotion">
                    <h2 style="color: #3D2C8D; margin-top: 0;">' . htmlspecialchars($promoTitle) . '</h2>
                    
                    <div class="discount-badge">' . $discountDisplay . '</div>
                    
                    ' . (!empty($promoDescription) ? '<p style="font-size: 16px; margin: 15px 0;">' . nl2br(htmlspecialchars($promoDescription)) . '</p>' : '') . '
                    
                    ' . (!empty($promoCode) ? '
                    <div class="promo-code">
                        <p style="margin: 0 0 5px 0; font-size: 14px;">Use Promo Code:</p>
                        <div class="promo-code-text">' . htmlspecialchars($promoCode) . '</div>
                    </div>' : '') . '
                    
                    <p><strong>Valid Period:</strong></p>
                    <p>From: ' . $validFrom . '<br>To: ' . $validTo . '</p>
                    
                    ' . ($minAmount > 0 ? '<p><strong>Minimum Booking:</strong> ' . CURRENCY_SYMBOL . ' ' . number_format($minAmount) . '</p>' : '') . '
                    ' . ($maxAmount > 0 ? '<p><strong>Maximum Discount:</strong> ' . CURRENCY_SYMBOL . ' ' . number_format($maxAmount) . '</p>' : '') . '
                    
                    <div style="text-align: center;">
                        <a href="' . APP_URL . '/guest_booking.php' . (!empty($promoCode) ? '?promo=' . urlencode($promoCode) : '') . '" class="cta-button">Book Now</a>
                    </div>
                </div>
                
                <p>Don\'t miss out on this amazing offer! Book your stay today.</p>
            </div>
            <div class="footer">
                <p>This offer is valid from ' . $validFrom . ' to ' . $validTo . '</p>
                <p>&copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Process notification queue
 */
function processNotificationQueue($limit = 50) {
    global $connection;
    
    // Get pending notifications
    $query = "SELECT * FROM notifications 
              WHERE status = 'pending' AND type = 'email'
              ORDER BY created_at ASC 
              LIMIT ?";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $processed = 0;
    $failed = 0;
    
    while ($notification = mysqli_fetch_assoc($result)) {
        // Get recipient email
        $email = null;
        
        if ($notification['user_id']) {
            $userQuery = "SELECT email FROM user WHERE id = ?";
            $userStmt = mysqli_prepare($connection, $userQuery);
            mysqli_stmt_bind_param($userStmt, "i", $notification['user_id']);
            mysqli_stmt_execute($userStmt);
            $userResult = mysqli_stmt_get_result($userStmt);
            $user = mysqli_fetch_assoc($userResult);
            $email = $user['email'] ?? null;
            mysqli_stmt_close($userStmt);
        } elseif ($notification['guest_id']) {
            $guestQuery = "SELECT email FROM guests WHERE guest_id = ?";
            $guestStmt = mysqli_prepare($connection, $guestQuery);
            mysqli_stmt_bind_param($guestStmt, "i", $notification['guest_id']);
            mysqli_stmt_execute($guestStmt);
            $guestResult = mysqli_stmt_get_result($guestStmt);
            $guest = mysqli_fetch_assoc($guestResult);
            $email = $guest['email'] ?? null;
            mysqli_stmt_close($guestStmt);
        }
        
        if ($email) {
            $sendResult = sendEmailNotification($email, $notification['subject'], $notification['message']);
            
            if ($sendResult['success']) {
                // Update notification status
                $updateQuery = "UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE notification_id = ?";
                $updateStmt = mysqli_prepare($connection, $updateQuery);
                mysqli_stmt_bind_param($updateStmt, "i", $notification['notification_id']);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
                $processed++;
            } else {
                // Mark as failed
                $updateQuery = "UPDATE notifications SET status = 'failed' WHERE notification_id = ?";
                $updateStmt = mysqli_prepare($connection, $updateQuery);
                mysqli_stmt_bind_param($updateStmt, "i", $notification['notification_id']);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
                $failed++;
            }
        } else {
            // No email found, mark as failed
            $updateQuery = "UPDATE notifications SET status = 'failed' WHERE notification_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "i", $notification['notification_id']);
            mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);
            $failed++;
        }
    }
    
    mysqli_stmt_close($stmt);
    
    return ['processed' => $processed, 'failed' => $failed];
}

