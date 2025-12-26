<?php
/**
 * Email Notifications System
 * Handles sending emails for bookings, reminders, and promotions
 */

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader if available, otherwise use manual include
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

/**
 * Get notification setting value
 */
function getNotificationSetting($key, $default = null) {
    global $connection;
    
    // Check if table exists
    $tableCheck = mysqli_query($connection, "SHOW TABLES LIKE 'notification_settings'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) == 0) {
        return $default;
    }
    
    $query = "SELECT setting_value FROM notification_settings WHERE setting_key = ? AND is_active = 1 LIMIT 1";
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        return $default;
    }
    
    mysqli_stmt_bind_param($stmt, "s", $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    
    return $default;
}

/**
 * Check if a notification type is enabled
 */
function isNotificationEnabled($notificationType) {
    $settingKey = $notificationType . '_enabled';
    return getNotificationSetting($settingKey, '0') === '1';
}

/**
 * Get email template by code
 */
function getEmailTemplate($templateCode) {
    global $connection;
    
    $query = "SELECT * FROM notification_templates WHERE template_code = ? AND is_active = 1 LIMIT 1";
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "s", $templateCode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return mysqli_fetch_assoc($result);
}

/**
 * Replace template variables with actual values
 */
function replaceTemplateVariables($template, $variables) {
    $subject = $template['template_subject'];
    $body = $template['template_body'];
    
    foreach ($variables as $key => $value) {
        $placeholder = '{' . $key . '}';
        $subject = str_replace($placeholder, $value, $subject);
        $body = str_replace($placeholder, $value, $body);
    }
    
    return [
        'subject' => $subject,
        'body' => $body
    ];
}

/**
 * Log email attempt
 */
function logEmail($recipientEmail, $recipientName, $subject, $templateCode, $emailType, $status, $errorMessage = null, $metadata = []) {
    global $connection;
    
    $sentAt = ($status === 'sent') ? date('Y-m-d H:i:s') : null;
    $metadataJson = json_encode($metadata);
    
    $query = "INSERT INTO email_logs (recipient_email, recipient_name, subject, template_code, email_type, status, error_message, sent_at, metadata, reference_type, reference_id, guest_id, booking_id, promotion_id) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($connection, $query);
    $referenceType = $metadata['reference_type'] ?? null;
    $referenceId = $metadata['reference_id'] ?? null;
    $guestId = $metadata['guest_id'] ?? null;
    $bookingId = $metadata['booking_id'] ?? null;
    $promotionId = $metadata['promotion_id'] ?? null;
    
    mysqli_stmt_bind_param($stmt, "sssssssssiiii", 
        $recipientEmail, $recipientName, $subject, $templateCode, $emailType, 
        $status, $errorMessage, $sentAt, $metadataJson,
        $referenceType, $referenceId, $guestId, $bookingId, $promotionId
    );
    
    mysqli_stmt_execute($stmt);
    return mysqli_insert_id($connection);
}

/**
 * Send email using PHPMailer
 */
function sendEmail($recipientEmail, $recipientName, $subject, $htmlBody, $templateCode = null, $emailType = 'general', $metadata = []) {
    global $connection;
    
    try {
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            throw new Exception('PHPMailer library not found. Please install it using Composer: composer require phpmailer/phpmailer');
        }
        
        // Get SMTP settings
        $smtpHost = getNotificationSetting('smtp_host', 'smtp.gmail.com');
        $smtpPort = getNotificationSetting('smtp_port', '587');
        $smtpUsername = getNotificationSetting('smtp_username', '');
        $smtpPassword = getNotificationSetting('smtp_password', '');
        $smtpEncryption = getNotificationSetting('smtp_encryption', 'tls');
        $fromEmail = getNotificationSetting('smtp_from_email', 'noreply@kaizenhotel.com');
        $fromName = getNotificationSetting('smtp_from_name', 'Kaizen Hotel');
        
        // Validate SMTP credentials
        if (empty($smtpUsername) || empty($smtpPassword)) {
            throw new Exception('SMTP credentials not configured. Please update notification_settings table.');
        }
        
        // Create PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUsername;
        $mail->Password   = $smtpPassword;
        $mail->SMTPSecure = $smtpEncryption;
        $mail->Port       = $smtpPort;
        
        // Recipients
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($recipientEmail, $recipientName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody); // Plain text version
        
        // Send email
        $mail->send();
        
        // Log success
        logEmail($recipientEmail, $recipientName, $subject, $templateCode, $emailType, 'sent', null, $metadata);
        
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } catch (Exception $e) {
        // Log failure
        $errorMessage = $e->getMessage();
        logEmail($recipientEmail, $recipientName, $subject, $templateCode, $emailType, 'failed', $errorMessage, $metadata);
        
        // Also log to error log for debugging
        error_log("Email send failed: " . $errorMessage);
        
        return ['success' => false, 'message' => $errorMessage];
    }
}

