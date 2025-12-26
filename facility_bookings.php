<?php
// Load facility functions
require_once __DIR__ . '/includes/facility_functions.php';

// Get user's branch if they're a branch admin
$user = getCurrentUser();
$userBranchId = null;

// Check if user is branch admin and get their branch
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

// Get facilities for dropdown
$facilitiesQuery = "SELECT f.facility_id, f.facility_name, f.facility_type, f.capacity, f.hourly_rate, f.full_day_rate, b.branch_name
                    FROM facilities f
                    LEFT JOIN branches b ON f.branch_id = b.branch_id
                    WHERE f.status = 'active'";

if ($userBranchId && !hasRole('super_admin')) {
    $facilitiesQuery .= " AND f.branch_id = " . intval($userBranchId);
}

$facilitiesQuery .= " ORDER BY f.facility_name";
$facilitiesResult = mysqli_query($connection, $facilitiesQuery);
$facilities = [];
while ($fac = mysqli_fetch_assoc($facilitiesResult)) {
    $facilities[] = $fac;
}

// Get bookings with filters
$selectedFacilityId = isset($_GET['facility_id']) ? intval($_GET['facility_id']) : null;
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selectedStatus = isset($_GET['status']) ? $_GET['status'] : 'all';

$bookingsQuery = "SELECT fb.*, f.facility_name, f.facility_type, u.username as booked_by_name
                  FROM facility_bookings fb
                  JOIN facilities f ON fb.facility_id = f.facility_id
                  LEFT JOIN user u ON fb.booked_by = u.id
                  WHERE 1=1";

// Add branch filter for branch admins
if ($userBranchId && !hasRole('super_admin')) {
    $bookingsQuery .= " AND f.branch_id = " . intval($userBranchId);
}

if ($selectedFacilityId) {
    $bookingsQuery .= " AND fb.facility_id = " . intval($selectedFacilityId);
}

if ($selectedDate) {
    $bookingsQuery .= " AND fb.booking_date = '" . mysqli_real_escape_string($connection, $selectedDate) . "'";
}

if ($selectedStatus != 'all') {
    $bookingsQuery .= " AND fb.status = '" . mysqli_real_escape_string($connection, $selectedStatus) . "'";
}

