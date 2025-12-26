<?php
// Load analytics functions
require_once __DIR__ . '/../includes/analytics_functions.php';

// Get user's branch if they're a branch admin
$user = getCurrentUser();
$userBranchId = null;

if (hasRole('administrator') && !hasRole('super_admin')) {
    $staffQuery = "SELECT branch_id FROM staff WHERE user_id = ? LIMIT 1";
    $staffStmt = mysqli_prepare($connection, $staffQuery);
    if ($staffStmt) {
        mysqli_stmt_bind_param($staffStmt, "i", $user['id']);
        mysqli_stmt_execute($staffStmt);
        $staffResult = mysqli_stmt_get_result($staffStmt);
        if ($staff = mysqli_fetch_assoc($staffResult)) {
            $userBranchId = $staff['branch_id'];
        }
        mysqli_stmt_close($staffStmt);
    }
}

// Date range for reports (default: last 30 days)
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get analytics data
$occupancy = getCurrentOccupancy($connection, $userBranchId);
$revenue = getRevenueSummary($connection, $startDate, $endDate, $userBranchId);
$upcomingActivity = getUpcomingActivity($connection, 7, $userBranchId);
$avgLOS = getAverageLengthOfStay($connection, $userBranchId);
$cancellationRate = getCancellationRate($connection, $userBranchId);
$topRoomTypes = getTopRoomTypes($connection, 5, $userBranchId);
$servicePerformance = getServicePerformance($connection, $userBranchId);
$bookingTrends = getBookingTrends($connection, 30, $userBranchId);
$guestDemographics = getGuestDemographics($connection, $userBranchId);
$revenueBySource = getRevenueBySource($connection, $userBranchId);

// Prepare data for charts
$trendLabels = [];
$trendBookings = [];
$trendRevenue = [];
foreach ($bookingTrends as $trend) {
    $trendLabels[] = date('M j', strtotime($trend['day']));
    $trendBookings[] = $trend['bookings'];
    $trendRevenue[] = round($trend['revenue'], 2);
}

$roomTypeLabels = [];
$roomTypeRevenue = [];
foreach ($topRoomTypes as $type) {
    $roomTypeLabels[] = ucfirst($type['room_type']);
    $roomTypeRevenue[] = round($type['revenue'], 2);
}
?>