/**
 * Send booking confirmation email
 */
function sendBookingConfirmationEmail($bookingId) {
    global $connection;
    
    // Check if notification tables exist
    $tableCheck = mysqli_query($connection, "SHOW TABLES LIKE 'notification_settings'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) == 0) {
        return ['success' => false, 'message' => 'Email notification system not configured. Please run the database migration.'];
    }
    
    // Check if booking confirmation is enabled
    if (!isNotificationEnabled('booking_confirmation')) {
        return ['success' => false, 'message' => 'Booking confirmation emails are disabled'];
    }
    
    // Get booking details
    $query = "SELECT b.*, g.name as guest_name, g.email as guest_email, 
                     r.room_no, rt.room_type, rt.price
              FROM booking b
              INNER JOIN guests g ON b.guest_id = g.guest_id
              INNER JOIN room r ON b.room_id = r.room_id
              INNER JOIN room_type rt ON r.room_type_id = rt.room_type_id
              WHERE b.booking_id = ?";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    
    if (!$booking) {
        return ['success' => false, 'message' => 'Booking not found'];
    }
    
    // Get email template
    $template = getEmailTemplate('booking_confirmation');
    if (!$template) {
        return ['success' => false, 'message' => 'Email template not found'];
    }
    
    // Prepare template variables
    $variables = [
        'guest_name' => $booking['guest_name'],
        'booking_reference' => 'BK-' . str_pad($booking['booking_id'], 6, '0', STR_PAD_LEFT),
        'room_type' => $booking['room_type'],
        'room_number' => $booking['room_no'],
        'check_in_date' => date('F j, Y', strtotime($booking['check_in'])),
        'check_out_date' => date('F j, Y', strtotime($booking['check_out'])),
        'num_guests' => $booking['no_of_guest'] ?? 1,
        'total_price' => number_format($booking['total_price'], 2),
        'payment_status' => ($booking['payment_status'] == 1) ? 'Paid' : 'Pending'
    ];
    
    // Replace variables
    $email = replaceTemplateVariables($template, $variables);
    
    // Send email
    $metadata = [
        'reference_type' => 'booking',
        'reference_id' => $bookingId,
        'guest_id' => $booking['guest_id'],
        'booking_id' => $bookingId
    ];
    
    return sendEmail(
        $booking['guest_email'],
        $booking['guest_name'],
        $email['subject'],
        $email['body'],
        'booking_confirmation',
        'booking_confirmation',
        $metadata
    );
}

/**
 * Send check-in reminder email
 */
