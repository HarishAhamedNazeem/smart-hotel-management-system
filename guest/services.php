<?php
/**
 * Guest Services Page
 * Allows guests to request services (Room Service, Housekeeping, Laundry, Spa)
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
require_once "includes/security.php";
require_once "includes/promotions.php";

// IMPORTANT: Prevent admin/staff users from accessing guest portal
// Clear admin/staff session if guest session exists to prevent conflicts
if (isset($_SESSION['user_id']) && isset($_SESSION['guest_id'])) {
    // Both sessions exist - this is a conflict
    // Clear admin/staff session variables to prevent conflicts
    unset($_SESSION['user_id']);
    unset($_SESSION['current_user_data']);
    unset($_SESSION['user_roles']);
    unset($_SESSION['user_permissions']);
}

// If admin/staff is logged in (but not guest), redirect to admin portal
// But preserve guest session if it exists
if (isset($_SESSION['user_id']) && !isset($_SESSION['guest_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Admin is logged in but no guest session - redirect to admin portal
    if (isset($_SESSION['LAST_ACTIVITY'])) {
        $_SESSION['LAST_ACTIVITY'] = time();
    }
    header('Location: ../index.php?dashboard');
    exit();
}

// Require guest login (using guest_id from guests table)
if (!isset($_SESSION['guest_id'])) {
    // No guest session - redirect to guest login
    // Don't redirect to admin portal even if admin is logged in
    header('Location: ../guest_login.php');
    exit();
}

// Check for inactivity timeout (30 minutes = 1800 seconds for guest portal)
// Use separate LAST_ACTIVITY_GUEST to avoid conflicts with admin session
if (isset($_SESSION['guest_id'])) {
    // Check guest-specific activity tracking
    if (isset($_SESSION['LAST_ACTIVITY_GUEST'])) {
        if (time() - $_SESSION['LAST_ACTIVITY_GUEST'] > 1800) {
            // Guest session expired due to inactivity
            // Only clear guest session vars, preserve admin session if exists
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

// Get guest record from guests table (NOT using getCurrentUser which is for admin/staff)
$guest_id = $_SESSION['guest_id'];
$guestQuery = "SELECT * FROM guests WHERE guest_id = ? AND status = 'active' LIMIT 1";
$guestStmt = mysqli_prepare($connection, $guestQuery);
mysqli_stmt_bind_param($guestStmt, "i", $guest_id);
mysqli_stmt_execute($guestStmt);
$guestResult = mysqli_stmt_get_result($guestStmt);
$user = mysqli_fetch_assoc($guestResult);
mysqli_stmt_close($guestStmt);

if (!$user) {
    session_destroy();
    header('Location: ../guest_login.php');
    exit();
}

// Get guest_id from session
$guest_id = $_SESSION['guest_id'] ?? null;

// Get active bookings for dropdown
$activeBookings = [];
if ($guest_id) {
    $bookingsQuery = "SELECT b.booking_id, r.room_no, rt.room_type, b.check_in, b.check_out
                     FROM booking b
                     LEFT JOIN room r ON b.room_id = r.room_id
                     LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id
                     WHERE b.guest_id = ? AND b.payment_status = 0
                     ORDER BY b.booking_date DESC";
    $bookingsStmt = mysqli_prepare($connection, $bookingsQuery);
    mysqli_stmt_bind_param($bookingsStmt, "i", $guest_id);
    mysqli_stmt_execute($bookingsStmt);
    $bookingsResult = mysqli_stmt_get_result($bookingsStmt);
    
    while ($row = mysqli_fetch_assoc($bookingsResult)) {
        $activeBookings[] = $row;
    }
    mysqli_stmt_close($bookingsStmt);
}

// Get service types - filter by guest's branch (from booking) or show global services
$serviceTypesQuery = "SELECT st.* FROM service_types st WHERE st.is_active = 1";
$guest_branch_id = null;

if ($guest_id) {
    // Get guest's branch_id from their most recent active booking
    // Branch is stored in room table, so we join booking -> room to get branch_id
    $bookingQuery = "SELECT r.branch_id FROM booking b 
                     INNER JOIN room r ON b.room_id = r.room_id
                     WHERE b.guest_id = ? AND b.payment_status = 0
                     ORDER BY b.booking_id DESC LIMIT 1";
    $bookingStmt = mysqli_prepare($connection, $bookingQuery);
    if ($bookingStmt) {
        mysqli_stmt_bind_param($bookingStmt, "i", $guest_id);
        mysqli_stmt_execute($bookingStmt);
        $bookingResult = mysqli_stmt_get_result($bookingStmt);
        $booking = mysqli_fetch_assoc($bookingResult);
        if ($booking && isset($booking['branch_id']) && $booking['branch_id'] !== null) {
            $guest_branch_id = intval($booking['branch_id']);
        }
        mysqli_stmt_close($bookingStmt);
    }
    
    // Filter service types based on branch
    if ($guest_branch_id) {
        // Show services for guest's branch and global services (branch_id IS NULL)
        $serviceTypesQuery .= " AND (st.branch_id = " . $guest_branch_id . " OR st.branch_id IS NULL)";
    } else {
        // Guest has no active booking with branch - show only global services
        $serviceTypesQuery .= " AND st.branch_id IS NULL";
    }
} else {
    // No guest_id - show only global services
    $serviceTypesQuery .= " AND st.branch_id IS NULL";
}

$serviceTypesQuery .= " ORDER BY st.service_name";
$serviceTypesResult = mysqli_query($connection, $serviceTypesQuery);
$service_types = [];
if ($serviceTypesResult) {
    while ($row = mysqli_fetch_assoc($serviceTypesResult)) {
        $service_types[] = $row;
    }
}

// Get service requests for this guest
$serviceRequests = [];
if ($guest_id) {
    $requestsQuery = "SELECT sr.*, st.service_name, st.category,
                     b.booking_id, r.room_no, rt.room_type
                     FROM service_requests sr
                     LEFT JOIN service_types st ON sr.service_type_id = st.service_type_id
                     LEFT JOIN booking b ON sr.booking_id = b.booking_id
                     LEFT JOIN room r ON b.room_id = r.room_id
                     LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id
                     WHERE sr.guest_id = ?
                     ORDER BY sr.requested_at DESC";
    $requestsStmt = mysqli_prepare($connection, $requestsQuery);
    mysqli_stmt_bind_param($requestsStmt, "i", $guest_id);
    mysqli_stmt_execute($requestsStmt);
    $requestsResult = mysqli_stmt_get_result($requestsStmt);
    
    while ($row = mysqli_fetch_assoc($requestsResult)) {
        $serviceRequests[] = $row;
    }
    mysqli_stmt_close($requestsStmt);
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request Services - KAIZEN Hotel</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <style>
        body {
            background: var(--color-bg);
        }
        .services-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 15px;
        }
        .services-card {
            background: var(--color-surface);
            border: 1px solid var(--color-accent);
            border-radius: 4px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .services-card h3 {
            color: var(--color-primary-dark);
            margin-bottom: 20px;
            font-weight: 300;
        }
        .service-type-card {
            background: var(--color-surface);
            border: 2px solid var(--color-accent);
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .service-type-card:hover {
            border-color: var(--color-primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(61, 44, 141, 0.2);
        }
        .service-type-card.selected {
            border-color: var(--color-primary);
            background: rgba(61, 44, 141, 0.05);
        }
        .service-type-card i {
            font-size: 32px;
            color: var(--color-primary);
            margin-bottom: 10px;
        }
        .service-request-item {
            background: var(--color-surface);
            border: 1px solid var(--color-accent);
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .request-status {
            padding: 5px 15px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }
        .status-pending {
            background: var(--color-warning);
            color: #fff;
        }
        .status-assigned {
            background: var(--color-primary);
            color: #fff;
        }
        .status-in_progress {
            background: var(--color-primary-light);
            color: #fff;
        }
        .status-completed {
            background: var(--color-success);
            color: #fff;
        }
        .status-cancelled {
            background: var(--color-error);
            color: #fff;
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

    <div class="services-container">
        <div class="services-card">
            <h3><i class="fa fa-bell"></i> Request Services</h3>
            
            <div id="service-messages"></div>
            
            <!-- Tabs -->
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active">
                    <a href="#request" aria-controls="request" role="tab" data-toggle="tab">New Request</a>
                </li>
                <li role="presentation">
                    <a href="#history" aria-controls="history" role="tab" data-toggle="tab">Requests</a>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content">
                <!-- New Request Tab -->
                <div role="tabpanel" class="tab-pane active" id="request">
                    <!-- Auto-routing Info -->
                    <div class="alert alert-info" style="margin-bottom: 20px;">
                        <i class="fa fa-info-circle"></i> <strong>Smart Routing:</strong> Your service requests are automatically routed to the appropriate staff for quick action.
                        <ul style="margin: 10px 0 0 20px;">
                            <li><strong>Housekeeping & Maintenance:</strong> Assigned to Housekeeping Staff</li>
                            <li><strong>Transport & Dining:</strong> Assigned to Concierge</li>
                        </ul>
                    </div>
                    
                    <form id="serviceRequestForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="action" value="create_service_request">
                        
                        <div class="form-group">
                            <label>Service Type *</label>
                            <div class="row">
                                <?php foreach ($service_types as $st): ?>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="service-type-card" data-service-id="<?php echo $st['service_type_id']; ?>" data-category="<?php echo htmlspecialchars($st['category']); ?>">
                                            <div class="text-center">
                                                <?php
                                                // Category-based icons (Font Awesome 4 compatible)
                                                $categoryIcons = [
                                                    'room_service' => 'fa-cutlery',
                                                    'housekeeping' => 'fa-tint',  // Water drop for cleaning (FA4 compatible)
                                                    'maintenance' => 'fa-wrench',
                                                    'dining' => 'fa-coffee',
                                                    'transport' => 'fa-car',
                                                    'concierge' => 'fa-bell',  // Changed from fa-concierge-bell (FA5 only)
                                                    'other' => 'fa-bell'
                                                ];
                                                $icon = $categoryIcons[$st['category']] ?? 'fa-bell';
                                                ?>
                                                <i class="fa <?php echo $icon; ?>"></i>
                                                <h5 style="margin-top: 10px; color: var(--color-primary-dark);">
                                                    <?php echo htmlspecialchars($st['service_name']); ?>
                                                </h5>
                                                <small style="color: var(--color-text-secondary); display: block; margin-top: 5px;">
                                                    <?php 
                                                    $categoryNames = [
                                                        'room_service' => 'Room Service',
                                                        'housekeeping' => 'Housekeeping',
                                                        'maintenance' => 'Maintenance',
                                                        'dining' => 'Dining',
                                                        'transport' => 'Transport',
                                                        'concierge' => 'Concierge',
                                                        'other' => 'Other'
                                                    ];
                                                    echo $categoryNames[$st['category']] ?? ucfirst($st['category']);
                                                    ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" id="selected_service_type" name="service_type_id" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Booking (Optional)</label>
                            <select name="booking_id" id="service_booking_id" class="form-control">
                                <option value="">Select a booking (optional)</option>
                                <?php foreach ($activeBookings as $booking): ?>
                                    <option value="<?php echo $booking['booking_id']; ?>">
                                        Booking #<?php echo $booking['booking_id']; ?> - 
                                        <?php echo htmlspecialchars($booking['room_type']); ?> 
                                        (Room #<?php echo htmlspecialchars($booking['room_no']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Request Title *</label>
                            <input type="text" name="request_title" id="service_title" class="form-control" required
                                   placeholder="Brief description of your request">
                        </div>
                        
                        <div class="form-group">
                            <label>Request Description *</label>
                            <textarea name="request_description" id="service_description" class="form-control" rows="4" required
                                      placeholder="Please provide details about your service request"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority" id="service_priority" class="form-control">
                                <option value="normal" selected>Normal</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fa fa-paper-plane"></i> Submit Request
                        </button>
                    </form>
                </div>
                
                <!-- Request History Tab -->
                <div role="tabpanel" class="tab-pane" id="history">
                    <?php if (empty($serviceRequests)): ?>
                        <div class="text-center" style="padding: 40px;">
                            <i class="fa fa-inbox" style="font-size: 48px; color: var(--color-accent); margin-bottom: 15px;"></i>
                            <h4>No service requests yet</h4>
                            <p>You haven't made any service requests.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($serviceRequests as $request): ?>
                            <div class="service-request-item">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                    <div>
                                        <h4 style="color: var(--color-primary-dark); margin: 0;">
                                            <?php echo htmlspecialchars($request['request_title']); ?>
                                        </h4>
                                        <p style="color: var(--color-text-secondary); margin: 5px 0 0 0;">
                                            <i class="fa fa-tag"></i> <?php echo htmlspecialchars($request['service_name']); ?>
                                        </p>
                                    </div>
                                    <span class="request-status status-<?php echo $request['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                    </span>
                                </div>
                                
                                <p style="color: var(--color-text-primary); margin-bottom: 15px;">
                                    <?php echo htmlspecialchars($request['request_description']); ?>
                                </p>
                                
                                <div style="display: flex; gap: 20px; flex-wrap: wrap; font-size: 14px; color: var(--color-text-secondary);">
                                    <span><i class="fa fa-calendar"></i> <?php echo date('d M Y, h:i A', strtotime($request['requested_at'])); ?></span>
                                    <?php if ($request['booking_id']): ?>
                                        <span><i class="fa fa-bed"></i> Room #<?php echo htmlspecialchars($request['room_no'] ?? 'N/A'); ?></span>
                                    <?php endif; ?>
                                    <span><i class="fa fa-flag"></i> <?php echo ucfirst($request['priority']); ?> Priority</span>
                                </div>
                                
                                <?php if (!empty($request['notes'])): ?>
                                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--color-accent);">
                                        <strong>Staff Notes:</strong>
                                        <p style="margin: 5px 0 0 0; color: var(--color-text-secondary);">
                                            <?php echo htmlspecialchars($request['notes']); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery-1.11.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            var selectedServiceType = null;
            
            // Service type selection
            $('.service-type-card').on('click', function() {
                $('.service-type-card').removeClass('selected');
                $(this).addClass('selected');
                selectedServiceType = $(this).data('service-id');
                $('#selected_service_type').val(selectedServiceType);
            });
            
            // Submit service request
            $('#serviceRequestForm').on('submit', function(e) {
                e.preventDefault();
                
                if (!selectedServiceType) {
                    showMessage('Please select a service type.', 'warning');
                    return;
                }
                
                var formData = $(this).serialize();
                
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showMessage('Service request submitted successfully!', 'success');
                            $('#serviceRequestForm')[0].reset();
                            $('.service-type-card').removeClass('selected');
                            selectedServiceType = null;
                            $('#selected_service_type').val('');
                            
                            // Reload page after 2 seconds to show new request
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            showMessage(response.message || 'Error submitting request. Please try again.', 'danger');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        console.error('Response:', xhr.responseText);
                        
                        var errorMessage = 'Error submitting request. Please try again.';
                        
                        // Try to parse error response if it's JSON
                        try {
                            var errorResponse = JSON.parse(xhr.responseText);
                            if (errorResponse.message) {
                                errorMessage = errorResponse.message;
                            }
                        } catch (e) {
                            // If not JSON, use the raw response or default message
                            if (xhr.responseText) {
                                errorMessage = 'Server error: ' + xhr.responseText.substring(0, 100);
                            }
                        }
                        
                        showMessage(errorMessage, 'danger');
                    }
                });
            });
            
            function showMessage(message, type) {
                var alertClass = 'alert-' + type;
                var html = '<div class="alert ' + alertClass + ' alert-dismissible" role="alert">';
                html += '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>';
                html += message;
                html += '</div>';
                $('#service-messages').html(html);
            }
        });
    </script>
</body>
</html>
