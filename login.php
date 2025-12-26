<?php
/**
 * Created by PhpStorm.
 * User: vishal
 * Date: 10/23/17
 * Time: 1:45 PM
 */

// Configure session cookie to expire when browser closes
session_set_cookie_params(0);

// Start session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include security functions for CSRF token
require_once 'includes/security.php';

// Generate CSRF token (this will create one if it doesn't exist)
$csrf_token = generateCSRFToken();
?>
<!--
    you can substitue the span of reauth email for a input with the email and
    include the remember me checkbox
    -->
<html>
<head>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css"/>
</head>
<body>


<div class="container">
    <div class="card card-container">
        <img id="profile-img" class="profile-img-card" src="img/logo.jpeg"/>
        
        <br>
        <div class="result">
            <?php
            if (isset($_GET['empty'])){
                echo '<div class="alert alert-danger">Enter Username or Password</div>';
            }elseif (isset($_GET['loginE'])){
                echo '<div class="alert alert-danger">Username or Password Don\'t Match</div>';
            } ?>
        </div>
        <form class="form-signin" data-toggle="validator" action="ajax.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="row">
                <div class="form-group col-lg-12">
                    <label>Username or Email</label>
                    <input type="text" name="email" class="form-control" placeholder="" required
                           data-error="Enter Username or Email" autocomplete="username">
                    <div class="help-block with-errors"></div>
                </div>
                <div class="form-group col-lg-12">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="" required
                           data-error="Enter Password" autocomplete="current-password">
                    <div class="help-block with-errors"></div>
                </div>
            </div>
            
            <?php
            // Display error messages
            if (isset($_GET['error'])) {
                $error = $_GET['error'];
                if ($error === 'csrf') {
                    echo '<div class="alert alert-danger">Security token mismatch. Please try again.</div>';
                } elseif ($error === 'rate_limit') {
                    echo '<div class="alert alert-danger">Too many login attempts. Please try again later.</div>';
                } elseif ($error === 'locked') {
                    echo '<div class="alert alert-danger">Account is temporarily locked due to multiple failed login attempts.</div>';
                }
            }
            ?>

            <button class="btn btn-lg btn-success btn-block btn-signin" type="submit" name="login">LOGIN</button>
            
            <!-- <div class="text-center" style="margin-top: 15px;">
                <a href="register.php">Don't have an account? Register here</a>
            </div> -->

        </form><!-- /form -->
    </div><!-- /card-container -->
</div><!-- /container -->

<script src="js/jquery-1.11.1.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/validator.min.js"></script>
</body>
</html>