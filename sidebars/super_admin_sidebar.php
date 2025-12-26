<?php
/**
 * Super Admin Sidebar
 * Full access to all system features
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
            <div class="profile-usertitle-name" style="color: white;">Super Admin</div>
            <div class="profile-usertitle-status">
                <span class="indicator label-success"></span>Super Admin
            </div>
        </div>
        <div class="clear"></div>
    </div>
    <div class="divider"></div>
    <ul class="nav menu">
        <!-- Dashboard -->
        <li class="<?php 
            $isDashboard = (isset($currentGet['dashboard']) || 
                          ($currentPage == 'index.php' && empty(array_filter($currentGet, function($k) { 
                              return in_array($k, ['room_mang', 'reservation', 'staff_mang', 'users', 'roles', 'services', 'service_types', 'facilities', 'facility_bookings', 'analytics']); 
                          }, ARRAY_FILTER_USE_KEY))));
            echo $isDashboard ? 'active' : '';
        ?>">
            <a href="<?php echo $linkBase; ?>index.php?dashboard">
                <em class="fa fa-dashboard">&nbsp;</em> Dashboard
            </a>
        </li>
        
        <!-- Hotel Operations -->
        <li class="<?php echo (isset($currentGet['reservation']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?reservation">
                <em class="fa fa-calendar">&nbsp;</em> Reservations
            </a>
        </li>
        
        <li class="<?php echo (isset($currentGet['room_mang']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?room_mang">
                <em class="fa fa-bed">&nbsp;</em> Manage Rooms
            </a>
        </li>
        
        <li class="<?php echo (isset($currentGet['staff_mang']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?staff_mang">
                <em class="fa fa-users">&nbsp;</em> Staff Management
            </a>
        </li>
        
        <li class="<?php echo (isset($currentGet['services']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?services">
                <em class="fa fa-bell">&nbsp;</em> Service Requests
            </a>
        </li>
        
        <li class="<?php echo (isset($currentGet['service_types']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?service_types">
                <em class="fa fa-cog">&nbsp;</em> Service Types
            </a>
        </li>
        
        <li class="<?php echo (isset($currentGet['meal_packages']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?meal_packages">
                <em class="fa fa-cutlery">&nbsp;</em> Meal Packages
            </a>
        </li>
        
        <!-- Facility Management -->
        <li class="<?php echo (isset($currentGet['facilities']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?facilities">
                <em class="fa fa-building-o">&nbsp;</em> Facilities
            </a>
        </li>
        
        <li class="<?php echo (isset($currentGet['facility_bookings']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?facility_bookings">
                <em class="fa fa-calendar-check-o">&nbsp;</em> Facility Bookings
            </a>
        </li>
        
        <!-- Analytics & Reports -->
        <li class="<?php echo (isset($currentGet['analytics']) ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?analytics">
                <em class="fa fa-bar-chart">&nbsp;</em> Analytics Dashboard
            </a>
        </li>
        
        <!-- System Administration -->
        <li class="<?php echo (isset($currentGet['users']) || $currentPage == 'users.php' ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>admin/users.php">
                <em class="fa fa-users">&nbsp;</em> Guest Management
            </a>
        </li>
        
        <li class="<?php echo (isset($currentGet['roles']) || $currentPage == 'roles.php' ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>admin/roles.php">
                <em class="fa fa-key">&nbsp;</em> Role Management
            </a>
        </li>
        
        <li class="<?php echo (isset($currentGet['branches']) || $currentPage == 'branches.php' ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>admin/branches.php">
                <em class="fa fa-building">&nbsp;</em> Branch Management
            </a>
        </li>
        
        <li class="<?php echo (isset($currentGet['promotions']) || $currentPage == 'promotions.php' ? 'active' : ''); ?>">
            <a href="<?php echo $linkBase; ?>index.php?promotions">
                <em class="fa fa-tag">&nbsp;</em> Promotions Management
            </a>
        </li>
        
        <!-- Logout -->
        <li>
            <a href="<?php echo $linkBase; ?>staff_logout.php">
                <em class="fa fa-sign-out">&nbsp;</em> Logout
            </a>
        </li>
    </ul>
</div><!--/.sidebar-->
