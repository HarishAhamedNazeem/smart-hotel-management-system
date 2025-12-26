<?php
// Determine base path for assets based on current directory
$currentScript = $_SERVER['PHP_SELF'];
$isAdminDir = (strpos($currentScript, '/admin/') !== false);
$isStaffDir = (strpos($currentScript, '/staff/') !== false);
$assetBase = ($isAdminDir || $isStaffDir) ? '../' : '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hotel Management System- Dashboard</title>
    <link href="<?php echo $assetBase; ?>css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $assetBase; ?>css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo $assetBase; ?>css/datepicker3.css" rel="stylesheet">
    <link href="<?php echo $assetBase; ?>css/dataTables.bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $assetBase; ?>css/styles.css" rel="stylesheet">

    <!--Custom Font-->
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-custom navbar-fixed-top" role="navigation">
    <div class="container-fluid">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#sidebar-collapse"><span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span></button>
            <a class="navbar-brand" href="<?php echo $assetBase; ?>index.php?dashboard"><span>KAIZEN </span>Hotel Management System</a>
            <ul class="nav navbar-top-links navbar-right">
                <li class="dropdown"><a class="dropdown-toggle count-info" data-toggle="dropdown" href="#">
                        <em class="fa fa-user"></em>
                    </a>
                    <ul class="dropdown-menu dropdown-alerts">
                        <li><a href="<?php echo $assetBase; ?>staff_logout.php"><i class="fa fa-power-off" style="color:#C62828;"></i>
                                Logout
                            </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div><!-- /.container-fluid -->
</nav>