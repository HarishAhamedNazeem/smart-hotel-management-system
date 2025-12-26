<?php
/**
 * Public Home Page - Customer-Facing Landing Page
 * Displays hotel information for non-authenticated users
 * Shows authenticated view for logged-in guests
 */

// Start session first to check if admin session exists
if (session_status() === PHP_SESSION_NONE) {
session_start();
}

// IMPORTANT: Preserve admin session cookie params if admin is already logged in
// Only set cookie params to 0 if no admin session exists
// Check if admin session exists BEFORE changing cookie params
$has_admin_session = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if (!$has_admin_session) {
    // No admin session, use guest session settings (browser close)
    // Only set if session hasn't been started yet, or restart with new params
    if (session_status() === PHP_SESSION_ACTIVE) {
        // Session already active, we can't change cookie params now
        // This is okay - we'll work with existing session
    } else {
        session_set_cookie_params(0);
    }
}
// If admin session exists, keep existing cookie params (already set to 7200 in index.php)

include_once "db.php";
require_once "includes/rbac.php";
require_once "includes/auth.php";
require_once "includes/promotions.php";

// IMPORTANT: Preserve admin session when admin views guest portal
// Only clear admin session if guest is also logged in (conflict)
if (isset($_SESSION['guest_id']) && isset($_SESSION['user_id'])) {
    // Both sessions exist - this is a conflict
    // Clear admin/staff session variables to prevent conflicts
    unset($_SESSION['user_id']);
    unset($_SESSION['current_user_data']);
    unset($_SESSION['user_roles']);
    unset($_SESSION['user_permissions']);
    unset($_SESSION['logged_in']);
    unset($_SESSION['LAST_ACTIVITY']); // Clear admin's LAST_ACTIVITY
}

// Check if admin is logged in (preserve their session)
$is_admin_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// If admin is logged in, update their LAST_ACTIVITY (preserve admin session)
if ($is_admin_logged_in) {
    // Update admin's last activity time (2 hour timeout)
    if (isset($_SESSION['LAST_ACTIVITY'])) {
        if (time() - $_SESSION['LAST_ACTIVITY'] > 7200) {
            // Admin session expired - but don't destroy here, let index.php handle it
            // Just mark that we shouldn't show admin data
            $is_admin_logged_in = false;
        } else {
            $_SESSION['LAST_ACTIVITY'] = time();
        }
    } else {
        $_SESSION['LAST_ACTIVITY'] = time();
    }
}

// Check if guest is logged in using guest_id session variable
$isLoggedIn = isset($_SESSION['guest_id']);
$is_guest = false;
$guest_user = null;
$is_staff_admin = false;

if ($isLoggedIn) {
    // Check for inactivity timeout (30 minutes = 1800 seconds for guest portal)
    // Use LAST_ACTIVITY_GUEST to avoid conflicts with admin session
    if (isset($_SESSION['LAST_ACTIVITY_GUEST'])) {
        if (time() - $_SESSION['LAST_ACTIVITY_GUEST'] > 1800) {
            // Guest session expired due to inactivity
            // Only destroy guest-related session vars, preserve admin session if exists
            unset($_SESSION['guest_id']);
            unset($_SESSION['guest_name']);
            unset($_SESSION['guest_email']);
            unset($_SESSION['LAST_ACTIVITY_GUEST']);
            $isLoggedIn = false;
            $is_guest = false;
        } else {
            // Update guest activity time (separate from admin)
            $_SESSION['LAST_ACTIVITY_GUEST'] = time();
        }
    } else {
        // Initialize LAST_ACTIVITY_GUEST if not set
        $_SESSION['LAST_ACTIVITY_GUEST'] = time();
    }
    
    // Guest is logged in - set guest flag and get user data
    $is_guest = true;
    
    // Get guest user data from session or database
    if (isset($_SESSION['guest_name']) && isset($_SESSION['guest_email'])) {
        // Use session data for guest info
        $guest_user = [
            'id' => $_SESSION['guest_id'],
            'name' => $_SESSION['guest_name'],
            'email' => $_SESSION['guest_email']
        ];
    } else {
        // Fallback: get from guests table if session data is missing
        try {
            $guest_id = $_SESSION['guest_id'];
            $guestQuery = "SELECT * FROM guests WHERE guest_id = ? AND status = 'active' LIMIT 1";
            $guestStmt = mysqli_prepare($connection, $guestQuery);
            mysqli_stmt_bind_param($guestStmt, "i", $guest_id);
            mysqli_stmt_execute($guestStmt);
            $guestResult = mysqli_stmt_get_result($guestStmt);
            $guest = mysqli_fetch_assoc($guestResult);
            mysqli_stmt_close($guestStmt);
            
            if ($guest) {
                // Update session with guest data
                $_SESSION['guest_name'] = $guest['name'];
                $_SESSION['guest_email'] = $guest['email'];
                $guest_user = [
                    'id' => $guest['guest_id'],
                    'name' => $guest['name'],
                    'email' => $guest['email']
                ];
            } else {
                // Guest session exists but guest not found - clear session and show public page
                session_destroy();
                $isLoggedIn = false;
                $is_guest = false;
            }
        } catch (Exception $e) {
            // If there's an error, clear session and show public page
            session_destroy();
            $isLoggedIn = false;
            $is_guest = false;
        }
    }
    
    // IMPORTANT: Do NOT check for staff/admin when guest is logged in
    // This prevents conflicts and ensures proper portal separation
    // If a user needs both access, they should logout from one portal and login to the other
}

