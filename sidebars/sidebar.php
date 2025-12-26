<?php
// Determine base path for links
$currentScript = $_SERVER['PHP_SELF'];
$isAdminPage = (strpos($currentScript, '/admin/') !== false);
$linkBase = $isAdminPage ? '../' : '';

// Ensure database connection and RBAC functions are available
if (!isset($connection)) {
    include_once $linkBase . 'db.php';
}
require_once $linkBase . 'includes/rbac.php';

// Check if user is super admin (show all menus)
$isSuperAdmin = hasRole('super_admin');
?>
<div id="sidebar-collapse" class="col-sm-3 col-lg-2 sidebar">
    <div class="profile-sidebar">
        <div class="profile-userpic">
            <img src="<?php echo $linkBase; ?>img/logo3.png" class="img-responsive" alt="">
        </div>
        <div class="profile-usertitle">
            <div class="profile-usertitle-name" style="color: white;"><?php 
                $primaryRole = getUserPrimaryRole();
                if ($primaryRole) {
                    echo ucfirst(str_replace('_', ' ', $primaryRole));
                } else {
                    echo 'User';
                }
            ?></div>
            <div class="profile-usertitle-status"><span class="indicator label-success"></span><?php 
                $primaryRole = getUserPrimaryRole();
                echo ucfirst(str_replace('_', ' ', $primaryRole ? $primaryRole : 'User'));
            ?></div>
        </div>
        <div class="clear"></div>
    </div>
    <div class="divider"></div>
    <ul class="nav menu">
    <?php
        
        // Dashboard - All authenticated users
        if (isset($_GET['dashboard'])){ ?>
            <li class="active">
                <a href="index.php?dashboard"><em class="fa fa-dashboard">&nbsp;</em>
                    Dashboard
                </a>
            </li>
        <?php } else{?>
            <li>
                <a href="index.php?dashboard"><em class="fa fa-dashboard">&nbsp;</em>
                    Dashboard
                </a>
            </li>
        <?php }
        
        // Reservation - Users with booking permissions OR super admin
        if ($isSuperAdmin || hasPermission('booking.create') || hasPermission('booking.read')) {
            if (isset($_GET['reservation'])){ ?>
                <li class="active">
                <a href="index.php?reservation"><em class="fa fa-calendar">&nbsp;</em>
                        Reservation
                    </a>
                </li>
            <?php } else{?>
                <li>
                <a href="index.php?reservation"><em class="fa fa-calendar">&nbsp;</em>
                        Reservation
                    </a>
                </li>
            <?php }
        }
        
        // Room Management - Show for super admin or users with permissions
        if ($isSuperAdmin) {
            if (isset($_GET['room_mang'])){ ?>
                <li class="active">
                    <a href="index.php?room_mang"><em class="fa fa-bed">&nbsp;</em>
                        Manage Rooms
                    </a>
                </li>
            <?php } else{?>
                <li>
                <a href="index.php?room_mang"><em class="fa fa-bed">&nbsp;</em>
                        Manage Rooms
                    </a>
                </li>
            <?php }
        } elseif (hasPermission('room.read') || hasPermission('room.create')) {
            if (isset($_GET['room_mang'])){ ?>
                <li class="active">
                    <a href="index.php?room_mang"><em class="fa fa-bed">&nbsp;</em>
                        Manage Rooms
                    </a>
                </li>
            <?php } else{?>
                <li>
                <a href="index.php?room_mang"><em class="fa fa-bed">&nbsp;</em>
                        Manage Rooms
                    </a>
                </li>
            <?php }
        }
        
        // Staff Management - Show for super admin or users with permissions
        if ($isSuperAdmin) {
            if (isset($_GET['staff_mang'])){ ?>
                <li class="active">
                    <a href="index.php?staff_mang"><em class="fa fa-users">&nbsp;</em>
                        Staff Section
                    </a>
                </li>
            <?php } else{?>
                <li>
                    <a href="index.php?staff_mang"><em class="fa fa-users">&nbsp;</em>
                        Staff Section
                    </a>
                </li>
            <?php }
        } elseif (hasPermission('staff.read') || hasPermission('staff.create')) {
            if (isset($_GET['staff_mang'])){ ?>
                <li class="active">
                    <a href="index.php?staff_mang"><em class="fa fa-users">&nbsp;</em>
                        Staff Section
                    </a>
                </li>
            <?php } else{?>
                <li>
                    <a href="index.php?staff_mang"><em class="fa fa-users">&nbsp;</em>
                        Staff Section
                    </a>
                </li>
            <?php }
        }
        
        // Service Requests - Show for super admin or users with permissions
        if ($isSuperAdmin) {
            if (isset($_GET['services'])){ ?>
                <li class="active">
                    <a href="index.php?services"><em class="fa fa-bell">&nbsp;</em>
                        Service Requests
                    </a>
                </li>
            <?php } else{?>
                <li>
                    <a href="index.php?services"><em class="fa fa-bell">&nbsp;</em>
                        Service Requests
                    </a>
                </li>
            <?php }
        } elseif (hasPermission('service.read') || hasPermission('service.create')) {
            if (isset($_GET['services'])){ ?>
                <li class="active">
                    <a href="index.php?services"><em class="fa fa-bell">&nbsp;</em>
                        Service Requests
                    </a>
                </li>
            <?php } else{?>
                <li>
                    <a href="index.php?services"><em class="fa fa-bell">&nbsp;</em>
                        Service Requests
                    </a>
                </li>
            <?php }
        }
        
        // Service Types - Show for super admin, administrators, or users with service.assign permission
        if ($isSuperAdmin) {
            if (isset($_GET['service_types'])){ ?>
                <li class="active">
                    <a href="index.php?service_types"><em class="fa fa-cog">&nbsp;</em>
                        Service Types
                    </a>
                </li>
            <?php } else{?>
                <li>
                    <a href="index.php?service_types"><em class="fa fa-cog">&nbsp;</em>
                        Service Types
                    </a>
                </li>
            <?php }
        } elseif (hasPermission('service.assign') || hasRole('administrator')) {
            if (isset($_GET['service_types'])){ ?>
                <li class="active">
                    <a href="index.php?service_types"><em class="fa fa-cog">&nbsp;</em>
                        Service Types
                    </a>
                </li>
            <?php } else{?>
                <li>
                    <a href="index.php?service_types"><em class="fa fa-cog">&nbsp;</em>
                        Service Types
                    </a>
                </li>
            <?php }
        }
        
        // Meal Packages - Show for super admin or users with permissions
        if ($isSuperAdmin) {
            if (isset($_GET['meal_packages'])){ ?>
                <li class="active">
                    <a href="index.php?meal_packages"><em class="fa fa-cutlery">&nbsp;</em>
                        Meal Packages
                    </a>
                </li>
            <?php } else{?>
                <li>
                    <a href="index.php?meal_packages"><em class="fa fa-cutlery">&nbsp;</em>
                        Meal Packages
                    </a>
                </li>
            <?php }
        } elseif (hasPermission('package.read') || hasPermission('package.create')) {
            if (isset($_GET['meal_packages'])){ ?>
                <li class="active">
                    <a href="index.php?meal_packages"><em class="fa fa-cutlery">&nbsp;</em>
                        Meal Packages
                    </a>
                </li>
            <?php } else{?>
                <li>
                    <a href="index.php?meal_packages"><em class="fa fa-cutlery">&nbsp;</em>
                        Meal Packages
                    </a>
                </li>
            <?php }
        }
        ?>

        <?php if ($isSuperAdmin): ?>
        <?php
        if (isset($_GET['users'])){ ?>
            <li class="active">
                <a href="admin/users.php"><em class="fa fa-users">&nbsp;</em>
                    Guest Management
                </a>
            </li>
        <?php } else{?>
        <li>
            <a href="admin/users.php"><em class="fa fa-users">&nbsp;</em>
                Guest Management
            </a>
        </li>
        <?php }?>
        <?php elseif (hasPermission('user.read')): ?>
        <?php
        if (isset($_GET['users'])){ ?>
            <li class="active">
                <a href="admin/users.php"><em class="fa fa-users">&nbsp;</em>
                    Guest Management
                </a>
            </li>
        <?php } else{?>
        <li>
            <a href="admin/users.php"><em class="fa fa-users">&nbsp;</em>
                Guest Management
            </a>
        </li>
        <?php }?>
        <?php endif; ?>

        <?php if ($isSuperAdmin): ?>
        <?php
        if (isset($_GET['roles'])){ ?>
            <li class="active">
                <a href="admin/roles.php"><em class="fa fa-key">&nbsp;</em>
                    Role Management
                </a>
            </li>
        <?php } else{?>
        <li>
            <a href="admin/roles.php"><em class="fa fa-key">&nbsp;</em>
                Role Management
            </a>
        </li>
        <?php }?>
        <?php elseif (hasPermission('user.manage_roles')): ?>
        <?php
        if (isset($_GET['roles'])){ ?>
            <li class="active">
                <a href="admin/roles.php"><em class="fa fa-key">&nbsp;</em>
                    Role Management
                </a>
            </li>
        <?php } else{?>
        <li>
            <a href="admin/roles.php"><em class="fa fa-key">&nbsp;</em>
                Role Management
            </a>
        </li>
        <?php }?>
        <?php endif; ?>

        <?php if ($isSuperAdmin): ?>
        <?php
        if (isset($_GET['audit'])){ ?>
            <li class="active">
                <a href="admin/audit_logs.php"><em class="fa fa-history">&nbsp;</em>
                    Audit Logs
                </a>
            </li>
        <?php } else{?>
        <li>
            <a href="admin/audit_logs.php"><em class="fa fa-history">&nbsp;</em>
                Audit Logs
            </a>
        </li>
        <?php }?>
        <?php elseif (hasPermission('system.audit')): ?>
        <?php
        if (isset($_GET['audit'])){ ?>
            <li class="active">
                <a href="admin/audit_logs.php"><em class="fa fa-history">&nbsp;</em>
                    Audit Logs
                </a>
            </li>
        <?php } else{?>
        <li>
            <a href="admin/audit_logs.php"><em class="fa fa-history">&nbsp;</em>
                Audit Logs
            </a>
        </li>
        <?php }?>
        <?php endif; ?>

        <li>
            <a href="<?php echo $linkBase; ?>staff_logout.php">
                <em class="fa fa-sign-out">&nbsp;</em> Logout
            </a>
        </li>
    </ul>
</div><!--/.sidebar-->