<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
        <ol class="breadcrumb">
            <li><a href="#"><em class="fa fa-home"></em></a></li>
            <li class="active">Analytics Dashboard</li>
        </ol>
    </div>

    <!-- Page Header -->
    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">
                <em class="fa fa-bar-chart"></em> Analytics Dashboard
                <small>Real-time Insights & Performance Metrics</small>
            </h1>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="row" style="margin-bottom: 25px;">
        <div class="col-lg-12">
            <div class="panel panel-default analytics-filter-panel">
                <div class="panel-body" style="padding: 20px;">
                    <form method="GET" action="index.php" class="form-inline" id="analyticsFilterForm" style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <input type="hidden" name="analytics" value="1">
                        <div class="form-group" style="margin: 0; display: flex; align-items: center; gap: 10px;">
                            <label style="margin: 0; font-weight: 500; white-space: nowrap;">Date Range:</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $startDate; ?>" style="min-width: 160px;">
                            <span style="margin: 0 5px;">to</span>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $endDate; ?>" style="min-width: 160px;">
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">
                                <em class="fa fa-filter"></em> Filter
                            </button>
                            <button type="button" class="btn btn-default" id="resetFilterBtn">
                                <em class="fa fa-refresh"></em> Reset
                            </button>
                            <button type="button" class="btn btn-info" onclick="window.print()">
                                <em class="fa fa-print"></em> Print Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Metrics Row 1 -->
    <div class="row" style="margin-bottom: 25px;">
        <div class="col-md-3" style="margin-bottom: 20px;">
            <div class="panel panel-purple analytics-metric-card" style="min-height: 180px; display: flex; flex-direction: column;">
                <div class="panel-heading" style="padding: 20px; flex: 1; display: flex; align-items: center;">
                    <div class="row" style="width: 100%; margin: 0;">
                        <div class="col-xs-3" style="display: flex; align-items: center; justify-content: center;">
                            <em class="fa fa-bed fa-4x" style="opacity: 0.8;"></em>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge" style="font-size: 2.5em; font-weight: 600; margin-bottom: 5px;"><?php echo $occupancy['rate']; ?>%</div>
                            <div style="font-size: 14px; opacity: 0.9; font-weight: 500;">Occupancy Rate</div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer" style="padding: 15px 20px;">
                    <span class="pull-left" style="font-size: 13px;"><?php echo $occupancy['occupied']; ?> of <?php echo $occupancy['total']; ?> rooms</span>
                    <span class="pull-right"><em class="fa fa-arrow-circle-right"></em></span>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>

        <div class="col-md-3" style="margin-bottom: 20px;">
            <div class="panel panel-purple analytics-metric-card" style="min-height: 180px; display: flex; flex-direction: column;">
                <div class="panel-heading" style="padding: 20px; flex: 1; display: flex; align-items: center;">
                    <div class="row" style="width: 100%; margin: 0;">
                        <div class="col-xs-3" style="display: flex; align-items: center; justify-content: center;">
                            <em class="fa fa-dollar fa-4x" style="opacity: 0.8;"></em>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge" style="font-size: 1.8em; font-weight: 600; margin-bottom: 5px; line-height: 1.2;">LKR <?php echo number_format($revenue['total_revenue'] ?? 0, 0); ?></div>
                            <div style="font-size: 14px; opacity: 0.9; font-weight: 500;">Total Revenue</div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer" style="padding: 15px 20px;">
                    <span class="pull-left" style="font-size: 13px;"><?php echo $revenue['total_bookings'] ?? 0; ?> bookings</span>
                    <span class="pull-right">Avg: LKR <?php echo number_format($revenue['avg_booking_value'] ?? 0, 0); ?></span>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>

        <div class="col-md-3" style="margin-bottom: 20px;">
            <div class="panel panel-purple analytics-metric-card" style="min-height: 180px; display: flex; flex-direction: column;">
                <div class="panel-heading" style="padding: 20px; flex: 1; display: flex; align-items: center;">
                    <div class="row" style="width: 100%; margin: 0;">
                        <div class="col-xs-3" style="display: flex; align-items: center; justify-content: center;">
                            <em class="fa fa-calendar-check-o fa-4x" style="opacity: 0.8;"></em>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge" style="font-size: 2.5em; font-weight: 600; margin-bottom: 5px;"><?php echo $upcomingActivity['check_ins']; ?></div>
                            <div style="font-size: 14px; opacity: 0.9; font-weight: 500;">Upcoming Check-ins</div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer" style="padding: 15px 20px;">
                    <span class="pull-left" style="font-size: 13px;">Next 7 days</span>
                    <span class="pull-right"><?php echo $upcomingActivity['check_outs']; ?> check-outs</span>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>

        <div class="col-md-3" style="margin-bottom: 20px;">
            <div class="panel panel-purple analytics-metric-card" style="min-height: 180px; display: flex; flex-direction: column;">
                <div class="panel-heading" style="padding: 20px; flex: 1; display: flex; align-items: center;">
                    <div class="row" style="width: 100%; margin: 0;">
                        <div class="col-xs-3" style="display: flex; align-items: center; justify-content: center;">
                            <em class="fa fa-clock-o fa-4x" style="opacity: 0.8;"></em>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge" style="font-size: 2.5em; font-weight: 600; margin-bottom: 5px;"><?php echo $avgLOS; ?></div>
                            <div style="font-size: 14px; opacity: 0.9; font-weight: 500;">Avg Length of Stay</div>
                        </div>
                    </div>
                </div>
                <div class="panel-footer" style="padding: 15px 20px;">
                    <span class="pull-left" style="font-size: 13px;">Days per booking</span>
                    <span class="pull-right">Last 90 days</span>
                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row" style="margin-bottom: 25px;">
        <div class="col-lg-8" style="margin-bottom: 20px;">
            <div class="panel panel-default analytics-chart-panel">
                <div class="panel-heading" style="padding: 18px 20px;">
                    <em class="fa fa-line-chart"></em> Booking Trends (Last 30 Days)
                </div>
                <div class="panel-body" style="padding: 25px;">
                    <canvas id="bookingTrendsChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4" style="margin-bottom: 20px;">
            <div class="panel panel-default analytics-chart-panel">
                <div class="panel-heading" style="padding: 18px 20px;">
                    <em class="fa fa-pie-chart"></em> Room Type Performance
                </div>
                <div class="panel-body" style="padding: 25px;">
                    <canvas id="roomTypesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row" style="margin-bottom: 25px;">
        <div class="col-lg-6" style="margin-bottom: 20px;">
            <div class="panel panel-default analytics-table-panel">
                <div class="panel-heading" style="padding: 18px 20px;">
                    <em class="fa fa-trophy"></em> Top Performing Room Types (Last 30 Days)
                </div>
                <div class="panel-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table table-striped" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th style="padding: 12px 15px;">Room Type</th>
                                    <th style="padding: 12px 15px;">Bookings</th>
                                    <th style="padding: 12px 15px;">Revenue</th>
                                    <th style="padding: 12px 15px;">Avg Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topRoomTypes as $type): ?>
                                <tr>
                                    <td style="padding: 12px 15px;"><strong><?php echo ucfirst($type['room_type'] ?? 'N/A'); ?></strong></td>
                                    <td style="padding: 12px 15px;"><?php echo $type['bookings'] ?? 0; ?></td>
                                    <td style="padding: 12px 15px;">LKR <?php echo number_format($type['revenue'] ?? 0, 2); ?></td>
                                    <td style="padding: 12px 15px;">LKR <?php echo number_format($type['avg_rate'] ?? 0, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6" style="margin-bottom: 20px;">
            <div class="panel panel-default analytics-table-panel">
                <div class="panel-heading" style="padding: 18px 20px;">
                    <em class="fa fa-bell"></em> Service Performance (Last 30 Days)
                </div>
                <div class="panel-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table table-striped" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th style="padding: 12px 15px;">Status</th>
                                    <th style="padding: 12px 15px;">Count</th>
                                    <th style="padding: 12px 15px;">Avg Response Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($servicePerformance as $status => $data): ?>
                                <tr>
                                    <td style="padding: 12px 15px;">
                                        <?php
                                        $badge = '';
                                        switch($status) {
                                            case 'completed': $badge = 'success'; break;
                                            case 'in_progress': $badge = 'info'; break;
                                            case 'pending': $badge = 'warning'; break;
                                            default: $badge = 'default';
                                        }
                                        ?>
                                        <span class="label label-<?php echo $badge; ?>" style="background-color: <?php echo $badge == 'info' ? '#3D2C8D' : ($badge == 'success' ? '#2E7D32' : ($badge == 'warning' ? '#ED6C02' : '#6B6B6B')); ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 15px;"><?php echo $data['count']; ?></td>
                                    <td style="padding: 12px 15px;"><?php echo round($data['avg_response_time']); ?> min</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Metrics -->
    <div class="row" style="margin-bottom: 25px;">
        <div class="col-lg-6" style="margin-bottom: 20px;">
            <div class="panel panel-default analytics-metric-panel">
                <div class="panel-heading" style="padding: 18px 20px;">
                    <em class="fa fa-users"></em> Guest Demographics
                </div>
                <div class="panel-body" style="padding: 25px;">
                    <dl class="dl-horizontal" style="margin-bottom: 0;">
                        <dt style="width: 180px; margin-bottom: 12px;">Total Unique Guests:</dt>
                        <dd style="margin-left: 200px; margin-bottom: 12px;"><?php echo $guestDemographics['total_guests']; ?></dd>
                        
                        <dt style="width: 180px; margin-bottom: 12px;">Male Guests:</dt>
                        <dd style="margin-left: 200px; margin-bottom: 12px;"><?php echo $guestDemographics['male_guests']; ?> (<?php echo round(($guestDemographics['male_guests']/$guestDemographics['total_guests'])*100); ?>%)</dd>
                        
                        <dt style="width: 180px; margin-bottom: 12px;">Female Guests:</dt>
                        <dd style="margin-left: 200px; margin-bottom: 12px;"><?php echo $guestDemographics['female_guests']; ?> (<?php echo round(($guestDemographics['female_guests']/$guestDemographics['total_guests'])*100); ?>%)</dd>
                        
                        <dt style="width: 180px; margin-bottom: 12px;">Average Age:</dt>
                        <dd style="margin-left: 200px; margin-bottom: 12px;"><?php echo round($guestDemographics['avg_age']); ?> years</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6" style="margin-bottom: 20px;">
            <div class="panel panel-default analytics-metric-panel">
                <div class="panel-heading" style="padding: 18px 20px;">
                    <em class="fa fa-times-circle"></em> Cancellation Metrics
                </div>
                <div class="panel-body" style="padding: 25px;">
                    <div class="progress" style="height: 35px; margin-bottom: 15px; border-radius: 6px;">
                        <div class="progress-bar progress-bar-<?php echo $cancellationRate['rate'] > 20 ? 'danger' : ($cancellationRate['rate'] > 10 ? 'warning' : 'success'); ?>" 
                             style="width: <?php echo $cancellationRate['rate']; ?>%; line-height: 35px; font-size: 14px; font-weight: 500;">
                            <?php echo $cancellationRate['rate']; ?>% Cancellation Rate
                        </div>
                    </div>
                    <p class="text-muted" style="margin-bottom: 0; font-size: 13px;">
                        <?php echo $cancellationRate['cancelled']; ?> cancellations out of <?php echo $cancellationRate['total']; ?> total bookings (last 30 days)
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue by Source -->
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default analytics-table-panel">
                <div class="panel-heading" style="padding: 18px 20px;">
                    <em class="fa fa-money"></em> Revenue by Booking Source (Last 30 Days)
                </div>
                <div class="panel-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="table table-hover" style="margin: 0;">
                            <thead>
                                <tr>
                                    <th style="padding: 12px 15px;">Booking Source</th>
                                    <th style="padding: 12px 15px;">Number of Bookings</th>
                                    <th style="padding: 12px 15px;">Total Revenue</th>
                                    <th style="padding: 12px 15px;">% of Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalSourceRevenue = array_sum(array_column($revenueBySource, 'revenue'));
                                foreach ($revenueBySource as $source): 
                                    $sourceRevenue = $source['revenue'] ?? 0;
                                    $percentage = $totalSourceRevenue > 0 ? ($sourceRevenue / $totalSourceRevenue) * 100 : 0;
                                ?>
                                <tr>
                                    <td style="padding: 12px 15px;"><strong><?php echo ucfirst($source['booking_source'] ?? 'Direct'); ?></strong></td>
                                    <td style="padding: 12px 15px;"><?php echo $source['bookings'] ?? 0; ?></td>
                                    <td style="padding: 12px 15px;">LKR <?php echo number_format($sourceRevenue, 2); ?></td>
                                    <td style="padding: 12px 15px;">
                                        <div class="progress" style="margin-bottom: 0; height: 24px; border-radius: 4px;">
                                            <div class="progress-bar progress-bar-info" style="width: <?php echo $percentage; ?>%; background-color: #3D2C8D; line-height: 24px; font-size: 12px;">
                                                <?php echo round($percentage, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
// Booking Trends Chart
var ctx1 = document.getElementById('bookingTrendsChart').getContext('2d');
var bookingTrendsChart = new Chart(ctx1, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($trendLabels); ?>,
        datasets: [{
            label: 'Number of Bookings',
            data: <?php echo json_encode($trendBookings); ?>,
            borderColor: '#3D2C8D',
            backgroundColor: 'rgba(61, 44, 141, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            yAxisID: 'y',
        }, {
            label: 'Revenue (LKR)',
            data: <?php echo json_encode($trendRevenue); ?>,
            borderColor: '#5A4BCF',
            backgroundColor: 'rgba(90, 75, 207, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            yAxisID: 'y1',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false,
            }
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: 'Date'
                },
                ticks: {
                    maxRotation: 45,
                    minRotation: 45,
                    maxTicksLimit: 15
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Bookings'
                },
                beginAtZero: true
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Revenue (LKR)'
                },
                grid: {
                    drawOnChartArea: false,
                },
                beginAtZero: true
            },
        }
    }
});

