<?php
/**
 * Guest Profile Page
 * Allows guests to view and update their profile
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
$customer = $guest; // Use guest data for customer references

// Get ID card types
$idCardTypesQuery = "SELECT * FROM id_card_type ORDER BY id_card_type";
$idCardTypesResult = mysqli_query($connection, $idCardTypesQuery);
$id_card_types = [];
if ($idCardTypesResult) {
    while ($row = mysqli_fetch_assoc($idCardTypesResult)) {
        $id_card_types[] = $row;
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile - KAIZEN Hotel</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <style>
        body {
            background: var(--color-bg);
        }
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 15px;
        }
        .profile-card {
            background: var(--color-surface);
            border: 1px solid var(--color-accent);
            border-radius: 4px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .profile-card h3 {
            color: var(--color-primary-dark);
            margin-bottom: 20px;
            font-weight: 300;
        }
        .profile-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--color-accent);
            margin-bottom: 30px;
        }
        .profile-header i {
            font-size: 64px;
            color: var(--color-primary);
            margin-bottom: 15px;
        }
        .profile-header h4 {
            color: var(--color-primary-dark);
            margin: 0;
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

    <div class="profile-container">
        <div class="profile-card">
            <div class="profile-header">
                <i class="fa fa-user-circle"></i>
                <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                <p style="color: var(--color-text-secondary); margin: 5px 0 0 0;">
                    Guest Account
                </p>
            </div>
            
            <div id="profile-messages"></div>
            
            <h3><i class="fa fa-info-circle"></i> Profile Information</h3>
            
            <form id="profileForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" class="form-control" required
                                   value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" readonly>
                            <small class="text-muted">Contact support to change your name</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                            <small class="text-muted">Contact support to change your email</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" class="form-control"
                                   value="<?php echo htmlspecialchars($guest['phone'] ?? $guest['contact_no'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control"
                                   value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" readonly>
                            <small class="text-muted">Username cannot be changed</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>ID Card Type</label>
                            <select name="id_card_type_id" class="form-control">
                                <option value="">Select ID Card Type</option>
                                <?php foreach ($id_card_types as $idct): ?>
                                    <option value="<?php echo $idct['id_card_type_id']; ?>"
                                            <?php echo ($customer && $customer['id_card_type_id'] == $idct['id_card_type_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($idct['id_card_type']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>ID Card Number</label>
                            <input type="text" name="id_card_no" class="form-control"
                                   value="<?php echo htmlspecialchars($customer['id_card_no'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fa fa-save"></i> Update Profile
                    </button>
                    <a href="home.php" class="btn btn-secondary btn-lg" style="margin-left: 10px;">
                        <i class="fa fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="js/jquery-1.11.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#profileForm').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&action=update_guest_profile';
                
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showMessage('Profile updated successfully!', 'success');
                        } else {
                            showMessage(response.message || 'Error updating profile. Please try again.', 'danger');
                        }
                    },
                    error: function() {
                        showMessage('Error updating profile. Please try again.', 'danger');
                    }
                });
            });
            
            function showMessage(message, type) {
                var alertClass = 'alert-' + type;
                var html = '<div class="alert ' + alertClass + ' alert-dismissible" role="alert">';
                html += '<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>';
                html += message;
                html += '</div>';
                $('#profile-messages').html(html);
            }
        });
    </script>
</body>
</html>
