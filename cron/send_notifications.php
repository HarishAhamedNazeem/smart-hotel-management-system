<?php
/**
 * Notification Queue Processor & Automated Reminders
 * Smart Hotel Management System
 * 
 * This script should be run via cron job to:
 * 1. Process pending email notifications
 * 2. Send check-in reminders (24 hours before check-in)
 * 3. Send check-out reminders (24 hours before check-out)
 * 
 * Example cron jobs:
 * - Every 5 minutes: (star)/5 * * * * php /path/to/send_notifications.php
 * - Twice daily: 0 9,18 * * * php /path/to/send_notifications.php
 * - Via web (with secret key): http://yoursite.com/cron/send_notifications.php?cron_key=your_secret_key
 */

// Prevent direct web access
if (php_sapi_name() !== 'cli' && !isset($_GET['cron_key'])) {
    // For web access, require a secret key
    $cron_key = $_GET['cron_key'] ?? '';
    if ($cron_key !== 'your_secret_cron_key_here_change_this') {
        die('Unauthorized');
    }
}

// Set execution time limit
set_time_limit(300); // 5 minutes

// Include required files
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../config.php';

echo "=== KAIZEN Hotel Notification System ===\n";
echo "Starting notification processing...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// ============================================
// 1. Process Queued Email Notifications
// ============================================
echo "--- Processing Email Notification Queue ---\n";
$emailResult = processNotificationQueue(50);
echo "Email notifications processed: {$emailResult['processed']}\n";
echo "Email notifications failed: {$emailResult['failed']}\n\n";

// ============================================
// 2. Send Check-in Reminders
// ============================================
echo "--- Processing Check-in Reminders ---\n";
$checkInReminders = 0;
$checkInErrors = 0;

// Get bookings with check-in tomorrow (24 hours notice)
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$tomorrowFormatted = date('d-m-Y', strtotime('+1 day')); // dd-mm-yyyy format

$checkInQuery = "SELECT b.booking_id, b.check_in, g.name, g.email 
                 FROM booking b
                 JOIN guests g ON b.guest_id = g.guest_id
                 WHERE (b.check_in = ? OR b.check_in = ?)
                 AND b.status IN ('confirmed', 'pending')
                 AND b.payment_status = 0";
$checkInStmt = mysqli_prepare($connection, $checkInQuery);
mysqli_stmt_bind_param($checkInStmt, "ss", $tomorrow, $tomorrowFormatted);
mysqli_stmt_execute($checkInStmt);
$checkInResult = mysqli_stmt_get_result($checkInStmt);

while ($booking = mysqli_fetch_assoc($checkInResult)) {
    $result = sendCheckInReminder($booking['booking_id']);
    if ($result['success']) {
        $checkInReminders++;
        echo "✓ Check-in reminder sent to: {$booking['name']} ({$booking['email']})\n";
    } else {
        $checkInErrors++;
        echo "✗ Failed to send check-in reminder to: {$booking['name']}\n";
    }
}
mysqli_stmt_close($checkInStmt);

echo "Check-in reminders queued: $checkInReminders\n";
echo "Check-in reminder errors: $checkInErrors\n\n";

// ============================================
// 3. Send Check-out Reminders
// ============================================
echo "--- Processing Check-out Reminders ---\n";
$checkOutReminders = 0;
$checkOutErrors = 0;

// Get bookings with check-out tomorrow (24 hours notice)
$checkOutQuery = "SELECT b.booking_id, b.check_out, g.name, g.email 
                  FROM booking b
                  JOIN guests g ON b.guest_id = g.guest_id
                  WHERE (b.check_out = ? OR b.check_out = ?)
                  AND b.status IN ('confirmed', 'checked_in')";
$checkOutStmt = mysqli_prepare($connection, $checkOutQuery);
mysqli_stmt_bind_param($checkOutStmt, "ss", $tomorrow, $tomorrowFormatted);
mysqli_stmt_execute($checkOutStmt);
$checkOutResult = mysqli_stmt_get_result($checkOutStmt);

while ($booking = mysqli_fetch_assoc($checkOutResult)) {
    $result = sendCheckOutReminder($booking['booking_id']);
    if ($result['success']) {
        $checkOutReminders++;
        echo "✓ Check-out reminder sent to: {$booking['name']} ({$booking['email']})\n";
    } else {
        $checkOutErrors++;
        echo "✗ Failed to send check-out reminder to: {$booking['name']}\n";
    }
}
mysqli_stmt_close($checkOutStmt);

echo "Check-out reminders queued: $checkOutReminders\n";
echo "Check-out reminder errors: $checkOutErrors\n\n";

// ============================================
// 4. Summary
// ============================================
echo "=== Notification Processing Summary ===\n";
echo "Emails processed: {$emailResult['processed']}\n";
echo "Emails failed: {$emailResult['failed']}\n";
echo "Check-in reminders: $checkInReminders\n";
echo "Check-out reminders: $checkOutReminders\n";
echo "Total notifications: " . ($emailResult['processed'] + $checkInReminders + $checkOutReminders) . "\n\n";

echo "Notification processing completed.\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "=========================================\n";

