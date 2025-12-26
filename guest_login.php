<?php
/**
 * Guest Login Portal
 * Allows guests to login or register for booking rooms
 */

// Configure session cookie to expire when browser closes
session_set_cookie_params(0);

// Start session
session_start();

include_once "db.php";

// Check if guest is already logged in (using guest_id from guests table)
$is_guest = isset($_SESSION['guest_id']);

if ($is_guest) {
    // Guest is already logged in, redirect to home page
    header('Location: home.php');
    exit();
}

// Get action parameter (book, login, register)
$action = isset($_GET['action']) ? $_GET['action'] : 'login';
// If action is 'book', treat it as login
if ($action == 'book') {
    $action = 'login';
}
$room_type_id = isset($_GET['room_type']) ? intval($_GET['room_type']) : 0;
?>
<!DOCTYPE html>
<html lang="en" class="guest-login-page">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Guest Login - KAIZEN Hotel</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
    <link href="css/login.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
    <style>
        /* Guest Login Portal Styles - Following Theme */
        html.guest-login-page,
        body.guest-login-page {
            background: url('img/bg5.jpeg') center center no-repeat !important;
            background-size: cover !important;
            background-attachment: fixed !important;
            background-image: url('img/bg5.jpeg') !important;
            min-height: 100vh;
            height: 100%;
            padding-top: 0;
        }
        html.guest-login-page {
            height: 100%;
        }
        .guest-login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 15px;
        }
        .guest-login-card {
            background: var(--color-surface);
            border-radius: 4px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            padding: 40px;
        }
        .guest-login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .guest-login-header img {
            max-width: 120px;
            margin-bottom: 20px;
        }
        .guest-login-header h2 {
            color: var(--color-primary-dark);
            font-weight: 300;
            margin-bottom: 10px;
        }
        .guest-login-header p {
            color: var(--color-text-secondary);
        }
        .guest-tabs {
            margin-bottom: 25px;
            border-bottom: 2px solid var(--color-accent);
        }
        .guest-tabs ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
        }
        .guest-tabs li {
            flex: 1;
        }
        .guest-tabs a {
            display: block;
            padding: 15px;
            text-align: center;
            color: var(--color-text-secondary);
            text-decoration: none;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
        }
        .guest-tabs a:hover {
            color: var(--color-primary);
            background: rgba(61, 44, 141, 0.05);
        }
        .guest-tabs a.active {
            color: var(--color-primary);
            border-bottom-color: var(--color-primary);
            font-weight: 500;
        }
        .form-group label {
            color: var(--color-primary-dark);
            font-weight: 500;
            margin-bottom: 8px;
        }
        .alert {
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .back-to-home {
            text-align: center;
            margin-top: 20px;
        }
        .back-to-home a {
            color: var(--color-primary);
            text-decoration: none;
        }
        .back-to-home a:hover {
            color: var(--color-primary-light);
        }
    </style>
</head>
<body class="guest-login-page">
    <div class="guest-login-container">
        <div class="guest-login-card">
            <div class="guest-login-header">
                <img src="img/logo.jpeg" alt="KAIZEN Hotel" class="img-responsive" style="margin: 0 auto;">
                <h2>KAIZEN Hotel</h2>
                <p>Guest Portal</p>
            </div>

            <!-- Tabs for Login/Register -->
            <div class="guest-tabs">
                <ul>
                    <li><a href="guest_login.php?action=login<?php echo $room_type_id ? '&room_type='.$room_type_id : ''; ?>" class="<?php echo $action == 'login' || $action == 'book' ? 'active' : ''; ?>">Login</a></li>
                    <li><a href="guest_login.php?action=register<?php echo $room_type_id ? '&room_type='.$room_type_id : ''; ?>" class="<?php echo $action == 'register' ? 'active' : ''; ?>">Register</a></li>
                </ul>
            </div>

            <!-- Messages -->
            <div class="result">
                <?php
                if (isset($_GET['empty'])){
                    echo '<div class="alert alert-danger">Please fill in all required fields</div>';
                } elseif (isset($_GET['loginE'])){
                    echo '<div class="alert alert-danger">Invalid email/username or password</div>';
                } elseif (isset($_GET['registerE'])){
                    echo '<div class="alert alert-danger">Registration failed. Please check your information.</div>';
                } elseif (isset($_GET['registerS'])){
                    echo '<div class="alert alert-success" style="border-left: 4px solid #5cb85c;">
                        <i class="fa fa-check-circle" style="font-size: 18px; color: #5cb85c;"></i> 
                        <strong style="font-size: 16px;">Registration Successful!</strong>
                        <p style="margin: 10px 0 0 0; font-size: 14px;">Your account has been created successfully. Please click on the <strong>Login</strong> tab above and enter your username and password to access your account.</p>
                    </div>';
                } elseif (isset($_GET['error'])){
                    $error = $_GET['error'];
                    if ($error === 'csrf') {
                        echo '<div class="alert alert-danger">Security token mismatch. Please try again.</div>';
                    } elseif ($error === 'rate_limit') {
                        echo '<div class="alert alert-danger">Too many attempts. Please try again later.</div>';
                    } elseif ($error === 'locked') {
                        echo '<div class="alert alert-danger">Account is temporarily locked.</div>';
                    } elseif ($error === 'admin_blocked') {
                        echo '<div class="alert alert-danger">Admin/staff accounts cannot login via guest portal. Please use the admin login page.</div>';
                    }
                }
                ?>
            </div>

            <?php if ($action == 'register'): ?>
                <!-- Registration Form -->
                <form class="form-signin" data-toggle="validator" action="ajax.php" method="post">
                    <?php
                    require_once 'includes/security.php';
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $csrf_token = generateCSRFToken();
                    ?>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="register_type" value="guest">
                    <?php if ($room_type_id): ?>
                        <input type="hidden" name="redirect_room_type" value="<?php echo $room_type_id; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Enter your full name" required
                               data-error="Please enter your name" autocomplete="name">
                        <div class="help-block with-errors"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required
                               data-error="Please enter a valid email" autocomplete="email">
                        <div class="help-block with-errors"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" class="form-control" placeholder="Enter your phone number" required
                               data-error="Please enter your phone number" autocomplete="tel">
                        <div class="help-block with-errors"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Choose a username" required
                               data-error="Please choose a username" autocomplete="username" minlength="3">
                        <div class="help-block with-errors"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Choose a password" required
                               data-error="Please enter a password" autocomplete="new-password" minlength="6">
                        <div class="help-block with-errors"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="password_confirm" class="form-control" placeholder="Confirm your password" required
                               data-error="Passwords must match" autocomplete="new-password">
                        <div class="help-block with-errors"></div>
                    </div>

                    <button class="btn btn-lg btn-primary btn-block btn-signin" type="submit" name="guest_register">
                        <i class="fa fa-user-plus"></i> Register
                    </button>
                </form>
            <?php else: ?>
                <!-- Login Form -->
                <form class="form-signin" data-toggle="validator" action="ajax.php" method="post">
                    <?php
                    require_once 'includes/security.php';
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $csrf_token = generateCSRFToken();
                    ?>
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="login_type" value="guest">
                    <?php if ($room_type_id): ?>
                        <input type="hidden" name="redirect_room_type" value="<?php echo $room_type_id; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Email or Username</label>
                        <input type="text" name="email" class="form-control" placeholder="Enter email or username" required
                               data-error="Enter email or username" autocomplete="username">
                        <div class="help-block with-errors"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required
                               data-error="Enter password" autocomplete="current-password">
                        <div class="help-block with-errors"></div>
                    </div>

                    <button class="btn btn-lg btn-primary btn-block btn-signin" type="submit" name="guest_login">
                        <i class="fa fa-sign-in"></i> Login
                    </button>
                </form>
            <?php endif; ?>

            <div class="back-to-home">
                <a href="home.php"><i class="fa fa-arrow-left"></i> Back to Home</a>
            </div>
        </div>
    </div>

    <script src="js/jquery-1.11.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/validator.min.js"></script>
    <script>
        // Password confirmation validation
        $(document).ready(function() {
            $('input[name="password_confirm"]').on('blur', function() {
                var password = $('input[name="password"]').val();
                var confirm = $(this).val();
                if (password !== confirm) {
                    $(this).closest('.form-group').addClass('has-error');
                    $(this).next('.help-block').text('Passwords do not match');
                } else {
                    $(this).closest('.form-group').removeClass('has-error');
                    $(this).next('.help-block').text('');
                }
            });
        });
    </script>
</body>
</html>
