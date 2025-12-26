<?php
/**
 * Staff Portal Header
 * Smart Hotel Management System
 */
if (!isset($user)) {
    $user = getCurrentUser();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Staff Portal - Hotel Management System</title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/font-awesome.min.css" rel="stylesheet">
    <link href="../css/styles.css" rel="stylesheet">
    <style>
        .staff-navbar {
            background: #2A1F5F;
            border: none;
            border-radius: 0;
            border-bottom: 2px solid #3D2C8D;
        }
        .staff-navbar .navbar-brand {
            color: #fff !important;
            font-weight: bold;
        }
        .staff-navbar .navbar-brand span {
            color: #BFC0C0;
        }
        .staff-navbar .navbar-nav > li > a {
            color: #fff !important;
            transition: all 0.2s ease;
        }
        .staff-navbar .navbar-nav > li > a:hover {
            background-color: #5A4BCF;
            color: #fff !important;
        }
        .staff-navbar .navbar-nav > li.active > a {
            background-color: #3D2C8D;
            color: #BFC0C0 !important;
        }
        .staff-container {
            margin-top: 80px;
            min-height: calc(100vh - 80px);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-custom staff-navbar navbar-fixed-top">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#staff-navbar-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="dashboard.php">
                    <span class="fa fa-hotel"></span> KAIZEN Hotel Management System
                </a>
            </div>
            <div class="collapse navbar-collapse" id="staff-navbar-collapse">
                <ul class="nav navbar-nav">
                    <li><a href="dashboard.php"><em class="fa fa-dashboard"></em> Dashboard</a></li>
                    <li><a href="services.php"><em class="fa fa-bell"></em> Service Requests</a></li>
                </ul>
                <ul class="nav navbar-nav navbar-right">
                    <li class="dropdown">
                        <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                            <em class="fa fa-user"></em> <?php echo htmlspecialchars($user['name']); ?> 
                            <span class="caret"></span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="../staff_logout.php"><i class="fa fa-power-off" style="color:#C62828;"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container staff-container">

