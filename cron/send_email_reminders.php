<?php
/**
 * Automated Email Reminders Cron Job
 * Sends check-in and check-out reminder emails
 * 
 * Setup Instructions:
 * Add to Windows Task Scheduler or Linux crontab:
 * - Run every hour: php C:\xampp\htdocs\Kaizen\cron\send_email_reminders.php
 * - Or via web: curl http://localhost/Kaizen/cron/send_email_reminders.php
 */

// Include required files
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/email_notifications.php';

// Set timezone
date_default_timezone_set('Asia/Colombo'); // Adjust to your timezone

// Log file
$logFile = __DIR__ . '/email_reminders.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

writeLog("========== Email Reminders Cron Job Started ==========");

try {
    // Get bookings needing check-in reminders
    $checkinBookings = getBookingsNeedingCheckinReminders();
    writeLog("Found " . count($checkinBookings) . " booking(s) needing check-in reminders");
    
    $checkinSent = 0;
    $checkinFailed = 0;
    
    foreach ($checkinBookings as $bookingId) {
        $result = sendCheckinReminderEmail($bookingId);
        if ($result['success']) {
            $checkinSent++;
            writeLog("✓ Check-in reminder sent for booking ID: $bookingId");
        } else {
            $checkinFailed++;
            writeLog("✗ Check-in reminder failed for booking ID: $bookingId - " . $result['message']);
        }
    }
    
    // Get bookings needing check-out reminders
    $checkoutBookings = getBookingsNeedingCheckoutReminders();
    writeLog("Found " . count($checkoutBookings) . " booking(s) needing check-out reminders");
    
    $checkoutSent = 0;
    $checkoutFailed = 0;
    
    foreach ($checkoutBookings as $bookingId) {
        $result = sendCheckoutReminderEmail($bookingId);
        if ($result['success']) {
            $checkoutSent++;
            writeLog("✓ Check-out reminder sent for booking ID: $bookingId");
        } else {
            $checkoutFailed++;
            writeLog("✗ Check-out reminder failed for booking ID: $bookingId - " . $result['message']);
        }
    }
    
    // Summary
    writeLog("========== Summary ==========");
    writeLog("Check-in reminders: $checkinSent sent, $checkinFailed failed");
    writeLog("Check-out reminders: $checkoutSent sent, $checkoutFailed failed");
    writeLog("Total emails sent: " . ($checkinSent + $checkoutSent));
    writeLog("========== Cron Job Completed ==========\n");
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    writeLog("========== Cron Job Failed ==========\n");
}

?>
