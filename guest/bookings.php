<?php
/**
 * Guest Bookings Page
 * Display all bookings for the logged-in guest
 */

// IMPORTANT: Preserve admin session - don't change cookie params if admin is logged in
// Check if session is already started (might be from admin portal)
if (session_status() === PHP_SESSION_NONE) {
    // Session not started yet - we can set cookie params
session_set_cookie_params(0);
session_start();
} else {
    // Session already started - can't change cookie params
    // This is okay - params are already set from wherever session was started
}

// Now check if admin session exists (after session is started)
$has_admin_session = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

include_once "db.php";
require_once "includes/auth.php";
require_once "includes/rbac.php";
require_once "includes/promotions.php";

// IMPORTANT: Prevent admin/staff users from accessing guest portal
if (isset($_SESSION['user_id']) && isset($_SESSION['guest_id'])) {
    unset($_SESSION['user_id']);
    unset($_SESSION['current_user_data']);
    unset($_SESSION['user_roles']);
    unset($_SESSION['user_permissions']);
    unset($_SESSION['logged_in']);
}

// If admin/staff is logged in (but not guest), redirect to admin portal
if (isset($_SESSION['user_id']) && !isset($_SESSION['guest_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['LAST_ACTIVITY'])) {
        $_SESSION['LAST_ACTIVITY'] = time();
    }
    header('Location: ../index.php?dashboard');
    exit();
}

// Require guest login (using guest_id from guests table)
if (!isset($_SESSION['guest_id'])) {
    header('Location: ../guest_login.php');
    exit();
}

// Check for inactivity timeout (30 minutes = 1800 seconds for guest portal)
// Use LAST_ACTIVITY_GUEST to avoid conflicts with admin session
if (isset($_SESSION['guest_id'])) {
    if (isset($_SESSION['LAST_ACTIVITY_GUEST'])) {
        if (time() - $_SESSION['LAST_ACTIVITY_GUEST'] > 1800) {
            unset($_SESSION['guest_id']);
            unset($_SESSION['guest_name']);
            unset($_SESSION['guest_email']);
            unset($_SESSION['LAST_ACTIVITY_GUEST']);
            header('Location: ../guest_login.php?error=session_timeout');
            exit();
        }
    }
    // Update guest activity time (separate from admin)
    $_SESSION['LAST_ACTIVITY_GUEST'] = time();
}

// Get guest record
$guest_id = $_SESSION['guest_id'];
$guestQuery = "SELECT * FROM guests WHERE guest_id = ? AND status = 'active' LIMIT 1";
$guestStmt = mysqli_prepare($connection, $guestQuery);
mysqli_stmt_bind_param($guestStmt, "i", $guest_id);
mysqli_stmt_execute($guestStmt);
$guestResult = mysqli_stmt_get_result($guestStmt);
$guest = mysqli_fetch_assoc($guestResult);
mysqli_stmt_close($guestStmt);

if (!$guest) {
    session_destroy();
    header('Location: ../guest_login.php');
    exit();
}

// Set $user variable for backward compatibility with template
$user = $guest;

// Check if guest_id column exists in booking table
$checkColumnQuery = "SHOW COLUMNS FROM booking LIKE 'guest_id'";
$columnResult = mysqli_query($connection, $checkColumnQuery);
$hasGuestIdColumn = mysqli_num_rows($columnResult) > 0;

// Get all bookings for this guest
$bookings = [];
if ($hasGuestIdColumn) {
    // Use guest_id column if it exists
    $bookingsQuery = "SELECT b.*, r.room_no, rt.room_type, rt.price as room_price,
                     br.branch_name, br.branch_code
                     FROM booking b
                     LEFT JOIN room r ON b.room_id = r.room_id
                     LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id
                     LEFT JOIN branches br ON r.branch_id = br.branch_id
                     WHERE b.guest_id = ?
                     ORDER BY b.booking_date DESC";
    $bookingsStmt = mysqli_prepare($connection, $bookingsQuery);
    mysqli_stmt_bind_param($bookingsStmt, "i", $guest_id);
    mysqli_stmt_execute($bookingsStmt);
    $bookingsResult = mysqli_stmt_get_result($bookingsStmt);
    
    while ($row = mysqli_fetch_assoc($bookingsResult)) {
        $bookings[] = $row;
    }
    mysqli_stmt_close($bookingsStmt);
} else {
    // Fallback: Check if there's a customer record linked to this guest
    // This handles cases where guest bookings were stored using customer_id
    // Note: This is a temporary fallback - ideally the guest_id column should be added
    $bookingsQuery = "SELECT b.*, r.room_no, rt.room_type, rt.price as room_price,
                     br.branch_name, br.branch_code
                     FROM booking b
                     LEFT JOIN room r ON b.room_id = r.room_id
                     LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id
                     LEFT JOIN branches br ON r.branch_id = br.branch_id
                     LEFT JOIN customer c ON b.customer_id = c.customer_id
                     WHERE c.email = ? OR (c.contact_no = ? AND c.contact_no IS NOT NULL)
                     ORDER BY b.booking_date DESC";
    $bookingsStmt = mysqli_prepare($connection, $bookingsQuery);
    $guest_email = $guest['email'];
    $guest_phone = $guest['contact_no'];
    mysqli_stmt_bind_param($bookingsStmt, "ss", $guest_email, $guest_phone);
    mysqli_stmt_execute($bookingsStmt);
    $bookingsResult = mysqli_stmt_get_result($bookingsStmt);
    
    while ($row = mysqli_fetch_assoc($bookingsResult)) {
        $bookings[] = $row;
    }
    mysqli_stmt_close($bookingsStmt);
}

// Filter bookings
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filtered_bookings = [];
$today = date('Y-m-d');

foreach ($bookings as $booking) {
    // Handle both DATE type and VARCHAR (dd-mm-yyyy) formats
    $check_out_raw = $booking['check_out'] ?? '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out_raw)) {
        // Already in YYYY-MM-DD format (DATE type)
        $check_out_date = $check_out_raw;
    } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_out_raw)) {
        // Old VARCHAR format (dd-mm-yyyy)
        $parts = explode('-', $check_out_raw);
        $check_out_date = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    } else {
        $check_out_date = date('Y-m-d', strtotime(str_replace('-', '/', $check_out_raw)));
    }
    
    if ($filter == 'upcoming' && $check_out_date >= $today) {
        $filtered_bookings[] = $booking;
    } elseif ($filter == 'past' && $check_out_date < $today) {
        $filtered_bookings[] = $booking;
    } elseif ($filter == 'all') {
        $filtered_bookings[] = $booking;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Bookings - KAIZEN Hotel</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <style>
        body {
            background: var(--color-bg);
        }
        .bookings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 15px;
        }
        .bookings-card {
            background: var(--color-surface);
            border: 1px solid var(--color-accent);
            border-radius: 4px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .bookings-card h3 {
            color: var(--color-primary-dark);
            margin-bottom: 20px;
            font-weight: 300;
        }
        .booking-item {
            background: var(--color-surface);
            border: 1px solid var(--color-accent);
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .booking-item:hover {
            box-shadow: 0 5px 15px rgba(61, 44, 141, 0.2);
            border-color: var(--color-primary);
        }
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .booking-id {
            font-size: 18px;
            font-weight: 500;
            color: var(--color-primary);
        }
        .booking-status {
            padding: 5px 15px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-paid {
            background: var(--color-success);
            color: #fff;
        }
        .status-pending {
            background: var(--color-warning);
            color: #fff;
        }
        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-label {
            font-size: 12px;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .detail-value {
            font-size: 16px;
            color: var(--color-text-primary);
            font-weight: 500;
        }
        .nav-tabs {
            border-bottom: 2px solid var(--color-accent);
            margin-bottom: 20px;
        }
        .nav-tabs > li > a {
            color: var(--color-text-secondary);
            border: none;
            border-bottom: 3px solid transparent;
            padding: 15px 20px;
        }
        .nav-tabs > li.active > a,
        .nav-tabs > li.active > a:hover,
        .nav-tabs > li.active > a:focus {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
            background: transparent;
        }
        .no-bookings {
            text-align: center;
            padding: 60px 20px;
            color: var(--color-text-secondary);
        }
        .no-bookings i {
            font-size: 64px;
            color: var(--color-accent);
            margin-bottom: 20px;
        }
        .booking-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--color-accent);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .booking-actions .btn {
            flex: 1;
            min-width: 120px;
        }
        .status-confirmed {
            background: var(--color-success);
            color: #fff;
        }
        .status-checked-in {
            background: var(--color-info);
            color: #fff;
        }
        .status-checked-out {
            background: #6c757d;
            color: #fff;
        }
        .status-cancelled {
            background: var(--color-danger);
            color: #fff;
        }
        .modal-content {
            border-radius: 4px;
        }
        /* Ensure cancel booking modal appears above guest navbar and center it */
        #cancelBookingModal.modal {
            z-index: 10000 !important;
        }
        #cancelBookingModal .modal-backdrop {
            z-index: 9999 !important;
        }
        #cancelBookingModal .modal-dialog {
            margin: 15% auto;
            max-width: 400px;
            width: auto;
            position: relative;
            z-index: 10001;
            pointer-events: auto;
        }
        #cancelBookingModal .modal-content {
            pointer-events: auto;
            position: relative;
            z-index: 10002;
        }
        /* Ensure buttons are on one line */
        #cancelBookingModal .modal-footer {
            display: flex;
            justify-content: flex-end;
        }
        #cancelBookingModal .modal-footer .btn {
            margin-left: 10px;
            white-space: nowrap;
        }
        #cancelBookingModal .modal-footer .btn:first-child {
            margin-left: 0;
        }
        /* Styled success message for booking cancellation */
        #cancel_booking_message {
            margin-bottom: 20px;
        }
        #cancel_booking_message .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #28a745;
            border-left: 5px solid #28a745;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.15);
            animation: slideInDown 0.5s ease-out;
        }
        #cancel_booking_message .alert-success i {
            font-size: 24px;
            color: #28a745;
            margin-right: 12px;
            vertical-align: middle;
        }
        #cancel_booking_message .alert-success strong {
            font-size: 16px;
            color: #155724;
            display: block;
            margin-bottom: 8px;
        }
        #cancel_booking_message .alert-success p {
            margin: 0;
            color: #155724;
            font-size: 14px;
            line-height: 1.6;
        }
        /* Styled error message for booking cancellation */
        #cancel_booking_message .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 2px solid #dc3545;
            border-left: 5px solid #dc3545;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.15);
            animation: slideInDown 0.5s ease-out;
        }
        #cancel_booking_message .alert-danger i {
            font-size: 24px;
            color: #dc3545;
            margin-right: 12px;
            vertical-align: middle;
        }
        #cancel_booking_message .alert-danger strong {
            font-size: 16px;
            color: #721c24;
            display: block;
            margin-bottom: 8px;
        }
        #cancel_booking_message .alert-danger p {
            margin: 0;
            color: #721c24;
            font-size: 14px;
            line-height: 1.6;
        }
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @media (max-width: 768px) {
            #cancelBookingModal .modal-dialog {
                width: 90%;
                max-width: 90%;
            }
        }
        .modal-header {
            background: var(--color-primary);
            color: #fff;
            border-radius: 4px 4px 0 0;
        }
        .modal-header .close {
            color: #fff;
            opacity: 0.8;
        }
        .modal-header .close:hover {
            opacity: 1;
        }
        /* Guest Navigation Bar Container */
        .public-navbar {
            border-bottom: none !important;
            margin-bottom: 0 !important;
        }
        .navbar-custom.public-navbar {
            border-bottom: none !important;
        }
        .guest-nav-bar-container {
            position: fixed;
            top: 70px;
            left: 0;
            right: 0;
            z-index: 9998;
            background: var(--color-primary-dark);
            border-top: 2px solid var(--color-primary);
            border-bottom: 2px solid var(--color-primary);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 0;
            margin-bottom: 0;
        }
        body.guest-logged-in {
            padding-top: 140px;
        }
        /* Guest Navigation Bar */
        .guest-nav-bar {
            margin: 0;
            padding: 15px 20px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .guest-nav-bar a {
            color: #fff;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 500;
        }
        .guest-nav-bar a i {
            font-size: 18px;
        }
        .guest-nav-bar a:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            color: #fff;
            text-decoration: none;
        }
        .guest-nav-bar a:active {
            transform: translateY(0);
        }
        @media (max-width: 768px) {
            .guest-nav-bar-container {
                top: 70px;
            }
            body.guest-logged-in {
                padding-top: 200px;
            }
            .guest-nav-bar {
                flex-direction: column;
                gap: 10px;
                padding: 15px;
            }
            .guest-nav-bar a {
                width: 100%;
                justify-content: center;
                padding: 12px 20px;
            }
        }
    </style>