function sendCheckinReminderEmail($bookingId) {
    global $connection;
    
    if (!isNotificationEnabled('checkin_reminder')) {
        return ['success' => false, 'message' => 'Check-in reminder emails are disabled'];
    }
    
    // Get booking details
    $query = "SELECT b.*, g.name as guest_name, g.email as guest_email, 
                     r.room_no, rt.room_type
              FROM booking b
              INNER JOIN guests g ON b.guest_id = g.guest_id
              INNER JOIN room r ON b.room_id = r.room_id
              INNER JOIN room_type rt ON r.room_type_id = rt.room_type_id
              WHERE b.booking_id = ?";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    
    if (!$booking) {
        return ['success' => false, 'message' => 'Booking not found'];
    }
    
    $template = getEmailTemplate('checkin_reminder');
    if (!$template) {
        return ['success' => false, 'message' => 'Email template not found'];
    }
    
    $variables = [
        'guest_name' => $booking['guest_name'],
        'booking_reference' => 'BK-' . str_pad($booking['booking_id'], 6, '0', STR_PAD_LEFT),
        'check_in_date' => date('F j, Y', strtotime($booking['check_in'])),
        'room_type' => $booking['room_type'],
        'room_number' => $booking['room_no']
    ];
    
    $email = replaceTemplateVariables($template, $variables);
    
    $metadata = [
        'reference_type' => 'booking',
        'reference_id' => $bookingId,
        'guest_id' => $booking['guest_id'],
        'booking_id' => $bookingId
    ];
    
    return sendEmail(
        $booking['guest_email'],
        $booking['guest_name'],
        $email['subject'],
        $email['body'],
        'checkin_reminder',
        'checkin_reminder',
        $metadata
    );
}

/**
 * Send check-out reminder email
 */
function sendCheckoutReminderEmail($bookingId) {
    global $connection;
    
    if (!isNotificationEnabled('checkout_reminder')) {
        return ['success' => false, 'message' => 'Check-out reminder emails are disabled'];
    }
    
    // Get booking details
    $query = "SELECT b.*, g.name as guest_name, g.email as guest_email, r.room_no
              FROM booking b
              INNER JOIN guests g ON b.guest_id = g.guest_id
              INNER JOIN room r ON b.room_id = r.room_id
              WHERE b.booking_id = ?";
    
    $stmt = mysqli_prepare($connection, $query);
    mysqli_stmt_bind_param($stmt, "i", $bookingId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);
    
    if (!$booking) {
        return ['success' => false, 'message' => 'Booking not found'];
    }
    
    $template = getEmailTemplate('checkout_reminder');
    if (!$template) {
        return ['success' => false, 'message' => 'Email template not found'];
    }
    
    $variables = [
        'guest_name' => $booking['guest_name'],
        'booking_reference' => 'BK-' . str_pad($booking['booking_id'], 6, '0', STR_PAD_LEFT),
        'check_out_date' => date('F j, Y', strtotime($booking['check_out'])),
        'room_number' => $booking['room_no']
    ];
    
    $email = replaceTemplateVariables($template, $variables);
    
    $metadata = [
        'reference_type' => 'booking',
        'reference_id' => $bookingId,
        'guest_id' => $booking['guest_id'],
        'booking_id' => $bookingId
    ];
    
    return sendEmail(
        $booking['guest_email'],
        $booking['guest_name'],
        $email['subject'],
        $email['body'],
        'checkout_reminder',
        'checkout_reminder',
        $metadata
    );
}

/**
 * Send promotion email to specific guest or all guests
 */
