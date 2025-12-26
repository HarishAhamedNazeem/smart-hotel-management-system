<?php
/**
 * Administrator Sidebar
 * Hotel management access
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
            <div class="profile-usertitle-name" style="color: white;">Administrator</div>
            <div class="profile-usertitle-status">
                <span class="indicator label-info"></span>Administrator
            </div>
        </div>
        <div class="clear"></div>
    </div>
    <div class="divider"></div>
    <ul class="nav menu">
        <li class="<?php echo (isset($_GET['dashboard']) ? 'active' : ''); ?>">
            <a href="index.php?dashboard">
                <em class="fa fa-dashboard">&nbsp;</em> Dashboard
            </a>
        </li>
        
        <?php if (hasPermission('booking.read') || hasPermission('booking.create')): ?>
        <li class="<?php echo (isset($_GET['reservation']) ? 'active' : ''); ?>">
            <a href="index.php?reservation">
                <em class="fa fa-calendar">&nbsp;</em> Reservations
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasPermission('room.read') || hasPermission('room.create')): ?>
        <li class="<?php echo (isset($_GET['room_mang']) ? 'active' : ''); ?>">
            <a href="index.php?room_mang">
                <em class="fa fa-bed">&nbsp;</em> Manage Rooms
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasPermission('staff.read') || hasPermission('staff.create')): ?>
        <li class="<?php echo (isset($_GET['staff_mang']) ? 'active' : ''); ?>">
            <a href="index.php?staff_mang">
                <em class="fa fa-users">&nbsp;</em> Staff Management
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
        
        <?php if (hasPermission('service.assign') || hasRole('super_admin') || hasRole('administrator')): ?>
        <li class="<?php echo (isset($_GET['service_types']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?service_types">
                <em class="fa fa-cog">&nbsp;</em> Service Types
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasPermission('package.read') || hasPermission('package.create')): ?>
        <li class="<?php echo (isset($_GET['meal_packages']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?meal_packages">
                <em class="fa fa-cutlery">&nbsp;</em> Meal Packages
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasPermission('facility.read') || hasPermission('facility.create') || hasRole('administrator')): ?>
        <li class="<?php echo (isset($_GET['facilities']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?facilities">
                <em class="fa fa-building-o">&nbsp;</em> Facilities
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasPermission('facility.read') || hasPermission('facility.create') || hasRole('administrator')): ?>
        <li class="<?php echo (isset($_GET['facility_bookings']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?facility_bookings">
                <em class="fa fa-calendar-check-o">&nbsp;</em> Facility Bookings
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole('administrator')): ?>
        <li class="<?php echo (isset($_GET['analytics']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?analytics">
                <em class="fa fa-bar-chart">&nbsp;</em> Analytics Dashboard
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasPermission('user.read')): ?>
        <li class="<?php echo (isset($_GET['users']) ? 'active' : ''); ?>">
            <a href="<?php echo $isAdminPage ? 'users.php' : 'admin/users.php'; ?>">
                <em class="fa fa-users">&nbsp;</em> Guest Management
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole('super_admin') || hasRole('administrator')): ?>
        <li class="<?php echo (isset($_GET['promotions']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?promotions">
                <em class="fa fa-tag">&nbsp;</em> Promotions
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

