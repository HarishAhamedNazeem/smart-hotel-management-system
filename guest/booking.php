<?php
/**
 * Guest Room Booking Page
 * Allows authenticated guests to book rooms
 */

// IMPORTANT: Preserve admin session - don't change cookie params if admin is logged in
// Check if session is already started (might be from admin portal)
if (session_status() === PHP_SESSION_NONE) {
    // Session not started yet - we can set cookie params
    // Check if we should preserve admin session settings
    // Since session isn't started, we can't check for admin session yet
    // So we'll set guest params, then check after starting
session_set_cookie_params(0);
session_start();
} else {
    // Session already started - can't change cookie params
    // This is okay - params are already set from wherever session was started
    // Just continue with existing session
}

// Now check if admin session exists (after session is started)
$has_admin_session = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

include_once "db.php";
require_once "includes/auth.php";
require_once "includes/rbac.php";
require_once "includes/security.php";
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

// Require guest login
if (!isset($_SESSION['guest_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../guest_login.php?action=book');
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

// Check if guests table has id_card_type_id column (new) or id_card_type (old)
$checkColumnQuery = "SHOW COLUMNS FROM guests LIKE 'id_card_type_id'";
$columnResult = mysqli_query($connection, $checkColumnQuery);
$hasIdCardTypeId = mysqli_num_rows($columnResult) > 0;

// If guest has old id_card_type (VARCHAR), try to match it with id_card_type table
if (!$hasIdCardTypeId && isset($guest['id_card_type']) && !empty($guest['id_card_type'])) {
    // Map old VARCHAR value to new id_card_type_id
    $mapQuery = "SELECT id_card_type_id FROM id_card_type WHERE id_card_type = ? LIMIT 1";
    $mapStmt = mysqli_prepare($connection, $mapQuery);
    if ($mapStmt) {
        mysqli_stmt_bind_param($mapStmt, "s", $guest['id_card_type']);
        mysqli_stmt_execute($mapStmt);
        $mapResult = mysqli_stmt_get_result($mapStmt);
        if ($mapRow = mysqli_fetch_assoc($mapResult)) {
            $guest['id_card_type_id'] = $mapRow['id_card_type_id'];
        }
        mysqli_stmt_close($mapStmt);
    }
}

if (!$guest) {
    session_destroy();
    header('Location: ../guest_login.php');
    exit();
}

// Set $user variable for backward compatibility with template
$user = $guest;
$customer = null; // No customer table link for guests

// Get all active branches for guest to select
$branchesQuery = "SELECT branch_id, branch_name, branch_code FROM branches WHERE status = 'active' ORDER BY branch_name";
$branchesResult = mysqli_query($connection, $branchesQuery);
$branches = [];
if ($branchesResult) {
    while ($row = mysqli_fetch_assoc($branchesResult)) {
        $branches[] = $row;
    }
}

// Room types will be loaded dynamically based on selected branch via AJAX
$room_types = [];

// Get ID card types
$id_card_types = [];
// Check if id_card_type table exists
$checkTableQuery = "SHOW TABLES LIKE 'id_card_type'";
$tableCheckResult = mysqli_query($connection, $checkTableQuery);

if ($tableCheckResult && mysqli_num_rows($tableCheckResult) > 0) {
    // Table exists, query it
    $idCardTypesQuery = "SELECT * FROM id_card_type WHERE is_active = 1 ORDER BY id_card_type";
    $idCardTypesResult = mysqli_query($connection, $idCardTypesQuery);
    
    if ($idCardTypesResult) {
        while ($row = mysqli_fetch_assoc($idCardTypesResult)) {
            $id_card_types[] = $row;
        }
    } else {
        // Query failed, log error
        error_log("Failed to fetch ID card types: " . mysqli_error($connection));
        // Provide fallback options
        $id_card_types = [
            ['id_card_type_id' => 1, 'id_card_type' => 'National Identity Card'],
            ['id_card_type_id' => 2, 'id_card_type' => 'Passport'],
            ['id_card_type_id' => 3, 'id_card_type' => 'Driving License'],
            ['id_card_type_id' => 4, 'id_card_type' => 'Other']
        ];
    }
} else {
    // Table doesn't exist, provide fallback options
    error_log("id_card_type table does not exist. Using fallback options.");
    $id_card_types = [
        ['id_card_type_id' => 1, 'id_card_type' => 'National Identity Card'],
        ['id_card_type_id' => 2, 'id_card_type' => 'Passport'],
        ['id_card_type_id' => 3, 'id_card_type' => 'Driving License'],
        ['id_card_type_id' => 4, 'id_card_type' => 'Other']
    ];
}

// Get preselected room type from URL (now expects room type name, not ID)
$preselected_room_type = isset($_GET['room_type']) ? sanitizeInput($_GET['room_type']) : '';

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book a Room - KAIZEN Hotel</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/datepicker3.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            background: var(--color-bg);
        }
        .booking-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 15px;
        }
        .booking-card {
            background: var(--color-surface);
            border: 1px solid var(--color-accent);
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .booking-card h3 {
            color: var(--color-primary-dark);
            margin-bottom: 15px;
            font-weight: 300;
            font-size: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        #available-rooms-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .room-card {
            background: var(--color-surface);
            border: 1px solid var(--color-accent);
            border-radius: 4px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            flex: 0 0 calc(33.333% - 14px);
            min-width: 0;
            box-sizing: border-box;
        }
        
        @media (max-width: 992px) {
            .room-card {
                flex: 0 0 calc(50% - 10px);
            }
        }
        
        @media (max-width: 768px) {
            .room-card {
                flex: 0 0 100%;
            }
        }
        .room-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(61, 44, 141, 0.2);
            border-color: var(--color-primary);
        }
        .room-card.selected {
            border-color: var(--color-primary);
            background: rgba(61, 44, 141, 0.05);
        }
        .room-card.available {
            border-color: #00b894;
        }
        .room-card.unavailable {
            border-color: #d63031;
            opacity: 0.6;
            cursor: not-allowed;
        }
        .availability-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 8px;
        }
        .availability-badge.available-badge {
            background-color: #00b894;
            color: white;
        }
        .availability-badge.unavailable-badge {
            background-color: #d63031;
            color: white;
        }
        .room-actions {
            margin-top: 10px;
        }
        .room-card h4 {
            color: var(--color-primary-dark);
            margin-bottom: 10px;
            font-size: 18px;
        }
        .room-price {
            font-size: 22px;
            color: var(--color-primary);
            font-weight: 500;
            line-height: 1.2;
        }
        .room-card p {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
        }
        .meal-package-card {
            border: 2px solid var(--color-accent);
            border-radius: 8px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--color-surface);
            box-shadow: 0 2px 8px rgba(61, 44, 141, 0.1);
            position: relative;
            overflow: hidden;
        }
        .meal-package-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-primary-dark), var(--color-primary));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .meal-package-card:hover {
            border-color: var(--color-primary);
            box-shadow: 0 6px 20px rgba(61, 44, 141, 0.25);
            transform: translateY(-4px);
        }
        .meal-package-card:hover::before {
            transform: scaleX(1);
        }
        .meal-package-card.selected {
            border-color: var(--color-primary);
            background: linear-gradient(135deg, rgba(61, 44, 141, 0.05) 0%, rgba(90, 75, 207, 0.05) 100%);
            box-shadow: 0 6px 20px rgba(61, 44, 141, 0.3);
        }
        .meal-package-card.selected::before {
            transform: scaleX(1);
        }
        .meal-package-card.selected::after {
            content: '✓';
            position: absolute;
            top: 15px;
            right: 15px;
            width: 30px;
            height: 30px;
            background: var(--color-primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            box-shadow: 0 2px 8px rgba(61, 44, 141, 0.4);
        }
        .booking-summary {
            background: var(--color-bg);
            border: 1px solid var(--color-accent);
            border-radius: 4px;
            padding: 15px;
        }
        .booking-summary h4 {
            color: var(--color-primary-dark);
            margin-bottom: 12px;
            font-size: 16px;
        }
        .alert {
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .no-rooms {
            text-align: center;
            padding: 40px;
            color: var(--color-text-secondary);
        }
        .no-rooms i {
            font-size: 48px;
            color: var(--color-accent);
            margin-bottom: 15px;
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

    <!-- Promotions Banner Section -->
    <?php
    // Get active promotions
    $activePromotions = getActivePromotions();
    if (!empty($activePromotions)):
    ?>
    <div class="container" style="margin-top: 20px; margin-bottom: 20px;">
        <div id="promotionsCarousel" class="carousel slide" data-ride="carousel" data-interval="5000">
            <div class="carousel-inner" role="listbox">
                <?php foreach ($activePromotions as $index => $promo): ?>
                    <div class="item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <div class="promotion-banner" style="background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%); border: 2px solid var(--color-accent); border-radius: 8px; padding: 25px; text-align: center; box-shadow: 0 8px 24px rgba(61, 44, 141, 0.3); color: white;">
                            <div class="row">
                                <div class="col-md-10 col-md-offset-1">
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 20px; flex-wrap: wrap;">
                                        <div style="flex: 1; min-width: 150px;">
                                            <div style="font-size: 42px; font-weight: bold; color: var(--color-accent); line-height: 1;">
                                                <?php echo formatPromotionDiscount($promo); ?>
                                            </div>
                                            <div style="font-size: 13px; color: rgba(255,255,255,0.9); margin-top: 5px;">
                                                Special Offer
                                            </div>
                                        </div>
                                        <div style="flex: 2; min-width: 250px; text-align: left;">
                                            <h3 style="margin: 0 0 8px 0; color: #fff; font-size: 22px; font-weight: 600;">
                                                <?php echo htmlspecialchars($promo['promotion_name']); ?>
                                            </h3>
                                            <?php if (!empty($promo['description'])): ?>
                                                <p style="color: rgba(255,255,255,0.95); margin: 0 0 12px 0; font-size: 15px; line-height: 1.5;">
                                                    <?php echo htmlspecialchars($promo['description']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                                <div style="font-size: 12px; color: rgba(255,255,255,0.85);">
                                                    <i class="fa fa-calendar" style="color: var(--color-accent);"></i> <?php echo formatPromotionDates($promo['start_date'], $promo['end_date']); ?>
                                                </div>
                                                <?php if (!empty($promo['promotion_code'])): ?>
                                                    <div style="background: rgba(191, 192, 192, 0.3); padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; color: #fff; border: 1px solid var(--color-accent);">
                                                        Code: <?php echo htmlspecialchars($promo['promotion_code']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($activePromotions) > 1): ?>
                <a class="left carousel-control" href="#promotionsCarousel" role="button" data-slide="prev" style="background: none; width: 50px;">
                    <span class="glyphicon glyphicon-chevron-left" aria-hidden="true" style="color: var(--color-accent); font-size: 30px;"></span>
                    <span class="sr-only">Previous</span>
                </a>
                <a class="right carousel-control" href="#promotionsCarousel" role="button" data-slide="next" style="background: none; width: 50px;">
                    <span class="glyphicon glyphicon-chevron-right" aria-hidden="true" style="color: var(--color-accent); font-size: 30px;"></span>
                    <span class="sr-only">Next</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="booking-container">
        <div class="booking-card">
            <h3><i class="fa fa-calendar"></i> Book a Room</h3>
            
            <div id="booking-messages"></div>
            
            <!-- Search Form -->
            <form id="searchRoomsForm">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Branch <span class="text-danger">*</span></label>
                            <select id="branch_filter" name="branch_id" class="form-control" required>
                                <option value="">Select Branch</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['branch_id']; ?>">
                                        <?php echo htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Check-In Date <span class="text-danger">*</span></label>
                            <input type="text" id="check_in_date" name="check_in" class="form-control" required 
                                   placeholder="Select check-in date" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Check-Out Date <span class="text-danger">*</span></label>
                            <input type="text" id="check_out_date" name="check_out" class="form-control" required 
                                   placeholder="Select check-out date" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa fa-search"></i> Search Rooms
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Available Rooms -->
        <div id="available-rooms-container" style="display: none;">
            <div class="booking-card">
                <h3><i class="fa fa-bed"></i> Available Rooms</h3>
                <div id="available-rooms-list"></div>
            </div>
        </div>

        <!-- Meal Package Selection -->
        <div id="meal-packages-container" style="display: none;">
            <div class="booking-card">
                <h3><i class="fa fa-cutlery"></i> Select Meal Package (Optional)</h3>
                <p class="text-muted" style="font-size: 13px; margin-bottom: 15px;">Choose a meal package to add to your booking. You can skip this step if you prefer to dine separately.</p>
                <div id="meal-packages-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 15px;"></div>
                <div style="margin-top: 15px;">
                    <button type="button" class="btn btn-default" id="skip-meal-package" style="border-radius: 4px;">
                        <i class="fa fa-times"></i> Skip Meal Package
                    </button>
                </div>
            </div>
        </div>

        <!-- Booking Form -->
        <div id="booking-form-container" style="display: none;">
            <div class="booking-card">
                <h3><i class="fa fa-edit"></i> Complete Your Booking</h3>
                <form id="bookingForm" data-toggle="validator">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" id="selected_room_id" name="room_id">
                    <input type="hidden" id="selected_meal_package_id" name="meal_package_id">
                    <input type="hidden" id="booking_check_in" name="check_in">
                    <input type="hidden" id="booking_check_out" name="check_out">
                    <input type="hidden" id="booking_total_price" name="total_price">
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" name="name" id="booking_name" class="form-control" required
                                       value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Email *</label>
                                <input type="email" name="email" id="booking_email" class="form-control" required
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Phone Number *</label>
                                <input type="tel" name="contact_no" id="booking_phone" class="form-control" required
                                       value="<?php echo htmlspecialchars($customer['contact_no'] ?? $user['phone'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>ID Card Type *</label>
                                <select name="id_card_id" id="booking_id_card_type" class="form-control" required>
                                    <option value="">Select ID Card Type</option>
                                    <?php foreach ($id_card_types as $idct): ?>
                                        <option value="<?php echo isset($idct['id_card_type_id']) ? $idct['id_card_type_id'] : ''; ?>"
                                                <?php 
                                                $guestIdCardTypeId = isset($guest['id_card_type_id']) ? $guest['id_card_type_id'] : null;
                                                $idctId = isset($idct['id_card_type_id']) ? $idct['id_card_type_id'] : null;
                                                echo ($guestIdCardTypeId == $idctId) ? 'selected' : ''; 
                                                ?>>
                                            <?php echo htmlspecialchars($idct['id_card_type'] ?? 'Unknown'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>ID Card Number *</label>
                                <input type="text" name="id_card_no" id="booking_id_card_no" class="form-control" required
                                       value="<?php echo htmlspecialchars($customer['id_card_no'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Address *</label>
                                <textarea name="address" id="booking_address" class="form-control" rows="2" required><?php echo htmlspecialchars($guest['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Promotion Code (Optional)</label>
                                <div class="input-group">
                                    <input type="text" name="promotion_code" id="promotion_code" class="form-control" 
                                           placeholder="Enter promotion code">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-primary" id="apply-promotion-btn">
                                            <i class="fa fa-check"></i> Apply
                                        </button>
                                    </span>
                                </div>
                                <small class="help-block" id="promotion-message" style="display: none;"></small>
                                <input type="hidden" id="selected_promotion_id" name="promotion_id">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Section -->
                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--color-accent);">
                        <h4 style="color: var(--color-primary-dark); margin-bottom: 15px; font-size: 16px;">
                            <i class="fa fa-credit-card"></i> Advance Payment (Optional)
                        </h4>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group" style="margin-bottom: 10px;">
                                    <label style="font-size: 13px;">Advance Payment Amount</label>
                                    <input type="number" name="advance_payment" id="advance_payment" class="form-control" 
                                           placeholder="0.00" min="0" step="0.01">
                                    <small class="help-block" style="font-size: 12px;">Leave empty to pay full amount at check-in</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Card Payment Details (shown when advance payment is entered) -->
                        <div id="card-payment-section" style="display: none; margin-top: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                            <div class="alert alert-warning" style="margin-bottom: 15px; padding: 8px 12px; font-size: 12px;">
                                <i class="fa fa-shield"></i> <strong>Note:</strong> This is a mockup payment form. No actual payment processing will occur.
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group" style="margin-bottom: 10px;">
                                        <label style="font-size: 13px;">Cardholder Name *</label>
                                        <input type="text" id="card_holder_name" class="form-control" 
                                               placeholder="John Doe">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group" style="margin-bottom: 10px;">
                                        <label style="font-size: 13px;">Card Number *</label>
                                        <input type="text" id="card_number" class="form-control" 
                                               placeholder="1234 5678 9012 3456" maxlength="19">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group" style="margin-bottom: 10px;">
                                        <label style="font-size: 13px;">Expiry Date *</label>
                                        <input type="text" id="card_expiry" class="form-control" 
                                               placeholder="MM/YY" maxlength="5">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group" style="margin-bottom: 10px;">
                                        <label style="font-size: 13px;">CVV *</label>
                                        <input type="text" id="card_cvv" class="form-control" 
                                               placeholder="123" maxlength="4">
                                    </div>
                                </div>
                            </div>
                            
                            <input type="hidden" name="payment_method" id="payment_method" value="card">
                        </div>
                    </div>
                    
                    <div class="booking-summary" style="margin-top: 20px;">
                        <h4 style="font-size: 16px; margin-bottom: 12px;">Booking Summary</h4>
                        <div class="row" style="margin-bottom: 8px;">
                            <div class="col-md-6"><strong>Room:</strong></div>
                            <div class="col-md-6" id="summary_room"></div>
                        </div>
                        <div class="row" style="margin-bottom: 8px;">
                            <div class="col-md-6"><strong>Check-In:</strong></div>
                            <div class="col-md-6" id="summary_check_in"></div>
                        </div>
                        <div class="row" style="margin-bottom: 8px;">
                            <div class="col-md-6"><strong>Check-Out:</strong></div>
                            <div class="col-md-6" id="summary_check_out"></div>
                        </div>
                        <div class="row" style="margin-bottom: 8px;">
                            <div class="col-md-6"><strong>Nights:</strong></div>
                            <div class="col-md-6" id="summary_nights"></div>
                        </div>
                        <div class="row" style="margin-bottom: 8px; padding-top: 8px; border-top: 1px solid var(--color-accent);">
                            <div class="col-md-6"><strong>Room Price:</strong></div>
                            <div class="col-md-6" id="summary_room_price">LKR 0</div>
                        </div>
                        <div class="row" id="summary_meal_row" style="display: none; margin-bottom: 8px;">
                            <div class="col-md-6"><strong>Meal Package:</strong></div>
                            <div class="col-md-6" id="summary_meal_price">LKR 0</div>
                        </div>
                        <div class="row" id="summary_discount_row" style="display: none; margin-bottom: 8px; color: #27ae60;">
                            <div class="col-md-6"><strong>Promotion Discount:</strong></div>
                            <div class="col-md-6" id="summary_discount">- LKR 0</div>
                        </div>
                        <div class="row" style="margin-bottom: 8px; padding-top: 8px; border-top: 1px solid var(--color-accent);">
                            <div class="col-md-6"><strong>Total Price:</strong></div>
                            <div class="col-md-6" id="summary_total" style="font-size: 18px; color: var(--color-primary); font-weight: bold;">LKR 0</div>
                        </div>
                        <div class="row" id="advance-payment-row" style="display: none; margin-bottom: 8px; color: #00b894;">
                            <div class="col-md-6"><strong>Advance Payment:</strong></div>
                            <div class="col-md-6" id="summary_advance">LKR 0</div>
                        </div>
                        <div class="row" id="remaining-payment-row" style="display: none; margin-bottom: 8px; font-weight: bold;">
                            <div class="col-md-6"><strong>Remaining at Check-in:</strong></div>
                            <div class="col-md-6" id="summary_remaining">LKR 0</div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--color-accent);">
                        <button type="submit" class="btn btn-primary btn-lg" style="min-width: 150px;">
                            <i class="fa fa-check"></i> Confirm Booking
                        </button>
                        <a href="home.php" class="btn btn-default btn-lg" style="margin-left: 10px; min-width: 120px;">
                            <i class="fa fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="js/jquery-1.11.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/bootstrap-datepicker.min.js"></script>
    <script src="js/validator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        $(document).ready(function() {
            // Payment form handling
            $('#advance_payment').on('input', function() {
                var advanceAmount = parseFloat($(this).val()) || 0;
                var totalPrice = parseFloat($('#booking_total_price').val()) || 0;
                
                if (advanceAmount > 0) {
                    // Show card payment section
                    $('#card-payment-section').slideDown();
                    $('#payment_method').val('card');
                    
                    // Validate advance payment
                    if (advanceAmount > totalPrice) {
                        $(this).val(totalPrice);
                        advanceAmount = totalPrice;
                        alert('Advance payment cannot exceed total price');
                    }
                    
                    // Update summary
                    var remainingAmount = totalPrice - advanceAmount;
                    $('#summary_advance').text('LKR ' + advanceAmount.toLocaleString());
                    $('#summary_remaining').text('LKR ' + remainingAmount.toLocaleString());
                    $('#advance-payment-row').show();
                    $('#remaining-payment-row').show();
                } else {
                    // Hide card payment section
                    $('#card-payment-section').slideUp();
                    $('#payment_method').val('pending');
                    $('#advance-payment-row').hide();
                    $('#remaining-payment-row').hide();
                }
            });
            
            // Card number formatting
            $('#card_number').on('input', function() {
                var value = $(this).val().replace(/\s/g, '');
                var formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
                $(this).val(formattedValue);
            });
            
            // Expiry date formatting
            $('#card_expiry').on('input', function() {
                var value = $(this).val().replace(/\D/g, '');
                if (value.length >= 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                $(this).val(value);
            });
            
            // CVV - numbers only
            $('#card_cvv').on('input', function() {
                $(this).val($(this).val().replace(/\D/g, ''));
            });
            
            // Helper function for padding (compatibility with older browsers)
            function padStart(str, targetLength, padString) {
                str = String(str);
                targetLength = targetLength >> 0;
                padString = String(padString || '0');
                if (str.length > targetLength) {
                    return str;
                } else {
                    targetLength = targetLength - str.length;
                    if (targetLength > padString.length) {
                        padString += padString.repeat(targetLength / padString.length);
                    }
                    return padString.slice(0, targetLength) + str;
                }
            }
            
            // Initialize date pickers with Flatpickr
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            
            // Check-in date picker
            var checkInPicker = flatpickr('#check_in_date', {
                enableTime: false,
                dateFormat: 'd-m-Y',
                minDate: 'today',
                defaultDate: 'today',
                locale: {
                    firstDayOfWeek: 1
                },
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length > 0) {
                        // When check-in date is selected, update check-out minimum date
                        var checkInDate = new Date(selectedDates[0]);
                        checkInDate.setDate(checkInDate.getDate() + 1); // Minimum check-out is next day
                        
                        checkOutPicker.set('minDate', checkInDate);
                        
                        // Clear previous search results
                        $('#available-rooms-container').hide();
                        $('#booking-form-container').hide();
                        selectedRoom = null;
                    }
                }
            });
            
            // Check-out date picker
            var checkOutPicker = flatpickr('#check_out_date', {
                enableTime: false,
                dateFormat: 'd-m-Y',
                minDate: new Date(today.getTime() + 24 * 60 * 60 * 1000), // Tomorrow
                locale: {
                    firstDayOfWeek: 1
                },
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length > 0) {
                        // Validate that check-out is after check-in
                        var checkIn = $('#check_in_date').val();
                        if (checkIn) {
                            var checkInDate = checkInPicker.parseDate(checkIn, 'd-m-Y');
                            var checkOutDate = selectedDates[0];
                            
                            if (checkOutDate <= checkInDate) {
                                showMessage('Check-out date must be after check-in date.', 'danger');
                                $('#check_out_date').val('');
                                return;
                            }
                        }
                        
                        // Clear previous search results
                        $('#available-rooms-container').hide();
                        $('#booking-form-container').hide();
                        selectedRoom = null;
                    }
                }
            });
            
            var selectedRoom = null;
            var selectedMealPackage = null;
            var availabilityRefreshInterval = null;
            var currentSearchParams = null;
            var currentCheckIn = null;
            var currentCheckOut = null;
            
            // Real-time availability refresh function
            function refreshAvailability() {
                if (!currentSearchParams) return;
                
                var branchId = currentSearchParams.branchId;
                var checkInFormatted = currentSearchParams.checkInFormatted;
                var checkOutFormatted = currentSearchParams.checkOutFormatted;
                
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: {
                        search_available_rooms: true,
                        branch_id: branchId,
                        check_in: checkInFormatted,
                        check_out: checkOutFormatted
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.rooms && response.rooms.length > 0) {
                            // Update availability without full refresh
                            updateRoomAvailability(response.rooms);
                            
                            // Update last refreshed time
                            var now = new Date();
                            var timeStr = now.toLocaleTimeString();
                            $('#availability-last-updated').text('Last updated: ' + timeStr);
                        }
                    },
                    error: function() {
                        // Silently fail - don't disturb user experience
                        console.log('Availability refresh failed');
                    }
                });
            }
            
            // Update room availability status
            function updateRoomAvailability(rooms) {
                // Create a map of room_id to availability status
                var roomAvailabilityMap = {};
                rooms.forEach(function(r) {
                    roomAvailabilityMap[r.room_id] = r.is_available !== false;
                });
                
                $('.room-card').each(function() {
                    var roomId = $(this).data('room-id');
                    var isAvailable = roomAvailabilityMap[roomId] !== undefined ? roomAvailabilityMap[roomId] : false;
                    
                    if (isAvailable) {
                        $(this).removeClass('unavailable').addClass('available');
                        // Replace unavailable badge with available badge
                        var $badgeContainer = $(this).find('p:contains("Max")').parent();
                        $(this).find('.availability-badge.unavailable-badge').remove();
                        if ($(this).find('.availability-badge.available-badge').length === 0) {
                            $badgeContainer.append('<div class="availability-badge available-badge"><i class="fa fa-check-circle"></i> Available</div>');
                        }
                        // Update button
                        var $actions = $(this).find('.room-actions');
                        $actions.show();
                        $actions.html('<button class="btn btn-primary btn-sm select-room-btn">Select Room</button>');
                    } else {
                        $(this).removeClass('available selected').addClass('unavailable');
                        // Replace available badge with unavailable badge
                        var $badgeContainer = $(this).find('p:contains("Max")').parent();
                        $(this).find('.availability-badge.available-badge').remove();
                        if ($(this).find('.availability-badge.unavailable-badge').length === 0) {
                            $badgeContainer.append('<div class="availability-badge unavailable-badge"><i class="fa fa-times-circle"></i> Unavailable</div>');
                        }
                        // Update button
                        var $actions = $(this).find('.room-actions');
                        $actions.html('<button class="btn btn-secondary btn-sm" disabled>Unavailable</button>');
                        
                        // If this was the selected room, clear selection
                        if (selectedRoom && selectedRoom.room_id == roomId) {
                            selectedRoom = null;
                            $('#booking-form-container').hide();
                            showMessage('The selected room is no longer available. Please select another room.', 'warning');
                        }
                    }
                });
            }
            
            // Start/stop real-time availability refresh
            function startAvailabilityRefresh(branchId, checkInFormatted, checkOutFormatted) {
                // Stop any existing refresh
                stopAvailabilityRefresh();
                
                // Store current search params
                currentSearchParams = {
                    branchId: branchId,
                    checkInFormatted: checkInFormatted,
                    checkOutFormatted: checkOutFormatted
                };
                
                // Refresh every 10 seconds (faster updates)
                availabilityRefreshInterval = setInterval(refreshAvailability, 10000);
                
                // Do an immediate refresh
                refreshAvailability();
                
                // Show refresh indicator
                if ($('#availability-last-updated').length === 0) {
                    $('#available-rooms-container .booking-card h3').after('<div id="availability-last-updated" class="text-muted small" style="margin-top: -10px; margin-bottom: 15px;"><i class="fa fa-refresh fa-spin"></i> Checking availability...</div>');
                }
            }
            
            function stopAvailabilityRefresh() {
                if (availabilityRefreshInterval) {
                    clearInterval(availabilityRefreshInterval);
                    availabilityRefreshInterval = null;
                }
                currentSearchParams = null;
            }
            
            // Search rooms
            $('#searchRoomsForm').on('submit', function(e) {
                e.preventDefault();
                
                var branchId = $('#branch_filter').val();
                var checkIn = $('#check_in_date').val();
                var checkOut = $('#check_out_date').val();
                
                // Stop any existing availability refresh
                stopAvailabilityRefresh();
                
                // Validate branch
                if (!branchId) {
                    showMessage('Please select a branch first.', 'danger');
                    return;
                }
                
                // Validate dates
                if (!checkIn || !checkOut) {
                    showMessage('Please select both check-in and check-out dates.', 'danger');
                    return;
                }
                
                // Validate date format and logic
                // Format: d-m-Y (e.g., "25-12-2024")
                var checkInParts = checkIn.split('-');
                var checkOutParts = checkOut.split('-');
                
                if (checkInParts.length !== 3 || checkOutParts.length !== 3) {
                    showMessage('Invalid date format. Please use the date picker.', 'danger');
                    return;
                }
                
                var checkInDate = new Date(
                    parseInt(checkInParts[2]), 
                    parseInt(checkInParts[1]) - 1, 
                    parseInt(checkInParts[0])
                );
                var checkOutDate = new Date(
                    parseInt(checkOutParts[2]), 
                    parseInt(checkOutParts[1]) - 1, 
                    parseInt(checkOutParts[0])
                );
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                
                // Validate dates
                if (checkInDate < today) {
                    showMessage('Check-in date cannot be in the past.', 'danger');
                    return;
                }
                
                if (checkOutDate <= checkInDate) {
                    showMessage('Check-out date must be after check-in date.', 'danger');
                    return;
                }
                
                // Convert dates to proper format for server (YYYY-MM-DD)
                var checkInFormatted = checkInParts[2] + '-' + 
                    padStart(checkInParts[1], 2, '0') + '-' + 
                    padStart(checkInParts[0], 2, '0');
                var checkOutFormatted = checkOutParts[2] + '-' + 
                    padStart(checkOutParts[1], 2, '0') + '-' + 
                    padStart(checkOutParts[0], 2, '0');
                
                // Show loading state
                var searchBtn = $('#searchRoomsForm button[type="submit"]');
                var originalBtnText = searchBtn.html();
                searchBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Searching...');
                $('#available-rooms-container').hide();
                $('#booking-form-container').hide();
                
                // Search for available rooms
                console.log('Searching rooms with:', {
                    branch_id: branchId,
                    check_in: checkInFormatted,
                    check_out: checkOutFormatted
                });
                
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: {
                        search_available_rooms: true,
                        branch_id: branchId,
                        check_in: checkInFormatted,
                        check_out: checkOutFormatted
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Response received:', response);
                        searchBtn.prop('disabled', false).html(originalBtnText);
                        
                        if (response.success && response.rooms && response.rooms.length > 0) {
                            // Store current dates for room selection
                            currentCheckIn = checkIn;
                            currentCheckOut = checkOut;
                            
                            displayAvailableRooms(response.rooms, checkIn, checkOut);
                            
                            // Start real-time availability refresh
                            startAvailabilityRefresh(branchId, checkInFormatted, checkOutFormatted);
                        } else {
                            $('#available-rooms-container').hide();
                            stopAvailabilityRefresh();
                            var message = 'No rooms available for the selected dates. Please try different dates.';
                            if (response.message) {
                                message = response.message;
                            }
                            showMessage(message, response.success === false ? 'danger' : 'warning');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {
                            status: status,
                            error: error,
                            statusCode: xhr.status,
                            responseText: xhr.responseText
                        });
                        searchBtn.prop('disabled', false).html(originalBtnText);
                        
                        // Try to parse error response
                        var errorMessage = 'Error searching for rooms. Please try again.';
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            if (errorResponse.message) {
                                errorMessage = errorResponse.message;
                            } else if (errorResponse.error) {
                                errorMessage = errorResponse.error;
                            }
                        } catch (e) {
                            // If response is not JSON, show server error
                            if (xhr.responseText) {
                                errorMessage = 'Server error: ' + xhr.responseText.substring(0, 200);
                            }
                        }
                        showMessage(errorMessage, 'danger');
                    }
                });
            });
            
            // Helper function to escape HTML
            function escapeHtml(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, function(m) { return map[m]; });
            }
            
            // Display available rooms
            function displayAvailableRooms(rooms, checkIn, checkOut) {
                var html = '';
                rooms.forEach(function(room) {
                    var hasPromotions = room.promotions && room.promotions.length > 0;
                    var originalPrice = parseFloat(room.price);
                    var bestDiscount = 0;
                    var bestPromotion = null;
                    
                    // Find best applicable promotion
                    if (hasPromotions) {
                        room.promotions.forEach(function(promo) {
                            var discount = 0;
                            if (promo.discount_type === 'percentage') {
                                discount = (originalPrice * parseFloat(promo.discount_value)) / 100;
                                if (promo.max_discount_amount && discount > parseFloat(promo.max_discount_amount)) {
                                    discount = parseFloat(promo.max_discount_amount);
                                }
                            } else {
                                discount = parseFloat(promo.discount_value);
                            }
                            if (discount > bestDiscount) {
                                bestDiscount = discount;
                                bestPromotion = promo;
                            }
                        });
                    }
                    
                    var finalPrice = originalPrice - bestDiscount;
                    
                    // Store promotions as JSON in data attribute for later retrieval
                    var promotionsJson = hasPromotions ? JSON.stringify(room.promotions) : '[]';
                    html += '<div class="room-card available" data-room-id="' + room.room_id + '" data-room-type="' + room.room_type + '" data-room-type-id="' + (room.room_type_id || '') + '" data-room-no="' + room.room_no + '" data-price="' + room.price + '">';
                    html += '<div data-promotions="' + escapeHtml(promotionsJson) + '" style="display: none;"></div>';
                    html += '<div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;">';
                    html += '<div style="flex: 1; min-width: 200px;">';
                    html += '<h4 style="margin-top: 0; margin-bottom: 10px; color: #2c3e50;"><i class="fa fa-home"></i> ' + room.room_type + '</h4>';
                    html += '<p style="margin: 5px 0; color: #555;"><i class="fa fa-bed"></i> Room #' + room.room_no + '</p>';
                    html += '<p style="margin: 5px 0; color: #555;"><i class="fa fa-users"></i> Max ' + room.max_person + ' Person' + (room.max_person > 1 ? 's' : '') + '</p>';
                    
                    // Display promotions
                    if (hasPromotions) {
                        html += '<div style="margin-top: 10px; padding: 8px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px;">';
                        html += '<div style="font-size: 12px; font-weight: bold; color: #856404; margin-bottom: 5px;"><i class="fa fa-tag"></i> Special Offers Available!</div>';
                        room.promotions.forEach(function(promo) {
                            var discountText = '';
                            if (promo.discount_type === 'percentage') {
                                discountText = promo.discount_value + '% OFF';
                            } else {
                                discountText = 'LKR ' + parseFloat(promo.discount_value).toLocaleString() + ' OFF';
                            }
                            html += '<div style="font-size: 11px; color: #856404; margin: 3px 0;">';
                            html += '<strong>' + promo.promotion_name + '</strong> - ' + discountText;
                            html += '<br><small style="color: #856404;">Code: <strong>' + promo.promotion_code + '</strong></small>';
                            html += '</div>';
                        });
                        html += '</div>';
                    }
                    
                    html += '<div class="availability-badge available-badge" style="margin-top: 8px;"><i class="fa fa-check-circle"></i> Available</div>';
                    html += '</div>';
                    html += '<div style="text-align: right; flex-shrink: 0;">';
                    
                    // Display price with promotion discount if applicable
                    if (bestDiscount > 0) {
                        html += '<div style="margin-bottom: 5px;">';
                        html += '<span style="font-size: 14px; color: #999; text-decoration: line-through;">LKR ' + originalPrice.toLocaleString() + '</span>';
                        html += '</div>';
                        html += '<div class="room-price" style="margin-bottom: 10px; font-size: 18px; font-weight: bold; color: #e74c3c;">LKR ' + finalPrice.toLocaleString() + '<br><small style="font-size: 12px; font-weight: normal; color: #7f8c8d;">/night</small></div>';
                        html += '<div style="font-size: 11px; color: #27ae60; font-weight: bold; margin-bottom: 10px;">Save LKR ' + bestDiscount.toLocaleString() + '!</div>';
                    } else {
                        html += '<div class="room-price" style="margin-bottom: 10px; font-size: 18px; font-weight: bold; color: #27ae60;">LKR ' + originalPrice.toLocaleString() + '<br><small style="font-size: 12px; font-weight: normal; color: #7f8c8d;">/night</small></div>';
                    }
                    
                    html += '<div class="room-actions">';
                    html += '<button class="btn btn-primary btn-sm select-room-btn"><i class="fa fa-check"></i> Select Room</button>';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                });
                
                $('#available-rooms-list').html(html);
                $('#available-rooms-container').show();
                
                // Update last refreshed time
                var now = new Date();
                var timeStr = now.toLocaleTimeString();
                if ($('#availability-last-updated').length === 0) {
                    $('#available-rooms-container .booking-card').find('h3').parent().after('<div id="availability-last-updated" class="text-muted small" style="margin-top: -10px; margin-bottom: 15px;">Last updated: ' + timeStr + ' <i class="fa fa-check-circle text-success"></i></div>');
                } else {
                    $('#availability-last-updated').html('Last updated: ' + timeStr + ' <i class="fa fa-check-circle text-success"></i>');
                }
                
                // Add manual refresh button handler
                $('#manual-refresh-btn').off('click').on('click', function() {
                    var $btn = $(this);
                    var originalHtml = $btn.html();
                    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
                    
                    if (currentSearchParams) {
                        refreshAvailability();
                        setTimeout(function() {
                            $btn.prop('disabled', false).html(originalHtml);
                        }, 1000);
                    } else {
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                });
                
                // Room selection - use event delegation for dynamically added cards
                $(document).off('click', '.room-card.available, .select-room-btn').on('click', '.room-card.available, .select-room-btn', function(e) {
                    e.stopPropagation();
                    var $card = $(this).closest('.room-card');
                    
                    // Don't allow selection if unavailable
                    if ($card.hasClass('unavailable')) {
                        return;
                    }
                    
                    $('.room-card').removeClass('selected');
                    $card.addClass('selected');
                    // Get promotions from the room card (stored in data attribute)
                    var roomPromotions = [];
                    var $promoData = $card.find('[data-promotions]');
                    if ($promoData.length > 0) {
                        try {
                            roomPromotions = JSON.parse($promoData.attr('data-promotions'));
                        } catch(e) {
                            console.error('Error parsing promotions:', e);
                        }
                    }
                    
                    selectedRoom = {
                        room_id: $card.data('room-id'),
                        room_type: $card.data('room-type'),
                        room_type_id: $card.data('room-type-id') || null,
                        room_no: $card.data('room-no'),
                        price: $card.data('price'),
                        promotions: roomPromotions
                    };
                    
                    // Calculate total price
                    // Use stored dates
                    var checkIn = currentCheckIn;
                    var checkOut = currentCheckOut;
                    
                    // Parse date format: d-m-Y
                    var checkInParts = checkIn.split('-');
                    var checkOutParts = checkOut.split('-');
                    
                    var checkInDate = new Date(
                        parseInt(checkInParts[2]), 
                        parseInt(checkInParts[1]) - 1, 
                        parseInt(checkInParts[0])
                    );
                    var checkOutDate = new Date(
                        parseInt(checkOutParts[2]), 
                        parseInt(checkOutParts[1]) - 1, 
                        parseInt(checkOutParts[0])
                    );
                    var nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                    var totalPrice = nights * selectedRoom.price;
                    
                    // Reset promotion when room changes
                    selectedPromotion = null;
                    $('#promotion_code').val('');
                    $('#selected_promotion_id').val('');
                    $('#promotion-message').hide();
                    
                    // Update booking form
                    $('#selected_room_id').val(selectedRoom.room_id);
                    $('#booking_check_in').val(checkIn);
                    $('#booking_check_out').val(checkOut);
                    $('#booking_total_price').val(totalPrice);
                    
                    // Load meal packages after room selection
                    loadMealPackages(nights);
                    
                    $('#summary_room').text(selectedRoom.room_type + ' (Room #' + selectedRoom.room_no + ')');
                    $('#summary_check_in').text(checkIn);
                    $('#summary_check_out').text(checkOut);
                    $('#summary_nights').text(nights + ' night' + (nights > 1 ? 's' : ''));
                    updateBookingSummary(totalPrice, 0); // Will be updated when meal package is selected
                    
                    // Show meal packages section
                    $('#meal-packages-container').show();
                    $('html, body').animate({
                        scrollTop: $('#meal-packages-container').offset().top - 100
                    }, 500);
                });
            }
            
            // Load meal packages
            function loadMealPackages(nights) {
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: {
                        action: 'get_meal_packages',
                        status: 'active'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.packages) {
                            displayMealPackages(response.packages, nights);
                        } else {
                            $('#meal-packages-list').html('<p class="text-muted">No meal packages available at this time.</p>');
                        }
                    },
                    error: function() {
                        $('#meal-packages-list').html('<p class="text-danger">Error loading meal packages. Please try again.</p>');
                    }
                });
            }
            
            // Display meal packages as cards
            function displayMealPackages(packages, nights) {
                var html = '';
                var mealTypeLabels = {
                    'breakfast': 'Breakfast',
                    'lunch': 'Lunch',
                    'dinner': 'Dinner',
                    'breakfast_dinner': 'Breakfast & Dinner',
                    'breakfast_lunch_dinner': 'Breakfast, Lunch & Dinner',
                    'all_day': 'All Day',
                    'custom': 'Custom'
                };
                
                packages.forEach(function(pkg) {
                    var mealTypeLabel = mealTypeLabels[pkg.meal_type] || pkg.meal_type;
                    var totalPackagePrice = parseFloat(pkg.package_price) * nights;
                    
                    html += '<div class="meal-package-card" data-package-id="' + pkg.package_id + '" data-package-price="' + pkg.package_price + '">';
                    html += '<div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">';
                    html += '<div style="flex: 1;">';
                    html += '<h4 style="margin: 0 0 8px 0; color: var(--color-primary-dark); font-size: 20px; font-weight: 600;">' + pkg.package_name + '</h4>';
                    html += '<p style="margin: 0; color: var(--color-text-secondary); font-size: 14px; display: flex; align-items: center; gap: 8px;">';
                    html += '<i class="fa fa-cutlery" style="color: var(--color-primary);"></i>';
                    html += '<span>' + mealTypeLabel + ' • ' + pkg.number_of_meals + ' meal' + (pkg.number_of_meals > 1 ? 's' : '') + ' per day</span>';
                    html += '</p>';
                    html += '</div>';
                    html += '<div style="text-align: right; flex-shrink: 0; margin-left: 15px;">';
                    html += '<div style="font-size: 22px; font-weight: bold; color: var(--color-primary); line-height: 1.2;">LKR ' + parseFloat(pkg.package_price).toLocaleString() + '</div>';
                    html += '<small style="color: var(--color-text-secondary); font-size: 12px;">per person/day</small>';
                    html += '</div>';
                    html += '</div>';
                    
                    if (pkg.package_description) {
                        html += '<p style="margin: 12px 0; color: var(--color-text-primary); font-size: 14px; line-height: 1.6;">' + pkg.package_description + '</p>';
                    }
                    
                    if (pkg.included_items) {
                        html += '<div style="margin: 15px 0; padding: 12px; background: rgba(61, 44, 141, 0.03); border-left: 3px solid var(--color-primary); border-radius: 4px;">';
                        html += '<strong style="font-size: 13px; color: var(--color-primary-dark); display: block; margin-bottom: 8px;"><i class="fa fa-check-circle" style="color: var(--color-primary); margin-right: 5px;"></i>Included:</strong>';
                        html += '<ul style="margin: 0; padding-left: 20px; font-size: 13px; color: var(--color-text-primary); line-height: 1.8;">';
                        var items = pkg.included_items.split(',').map(function(item) { return item.trim(); });
                        items.forEach(function(item) {
                            html += '<li style="margin-bottom: 4px;">' + item + '</li>';
                        });
                        html += '</ul>';
                        html += '</div>';
                    }
                    
                    if (pkg.dietary_options) {
                        html += '<div style="margin: 12px 0;">';
                        html += '<strong style="font-size: 13px; color: var(--color-primary-dark); display: block; margin-bottom: 8px;"><i class="fa fa-leaf" style="color: var(--color-primary); margin-right: 5px;"></i>Dietary Options:</strong>';
                        html += '<div style="margin-top: 5px; display: flex; flex-wrap: wrap; gap: 6px;">';
                        var options = pkg.dietary_options.split(',').map(function(opt) { return opt.trim(); });
                        options.forEach(function(opt) {
                            html += '<span style="display: inline-block; background: var(--color-accent); color: var(--color-primary-dark); padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; border: 1px solid var(--color-primary);">' + opt + '</span>';
                        });
                        html += '</div>';
                        html += '</div>';
                    }
                    
                    html += '<div style="margin-top: 18px; padding-top: 18px; border-top: 2px solid var(--color-accent);">';
                    html += '<div style="display: flex; justify-content: space-between; align-items: center;">';
                    html += '<div style="color: var(--color-text-secondary); font-size: 14px;">';
                    html += '<strong style="color: var(--color-primary-dark);">Total for ' + nights + ' night' + (nights > 1 ? 's' : '') + ':</strong>';
                    html += '</div>';
                    html += '<div style="font-size: 24px; font-weight: bold; color: var(--color-primary);">LKR ' + totalPackagePrice.toLocaleString() + '</div>';
                    html += '</div>';
                    html += '</div>';
                    html += '</div>';
                });
                
                $('#meal-packages-list').html(html);
                
                // Add click handlers for meal package cards
                $('.meal-package-card').on('click', function() {
                    $('.meal-package-card').removeClass('selected');
                    
                    $(this).addClass('selected');
                    
                    var packageId = $(this).data('package-id');
                    var packagePrice = $(this).data('package-price');
                    // Calculate nights from check-in and check-out dates
                    var checkIn = $('#booking_check_in').val();
                    var checkOut = $('#booking_check_out').val();
                    var nights = 1;
                    if (checkIn && checkOut) {
                        var checkInParts = checkIn.split('-');
                        var checkOutParts = checkOut.split('-');
                        var checkInDate = new Date(parseInt(checkInParts[2]), parseInt(checkInParts[1]) - 1, parseInt(checkInParts[0]));
                        var checkOutDate = new Date(parseInt(checkOutParts[2]), parseInt(checkOutParts[1]) - 1, parseInt(checkOutParts[0]));
                        nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                    }
                    
                    selectedMealPackage = {
                        package_id: packageId,
                        package_name: $(this).find('h4').text(),
                        package_price: packagePrice,
                        total_price: parseFloat(packagePrice) * nights
                    };
                    
                    $('#selected_meal_package_id').val(packageId);
                    
                    // Update booking summary with meal package
                    // Get original room price (before any discount)
                    var checkIn = $('#booking_check_in').val();
                    var checkOut = $('#booking_check_out').val();
                    var checkInParts = checkIn.split('-');
                    var checkOutParts = checkOut.split('-');
                    var checkInDate = new Date(parseInt(checkInParts[2]), parseInt(checkInParts[1]) - 1, parseInt(checkInParts[0]));
                    var checkOutDate = new Date(parseInt(checkOutParts[2]), parseInt(checkOutParts[1]) - 1, parseInt(checkOutParts[0]));
                    var nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                    var roomPrice = nights * selectedRoom.price;
                    var mealPrice = selectedMealPackage.total_price;
                    
                    updateBookingSummary(roomPrice, mealPrice);
                    
                    // Show booking form
                    $('#booking-form-container').show();
                    $('html, body').animate({
                        scrollTop: $('#booking-form-container').offset().top - 100
                    }, 500);
                });
            }
            
            // Store selected promotion
            var selectedPromotion = null;
            
            // Apply promotion code
            $('#apply-promotion-btn').on('click', function() {
                var promoCode = $('#promotion_code').val().trim().toUpperCase();
                var $message = $('#promotion-message');
                
                if (!promoCode) {
                    $message.removeClass('text-success').addClass('text-danger').text('Please enter a promotion code').show();
                    return;
                }
                
                if (!selectedRoom || !selectedRoom.promotions || selectedRoom.promotions.length === 0) {
                    $message.removeClass('text-success').addClass('text-danger').text('No promotions available for this room').show();
                    return;
                }
                
                // Find matching promotion
                var foundPromo = null;
                selectedRoom.promotions.forEach(function(promo) {
                    if (promo.promotion_code.toUpperCase() === promoCode) {
                        foundPromo = promo;
                    }
                });
                
                if (foundPromo) {
                    selectedPromotion = foundPromo;
                    $('#selected_promotion_id').val(foundPromo.promotion_id);
                    $message.removeClass('text-danger').addClass('text-success').text('Promotion applied: ' + foundPromo.promotion_name).show();
                    
                    // Recalculate summary with promotion
                    var checkIn = $('#booking_check_in').val();
                    var checkOut = $('#booking_check_out').val();
                    var checkInParts = checkIn.split('-');
                    var checkOutParts = checkOut.split('-');
                    var checkInDate = new Date(parseInt(checkInParts[2]), parseInt(checkInParts[1]) - 1, parseInt(checkInParts[0]));
                    var checkOutDate = new Date(parseInt(checkOutParts[2]), parseInt(checkOutParts[1]) - 1, parseInt(checkOutParts[0]));
                    var nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                    var roomPrice = nights * selectedRoom.price;
                    var mealPrice = selectedMealPackage ? selectedMealPackage.total_price : 0;
                    updateBookingSummary(roomPrice, mealPrice);
                } else {
                    selectedPromotion = null;
                    $('#selected_promotion_id').val('');
                    $message.removeClass('text-success').addClass('text-danger').text('Invalid promotion code').show();
                    
                    // Recalculate without promotion
                    var checkIn = $('#booking_check_in').val();
                    var checkOut = $('#booking_check_out').val();
                    var checkInParts = checkIn.split('-');
                    var checkOutParts = checkOut.split('-');
                    var checkInDate = new Date(parseInt(checkInParts[2]), parseInt(checkInParts[1]) - 1, parseInt(checkInParts[0]));
                    var checkOutDate = new Date(parseInt(checkOutParts[2]), parseInt(checkOutParts[1]) - 1, parseInt(checkOutParts[0]));
                    var nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                    var roomPrice = nights * selectedRoom.price;
                    var mealPrice = selectedMealPackage ? selectedMealPackage.total_price : 0;
                    updateBookingSummary(roomPrice, mealPrice);
                }
            });
            
            // Update booking summary
            function updateBookingSummary(roomPrice, mealPrice) {
                // Calculate base total
                var baseTotal = roomPrice + mealPrice;
                
                // Calculate discount if promotion is applied
                var discountAmount = 0;
                if (selectedPromotion && baseTotal > 0) {
                    if (selectedPromotion.discount_type === 'percentage') {
                        discountAmount = (baseTotal * parseFloat(selectedPromotion.discount_value)) / 100;
                        // Apply max discount limit if set
                        if (selectedPromotion.max_discount_amount && discountAmount > parseFloat(selectedPromotion.max_discount_amount)) {
                            discountAmount = parseFloat(selectedPromotion.max_discount_amount);
                        }
                    } else {
                        discountAmount = parseFloat(selectedPromotion.discount_value);
                    }
                    
                    // Check minimum purchase amount
                    if (selectedPromotion.min_purchase_amount && baseTotal < parseFloat(selectedPromotion.min_purchase_amount)) {
                        discountAmount = 0;
                        $('#promotion-message').removeClass('text-success').addClass('text-danger')
                            .text('Minimum purchase of LKR ' + parseFloat(selectedPromotion.min_purchase_amount).toLocaleString() + ' required').show();
                    }
                }
                
                // Calculate final total
                var totalPrice = baseTotal - discountAmount;
                if (totalPrice < 0) totalPrice = 0;
                
                $('#booking_total_price').val(totalPrice);
                
                // Update display
                $('#summary_room_price').text('LKR ' + roomPrice.toLocaleString());
                
                if (mealPrice > 0) {
                    $('#summary_meal_row').show();
                    $('#summary_meal_price').text('LKR ' + mealPrice.toLocaleString());
                } else {
                    $('#summary_meal_row').hide();
                }
                
                if (discountAmount > 0) {
                    $('#summary_discount_row').show();
                    $('#summary_discount').text('- LKR ' + discountAmount.toLocaleString());
                } else {
                    $('#summary_discount_row').hide();
                }
                
                $('#summary_total').text('LKR ' + totalPrice.toLocaleString());
            }
            
            // Skip meal package button
            $('#skip-meal-package').on('click', function() {
                selectedMealPackage = null;
                $('#selected_meal_package_id').val('');
                
                // Get original room price
                var checkIn = $('#booking_check_in').val();
                var checkOut = $('#booking_check_out').val();
                var checkInParts = checkIn.split('-');
                var checkOutParts = checkOut.split('-');
                var checkInDate = new Date(parseInt(checkInParts[2]), parseInt(checkInParts[1]) - 1, parseInt(checkInParts[0]));
                var checkOutDate = new Date(parseInt(checkOutParts[2]), parseInt(checkOutParts[1]) - 1, parseInt(checkOutParts[0]));
                var nights = Math.ceil((checkOutDate - checkInDate) / (1000 * 60 * 60 * 24));
                var roomPrice = nights * selectedRoom.price;
                
                updateBookingSummary(roomPrice, 0);
                
                // Show booking form
                $('#booking-form-container').show();
                $('html, body').animate({
                    scrollTop: $('#booking-form-container').offset().top - 100
                }, 500);
            });
            
            // Stop refresh when form is submitted or page is unloaded
            $(window).on('beforeunload', function() {
                stopAvailabilityRefresh();
            });
            
            // Re-check availability before booking submission
            function verifyRoomAvailability(callback) {
                if (!currentSearchParams || !selectedRoom) {
                    callback(true);
                    return;
                }
                
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: {
                        search_available_rooms: true,
                        branch_id: currentSearchParams.branchId,
                        check_in: currentSearchParams.checkInFormatted,
                        check_out: currentSearchParams.checkOutFormatted
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.rooms) {
                            var isStillAvailable = response.rooms.some(function(r) {
                                return r.room_id == selectedRoom.room_id;
                            });
                            callback(isStillAvailable);
                        } else {
                            callback(false);
                        }
                    },
                    error: function() {
                        // If check fails, allow booking to proceed (server will verify)
                        callback(true);
                    }
                });
            }
            
            // Submit booking
            $('#bookingForm').on('submit', function(e) {
                e.preventDefault();
                
                if (!selectedRoom) {
                    showMessage('Please select a room first.', 'warning');
                    return;
                }
                
                // Validate card details if advance payment is provided
                var advanceAmount = parseFloat($('#advance_payment').val()) || 0;
                if (advanceAmount > 0) {
                    var cardHolderName = $('#card_holder_name').val().trim();
                    var cardNumber = $('#card_number').val().replace(/\s/g, '');
                    var cardExpiry = $('#card_expiry').val().trim();
                    var cardCvv = $('#card_cvv').val().trim();
                    
                    if (!cardHolderName) {
                        showMessage('Please enter cardholder name.', 'danger');
                        $('#card_holder_name').focus();
                        return;
                    }
                    
                    if (!cardNumber || cardNumber.length < 13) {
                        showMessage('Please enter a valid card number (13-19 digits).', 'danger');
                        $('#card_number').focus();
                        return;
                    }
                    
                    if (!cardExpiry || !cardExpiry.match(/^\d{2}\/\d{2}$/)) {
                        showMessage('Please enter a valid expiry date (MM/YY).', 'danger');
                        $('#card_expiry').focus();
                        return;
                    }
                    
                    if (!cardCvv || cardCvv.length < 3) {
                        showMessage('Please enter a valid CVV (3-4 digits).', 'danger');
                        $('#card_cvv').focus();
                        return;
                    }
                }
                
                // Verify room is still available before submitting
                var submitBtn = $(this).find('button[type="submit"]');
                var originalBtnText = submitBtn.html();
                submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verifying availability...');
                
                verifyRoomAvailability(function(isAvailable) {
                    if (!isAvailable) {
                        submitBtn.prop('disabled', false).html(originalBtnText);
                        selectedRoom = null;
                        $('#booking-form-container').hide();
                        showMessage('The selected room is no longer available. Please select another room.', 'danger');
                        // Refresh the room list
                        if (currentSearchParams) {
                            refreshAvailability();
                        }
                        return;
                    }
                    
                    // Proceed with booking submission
                    submitBtn.html('<i class="fa fa-spinner fa-spin"></i> Processing...');
                    
                    // Stop availability refresh during booking
                    stopAvailabilityRefresh();
                    
                    var formData = $('#bookingForm').serialize();
                formData += '&booking=1';
                formData += '&room_id=' + selectedRoom.room_id;
                
                // Convert dates back to server format (YYYY-MM-DD)
                var checkIn = $('#booking_check_in').val();
                var checkOut = $('#booking_check_out').val();
                
                var checkInParts = checkIn.split('-');
                var checkOutParts = checkOut.split('-');
                
                var checkInFormatted = checkInParts[2] + '-' + 
                    padStart(checkInParts[1], 2, '0') + '-' + 
                    padStart(checkInParts[0], 2, '0');
                var checkOutFormatted = checkOutParts[2] + '-' + 
                    padStart(checkOutParts[1], 2, '0') + '-' + 
                    padStart(checkOutParts[0], 2, '0');
                
                formData += '&check_in=' + encodeURIComponent(checkInFormatted);
                formData += '&check_out=' + encodeURIComponent(checkOutFormatted);
                
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                        success: function(response) {
                            if (response.done) {
                                showMessage('Booking confirmed successfully! Redirecting...', 'success');
                                
                                // Immediately refresh availability to show the room as unavailable
                                // This helps other users see the updated status right away
                                if (currentSearchParams) {
                                    // Force immediate refresh multiple times to ensure visibility
                                    // First refresh after 200ms (to ensure booking is committed)
                                    setTimeout(function() {
                                        refreshAvailability();
                                    }, 200);
                                    
                                    // Second refresh after 1 second
                                    setTimeout(function() {
                                        refreshAvailability();
                                    }, 1000);
                                    
                                    // Third refresh after 2 seconds
                                    setTimeout(function() {
                                        refreshAvailability();
                                    }, 2000);
                                }
                                
                                setTimeout(function() {
                                    stopAvailabilityRefresh();
                                    window.location.href = 'guest_bookings.php';
                                }, 2500);
                            } else {
                                submitBtn.prop('disabled', false).html(originalBtnText);
                                showMessage(response.data || 'Booking failed. Please try again.', 'danger');
                            }
                        },
                    error: function(xhr, status, error) {
                        console.error('Booking Error:', {
                            status: status,
                            error: error,
                            responseText: xhr.responseText,
                            statusCode: xhr.status
                        });
                        
                        // Try to parse error response
                        var errorMessage = 'Error processing booking. Please try again.';
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            if (errorResponse.data) {
                                errorMessage = errorResponse.data;
                            }
                        } catch (e) {
                            // If response is not JSON, show server error
                            if (xhr.responseText) {
                                errorMessage = 'Server error: ' + xhr.responseText.substring(0, 200);
                            }
                        }
                        
                        submitBtn.prop('disabled', false).html(originalBtnText);
                        showMessage(errorMessage, 'danger');
                    }
                });
                }); // End of verifyRoomAvailability callback
            });
            
            function showMessage(message, type) {
                var alertClass = 'alert-' + type;
                var html = '<div class="alert ' + alertClass + ' alert-dismissible" role="alert">';
                html += '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>';
                html += message;
                html += '</div>';
                $('#booking-messages').html(html);
            }
        });
    </script>
</body>
</html>