function sendPromotionEmail($promotionId, $guestId = null) {
    global $connection;
    
    if (!isNotificationEnabled('promotion_email')) {
        return ['success' => false, 'message' => 'Promotion emails are disabled'];
    }
    
    // Get promotion details
    $query = "SELECT * FROM promotions WHERE promotion_id = ?";
    $stmt = mysqli_prepare($connection, $query);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)];
    }
    mysqli_stmt_bind_param($stmt, "i", $promotionId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $promotion = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$promotion) {
        return ['success' => false, 'message' => 'Promotion not found'];
    }
    
    // Get template
    $template = getEmailTemplate('promotion_email');
    if (!$template) {
        return ['success' => false, 'message' => 'Email template not found'];
    }
    
    // Get recipients
    if ($guestId) {
        $guestQuery = "SELECT guest_id, name, email FROM guests WHERE guest_id = ? AND status = 'active'";
        $guestStmt = mysqli_prepare($connection, $guestQuery);
        mysqli_stmt_bind_param($guestStmt, "i", $guestId);
    } else {
        $guestQuery = "SELECT guest_id, name, email FROM guests WHERE status = 'active' AND email IS NOT NULL AND email != ''";
        $guestStmt = mysqli_prepare($connection, $guestQuery);
    }
    
    mysqli_stmt_execute($guestStmt);
    $guestResult = mysqli_stmt_get_result($guestStmt);
    
    $sent = 0;
    $failed = 0;
    
    while ($guest = mysqli_fetch_assoc($guestResult)) {
        // Prepare variables
        $discountDisplay = '';
        if ($promotion['discount_type'] === 'percentage') {
            $discountDisplay = $promotion['discount_value'] . '% OFF';
        } else {
            $discountDisplay = 'LKR ' . number_format($promotion['discount_value'], 2) . ' OFF';
        }
        
        $variables = [
            'guest_name' => $guest['name'],
            'promotion_title' => $promotion['promotion_name'],
            'promotion_description' => $promotion['description'] ?? '',
            'discount_display' => $discountDisplay,
            'promotion_code' => $promotion['promotion_code'],
            'valid_from' => date('F j, Y', strtotime($promotion['start_date'])),
            'valid_to' => date('F j, Y', strtotime($promotion['end_date'])),
            'booking_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/Kaizen/guest_login.php',
            'unsubscribe_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/Kaizen/unsubscribe.php?guest_id=' . $guest['guest_id']
        ];
        
        $email = replaceTemplateVariables($template, $variables);
        
        $metadata = [
            'reference_type' => 'promotion',
            'reference_id' => $promotionId,
            'guest_id' => $guest['guest_id'],
            'promotion_id' => $promotionId
        ];
        
        $result = sendEmail(
            $guest['email'],
            $guest['name'],
            $email['subject'],
            $email['body'],
            'promotion_email',
            'promotion',
            $metadata
        );
        
        if ($result['success']) {
            $sent++;
        } else {
            $failed++;
        }
    }
    
    // Close statement
    if (isset($guestStmt)) {
        mysqli_stmt_close($guestStmt);
    }
    
    return [
        'success' => true,
        'message' => "Sent $sent emails successfully, $failed failed",
        'sent' => $sent,
        'failed' => $failed
    ];
}

/**
 * Get bookings that need check-in reminders
 */
function getBookingsNeedingCheckinReminders() {
    global $connection;
    
    $reminderHours = intval(getNotificationSetting('checkin_reminder_hours', '24'));
    
    // Get bookings where check-in is within reminder window and reminder hasn't been sent
    $query = "SELECT DISTINCT b.booking_id 
              FROM booking b
              WHERE b.check_in >= NOW() 
              AND b.check_in <= DATE_ADD(NOW(), INTERVAL $reminderHours HOUR)
              AND NOT EXISTS (
                  SELECT 1 FROM email_logs el 
                  WHERE el.booking_id = b.booking_id 
                  AND el.email_type = 'checkin_reminder'
                  AND el.status = 'sent'
              )";
    
    $result = mysqli_query($connection, $query);
    $bookings = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $bookings[] = $row['booking_id'];
    }
    
    return $bookings;
}

/**
 * Get bookings that need check-out reminders
 */
function getBookingsNeedingCheckoutReminders() {
    global $connection;
    
    $reminderHours = intval(getNotificationSetting('checkout_reminder_hours', '6'));
    
    $query = "SELECT DISTINCT b.booking_id 
              FROM booking b
              WHERE b.check_out >= NOW() 
              AND b.check_out <= DATE_ADD(NOW(), INTERVAL $reminderHours HOUR)
              AND NOT EXISTS (
                  SELECT 1 FROM email_logs el 
                  WHERE el.booking_id = b.booking_id 
                  AND el.email_type = 'checkout_reminder'
                  AND el.status = 'sent'
              )";
    
    $result = mysqli_query($connection, $query);
    $bookings = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $bookings[] = $row['booking_id'];
    }
    
    return $bookings;
}

?>