// Room Types Chart
var ctx2 = document.getElementById('roomTypesChart').getContext('2d');
var roomTypesChart = new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($roomTypeLabels); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode($roomTypeRevenue); ?>,
            backgroundColor: [
                '#3D2C8D',
                '#5A4BCF',
                '#2A1F5F',
                '#BFC0C0',
                '#6B6B6B',
            ],
            borderColor: '#FFFFFF',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});

// Reset Filter Button
document.getElementById('resetFilterBtn').addEventListener('click', function() {
    // Set default date range (last 30 days)
    var today = new Date();
    var thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);
    
    // Format dates as YYYY-MM-DD
    var formatDate = function(date) {
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    };
    
    document.getElementById('start_date').value = formatDate(thirtyDaysAgo);
    document.getElementById('end_date').value = formatDate(today);
    
    // Submit the form to reload with default dates
    document.getElementById('analyticsFilterForm').submit();
});
</script>

<style>
/* Analytics Dashboard UI Improvements */
.analytics-dashboard {
    background: var(--color-bg);
}

/* Metric Cards */
.analytics-metric-card {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    overflow: hidden;
}

.analytics-metric-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.panel-purple {
    background: var(--color-primary);
    color: #fff;
    border: none;
}

.panel-purple .panel-heading {
    background: var(--color-primary);
    color: #fff;
    border: none;
}