$bookingsQuery .= " ORDER BY fb.booking_date DESC, fb.start_time DESC";
$bookingsResult = mysqli_query($connection, $bookingsQuery);

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
        <ol class="breadcrumb">
            <li><a href="#">
                    <em class="fa fa-home"></em>
                </a></li>
            <li class="active">Facility Bookings</li>
        </ol>
    </div><!--/.row-->

    <br>

    <div class="row">
        <div class="col-lg-12">
            <div id="bookings-response"></div>
        </div>
    </div>

    <!-- Filters and Quick Stats -->
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <em class="fa fa-filter"></em> Filters & Quick Booking
                    <div class="pull-right">
                        <?php if (hasPermission('facility.create') || hasRole('super_admin') || hasRole('administrator')): ?>
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addBookingModal" style="border-radius: 4px; font-weight: 500;">
                            <em class="fa fa-plus"></em> New Booking
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="GET" action="index.php" class="form-inline">
                        <input type="hidden" name="facility_bookings" value="1">
                        
                        <div class="form-group" style="margin-right: 10px;">
                            <label>Facility:</label>
                            <select name="facility_id" class="form-control">
                                <option value="">All Facilities</option>
                                <?php foreach ($facilities as $fac): ?>
                                <option value="<?php echo $fac['facility_id']; ?>" <?php echo $selectedFacilityId == $fac['facility_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($fac['facility_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="margin-right: 10px;">
                            <label>Date:</label>
                            <input type="date" name="date" class="form-control" value="<?php echo $selectedDate ? htmlspecialchars($selectedDate) : ''; ?>" placeholder="All Dates">
                            <small class="help-block" style="font-size: 11px; color: #666;">Leave empty to show all dates</small>
                        </div>
                        
                        <div class="form-group" style="margin-right: 10px;">
                            <label>Status:</label>
                            <select name="status" class="form-control">
                                <option value="all" <?php echo $selectedStatus == 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $selectedStatus == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $selectedStatus == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="in_progress" <?php echo $selectedStatus == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $selectedStatus == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $selectedStatus == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary"><em class="fa fa-search"></em> Filter</button>
                        <a href="index.php?facility_bookings" class="btn btn-default">Clear</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <em class="fa fa-calendar-check-o"></em> Facility Bookings
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="bookingsTable">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Facility</th>
                                    <th>Event Name</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Customer</th>
                                    <th>Cost</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($bookingsResult && mysqli_num_rows($bookingsResult) > 0) {
                                    while ($booking = mysqli_fetch_assoc($bookingsResult)) {
                                        $statusLabels = [
                                            'pending' => '<span class="label label-warning">Pending</span>',
                                            'confirmed' => '<span class="label label-success">Confirmed</span>',
                                            'in_progress' => '<span class="label label-info">In Progress</span>',
                                            'completed' => '<span class="label label-default">Completed</span>',
                                            'cancelled' => '<span class="label label-danger">Cancelled</span>'
                                        ];
                                        
                                        $statusBadge = $statusLabels[$booking['status']] ?? $booking['status'];
                                        
                                        $typeLabels = [
                                            'event_hall' => '<span class="badge badge-primary">Event Hall</span>',
                                            'conference_room' => '<span class="badge badge-info">Conference</span>',
                                            'banquet_hall' => '<span class="badge badge-success">Banquet</span>',
                                            'meeting_room' => '<span class="badge badge-warning">Meeting</span>'
                                        ];
                                        
                                        $typeBadge = $typeLabels[$booking['facility_type']] ?? $booking['facility_type'];
                                        
                                        echo '<tr>';
                                        echo '<td><strong>' . htmlspecialchars($booking['booking_reference']) . '</strong></td>';
                                        echo '<td>' . htmlspecialchars($booking['facility_name']) . '<br>' . $typeBadge . '</td>';
                                        echo '<td><strong>' . htmlspecialchars($booking['event_name']) . '</strong></td>';
                                        echo '<td>' . date('M d, Y', strtotime($booking['booking_date'])) . '</td>';
                                        echo '<td>' . date('h:i A', strtotime($booking['start_time'])) . ' - ' . date('h:i A', strtotime($booking['end_time'])) . '</td>';
                                        echo '<td>' . htmlspecialchars($booking['customer_name']) . '<br><small>' . htmlspecialchars($booking['customer_email']) . '</small></td>';
                                        echo '<td><strong>LKR ' . number_format($booking['total_cost'], 2) . '</strong></td>';
                                        echo '<td>' . $statusBadge . '</td>';
                                        echo '<td style="white-space: nowrap;">';
                                        
                                        if (hasPermission('facility.update') || hasRole('super_admin') || hasRole('administrator')) {
                                            echo '<button class="btn btn-info btn-xs view-booking" 
                                                    data-booking-id="' . $booking['booking_id'] . '"
                                                    style="margin-right: 3px;">
                                                    <em class="fa fa-eye"></em>
                                                  </button>';
                                            
                                            if ($booking['status'] == 'pending') {
                                                echo '<button class="btn btn-success btn-xs confirm-booking" 
                                                        data-booking-id="' . $booking['booking_id'] . '"
                                                        style="margin-right: 3px;">
                                                        <em class="fa fa-check"></em>
                                                      </button>';
                                            }
                                            
                                            if (in_array($booking['status'], ['pending', 'confirmed'])) {
                                                echo '<button class="btn btn-danger btn-xs cancel-booking" 
                                                        data-booking-id="' . $booking['booking_id'] . '"
                                                        data-booking-reference="' . htmlspecialchars($booking['booking_reference']) . '">
                                                        <em class="fa fa-times"></em>
                                                      </button>';
                                            }
                                        }
                                        
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    if (!$bookingsResult) {
                                        echo '<tr><td colspan="9" class="text-center text-danger">Error loading bookings: ' . htmlspecialchars(mysqli_error($connection)) . '</td></tr>';
                                    } else {
                                        echo '<tr><td colspan="9" class="text-center">No bookings found matching your filters. <a href="index.php?facility_bookings">Clear filters</a> to see all bookings.</td></tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Booking Modal -->
<div class="modal fade" id="addBookingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><em class="fa fa-calendar-plus-o"></em> Create Facility Booking</h4>
            </div>
            <form id="addBookingForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <!-- Availability Check Result -->
                    <div id="availability-result" style="display:none;"></div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Facility <span class="text-danger">*</span></label>
                                <select class="form-control" name="facility_id" id="booking_facility_id" required>
                                    <option value="">Select Facility</option>
                                    <?php foreach ($facilities as $fac): ?>
                                    <option value="<?php echo $fac['facility_id']; ?>" 
                                            data-hourly-rate="<?php echo $fac['hourly_rate']; ?>"
                                            data-full-day-rate="<?php echo $fac['full_day_rate']; ?>"
                                            data-capacity="<?php echo $fac['capacity']; ?>">
                                        <?php echo htmlspecialchars($fac['facility_name']); ?> 
                                        (<?php echo ucfirst(str_replace('_', ' ', $fac['facility_type'])); ?>) - 
                                        Capacity: <?php echo $fac['capacity']; ?> | 
                                        LKR <?php echo number_format($fac['hourly_rate'], 2); ?>/hr
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Event Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="event_name" required placeholder="e.g., Annual Conference, Wedding Reception">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Booking Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="booking_date" id="booking_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Start Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="start_time" id="start_time" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>End Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="end_time" id="end_time" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-info btn-block" id="checkAvailabilityBtn">
                                <em class="fa fa-search"></em> Check Availability
                            </button>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h5><strong>Customer Information</strong></h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="customer_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="customer_email" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Customer Phone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" name="customer_phone" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Number of Guests</label>
                                <input type="number" class="form-control" name="number_of_guests" min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Special Requirements</label>
                        <textarea class="form-control" name="special_requirements" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" name="status">
                                    <option value="pending">Pending</option>
                                    <option value="confirmed">Confirmed</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Estimated Cost</label>
                                <input type="text" class="form-control" id="estimated_cost" readonly>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBookingBtn">
                        <em class="fa fa-save"></em> Create Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Booking Details Modal -->
<div class="modal fade" id="viewBookingModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><em class="fa fa-info-circle"></em> Booking Details</h4>
            </div>
            <div class="modal-body" id="bookingDetailsContent">
                <!-- Content loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for jQuery to be loaded (from footer.php)
(function() {
    function initFacilityBookings() {
        if (typeof jQuery === 'undefined') {
            setTimeout(function() { initFacilityBookings(); }, 50);
            return;
        }
        
        var $ = jQuery;
        
        $(document).ready(function() {
    // Check Availability
    $('#checkAvailabilityBtn').click(function() {
        var facilityId = $('#booking_facility_id').val();
        var bookingDate = $('#booking_date').val();
        var startTime = $('#start_time').val();
        var endTime = $('#end_time').val();
        
        if (!facilityId || !bookingDate || !startTime || !endTime) {
            alert('Please fill in Facility, Date, Start Time, and End Time first.');
            return;
        }
        
        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            data: {
                action: 'check_facility_availability',
                facility_id: facilityId,
                booking_date: bookingDate,
                start_time: startTime,
                end_time: endTime
            },
            dataType: 'json',
            success: function(response) {
                if (response.available) {
                    $('#availability-result').html('<div class="alert alert-success"><em class="fa fa-check-circle"></em> Facility is available for the selected time slot!</div>').show();
                    $('#submitBookingBtn').prop('disabled', false);
                    
                    // Calculate and show cost
                    if (response.cost_estimate) {
                        $('#estimated_cost').val('LKR ' + response.cost_estimate.total_cost + ' (' + response.cost_estimate.hours + ' hours)');
                    }
                } else {
                    var conflictMsg = '<div class="alert alert-danger"><em class="fa fa-exclamation-triangle"></em> <strong>Facility is not available!</strong><br>';
                    if (response.conflicts && response.conflicts.length > 0) {
                        conflictMsg += '<ul>';
                        response.conflicts.forEach(function(conflict) {
                            if (conflict.type === 'maintenance') {
                                conflictMsg += '<li>Maintenance scheduled: ' + conflict.start_time + ' - ' + conflict.end_time + '</li>';
                            } else {
                                conflictMsg += '<li>Existing booking: ' + conflict.booking_reference + ' (' + conflict.start_time + ' - ' + conflict.end_time + ')</li>';
                            }
                        });
                        conflictMsg += '</ul>';
                    }
                    conflictMsg += '</div>';
                    $('#availability-result').html(conflictMsg).show();
                    $('#submitBookingBtn').prop('disabled', true);
                }
            },
            error: function() {
                $('#availability-result').html('<div class="alert alert-warning">Unable to check availability. Please try again.</div>').show();
            }
        });
    });
    
    // Add Booking
    $('#addBookingForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            data: $(this).serialize() + '&action=create_facility_booking',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#bookings-response').html('<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + response.message + '</div>');
                    $('#addBookingModal').modal('hide');
                    $('#addBookingForm')[0].reset();
                    // Reload without date filter to show the new booking
                    setTimeout(function() {
                        // Remove date filter from URL to show all bookings
                        var url = new URL(window.location.href);
                        url.searchParams.delete('date');
                        window.location.href = url.toString();
                    }, 1500);
                } else {
                    $('#bookings-response').html('<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = 'An error occurred. Please try again.';
                if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.message) {
                            errorMsg = response.message;
                        }
                    } catch(e) {
                        // If response is not JSON, show raw response (might be PHP error)
                        if (xhr.responseText.length < 500) {
                            errorMsg = 'Error: ' + xhr.responseText.substring(0, 200);
                        }
                    }
                }
                $('#bookings-response').html('<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + errorMsg + '</div>');
                console.error('AJAX Error:', status, error, xhr.responseText);
            }
        });
    });
    
    // View Booking Details
    $('.view-booking').click(function() {
        var bookingId = $(this).data('booking-id');
        
        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            data: {
                action: 'get_booking_details',
                booking_id: bookingId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#bookingDetailsContent').html(response.html);
                    $('#viewBookingModal').modal('show');
                } else {
                    alert('Error loading booking details');
                }
            }
        });
    });
    
    // Confirm Booking
    $('.confirm-booking').click(function() {
        var bookingId = $(this).data('booking-id');
        
        if (confirm('Confirm this booking?')) {
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: {
                    action: 'update_booking_status',
                    booking_id: bookingId,
                    status: 'confirmed',
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#bookings-response').html('<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + response.message + '</div>');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        alert(response.message);
                    }
                }
            });
        }
    });
    
    // Cancel Booking
    $('.cancel-booking').click(function() {
        var bookingId = $(this).data('booking-id');
        var bookingRef = $(this).data('booking-reference');
        
        if (confirm('Are you sure you want to cancel booking ' + bookingRef + '?')) {
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: {
                    action: 'update_booking_status',
                    booking_id: bookingId,
                    status: 'cancelled',
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#bookings-response').html('<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + response.message + '</div>');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        alert(response.message);
                    }
                }
            });
        }
    });
        });
    }
    initFacilityBookings();
})();
</script>