// Fetch room types from database (using room_type table via foreign key)
// Check if deleteStatus or is_deleted column exists
$checkColumn = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'deleteStatus'");
$hasDeleteStatus = mysqli_num_rows($checkColumn) > 0;

$checkColumn2 = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'is_deleted'");
$hasIsDeleted = mysqli_num_rows($checkColumn2) > 0;

$room_types_query = "SELECT DISTINCT rt.room_type_id, rt.room_type, rt.price, rt.max_person 
                      FROM room r 
                      INNER JOIN room_type rt ON r.room_type_id = rt.room_type_id 
                      WHERE 1=1";

// Add soft delete filter if column exists
if ($hasDeleteStatus) {
    $room_types_query .= " AND r.deleteStatus = 0";
} elseif ($hasIsDeleted) {
    $room_types_query .= " AND r.is_deleted = 0";
}

$room_types_query .= " ORDER BY rt.price ASC";

$room_types_result = mysqli_query($connection, $room_types_query);
$room_types = [];
if ($room_types_result) {
    while ($row = mysqli_fetch_assoc($room_types_result)) {
        $room_types[] = $row;
    }
}

// Hotel information (can be moved to database later)
$hotel_name = "KAIZEN Hotel";
$hotel_star_rating = 5;
$hotel_description = "Experience luxury and comfort at KAIZEN Hotel. We offer world-class amenities, exceptional service, and elegant accommodations designed to make your stay unforgettable.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($hotel_name); ?> - Luxury Hotel Experience</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <style>
        /* Public Home Page Styles - Following Theme */
        .public-navbar {
            background: var(--color-primary-dark);
            border-bottom: 2px solid var(--color-primary);
            min-height: 70px;
            margin-bottom: 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 9999;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .public-navbar.guest-logged-in {
            border-bottom: none;
        }
        .public-navbar .navbar-brand {
            color: #fff;
            font-size: 24px;
            font-weight: 500;
            padding: 20px 15px;
        }
        .public-navbar .navbar-brand span {
            color: var(--color-accent);
        }
        .public-navbar .navbar-nav > li > a {
            color: #fff;
            padding: 25px 15px;
            transition: all 0.2s ease;
        }
        .public-navbar .navbar-nav > li > a[href^="#"] {
            background-color: var(--color-primary);
            border-radius: 4px;
            margin: 15px 10px;
            padding: 8px 20px;
        }
        .public-navbar .navbar-nav > li > a:hover,
        .public-navbar .navbar-nav > li > a:focus {
            background-color: var(--color-primary-light);
            color: #fff;
        }
        .public-navbar .navbar-collapse {
            text-align: center;
        }
        .public-navbar .navbar-nav {
            float: none;
            display: inline-block;
            vertical-align: top;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%);
            color: #fff;
            padding: 80px 0 50px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .hero-section.guest-logged-in {
            padding: 80px 0 60px;
            min-height: auto;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('img/bg7.jpeg') center/cover;
            opacity: 0.15;
            z-index: 0;
        }
        .hero-section .container {
            position: relative;
            z-index: 1;
        }
        .hero-section h1 {
            font-size: 38px;
            font-weight: 300;
            margin-bottom: 15px;
            color: #fff;
        }
        .hero-section .star-rating {
            font-size: 20px;
            color: #FFD700;
            margin-bottom: 15px;
        }
        .hero-section p {
            font-size: 18px;
            margin-bottom: 20px;
            color: rgba(255, 255, 255, 0.9);
        }
        .hero-cta {
            margin-top: 20px;
        }
        
        /* Guest Navigation Bar Container */
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
        
        /* Responsive styles for guest navigation */
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
        
        /* Section Styles */
        .section {
            padding: 50px 0;
        }
        .section-title {
            text-align: center;
            margin-bottom: 35px;
        }
        .section-title h2 {
            font-size: 32px;
            font-weight: 300;
            color: var(--color-primary-dark);
            margin-bottom: 10px;
        }
        .section-title p {
            font-size: 16px;
            color: var(--color-text-secondary);
        }
        
        /* Room Type Cards */
        .room-card {
            background: var(--color-surface);
            border: 1px solid var(--color-accent);
            border-radius: 4px;
            padding: 0;
            margin-bottom: 25px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(61, 44, 141, 0.2);
            border-color: var(--color-primary);
        }
        .room-card-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }
        .room-card-content {
            padding: 20px;
        }
        .room-card h3 {
            color: var(--color-primary-dark);
            font-size: 22px;
            margin-bottom: 12px;
        }
        .room-card .price {
            font-size: 28px;
            color: var(--color-primary);
            font-weight: 500;
            margin: 12px 0;
        }
        .room-card .price span {
            font-size: 16px;
            color: var(--color-text-secondary);
        }
        .room-card .features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        .room-card .features li {
            padding: 8px 0;
            color: var(--color-text-secondary);
        }
        .room-card .features li i {
            color: var(--color-primary);
            margin-right: 10px;
        }
        
        /* Facilities Section */
        .facility-item {
            background: var(--color-surface);
            border: 1px solid var(--color-accent);
            border-radius: 8px;
            padding: 0;
            margin-bottom: 25px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .facility-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(61, 44, 141, 0.2);
            border-color: var(--color-primary);
        }
        .facility-item-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }
        .facility-item-content {
            padding: 20px 15px;
        }
        .facility-item i {
            font-size: 36px;
            color: var(--color-primary);
            margin-bottom: 12px;
        }
        .facility-item h4 {
            color: var(--color-primary-dark);
            margin-bottom: 8px;
            font-size: 18px;
            font-weight: 500;
        }
        .facility-item p {
            color: var(--color-text-secondary);
            font-size: 14px;
            margin-bottom: 0;
            line-height: 1.5;
        }
        
        /* Testimonials */
        .testimonial-card {
            background: var(--color-surface);
            border: 1px solid var(--color-accent);
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .testimonial-card .testimonial-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--color-primary);
            margin-bottom: 15px;
        }
        .testimonial-card .stars {
            color: #FFD700;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .testimonial-card p {
            font-style: italic;
            color: var(--color-text-secondary);
            margin-bottom: 15px;
            font-size: 14px;
            line-height: 1.6;
        }
        .testimonial-card .author {
            color: var(--color-primary-dark);
            font-weight: 500;
            font-size: 14px;
        }
        
        /* Contact Section */
        .contact-section {
            background: var(--color-bg);
        }
        .contact-info {
            padding: 20px;
        }
        .contact-info i {
            color: var(--color-primary);
            font-size: 24px;
            margin-right: 15px;
        }
        .contact-info h4 {
            color: var(--color-primary-dark);
            margin-bottom: 10px;
        }
        .contact-info p {
            color: var(--color-text-secondary);
        }
        
        /* Footer */
        .public-footer {
            background: var(--color-primary-dark);
            color: #fff;
            padding: 30px 0 15px;
        }
        .public-footer h4 {
            color: var(--color-accent);
            margin-bottom: 20px;
        }
        .public-footer a {
            color: rgba(255, 255, 255, 0.8);
            transition: color 0.2s ease;
        }
        .public-footer a:hover {
            color: var(--color-accent);
        }
        .public-footer .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 30px;
            padding-top: 20px;
            text-align: center;
        }
        
        /* About Section */
        .about-section {
            background: var(--color-bg);
        }
    </style>