</head>
<body class="guest-logged-in" style="padding-top: 140px;">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-custom public-navbar navbar-fixed-top" role="navigation">
        <div class="container">
            <div class="navbar-header">
                <a class="navbar-brand" href="home.php"><span>KAIZEN</span> Hotel</a>
            </div>
        </div>
    </nav>

    <!-- Guest Navigation Bar -->
    <div class="guest-nav-bar-container">
        <div class="container">
            <div class="guest-nav-bar">
                <a href="home.php">
                    <i class="fa fa-home"></i> Home
                </a>
                <a href="guest_booking.php">
                    <i class="fa fa-calendar"></i> Book Room
                </a>
                <a href="guest_bookings.php">
                    <i class="fa fa-list"></i> My Bookings
                </a>
                <a href="guest_services.php">
                    <i class="fa fa-bell"></i> Request Services
                </a>
                <a href="guest_profile.php">
                    <i class="fa fa-user"></i> Profile
                </a>
                <a href="guest_logout.php">
                    <i class="fa fa-sign-out"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <?php include 'includes/promotion_banner.php'; ?>

    <div class="bookings-container">
        <div class="bookings-card">
            <h3><i class="fa fa-list"></i> My Bookings</h3>
            
            <!-- Tabs -->
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="<?php echo $filter == 'all' ? 'active' : ''; ?>">
                    <a href="guest_bookings.php?filter=all">All Bookings</a>
                </li>
                <li role="presentation" class="<?php echo $filter == 'upcoming' ? 'active' : ''; ?>">
                    <a href="guest_bookings.php?filter=upcoming">Upcoming</a>
                </li>
                <li role="presentation" class="<?php echo $filter == 'past' ? 'active' : ''; ?>">
                    <a href="guest_bookings.php?filter=past">Past</a>
                </li>
            </ul>
            
            <!-- Bookings List -->
            <?php if (empty($filtered_bookings)): ?>
                <div class="no-bookings">
                    <i class="fa fa-calendar-times-o"></i>
                    <h4>No bookings found</h4>
                    <p>You don't have any <?php echo $filter == 'all' ? '' : $filter; ?> bookings yet.</p>
                    <a href="guest_booking.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fa fa-calendar"></i> Book a Room
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($filtered_bookings as $booking): 
                    // Determine booking status
                    $status = $booking['status'] ?? 'confirmed';
                    $status_display = ucfirst(str_replace('_', ' ', $status));
                    $status_class = 'status-' . str_replace('_', '-', $status);
                    
                    // Check if booking can be modified or cancelled
                    $can_modify = in_array($status, ['confirmed', 'pending']);
                    $can_cancel = in_array($status, ['confirmed', 'pending']);
                    
                    // Check if check-in date is in the future
                    $check_in_raw = $booking['check_in'] ?? '';
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in_raw)) {
                        $check_in_date = $check_in_raw;
                    } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_in_raw)) {
                        $parts = explode('-', $check_in_raw);
                        $check_in_date = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                    } else {
                        $check_in_date = date('Y-m-d');
                    }
                    
                    $is_future_booking = strtotime($check_in_date) > strtotime($today);
                    
                    // Only allow modifications for future bookings
                    $can_modify = $can_modify && $is_future_booking;
                    $can_cancel = $can_cancel && $is_future_booking;
                ?>
                    <div class="booking-item" data-booking-id="<?php echo $booking['booking_id']; ?>">
                        <div class="booking-header">
                            <div class="booking-id">Booking #<?php echo $booking['booking_id']; ?></div>
                            <div>
                                <span class="booking-status <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($status_display); ?>
                                </span>
                                <?php if ($booking['payment_status'] == 1): ?>
                                    <span class="booking-status status-paid" style="margin-left: 5px;">
                                        <i class="fa fa-check"></i> Paid
                                    </span>
                                <?php else: ?>
                                    <span class="booking-status status-pending" style="margin-left: 5px;">
                                        <i class="fa fa-clock-o"></i> Payment Pending
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="booking-details">
                            <div class="detail-item">
                                <span class="detail-label">Room Type</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['room_type'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Room Number</span>
                                <span class="detail-value"><?php echo htmlspecialchars($booking['room_no'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Check-In</span>
                                <span class="detail-value">
                                    <?php 
                                    $check_in = $booking['check_in'] ?? '';
                                    if ($check_in) {
                                        // Format date for display - handle both DATE and VARCHAR formats
                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in)) {
                                            // DATE format (YYYY-MM-DD) - format nicely
                                            echo date('d M Y', strtotime($check_in));
                                        } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_in)) {
                                            // Old VARCHAR format (dd-mm-yyyy)
                                            $parts = explode('-', $check_in);
                                            echo date('d M Y', strtotime($parts[2] . '-' . $parts[1] . '-' . $parts[0]));
                                        } else {
                                            echo htmlspecialchars($check_in);
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Check-Out</span>
                                <span class="detail-value">
                                    <?php 
                                    $check_out = $booking['check_out'] ?? '';
                                    if ($check_out) {
                                        // Format date for display - handle both DATE and VARCHAR formats
                                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) {
                                            // DATE format (YYYY-MM-DD) - format nicely
                                            echo date('d M Y', strtotime($check_out));
                                        } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $check_out)) {
                                            // Old VARCHAR format (dd-mm-yyyy)
                                            $parts = explode('-', $check_out);
                                            echo date('d M Y', strtotime($parts[2] . '-' . $parts[1] . '-' . $parts[0]));
                                        } else {
                                            echo htmlspecialchars($check_out);
                                        }
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Total Price</span>
                                <span class="detail-value">LKR <?php echo number_format($booking['total_price'] ?? 0); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Booking Date</span>
                                <span class="detail-value"><?php echo date('d M Y', strtotime($booking['booking_date'])); ?></span>
                            </div>
                        </div>
                        <?php if (!empty($booking['branch_name'])): ?>
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--color-accent);">
                                <span class="label label-info">
                                    <i class="fa fa-building"></i> <?php echo htmlspecialchars($booking['branch_name']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($can_modify || $can_cancel): ?>
                            <div class="booking-actions">
                                <?php if ($can_modify): ?>
                                    <button class="btn btn-info btn-modify-booking" 
                                            data-booking-id="<?php echo $booking['booking_id']; ?>"
                                            data-room-id="<?php echo $booking['room_id']; ?>"
                                            data-check-in="<?php echo $check_in_date; ?>"
                                            data-check-out="<?php echo preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking['check_out']) ? $booking['check_out'] : date('Y-m-d', strtotime(str_replace('-', '/', $booking['check_out']))); ?>"
                                            data-room-type="<?php echo htmlspecialchars($booking['room_type'] ?? ''); ?>"
                                            data-total-price="<?php echo $booking['total_price']; ?>">
                                        <i class="fa fa-edit"></i> Modify Booking
                                    </button>
                                <?php endif; ?>
                                <?php if ($can_cancel): ?>
                                    <button class="btn btn-danger btn-cancel-booking" 
                                            data-booking-id="<?php echo $booking['booking_id']; ?>">
                                        <i class="fa fa-times"></i> Cancel Booking
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modify Booking Modal -->
    <div class="modal fade" id="modifyBookingModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-edit"></i> Modify Booking</h4>
                </div>
                <div class="modal-body">
                    <form id="modifyBookingForm">
                        <input type="hidden" id="modify_booking_id" name="booking_id">
                        <input type="hidden" id="modify_old_room_id" name="old_room_id">
                        
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> You can modify your check-in/check-out dates. Room type changes may affect pricing.
                        </div>
                        
                        <div class="form-group">
                            <label>Current Room Type</label>
                            <input type="text" class="form-control" id="modify_current_room_type" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="modify_check_in">Check-In Date *</label>
                            <input type="date" class="form-control" id="modify_check_in" name="check_in" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="modify_check_out">Check-Out Date *</label>
                            <input type="date" class="form-control" id="modify_check_out" name="check_out" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Number of Days</label>
                            <input type="text" class="form-control" id="modify_days" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Estimated Total Price</label>
                            <div class="input-group">
                                <span class="input-group-addon">LKR</span>
                                <input type="text" class="form-control" id="modify_total_price" readonly>
                            </div>
                        </div>
                        
                        <div id="modify_error_message" class="alert alert-danger" style="display: none;"></div>
                        <div id="modify_success_message" class="alert alert-success" style="display: none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btnSaveModifiedBooking">
                        <i class="fa fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Booking Modal -->
    <div class="modal fade" id="cancelBookingModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--color-danger);">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-exclamation-triangle"></i> Cancel Booking</h4>
                </div>
                <div class="modal-body">
                    <div id="cancel_booking_message" style="display: none;"></div>
                    <div id="cancel_booking_confirmation">
                        <p>Are you sure you want to cancel this booking?</p>
                        <p><strong>Booking #<span id="cancel_booking_id"></span></strong></p>
                        <div class="alert alert-warning">
                            <i class="fa fa-info-circle"></i> This action cannot be undone.
                        </div>
                    </div>
                    <input type="hidden" id="cancel_booking_id_input">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">No, Keep It</button>
                    <button type="button" class="btn btn-danger" id="btnConfirmCancelBooking">
                        <i class="fa fa-times"></i> Yes, Cancel Booking
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery-1.11.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function() {
        // Modify Booking Button Click
        $(document).on('click', '.btn-modify-booking', function() {
            var bookingId = $(this).data('booking-id');
            var roomId = $(this).data('room-id');
            var checkIn = $(this).data('check-in');
            var checkOut = $(this).data('check-out');
            var roomType = $(this).data('room-type');
            var totalPrice = $(this).data('total-price');
            
            $('#modify_booking_id').val(bookingId);
            $('#modify_old_room_id').val(roomId);
            $('#modify_current_room_type').val(roomType);
            $('#modify_check_in').val(checkIn);
            $('#modify_check_out').val(checkOut);
            $('#modify_total_price').val(parseFloat(totalPrice).toFixed(2));
            
            // Calculate days
            calculateModifyDays();
            
            // Set min date to today
            var today = new Date().toISOString().split('T')[0];
            $('#modify_check_in').attr('min', today);
            
            $('#modify_error_message').hide();
            $('#modify_success_message').hide();
            
            $('#modifyBookingModal').modal('show');
        });
        
        // Calculate days when dates change
        $('#modify_check_in, #modify_check_out').on('change', function() {
            calculateModifyDays();
            
            // Update check-out min date
            var checkInDate = $('#modify_check_in').val();
            if (checkInDate) {
                var minCheckOut = new Date(checkInDate);
                minCheckOut.setDate(minCheckOut.getDate() + 1);
                $('#modify_check_out').attr('min', minCheckOut.toISOString().split('T')[0]);
            }
        });
        
        function calculateModifyDays() {
            var checkIn = $('#modify_check_in').val();
            var checkOut = $('#modify_check_out').val();
            
            if (checkIn && checkOut) {
                var date1 = new Date(checkIn);
                var date2 = new Date(checkOut);
                var diffTime = Math.abs(date2 - date1);
                var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                if (diffDays > 0) {
                    $('#modify_days').val(diffDays + ' day' + (diffDays > 1 ? 's' : ''));
                    
                    // Recalculate price (simplified - you may want to fetch actual price from server)
                    var currentTotal = parseFloat($('#modify_total_price').val().replace(/,/g, ''));
                    var currentDays = parseInt($('#modify_days').val());
                    if (currentDays > 0) {
                        var pricePerDay = currentTotal / currentDays;
                        var newTotal = pricePerDay * diffDays;
                        $('#modify_total_price').val(newTotal.toFixed(2));
                    }
                } else {
                    $('#modify_days').val('0 days');
                }
            }
        }
        
        // Save Modified Booking
        $('#btnSaveModifiedBooking').click(function() {
            var bookingId = $('#modify_booking_id').val();
            var checkIn = $('#modify_check_in').val();
            var checkOut = $('#modify_check_out').val();
            
            if (!checkIn || !checkOut) {
                $('#modify_error_message').text('Please select both check-in and check-out dates.').show();
                return;
            }
            
            if (new Date(checkOut) <= new Date(checkIn)) {
                $('#modify_error_message').text('Check-out date must be after check-in date.').show();
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
            $('#modify_error_message').hide();
            
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: {
                    modify_guest_booking: true,
                    booking_id: bookingId,
                    check_in: checkIn,
                    check_out: checkOut
                },
                dataType: 'json',
                success: function(response) {
                    if (response.done) {
                        $('#modify_success_message').text('Booking modified successfully! Reloading...').show();
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $('#modify_error_message').text(response.data || 'Failed to modify booking.').show();
                        $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Changes');
                    }
                },
                error: function() {
                    $('#modify_error_message').text('An error occurred. Please try again.').show();
                    $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Changes');
                }
            });
        });
        
        // Cancel Booking Button Click
        $(document).on('click', '.btn-cancel-booking', function() {
            var bookingId = $(this).data('booking-id');
            $('#cancel_booking_id').text(bookingId);
            $('#cancel_booking_id_input').val(bookingId);
            $('#cancelBookingModal').modal('show');
        });
        
        // Confirm Cancel Booking
        $('#btnConfirmCancelBooking').click(function() {
            var bookingId = $('#cancel_booking_id_input').val();
            var $btn = $(this);
            var $modal = $('#cancelBookingModal');
            var $messageDiv = $('#cancel_booking_message');
            var $confirmationDiv = $('#cancel_booking_confirmation');
            var $modalFooter = $modal.find('.modal-footer');
            
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Cancelling...');
            
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: {
                    cancel_guest_booking: true,
                    booking_id: bookingId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.done) {
                        // Hide confirmation content
                        $confirmationDiv.hide();
                        // Hide footer buttons
                        $modalFooter.hide();
                        // Show styled success message
                        $messageDiv.html(
                            '<div class="alert alert-success">' +
                            '<i class="fa fa-check-circle"></i>' +
                            '<strong>Booking Cancelled Successfully!</strong>' +
                            '<p>Your booking #' + bookingId + ' has been cancelled successfully. The page will refresh shortly.</p>' +
                            '</div>'
                        ).show();
                        
                        // Reload page after 2 seconds
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        // Show error message
                        $messageDiv.html(
                            '<div class="alert alert-danger">' +
                            '<i class="fa fa-exclamation-circle"></i>' +
                            '<strong>Error!</strong>' +
                            '<p>' + (response.data || 'Failed to cancel booking. Please try again.') + '</p>' +
                            '</div>'
                        ).show();
                        $btn.prop('disabled', false).html('<i class="fa fa-times"></i> Yes, Cancel Booking');
                    }
                },
                error: function() {
                    // Show error message
                    $messageDiv.html(
                        '<div class="alert alert-danger">' +
                        '<i class="fa fa-exclamation-circle"></i>' +
                        '<strong>Error!</strong>' +
                        '<p>An error occurred while cancelling the booking. Please try again.</p>' +
                        '</div>'
                    ).show();
                    $btn.prop('disabled', false).html('<i class="fa fa-times"></i> Yes, Cancel Booking');
                }
            });
        });
        
        // Reset modal when closed
        $('#cancelBookingModal').on('hidden.bs.modal', function() {
            $('#cancel_booking_message').hide().html('');
            $('#cancel_booking_confirmation').show();
            $(this).find('.modal-footer').show();
            $('#btnConfirmCancelBooking').prop('disabled', false).html('<i class="fa fa-times"></i> Yes, Cancel Booking');
        });
    });
    </script>
</body>
</html>
