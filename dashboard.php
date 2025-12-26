
<body>
	<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
		<div class="row">
			<ol class="breadcrumb">
				<li><a href="#">
					<em class="fa fa-home"></em>
				</a></li>
				<li class="active">Dashboard</li>
			</ol>
		</div><!--/.row-->
		
		<?php
		// Get additional statistics for administrators
		$serviceRequestsCount = 0;
		$pendingServiceRequests = 0;
		$totalUsers = 0;
		$activeUsers = 0;
		
		if (hasPermission('service.read')) {
            $serviceQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                FROM service_requests";
            $serviceResult = mysqli_query($connection, $serviceQuery);
            $serviceStats = mysqli_fetch_assoc($serviceResult);
            $serviceRequestsCount = $serviceStats['total'] ?? 0;
            $pendingServiceRequests = $serviceStats['pending'] ?? 0;
        }
        
        if (hasPermission('user.read')) {
            $usersQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
                FROM user";
            $usersResult = mysqli_query($connection, $usersQuery);
            $usersStats = mysqli_fetch_assoc($usersResult);
            $totalUsers = $usersStats['total'] ?? 0;
            $activeUsers = $usersStats['active'] ?? 0;
        }
		?>
		
		<div class="panel panel-container">
			<div class="row">
				<div class="col-xs-6 col-md-3 col-lg-3 no-padding">
					<div class="panel panel-teal panel-widget border-right">
						<div class="row no-padding"><em class="fa fa-xl fa-bed color-blue"></em>
							<div class="large"><?php include 'counters/room-count.php'?></div>
							<div class="text-muted">Total Rooms</div>
						</div>
					</div>
				</div>
				<div class="col-xs-6 col-md-3 col-lg-3 no-padding">
					<div class="panel panel-blue panel-widget border-right">
						<div class="row no-padding"><em class="fa fa-xl fa-bookmark color-orange"></em>
							<div class="large"><?php include 'counters/reserve-count.php'?></div>
							<div class="text-muted">Reservations</div>
						</div>
					</div>
				</div>
				<div class="col-xs-6 col-md-3 col-lg-3 no-padding">
					<div class="panel panel-orange panel-widget border-right">
						<div class="row no-padding"><em class="fa fa-xl fa-users color-teal"></em>
							<div class="large"><?php include 'counters/staff-count.php'?></div>
							<div class="text-muted">Staffs</div>
						</div>
					</div>
				</div>
			</div><!--/.row-->

			<hr>

			<div class="row">
				<div class="col-xs-6 col-md-3 col-lg-3 no-padding">
					<div class="panel panel-teal panel-widget border-right">
						<div class="row no-padding"><em class="fa fa-xl fa-reorder color-red"></em>
							<div class="large"><?php include 'counters/bookedroom-count.php'?></div>
							<div class="text-muted">Booked Rooms</div>
						</div>
					</div>
				</div>
				<div class="col-xs-6 col-md-3 col-lg-3 no-padding">
					<div class="panel panel-blue panel-widget border-right">
						<div class="row no-padding"><em class="fa fa-xl fa-check-circle color-green"></em>
							<div class="large"><?php include 'counters/avrooms-count.php'?></div>
							<div class="text-muted">Available Rooms</div>
						</div>
					</div>
				</div>
				<div class="col-xs-6 col-md-3 col-lg-3 no-padding">
					<div class="panel panel-orange panel-widget border-right">
						<div class="row no-padding"><em class="fa fa-xl fa-check-square-o color-magg"></em>
							<div class="large"><?php include 'counters/checkedin-count.php'?></div>
							<div class="text-muted">Checked In</div>
						</div>
					</div>
				</div>
				<div class="col-xs-6 col-md-3 col-lg-3 no-padding">
					<div class="panel panel-red panel-widget ">
						<div class="row no-padding"><em class="fa fa-xl fa-spinner color-blue"></em>
							<div class="large"><?php include 'counters/pendingpay-count.php'?></div>
							<div class="text-muted">Total Pending Payments</div>
						</div>
					</div>
				</div>
			</div><!--/.row-->

			<hr>

			<div class="row">
				<div class="col-xs-6 col-md-2 col-lg-2 no-padding">
					
				</div>

				<div class="col-xs-6 col-md-4 col-lg-4 no-padding">
					<div class="panel panel-red panel-widget border-right">
						<div class="row no-padding"><em class="fa fa-xl fa-money color-red"></em>
							<div class="large">LKR <?php include 'counters/income-count.php'?></div>
							<div class="text-muted">Total Earnings</div>
						</div>
					</div>
				</div>
				<div class="col-xs-6 col-md-4 col-lg-4 no-padding">
					<div class="panel panel-orange panel-widget ">
						<div class="row no-padding"><em class="fa fa-xl fa-credit-card color-purp"></em>
							<div class="large">LKR <?php include 'counters/pendingpayment.php'?></div>
							<div class="text-muted">Pending Payment</div>
						</div>
					</div>
				</div>
				<div class="col-xs-6 col-md-2 col-lg-2 no-padding">
					
				</div>
			</div><!--/.row-->

			<?php if (hasPermission('service.read') || hasPermission('user.read')): ?>
			<hr>
			<div class="row">
				<?php if (hasPermission('service.read')): ?>
				<div class="col-xs-6 col-md-3 col-lg-3 no-padding">
					<div class="panel panel-purple panel-widget border-right">
						<div class="row no-padding"><em class="fa fa-xl fa-bell color-purple"></em>
							<div class="large"><?php echo $serviceRequestsCount; ?></div>
							<div class="text-muted">Service Requests</div>
						</div>
					</div>
				</div>
				<div class="col-xs-6 col-md-3 col-lg-3 no-padding">
					<div class="panel panel-yellow panel-widget border-right">
						<div class="row no-padding"><em class="fa fa-xl fa-clock-o color-yellow"></em>
							<div class="large"><?php echo $pendingServiceRequests; ?></div>
							<div class="text-muted">Pending Requests</div>
						</div>
					</div>
				</div>
				<?php endif; ?>
				
				<?php if (hasPermission('user.read')): ?>
				<div class="col-xs-6 col-md-3 col-lg-3 no-padding">
					<div class="panel panel-green panel-widget border-right">
						<div class="row no-padding"><em class="fa fa-xl fa-user color-green"></em>
							<div class="large"><?php echo $totalUsers; ?></div>
							<div class="text-muted">Total Users</div>
						</div>
					</div>
				</div>
				<div class="col-xs-6 col-md-3 col-lg-3 no-padding">
					<div class="panel panel-blue panel-widget">
						<div class="row no-padding"><em class="fa fa-xl fa-check-circle color-blue"></em>
							<div class="large"><?php echo $activeUsers; ?></div>
							<div class="text-muted">Active Users</div>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</div><!--/.row-->
			<?php endif; ?>
		</div>
		
	</div>	<!--/.main-->
	

		
</body>
</html>