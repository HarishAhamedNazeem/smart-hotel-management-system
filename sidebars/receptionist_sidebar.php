<?php
/**
 * Receptionist Sidebar
 * Front desk operations
 */

// Determine base path for links
$currentScript = $_SERVER['PHP_SELF'];
$isAdminPage = (strpos($currentScript, '/admin/') !== false);
$linkBase = $isAdminPage ? '../' : '';
$currentPage = basename($currentScript);
$currentGet = $_GET;
?>
<div id="sidebar-collapse" class="col-sm-3 col-lg-2 sidebar">
    <div class="profile-sidebar">
        <div class="profile-userpic">
            <img src="<?php echo $linkBase; ?>img/logo3.png" class="img-responsive" alt="">
        </div>
        <div class="profile-usertitle">
            <div class="profile-usertitle-name" style="color: white;">Receptionist</div>
            <div class="profile-usertitle-status">
                <span class="indicator label-primary"></span>Receptionist
            </div>
        </div>
        <div class="clear"></div>
    </div>
    <div class="divider"></div>
    <ul class="nav menu">
        <li class="<?php echo (isset($_GET['dashboard']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?dashboard">
                <em class="fa fa-dashboard">&nbsp;</em> Dashboard
            </a>
        </li>
        
        <?php if (hasPermission('booking.create') || hasPermission('booking.read')): ?>
        <li class="<?php echo (isset($_GET['reservation']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?reservation">
                <em class="fa fa-calendar">&nbsp;</em> Reservations
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasPermission('room.read')): ?>
        <li class="<?php echo (isset($_GET['room_mang']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?room_mang">
                <em class="fa fa-bed">&nbsp;</em> View Rooms
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasPermission('service.read')): ?>
        <li class="<?php echo (isset($_GET['services']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?services">
                <em class="fa fa-bell">&nbsp;</em> Service Requests
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasPermission('facility.read') || hasPermission('facility.create')): ?>
        <li class="<?php echo (isset($_GET['facilities']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?facilities">
                <em class="fa fa-building-o">&nbsp;</em> Facilities
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasPermission('facility.read') || hasPermission('facility.create')): ?>
        <li class="<?php echo (isset($_GET['facility_bookings']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?facility_bookings">
                <em class="fa fa-calendar-check-o">&nbsp;</em> Facility Bookings
            </a>
        </li>
        <?php endif; ?>
        
        <li>
            <a href="<?php echo $linkBase; ?>staff_logout.php">
                <em class="fa fa-sign-out">&nbsp;</em> Logout
            </a>
        </li>
    </ul>
</div><!--/.sidebar-->