</head>
<body class="<?php echo ($is_guest && $guest_user) ? 'guest-logged-in' : ''; ?>" style="padding-top: <?php echo ($is_guest && $guest_user) ? '140px' : '70px'; ?>;">
    <!-- Public Navigation Bar -->
    <nav class="navbar navbar-custom public-navbar <?php echo ($is_guest && $guest_user) ? 'guest-logged-in' : ''; ?> navbar-fixed-top" role="navigation">
        <div class="container">
            <div class="navbar-header">
                <?php if (!$is_guest || !$guest_user): ?>
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#public-navbar-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <?php endif; ?>
                <a class="navbar-brand" href="home.php"><span>THE KAIZEN</span> Hotel</a>
            </div>
            <?php if (!$is_guest || !$guest_user): ?>
            <div class="collapse navbar-collapse" id="public-navbar-collapse">
                <ul class="nav navbar-nav">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#rooms">Rooms</a></li>
                    <li><a href="#facilities">Facilities</a></li>
                    <li><a href="#testimonials">Testimonials</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <?php if ($is_staff_admin && $guest_user): ?>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" style="padding: 25px 15px;">
                                <?php echo htmlspecialchars(explode(' ', $guest_user['name'])[0]); ?> <span class="caret"></span>
                            </a>
                            <ul class="dropdown-menu" style="background: var(--color-surface); border: 1px solid var(--color-accent); box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                <li><a href="index.php?dashboard"><i class="fa fa-dashboard"></i> Admin Dashboard</a></li>
                                <li role="separator" class="divider"></li>
                                <li><a href="staff_logout.php"><i class="fa fa-sign-out"></i> Logout</a></li>
                            </ul>
                        </li>
                        <li><a href="guest_login.php" class="btn btn-primary" style="margin: 15px 10px; padding: 8px 20px;">Guest Login</a></li>
                    <?php else: ?>
                        <li><a href="guest_login.php" class="btn btn-primary" style="margin: 15px 10px; padding: 8px 20px;">Guest Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <?php if ($is_guest && $guest_user): ?>
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
    <?php endif; ?>

    <section class="hero-section <?php echo ($is_guest && $guest_user) ? 'guest-logged-in' : ''; ?>" id="home">
        <div class="container">
            <?php if ($is_guest && $guest_user): ?>
                <h2 style="font-size: 36px; margin-bottom: 20px; color: rgba(255, 255, 255, 0.95); font-weight: 400;">Welcome, <?php echo htmlspecialchars($guest_user['name']); ?>!</h2>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($hotel_name); ?></h1>
            <?php if (!$is_guest || !$guest_user): ?>
                <div class="star-rating">
                    <?php for ($i = 0; $i < $hotel_star_rating; $i++): ?>
                        <i class="fa fa-star"></i>
                    <?php endfor; ?>
                </div>
                <p>Experience Luxury, Comfort, and Exceptional Service</p>
            <?php else: ?>
                <p style="font-size: 22px; margin-top: 15px; color: rgba(255, 255, 255, 0.9);">Your Guest Portal</p>
            <?php endif; ?>
            <div class="hero-cta">
                <?php if ($is_guest): ?>
                    <a href="guest_booking.php" class="btn btn-lg btn-primary" style="padding: 15px 40px; font-size: 18px;">
                        <i class="fa fa-calendar"></i> Book a Room
                    </a>
                <?php else: ?>
                    <a href="guest_login.php?action=book" class="btn btn-lg btn-primary" style="padding: 15px 40px; font-size: 18px;">
                        <i class="fa fa-calendar"></i> Book a Room
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Promotions Banner Section -->
    <?php
    // Get active promotions
    $activePromotions = getActivePromotions();
    if (!empty($activePromotions)):
    ?>
    <section class="promotions-banner-section" style="background: linear-gradient(135deg, var(--color-primary-dark) 0%, var(--color-primary) 100%); padding: 30px 0; margin-top: 0;">
        <div class="container">
            <div id="promotionsCarousel" class="carousel slide" data-ride="carousel" data-interval="5000">
                <div class="carousel-inner" role="listbox">
                    <?php foreach ($activePromotions as $index => $promo): ?>
                        <div class="item <?php echo $index === 0 ? 'active' : ''; ?>">
                            <div class="promotion-banner" style="background: var(--color-surface); border: 2px solid var(--color-primary); border-radius: 8px; padding: 30px; text-align: center; box-shadow: 0 8px 24px rgba(61, 44, 141, 0.2);">
                                <div class="row">
                                    <div class="col-md-8 col-md-offset-2">
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 20px; flex-wrap: wrap;">
                                            <div style="flex: 1; min-width: 200px;">
                                                <div style="font-size: 48px; font-weight: bold; color: var(--color-primary); line-height: 1;">
                                                    <?php echo formatPromotionDiscount($promo); ?>
                                                </div>
                                                <div style="font-size: 14px; color: var(--color-text-secondary); margin-top: 5px;">
                                                    Special Offer
                                                </div>
                                            </div>
                                            <div style="flex: 2; min-width: 300px; text-align: left;">
                                                <h3 style="margin: 0 0 10px 0; color: var(--color-primary-dark); font-size: 24px; font-weight: 600;">
                                                    <?php echo htmlspecialchars($promo['promotion_name']); ?>
                                                </h3>
                                                <?php if (!empty($promo['description'])): ?>
                                                    <p style="color: var(--color-text-secondary); margin: 0 0 15px 0; font-size: 16px; line-height: 1.6;">
                                                        <?php echo htmlspecialchars($promo['description']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                                                    <div style="font-size: 13px; color: var(--color-text-secondary);">
                                                        <i class="fa fa-calendar" style="color: var(--color-primary);"></i> <?php echo formatPromotionDates($promo['start_date'], $promo['end_date']); ?>
                                                    </div>
                                                    <?php if (!empty($promo['promotion_code'])): ?>
                                                        <div style="background: var(--color-accent); padding: 5px 12px; border-radius: 4px; font-size: 13px; font-weight: 600; color: var(--color-primary-dark); border: 1px solid var(--color-primary);">
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
    </section>
    <?php endif; ?>

    <?php if (!$is_guest || !$guest_user): ?>
    <!-- About Section -->
    <section class="section about-section" id="about">
        <div class="container">
            <div class="section-title">
                <h2>About <?php echo htmlspecialchars($hotel_name); ?></h2>
                <p>Your Perfect Getaway Awaits</p>
            </div>
            <div class="row">
                <div class="col-md-10 col-md-offset-1 text-center">
                    <p style="font-size: 16px; line-height: 1.7; color: var(--color-text-secondary);">
                        <?php echo htmlspecialchars($hotel_description); ?> Located in the heart of the city, we offer a perfect blend of modern amenities and traditional hospitality. Whether you're traveling for business or leisure, our dedicated team ensures your stay is memorable and comfortable.
                    </p>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Room Types Section -->
    <section class="section" id="rooms">
        <div class="container">
            <div class="section-title">
                <h2>Our Room Types</h2>
                <p>Choose from our elegant selection of accommodations</p>
            </div>
            <div class="row">
                <?php if (!empty($room_types)): ?>
                    <?php 
                    // Get all active branches for branch-wise availability display
                    $branchesQuery = "SELECT branch_id, branch_name FROM branches WHERE status = 'active' ORDER BY branch_name";
                    $branchesResult = mysqli_query($connection, $branchesQuery);
                    $branches = [];
                    if ($branchesResult) {
                        while ($branchRow = mysqli_fetch_assoc($branchesResult)) {
                            $branches[$branchRow['branch_id']] = $branchRow['branch_name'];
                        }
                    }
                    
                    foreach ($room_types as $room_type): 
                        // Get available room count per branch for this room type
                        $branch_availability = [];
                        $total_available = 0;
                        
                        foreach ($branches as $branch_id => $branch_name) {
                            $countQuery = "SELECT COUNT(r.room_id) as available 
                                           FROM room r 
                                           INNER JOIN room_type rt ON r.room_type_id = rt.room_type_id 
                                           WHERE rt.room_type_id = ? AND r.branch_id = ? AND r.status = 0";
                            
                            if ($hasDeleteStatus) {
                                $countQuery .= " AND r.deleteStatus = 0";
                            } elseif ($hasIsDeleted) {
                                $countQuery .= " AND r.is_deleted = 0";
                            }
                            
                            $countStmt = mysqli_prepare($connection, $countQuery);
                            mysqli_stmt_bind_param($countStmt, "ii", $room_type['room_type_id'], $branch_id);
                            mysqli_stmt_execute($countStmt);
                            $countResult = mysqli_stmt_get_result($countStmt);
                            if ($countRow = mysqli_fetch_assoc($countResult)) {
                                $branch_count = (int)$countRow['available'];
                                if ($branch_count > 0) {
                                    $branch_availability[$branch_name] = $branch_count;
                                    $total_available += $branch_count;
                                }
                            }
                            mysqli_stmt_close($countStmt);
                        }
                        
                        $available_count = $total_available;
                        
                        // Map room type to image
                        $room_type_lower = strtolower($room_type['room_type']);
                        $room_image = 'img/bg1.jpeg'; // Default fallback
                        if (strpos($room_type_lower, 'single') !== false) {
                            $room_image = 'img/s1.jpg';
                        } elseif (strpos($room_type_lower, 'double') !== false) {
                            $room_image = 'img/s2.jpg';
                        } elseif (strpos($room_type_lower, 'deluxe') !== false) {
                            $room_image = 'img/s3.jpg';
                        }
                    ?>
                        <div class="col-md-4">
                            <div class="room-card" data-room-type-id="<?php echo $room_type['room_type_id']; ?>">
                                <img src="<?php echo $room_image; ?>" alt="<?php echo htmlspecialchars($room_type['room_type']); ?>" class="room-card-image" onerror="this.src='img/bg1.jpeg'">
                                <div class="room-card-content">
                                    <h3><?php echo htmlspecialchars($room_type['room_type']); ?></h3>
                                    <div class="price">
                                        LKR <?php echo number_format($room_type['price']); ?>
                                        <span>/night</span>
                                    </div>
                                    <?php if ($is_guest && $guest_user): ?>
                                    <div class="availability-badge" style="background: <?php echo $available_count > 0 ? '#28a745' : '#dc3545'; ?>; color: white; padding: 8px 12px; border-radius: 4px; margin: 10px 0; text-align: center; font-weight: 500;">
                                        <i class="fa fa-<?php echo $available_count > 0 ? 'check-circle' : 'times-circle'; ?>"></i>
                                        <?php if ($available_count > 0 && !empty($branch_availability)): ?>
                                            <div style="font-size: 0.9em; margin-top: 5px;">
                                                <?php 
                                                $branch_counts = [];
                                                foreach ($branch_availability as $branch_name => $count) {
                                                    $branch_counts[] = "<strong>{$branch_name}</strong>: {$count}";
                                                }
                                                echo implode(', ', $branch_counts);
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="available-count"><?php echo $available_count; ?></span> Room<?php echo $available_count != 1 ? 's' : ''; ?> Available
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    <ul class="features">
                                        <li><i class="fa fa-users"></i> Max <?php echo $room_type['max_person']; ?> Person<?php echo $room_type['max_person'] > 1 ? 's' : ''; ?></li>
                                        <li><i class="fa fa-wifi"></i> Free WiFi</li>
                                        <li><i class="fa fa-tv"></i> Flat Screen TV</li>
                                        <li><i class="fa fa-snowflake-o"></i> Air Conditioning</li>
                                    </ul>
                                    <?php if ($is_guest && $guest_user): ?>
                                        <?php if ($available_count > 0): ?>
                                            <a href="guest_booking.php?room_type=<?php echo urlencode($room_type['room_type']); ?>" class="btn btn-primary btn-block">
                                                Book Now
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-block" disabled>
                                                Fully Booked
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="guest_login.php?action=book&room_type=<?php echo urlencode($room_type['room_type']); ?>" class="btn btn-primary btn-block">
                                            Book Now
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-md-12 text-center">
                        <p>Room types will be available soon.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if (!$is_guest || !$guest_user): ?>
    <!-- Facilities Section -->
    <section class="section" id="facilities" style="background: var(--color-bg);">
        <div class="container">
            <div class="section-title">
                <h2>Facilities & Services</h2>
                <p>Everything you need for a comfortable stay</p>
            </div>
            <div class="row">
                <div class="col-md-4 col-sm-6">
                    <div class="facility-item">
                        <img src="img/f4.jpg" alt="Free WiFi" class="facility-item-image" onerror="this.src='img/bg1.jpeg'">
                        <div class="facility-item-content">
                            <i class="fa fa-wifi"></i>
                            <h4>Free WiFi</h4>
                            <p>High-speed internet throughout</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="facility-item">
                        <img src="img/f6.jpg" alt="Restaurant" class="facility-item-image" onerror="this.src='img/bg2.jpeg'">
                        <div class="facility-item-content">
                            <i class="fa fa-cutlery"></i>
                            <h4>Restaurant</h4>
                            <p>Fine dining & room service</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="facility-item">
                        <img src="img/f5.jpg" alt="Parking" class="facility-item-image" onerror="this.src='img/bg3.jpeg'">
                        <div class="facility-item-content">
                            <i class="fa fa-car"></i>
                            <h4>Parking</h4>
                            <p>Complimentary parking</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="facility-item">
                        <img src="img/f2.jpg" alt="Grand Ball Room" class="facility-item-image">
                        <div class="facility-item-content">
                            <i class="fa fa-glass"></i>
                            <h4>Grand Ball Room</h4>
                            <p>Elegant venue for events</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="facility-item">
                        <img src="img/f1.jpg" alt="Executive Conference Room" class="facility-item-image">
                        <div class="facility-item-content">
                            <i class="fa fa-users"></i>
                            <h4>Executive Conference Room</h4>
                            <p>Professional meeting space</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="facility-item">
                        <img src="img/f3.jpg" alt="Meeting Room" class="facility-item-image">
                        <div class="facility-item-content">
                            <i class="fa fa-comments"></i>
                            <h4>Meeting Room</h4>
                            <p>Modern meeting facilities</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="section" id="testimonials">
        <div class="container">
            <div class="section-title">
                <h2>Guest Testimonials</h2>
                <p>What our guests say about us</p>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="testimonial-card">
                        <img src="img/t1.jpg" alt="Sarah Johnson" class="testimonial-image" onerror="this.src='img/user.png'">
                        <div class="stars">
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                        </div>
                        <p>"Absolutely wonderful experience! The staff was incredibly attentive and the rooms were immaculate. Will definitely return!"</p>
                        <div class="author">- Sarah Johnson</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="testimonial-card">
                        <img src="img/t2.jpg" alt="Michael Chen" class="testimonial-image" onerror="this.src='img/user.png'">
                        <div class="stars">
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                        </div>
                        <p>"Perfect location, excellent service, and beautiful accommodations. The best hotel experience I've had in years!"</p>
                        <div class="author">- Michael Chen</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="section contact-section" id="contact">
        <div class="container">
            <div class="section-title">
                <h2>Contact Us</h2>
                <p>We're here to help you plan your perfect stay</p>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="contact-info">
                        <h4><i class="fa fa-map-marker"></i> Address</h4>
                        <p>12 Luxury Street<br>Colombo 07, <br>Sri Lanka</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="contact-info">
                        <h4><i class="fa fa-phone"></i> Phone</h4>
                        <p>+1 (555) 123-4567<br>+1 (555) 123-4568</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="contact-info">
                        <h4><i class="fa fa-envelope"></i> Email</h4>
                        <p>kaizenhotelmanagementsystem@gmail.com</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="public-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h4>About KAIZEN Hotel</h4>
                    <p style="color: rgba(255, 255, 255, 0.8); font-size: 14px;">Experience luxury and comfort at our premium hotel. We are committed to providing exceptional service and creating memorable experiences for all our guests.</p>
                </div>
                <div class="col-md-4">
                    <h4>Quick Links</h4>
                    <ul style="list-style: none; padding: 0;">
                        <li><a href="#home">Home</a></li>
                        <?php if (!$is_guest || !$guest_user): ?>
                            <li><a href="#about">About</a></li>
                        <?php endif; ?>
                        <li><a href="#rooms">Rooms</a></li>
                        <?php if (!$is_guest || !$guest_user): ?>
                            <li><a href="#facilities">Facilities</a></li>
                        <?php endif; ?>
                        <li><a href="guest_login.php">Guest Login</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h4>Connect With Us</h4>
                    <p style="color: rgba(255, 255, 255, 0.8);">
                        <a href="#" style="margin-right: 15px; font-size: 24px;"><i class="fa fa-facebook"></i></a>
                        <a href="#" style="margin-right: 15px; font-size: 24px;"><i class="fa fa-twitter"></i></a>
                        <a href="#" style="margin-right: 15px; font-size: 24px;"><i class="fa fa-instagram"></i></a>
                        <a href="#" style="font-size: 24px;"><i class="fa fa-linkedin"></i></a>
                    </p>
                </div>
            </div>
            <div class="footer-bottom">
                <p style="color: rgba(255, 255, 255, 0.6); margin: 0; font-size: 14px;">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($hotel_name); ?>. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script src="js/jquery-1.11.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Smooth scrolling for anchor links
        $(document).ready(function() {
            $('a[href^="#"]').on('click', function(event) {
                var target = $(this.getAttribute('href'));
                if( target.length ) {
                    event.preventDefault();
                    $('html, body').stop().animate({
                        scrollTop: target.offset().top - 70
                    }, 1000);
                }
            });
            
            // Real-time room availability updates (only for logged-in guests)
            <?php if ($is_guest && $guest_user): ?>
            function updateRoomAvailability() {
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: { get_available_room_counts: true },
                    dataType: 'json',
                    success: function(response) {
                        if (response.done && response.room_types) {
                            $.each(response.room_types, function(index, roomType) {
                                var $card = $('.room-card[data-room-type-id="' + roomType.room_type_id + '"]');
                                if ($card.length) {
                                    var $badge = $card.find('.availability-badge');
                                    var $icon = $badge.find('i');
                                    var $button = $card.find('.btn-block');
                                    var newCount = roomType.available;
                                    
                                    // Get old count from badge
                                    var oldCount = 0;
                                    var $oldCountSpan = $badge.find('.available-count');
                                    if ($oldCountSpan.length) {
                                        oldCount = parseInt($oldCountSpan.text()) || 0;
                                    } else {
                                        // Try to extract from branch availability text
                                        var branchText = $badge.find('div').text();
                                        if (branchText) {
                                            var matches = branchText.match(/\d+/g);
                                            if (matches) {
                                                oldCount = matches.reduce(function(a, b) { return parseInt(a) + parseInt(b); }, 0);
                                            }
                                        }
                                    }
                                    
                                    // Update badge content with branch-wise availability
                                    var badgeContent = '';
                                    if (newCount > 0 && roomType.branch_availability && Object.keys(roomType.branch_availability).length > 0) {
                                        var branchCounts = [];
                                        $.each(roomType.branch_availability, function(branchName, count) {
                                            branchCounts.push('<strong>' + branchName + '</strong>: ' + count);
                                        });
                                        badgeContent = '<div style="font-size: 0.9em; margin-top: 5px;">' + branchCounts.join(', ') + '</div>';
                                    } else {
                                        badgeContent = '<span class="available-count">' + newCount + '</span> Room' + (newCount != 1 ? 's' : '') + ' Available';
                                    }
                                    
                                    // Update badge HTML (keep icon, replace content)
                                    $badge.html($icon[0].outerHTML + badgeContent);
                                    
                                    // Update badge color and icon
                                    if (newCount > 0) {
                                        $badge.css('background', '#28a745');
                                        $badge.find('i').removeClass('fa-times-circle').addClass('fa-check-circle');
                                        
                                        // Update button
                                        if ($button.prop('disabled')) {
                                            $button.prop('disabled', false)
                                                   .removeClass('btn-secondary')
                                                   .addClass('btn-primary')
                                                   .text('Book Now');
                                        }
                                    } else {
                                        $badge.css('background', '#dc3545');
                                        $badge.find('i').removeClass('fa-check-circle').addClass('fa-times-circle');
                                        
                                        // Update button
                                        if (!$button.prop('disabled')) {
                                            $button.prop('disabled', true)
                                                   .removeClass('btn-primary')
                                                   .addClass('btn-secondary')
                                                   .text('Fully Booked');
                                        }
                                    }
                                    
                                    // Highlight change with animation
                                    if (oldCount !== newCount) {
                                        $badge.fadeOut(200).fadeIn(200).fadeOut(200).fadeIn(200);
                                    }
                                }
                            });
                        }
                    },
                    error: function() {
                        console.log('Failed to update room availability');
                    }
                });
            }
            
            // Update every 5 seconds (only for logged-in guests)
            setInterval(updateRoomAvailability, 5000);
            
            // Also update when page becomes visible
            $(document).on('visibilitychange', function() {
                if (!document.hidden) {
                    updateRoomAvailability();
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
