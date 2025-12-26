<?php
/**
 * User Registration Page
 * Smart Hotel Management System
 */
session_start();
?>
<!--
    User Registration Page
    Allows new users to create accounts with duplicate prevention
-->
<html>
<head>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css"/>
    <title>Register - Hotel Management System</title>
</head>
<body>

<div class="container">
    <div class="card card-container">
        <img id="profile-img" class="profile-img-card" src="img/htl.png"/>
        
        <br>
        <div class="result">
            <?php
            if (isset($_GET['empty'])){
                echo '<div class="alert alert-danger">Please fill all required fields</div>';
            } elseif (isset($_GET['email_exists'])){
                echo '<div class="alert alert-danger">Email already exists. Please use a different email.</div>';
            } elseif (isset($_GET['username_exists'])){
                echo '<div class="alert alert-danger">Username already exists. Please choose a different username.</div>';
            } elseif (isset($_GET['password_mismatch'])){
                echo '<div class="alert alert-danger">Passwords do not match.</div>';
            } elseif (isset($_GET['weak_password'])){
                echo '<div class="alert alert-danger">Password must be at least 8 characters long.</div>';
            } elseif (isset($_GET['invalid_email'])){
                echo '<div class="alert alert-danger">Invalid email address.</div>';
            } elseif (isset($_GET['success'])){
                echo '<div class="alert alert-success">Registration successful! Please login.</div>';
            } elseif (isset($_GET['error'])){
                echo '<div class="alert alert-danger">An error occurred. Please try again.</div>';
            }
            ?>
        </div>
        <form class="form-signin" data-toggle="validator" action="ajax.php" method="post" id="registerForm">
            <?php
            require_once 'includes/security.php';
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $csrf_token = generateCSRFToken();
            ?>
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="row">
                <div class="form-group col-lg-12">
                    <label>Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="Enter your full name" required
                           data-error="Enter your full name" autocomplete="name">
                    <div class="help-block with-errors"></div>
                </div>
                
                <div class="form-group col-lg-12">
                    <label>Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="Choose a username" required
                           data-error="Enter a username" autocomplete="username" minlength="3" maxlength="15">
                    <div class="help-block with-errors"></div>
                </div>
                
                <div class="form-group col-lg-12">
                    <label>Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="Enter your email" required
                           data-error="Enter a valid email address" autocomplete="email">
                    <div class="help-block with-errors"></div>
                </div>
                
                <div class="form-group col-lg-12">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" class="form-control" placeholder="Enter your phone number"
                           autocomplete="tel">
                    <div class="help-block with-errors"></div>
                </div>
                
                <div class="form-group col-lg-12">
                    <label>Password <span class="text-danger">*</span></label>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required
                           data-error="Password must be at least 8 characters" autocomplete="new-password" minlength="8">
                    <div class="help-block with-errors"></div>
                </div>
                
                <div class="form-group col-lg-12">
                    <label>Confirm Password <span class="text-danger">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Confirm password" required
                           data-error="Passwords must match" autocomplete="new-password" data-match="#password" data-match-error="Passwords do not match">
                    <div class="help-block with-errors"></div>
                </div>
            </div>

            <button class="btn btn-lg btn-success btn-block btn-signin" type="submit" name="register">REGISTER</button>
            
            <div class="text-center" style="margin-top: 15px;">
                <a href="login.php">Already have an account? Login here</a>
            </div>

        </form><!-- /form -->
    </div><!-- /card-container -->
</div><!-- /container -->

<script src="js/jquery-1.11.1.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/validator.min.js"></script>
</body>
</html>

