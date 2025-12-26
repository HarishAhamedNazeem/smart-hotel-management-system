<?php
/**
 * Super Admin Dashboard
 * Comprehensive dashboard with all system statistics
 */

// Ensure database connection is available
if (!isset($connection)) {
    include_once 'db.php';
}
?>
<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
        <ol class="breadcrumb">
            <li><a href="#"><em class="fa fa-home"></em></a></li>
            <li class="active">Super Admin Dashboard</li>
        </ol>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">
                <em class="fa fa-dashboard"></em> Super Admin Dashboard
            </h1>
        </div>
    </div>

    <?php
    // Get comprehensive statistics
    // Check if deleteStatus column exists in room table
    $checkDeleteStatus = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'deleteStatus'");
    $hasDeleteStatus = mysqli_num_rows($checkDeleteStatus) > 0;
    
    // Check if check_in_status and check_out_status columns exist
    $checkCheckinStatus = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'check_in_status'");
    $hasCheckinStatus = mysqli_num_rows($checkCheckinStatus) > 0;
    
    // Room Statistics
    $roomStatsQuery = "SELECT 
        COUNT(*) as total_rooms,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as booked_rooms,
        SUM(CASE WHEN status IS NULL OR status = 0 THEN 1 ELSE 0 END) as available_rooms
        FROM room";
    
    if ($hasDeleteStatus) {
        $roomStatsQuery .= " WHERE deleteStatus = 0";
    }
    
    $roomStats = mysqli_fetch_assoc(mysqli_query($connection, $roomStatsQuery));
    
    // Booking Statistics
    $bookingStatsQuery = "SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN payment_status = 1 THEN 1 ELSE 0 END) as paid_bookings,
        SUM(CASE WHEN payment_status = 0 THEN 1 ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN payment_status = 1 THEN total_price ELSE 0 END) as total_revenue,
        SUM(CASE WHEN payment_status = 0 THEN total_price ELSE 0 END) as pending_revenue
        FROM booking";
    $bookingStats = mysqli_fetch_assoc(mysqli_query($connection, $bookingStatsQuery));
    
    // Staff Statistics
    $staffStatsQuery = "SELECT COUNT(*) as total_staff FROM staff";
    $staffStats = mysqli_fetch_assoc(mysqli_query($connection, $staffStatsQuery));
    
    // User Statistics
    $userStatsQuery = "SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_users
        FROM user";
    $userStats = mysqli_fetch_assoc(mysqli_query($connection, $userStatsQuery));
    
    // Service Request Statistics
    $serviceStatsQuery = "SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests
        FROM service_requests";
    $serviceStats = mysqli_fetch_assoc(mysqli_query($connection, $serviceStatsQuery));
    
    // Check-in/Check-out Statistics (from room table)
    if ($hasCheckinStatus) {
        $checkinStatsQuery = "SELECT 
            SUM(CASE WHEN check_in_status = 1 THEN 1 ELSE 0 END) as checked_in,
            SUM(CASE WHEN check_out_status = 1 THEN 1 ELSE 0 END) as checked_out
            FROM room";
        
        if ($hasDeleteStatus) {
            $checkinStatsQuery .= " WHERE deleteStatus = 0";
        }
        
        $checkinStats = mysqli_fetch_assoc(mysqli_query($connection, $checkinStatsQuery));
    } else {
        // If check_in_status columns don't exist, return zeros
        $checkinStats = [
            'checked_in' => 0,
            'checked_out' => 0
        ];
    }
    ?>

    <!-- Room Statistics -->
    <div class="panel panel-container">
        <div class="row">
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-teal panel-widget border-right">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-bed color-blue"></em>
                        <div class="large"><?php echo $roomStats['total_rooms'] ?? 0; ?></div>
                        <div class="text-muted">Total Rooms</div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-blue panel-widget border-right">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-bookmark color-orange"></em>
                        <div class="large"><?php echo $bookingStats['total_bookings'] ?? 0; ?></div>
                        <div class="text-muted">Total Reservations</div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-orange panel-widget border-right">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-users color-teal"></em>
                        <div class="large"><?php echo $staffStats['total_staff'] ?? 0; ?></div>
                        <div class="text-muted">Total Staff</div>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <div class="row">
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-teal panel-widget border-right">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-reorder color-red"></em>
                        <div class="large"><?php echo $roomStats['booked_rooms'] ?? 0; ?></div>
                        <div class="text-muted">Booked Rooms</div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-blue panel-widget border-right">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-check-circle color-green"></em>
                        <div class="large"><?php echo $roomStats['available_rooms'] ?? 0; ?></div>
                        <div class="text-muted">Available Rooms</div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-orange panel-widget border-right">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-check-square-o color-magg"></em>
                        <div class="large"><?php echo $checkinStats['checked_in'] ?? 0; ?></div>
                        <div class="text-muted">Checked In</div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-red panel-widget">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-spinner color-blue"></em>
                        <div class="large"><?php echo $bookingStats['pending_bookings'] ?? 0; ?></div>
                        <div class="text-muted">Pending Payments</div>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <div class="row">
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-red panel-widget border-right">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-money color-red"></em>
                        <div class="large">LKR <?php echo number_format($bookingStats['total_revenue'] ?? 0); ?></div>
                        <div class="text-muted">Total Earnings</div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-orange panel-widget border-right">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-credit-card color-purp"></em>
                        <div class="large">LKR <?php echo number_format($bookingStats['pending_revenue'] ?? 0); ?></div>
                        <div class="text-muted">Pending Payment</div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-purple panel-widget border-right">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-bell color-purple"></em>
                        <div class="large"><?php echo $serviceStats['total_requests'] ?? 0; ?></div>
                        <div class="text-muted">Service Requests</div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-yellow panel-widget">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-clock-o color-yellow"></em>
                        <div class="large"><?php echo $serviceStats['pending_requests'] ?? 0; ?></div>
                        <div class="text-muted">Pending Requests</div>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <div class="row">
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-green panel-widget border-right">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-user color-green"></em>
                        <div class="large"><?php echo $userStats['total_users'] ?? 0; ?></div>
                        <div class="text-muted">Total Users</div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-blue panel-widget border-right">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-check-circle color-blue"></em>
                        <div class="large"><?php echo $userStats['active_users'] ?? 0; ?></div>
                        <div class="text-muted">Active Users</div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-md-3 col-lg-3 no-padding">
                <div class="panel panel-success panel-widget">
                    <div class="row no-padding">
                        <em class="fa fa-xl fa-check color-green"></em>
                        <div class="large"><?php echo $serviceStats['completed_requests'] ?? 0; ?></div>
                        <div class="text-muted">Completed Services</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