.panel-purple .panel-footer {
    background: rgba(255, 255, 255, 0.1);
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.9);
}

.panel-teal {
    background: var(--color-primary-light);
    color: #fff;
    border: none;
}

.panel-teal .panel-heading {
    background: var(--color-primary-light);
    color: #fff;
    border: none;
}

.panel-teal .panel-footer {
    background: rgba(255, 255, 255, 0.1);
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.9);
}

.panel-blue {
    background: var(--color-primary);
    color: #fff;
    border: none;
}

.panel-blue .panel-heading {
    background: var(--color-primary);
    color: #fff;
    border: none;
}

.panel-blue .panel-footer {
    background: rgba(255, 255, 255, 0.1);
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.9);
}

/* Chart Panels */
.analytics-chart-panel {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: box-shadow 0.2s ease;
}

.analytics-chart-panel:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Table Panels */
.analytics-table-panel {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.analytics-metric-panel {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

/* Filter Panel */
.analytics-filter-panel {
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    background: var(--color-surface);
}

.panel-default .panel-heading {
    background: var(--color-surface);
    border-bottom: 2px solid var(--color-primary);
    color: var(--color-primary-dark);
    font-weight: 500;
}

.panel-default .panel-heading em {
    color: var(--color-primary);
    margin-right: 8px;
}

.page-header {
    border-bottom: 3px solid var(--color-primary);
    padding-bottom: 15px;
    margin-bottom: 25px;
}

.page-header h1 {
    color: var(--color-primary-dark);
    font-weight: 400;
}

.page-header h1 em {
    color: var(--color-primary);
    margin-right: 10px;
}

.table thead th {
    background-color: var(--color-primary);
    color: #fff;
    font-weight: 500;
    border: none;
}

.table tbody tr {
    transition: background-color 0.2s ease;
}

.table tbody tr:hover {
    background-color: rgba(61, 44, 141, 0.05);
}

.progress {
    background-color: var(--color-accent);
    border-radius: 4px;
}

.progress-bar-info {
    background-color: var(--color-primary) !important;
}

.progress-bar-success {
    background-color: #2E7D32 !important;
}

.progress-bar-warning {
    background-color: #ED6C02 !important;
}

.progress-bar-danger {
    background-color: #C62828 !important;
}

.dl-horizontal dt {
    color: var(--color-primary-dark);
    font-weight: 500;
}

.dl-horizontal dd {
    color: var(--color-text-primary);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .analytics-metric-card .panel-heading {
        padding: 15px !important;
    }
    
    .analytics-metric-card .huge {
        font-size: 2em !important;
    }
    
    .analytics-filter-panel .form-inline {
        flex-direction: column;
        align-items: stretch !important;
    }
    
    .analytics-filter-panel .form-group {
        width: 100%;
        margin-bottom: 10px;
    }
    
    .analytics-filter-panel .form-group input {
        width: 100% !important;
    }
}

@media print {
    .sidebar, .breadcrumb, button, .fa-print {
        display: none !important;
    }
    .main {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .panel-purple, .panel-teal, .panel-blue {
        background: #fff !important;
        color: #000 !important;
        border: 1px solid #000 !important;
    }
    .analytics-metric-card, .analytics-chart-panel, .analytics-table-panel {
        box-shadow: none !important;
        page-break-inside: avoid;
    }
}
</style>
