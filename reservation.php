<?php
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

// Check if status column exists in booking table
$checkStatusColumn = mysqli_query($connection, "SHOW COLUMNS FROM booking LIKE 'status'");
$hasStatusColumn = mysqli_num_rows($checkStatusColumn) > 0;

// Check if advance_payment column exists
$checkAdvancePaymentColumn = mysqli_query($connection, "SHOW COLUMNS FROM booking LIKE 'advance_payment'");
$hasAdvancePaymentColumn = mysqli_num_rows($checkAdvancePaymentColumn) > 0;

// Get all reservations with guest and room details
// Calculate remaining_price: if advance_payment exists, deduct it from total_price
if ($hasAdvancePaymentColumn) {
    $reservationsQuery = "SELECT b.*, 
                          g.name as customer_name, g.contact_no, g.email, g.address,
                          r.room_no, r.branch_id as room_branch_id, rt.room_type, r.price as room_price,
                          br.branch_name, br.branch_code,
                          COALESCE(b.remaining_price, b.total_price - COALESCE(b.advance_payment, 0)) as calculated_remaining_price
                          FROM booking b
                          LEFT JOIN guests g ON b.guest_id = g.guest_id
                          LEFT JOIN room r ON b.room_id = r.room_id
                          LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id
                          LEFT JOIN branches br ON r.branch_id = br.branch_id
                          WHERE 1=1";
} else {
    $reservationsQuery = "SELECT b.*, 
                          g.name as customer_name, g.contact_no, g.email, g.address,
                          r.room_no, r.branch_id as room_branch_id, rt.room_type, r.price as room_price,
                          br.branch_name, br.branch_code,
                          COALESCE(b.remaining_price, b.total_price) as calculated_remaining_price
                          FROM booking b
                          LEFT JOIN guests g ON b.guest_id = g.guest_id
                          LEFT JOIN room r ON b.room_id = r.room_id
                          LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id
                          LEFT JOIN branches br ON r.branch_id = br.branch_id
                          WHERE 1=1";
}

// Add branch filter for branch admins
if ($userBranchId && !hasRole('super_admin')) {
    $reservationsQuery .= " AND r.branch_id = " . intval($userBranchId);
}

$reservationsQuery .= " ORDER BY b.booking_date DESC";
$reservationsResult = mysqli_query($connection, $reservationsQuery);

// Get room types for dropdown (from room_type table)
$roomTypesQuery = "SELECT room_type, price, max_person FROM room_type ORDER BY room_type";
$roomTypesResult = mysqli_query($connection, $roomTypesQuery);

// Get ID card types
$idCardTypesQuery = "SELECT * FROM id_card_type ORDER BY id_card_type";
$idCardTypesResult = mysqli_query($connection, $idCardTypesQuery);

// If table doesn't exist, set result to false to prevent errors
if (!$idCardTypesResult) {
    $idCardTypesResult = false;
}
?>
<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
        <ol class="breadcrumb">
            <li><a href="#">
                    <em class="fa fa-home"></em>
                </a></li>
            <li class="active">Reservation</li>
        </ol>
    </div><!--/.row-->

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">Reservations:
                    <div class="pull-right">
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addReservationModal" style="border-radius: 4px; font-weight: 500; padding: 8px 16px;">
                            <em class="fa fa-plus"></em> Add Reservation
                        </button>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="reservationsTable">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Customer Name</th>
                                    <th>Room</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Total Price</th>
                                    <th>Remaining Amount</th>
                                    <th>Payment Status</th>
                                    <?php if ($hasStatusColumn): ?>
                                    <th>Status</th>
                                    <?php endif; ?>
                                    <th>Branch</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (mysqli_num_rows($reservationsResult) > 0) {
                                    while ($reservation = mysqli_fetch_assoc($reservationsResult)): 
                                        $paymentStatus = $reservation['payment_status'] == 1 ? 
                                            '<span class="label label-success">Paid</span>' : 
                                            '<span class="label label-warning">Pending</span>';
                                        
                                        // Get booking status
                                        $bookingStatus = $hasStatusColumn ? ($reservation['status'] ?? 'pending') : 'confirmed';
                                        $isCompleted = ($bookingStatus == 'checked_out' || $bookingStatus == 'cancelled');
                                        
                                        // Status badge styling
                                        $statusBadge = '';
                                        switch($bookingStatus) {
                                            case 'pending':
                                                $statusBadge = '<span class="label label-default">Pending</span>';
                                                break;
                                            case 'confirmed':
                                                $statusBadge = '<span class="label label-info">Confirmed</span>';
                                                break;
                                            case 'checked_in':
                                                $statusBadge = '<span class="label label-primary">Checked In</span>';
                                                break;
                                            case 'checked_out':
                                                $statusBadge = '<span class="label label-success">Checked Out</span>';
                                                break;
                                            case 'cancelled':
                                                $statusBadge = '<span class="label label-danger">Cancelled</span>';
                                                break;
                                            default:
                                                $statusBadge = '<span class="label label-default">' . htmlspecialchars(ucfirst($bookingStatus)) . '</span>';
                                        }
                                ?>
                                <tr>
                                    <td>#<?php echo $reservation['booking_id']; ?></td>
                                    <td><?php echo htmlspecialchars($reservation['customer_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($reservation['room_type'] ?? 'N/A'); ?>
                                        <?php if (!empty($reservation['room_no'])): ?>
                                            <br><small class="text-muted">Room #<?php echo htmlspecialchars($reservation['room_no']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($reservation['check_in'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['check_out'] ?? 'N/A'); ?></td>
                                    <td>LKR <?php echo number_format($reservation['total_price'] ?? 0); ?></td>
                                    <td>
                                        <?php 
                                        $remainingAmount = $reservation['calculated_remaining_price'] ?? ($reservation['remaining_price'] ?? $reservation['total_price'] ?? 0);
                                        if ($hasAdvancePaymentColumn && isset($reservation['advance_payment']) && $reservation['advance_payment'] > 0) {
                                            echo '<span style="color: var(--color-primary); font-weight: bold;">LKR ' . number_format($remainingAmount) . '</span>';
                                            echo '<br><small class="text-muted">(Advance: LKR ' . number_format($reservation['advance_payment']) . ')</small>';
                                        } else {
                                            echo 'LKR ' . number_format($remainingAmount);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $paymentStatus; ?></td>
                                    <?php if ($hasStatusColumn): ?>
                                    <td><?php echo $statusBadge; ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if (!empty($reservation['branch_name'])): ?>
                                            <span class="label label-info">
                                                <?php echo htmlspecialchars($reservation['branch_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-info btn-sm view-reservation" 
                                                    data-booking-id="<?php echo $reservation['booking_id']; ?>"
                                                    data-customer-name="<?php echo htmlspecialchars($reservation['customer_name'] ?? ''); ?>"
                                                    data-room-type="<?php echo htmlspecialchars($reservation['room_type'] ?? ''); ?>"
                                                    data-room-no="<?php echo htmlspecialchars($reservation['room_no'] ?? ''); ?>"
                                                    data-check-in="<?php echo htmlspecialchars($reservation['check_in'] ?? ''); ?>"
                                                    data-check-out="<?php echo htmlspecialchars($reservation['check_out'] ?? ''); ?>"
                                                    data-total-price="<?php echo $reservation['total_price'] ?? 0; ?>"
                                                    data-payment-status="<?php echo $reservation['payment_status']; ?>"
                                                    data-contact="<?php echo htmlspecialchars($reservation['contact_no'] ?? ''); ?>"
                                                    data-email="<?php echo htmlspecialchars($reservation['email'] ?? ''); ?>"
                                                    data-address="<?php echo htmlspecialchars($reservation['address'] ?? ''); ?>"
                                                    data-branch="<?php echo htmlspecialchars($reservation['branch_name'] ?? ''); ?>"
                                                    data-remaining-price="<?php echo $reservation['remaining_price'] ?? 0; ?>"
                                                    data-guest-id="<?php echo $reservation['guest_id'] ?? 0; ?>"
                                                    data-room-id="<?php echo $reservation['room_id'] ?? 0; ?>"
                                                    data-status="<?php echo htmlspecialchars($bookingStatus); ?>">
                                                <em class="fa fa-eye"></em> View
                                            </button>
                                            <?php if (!$isCompleted): ?>
                                                <?php if ($bookingStatus == 'confirmed' || $bookingStatus == 'pending'): ?>
                                                <button class="btn btn-success btn-sm check-in-booking"
                                                        data-booking-id="<?php echo $reservation['booking_id']; ?>"
                                                        data-customer-name="<?php echo htmlspecialchars($reservation['customer_name'] ?? ''); ?>"
                                                        data-room-type="<?php echo htmlspecialchars($reservation['room_type'] ?? ''); ?>"
                                                        data-room-no="<?php echo htmlspecialchars($reservation['room_no'] ?? ''); ?>"
                                                        data-check-in="<?php echo htmlspecialchars($reservation['check_in'] ?? ''); ?>"
                                                        data-check-out="<?php echo htmlspecialchars($reservation['check_out'] ?? ''); ?>"
                                                        data-total-price="<?php echo $reservation['total_price'] ?? 0; ?>"
                                                        data-remaining-price="<?php echo $reservation['calculated_remaining_price'] ?? ($reservation['remaining_price'] ?? 0); ?>"
                                                        data-advance-payment="<?php echo $reservation['advance_payment'] ?? 0; ?>"
                                                        data-advance-payment-method="<?php echo $reservation['advance_payment_method'] ?? 'none'; ?>">
                                                    <em class="fa fa-sign-in"></em> Check In
                                                </button>
                                                <?php elseif ($bookingStatus == 'checked_in'): ?>
                                                <button class="btn btn-primary btn-sm check-out-booking"
                                                        data-booking-id="<?php echo $reservation['booking_id']; ?>"
                                                        data-customer-name="<?php echo htmlspecialchars($reservation['customer_name'] ?? ''); ?>"
                                                        data-room-type="<?php echo htmlspecialchars($reservation['room_type'] ?? ''); ?>"
                                                        data-room-no="<?php echo htmlspecialchars($reservation['room_no'] ?? ''); ?>"
                                                        data-check-in="<?php echo htmlspecialchars($reservation['check_in'] ?? ''); ?>"
                                                        data-check-out="<?php echo htmlspecialchars($reservation['check_out'] ?? ''); ?>"
                                                        data-total-price="<?php echo $reservation['total_price'] ?? 0; ?>"
                                                        data-remaining-price="<?php echo $reservation['calculated_remaining_price'] ?? ($reservation['remaining_price'] ?? 0); ?>"
                                                        data-advance-payment="<?php echo $reservation['advance_payment'] ?? 0; ?>">
                                                    <em class="fa fa-sign-out"></em> Check Out
                                                </button>
                                                <?php endif; ?>
                                            <button class="btn btn-danger btn-sm cancel-reservation"
                                                    data-booking-id="<?php echo $reservation['booking_id']; ?>">
                                                <em class="fa fa-times"></em> Cancel
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                } else {
                                    $colspan = $hasStatusColumn ? 10 : 9;
                                    echo '<tr><td colspan="' . $colspan . '" class="text-center">No reservations found.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>    <!--/.main-->

<!-- Add Reservation Modal -->
<div id="addReservationModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-calendar-plus"></em> Add New Reservation</h4>
            </div>
            <div class="modal-body">
                <div class="add-reservation-response"></div>
                <form role="form" id="addReservationForm" data-toggle="validator">
                    <!-- Room Information -->
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Room Information</strong></div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="form-group col-lg-6">
                                    <label>Room Type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="modal_room_type" onchange="fetch_room_modal(this.value);" required data-error="Select Room Type">
                                        <option value="" selected disabled>Select Room Type</option>
                                        <?php
                                        mysqli_data_seek($roomTypesResult, 0);
                                        if (mysqli_num_rows($roomTypesResult) > 0) {
                                            while ($room_type = mysqli_fetch_assoc($roomTypesResult)) {
                                                echo '<option value="' . htmlspecialchars($room_type['room_type']) . '">' . htmlspecialchars($room_type['room_type']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>Room No <span class="text-danger">*</span></label>
                                    <select class="form-control" id="modal_room_no" onchange="fetch_price_modal(this.value)" required data-error="Select Room No">
                                        <option value="" selected disabled>Select Room No</option>
                                    </select>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>Check In Date <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control datepicker" placeholder="mm/dd/yyyy" id="modal_check_in_date" data-error="Select Check In Date" required>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>Check Out Date <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control datepicker" placeholder="mm/dd/yyyy" id="modal_check_out_date" data-error="Select Check Out Date" required>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="col-lg-12">
                                    <h4 style="font-weight: bold">Total Days : <span id="modal_staying_day">0</span> Days</h4>
                                    <h4 style="font-weight: bold">Price: LKR <span id="modal_price">0</span></h4>
                                    <h4 style="font-weight: bold">Total Amount : LKR <span id="modal_total_price">0</span></h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Details -->
                    <div class="panel panel-default">
                        <div class="panel-heading"><strong>Customer Details</strong></div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="form-group col-lg-6">
                                    <label>First Name <span class="text-danger">*</span></label>
                                    <input class="form-control" placeholder="First Name" id="modal_first_name" data-error="Enter First Name" required>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>Last Name</label>
                                    <input class="form-control" placeholder="Last Name" id="modal_last_name">
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>Contact Number <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" data-error="Enter Min 10 Digit" data-minlength="10" placeholder="Contact No" id="modal_contact_no" required>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>Email Address <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" placeholder="Email Address" id="modal_email" data-error="Enter Valid Email Address" required>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>ID Card Type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="modal_id_card_id" data-error="Select ID Card Type" required onchange="validId_modal(this.value);">
                                        <option value="" selected disabled>Select ID Card Type</option>
                                        <?php
                                        if ($idCardTypesResult && mysqli_num_rows($idCardTypesResult) > 0) {
                                            mysqli_data_seek($idCardTypesResult, 0);
                                            while ($id_card_type = mysqli_fetch_assoc($idCardTypesResult)) {
                                                echo '<option value="' . $id_card_type['id_card_type_id'] . '">' . htmlspecialchars($id_card_type['id_card_type']) . '</option>';
                                            }
                                        } else {
                                            // Fallback options if table doesn't exist
                                            echo '<option value="1">National Identity Card</option>';
                                            echo '<option value="2">Passport</option>';
                                            echo '<option value="3">Driving License</option>';
                                            echo '<option value="4">Other</option>';
                                        }
                                        ?>
                                    </select>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>ID Card Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" placeholder="ID Card Number" id="modal_id_card_no" data-error="Enter Valid ID Card No" required>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-12">
                                    <label>Residential Address <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" placeholder="Full Address" id="modal_address" required>
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitReservation" style="border-radius: 4px; font-weight: 500;">
                    <em class="fa fa-save"></em> Submit Reservation
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Reservation Details Modal -->
<div id="viewReservationModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-eye"></em> Reservation Details</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-12">
                        <table class="table table-striped table-bordered">
                            <tbody>
                                <tr>
                                    <td><b>Booking ID</b></td>
                                    <td id="view_booking_id"></td>
                                </tr>
                                <tr>
                                    <td><b>Customer Name</b></td>
                                    <td id="view_customer_name"></td>
                                </tr>
                                <tr>
                                    <td><b>Contact Number</b></td>
                                    <td id="view_contact"></td>
                                </tr>
                                <tr>
                                    <td><b>Email Address</b></td>
                                    <td id="view_email"></td>
                                </tr>
                                <tr>
                                    <td><b>Address</b></td>
                                    <td id="view_address"></td>
                                </tr>
                                <tr>
                                    <td><b>Room Type</b></td>
                                    <td id="view_room_type"></td>
                                </tr>
                                <tr>
                                    <td><b>Room No</b></td>
                                    <td id="view_room_no"></td>
                                </tr>
                                <tr>
                                    <td><b>Check In Date</b></td>
                                    <td id="view_check_in"></td>
                                </tr>
                                <tr>
                                    <td><b>Check Out Date</b></td>
                                    <td id="view_check_out"></td>
                                </tr>
                                <tr>
                                    <td><b>Total Amount</b></td>
                                    <td id="view_total_price"></td>
                                </tr>
                                <tr>
                                    <td><b>Remaining Amount</b></td>
                                    <td id="view_remaining_price"></td>
                                </tr>
                                <tr>
                                    <td><b>Payment Status</b></td>
                                    <td id="view_payment_status"></td>
                                </tr>
                                <?php if ($hasStatusColumn): ?>
                                <tr>
                                    <td><b>Booking Status</b></td>
                                    <td id="view_status"></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><b>Branch</b></td>
                                    <td id="view_branch"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 4px;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Booking Confirmation Modal -->
<div id="cancelReservationModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-exclamation-triangle"></em> Cancel Reservation</h4>
            </div>
            <div class="modal-body">
                <div class="cancel-reservation-response"></div>
                <p>Are you sure you want to cancel this reservation?</p>
                <p><strong>Booking ID: #<span id="cancel_booking_id"></span></strong></p>
                <input type="hidden" id="cancel_booking_id_input">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">No, Keep It</button>
                <button type="button" class="btn btn-danger" id="confirmCancelReservation">
                    <em class="fa fa-times"></em> Yes, Cancel Booking
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Check In Modal -->
<div id="checkInModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title text-center"><b>Check In Guest</b></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-12">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td><b>Customer Name</b></td>
                                    <td id="checkin_customer_name"></td>
                                </tr>
                                <tr>
                                    <td><b>Room Type</b></td>
                                    <td id="checkin_room_type"></td>
                                </tr>
                                <tr>
                                    <td><b>Room Number</b></td>
                                    <td id="checkin_room_no"></td>
                                </tr>
                                <tr>
                                    <td><b>Check In</b></td>
                                    <td id="checkin_check_in"></td>
                                </tr>
                                <tr>
                                    <td><b>Check Out</b></td>
                                    <td id="checkin_check_out"></td>
                                </tr>
                                <tr>
                                    <td><b>Total Price</b></td>
                                    <td id="checkin_total_price"></td>
                                </tr>
                                <tr id="checkin_advance_row" style="display: none;">
                                    <td><b>Advance Paid</b></td>
                                    <td id="checkin_advance_display" style="color: #27ae60;"></td>
                                </tr>
                                <tr>
                                    <td><b>Remaining Amount</b></td>
                                    <td id="checkin_remaining_price" style="font-weight: bold; color: var(--color-primary);"></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <!-- Show already paid advance payment by guest -->
                        <div id="guest_advance_payment_info" style="display: none; margin-bottom: 15px;">
                            <div class="alert alert-success">
                                <i class="fa fa-check-circle"></i> <strong>Guest has already paid advance payment:</strong> 
                                LKR <span id="guest_paid_advance">0</span> via <span id="guest_payment_method_display">Card</span>
                                <br><small><i class="fa fa-info-circle"></i> No additional advance payment required. Balance will be collected at check-out.</small>
                            </div>
                        </div>
                        
                        <form role="form" id="checkInForm">
                            <div class="checkin-response"></div>
                            
                            <!-- Only show advance payment option if guest hasn't paid yet -->
                            <div id="no_advance_group">
                                <div class="form-group">
                                    <label>Advance Payment (Optional)</label>
                                    <input type="number" class="form-control" id="checkin_advance_payment"
                                           placeholder="Enter advance payment amount" min="0" step="0.01">
                                    <div class="help-block">Leave empty if no advance payment</div>
                                </div>
                                <div class="form-group" id="advance_payment_method_group" style="display: none;">
                                    <label>Advance Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-control" id="checkin_advance_payment_method">
                                        <option value="">Select Payment Method</option>
                                        <option value="cash">Cash</option>
                                        <option value="card">Card</option>
                                    </select>
                                </div>
                            </div>
                            
                            <input type="hidden" id="checkin_booking_id" value="">
                            <input type="hidden" id="existing_advance_payment" value="0">
                            <button type="submit" class="btn btn-success pull-right">
                                <em class="fa fa-check"></em> Confirm Check In
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Check Out Modal-->
<div id="checkOutModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title text-center"><b>Check Out Guest</b></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-12">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <td><b>Customer Name</b></td>
                                    <td id="checkout_customer_name"></td>
                                </tr>
                                <tr>
                                    <td><b>Room Type</b></td>
                                    <td id="checkout_room_type"></td>
                                </tr>
                                <tr>
                                    <td><b>Room Number</b></td>
                                    <td id="checkout_room_no"></td>
                                </tr>
                                <tr>
                                    <td><b>Check In</b></td>
                                    <td id="checkout_check_in"></td>
                                </tr>
                                <tr>
                                    <td><b>Check Out</b></td>
                                    <td id="checkout_check_out"></td>
                                </tr>
                                <tr>
                                    <td><b>Total Amount</b></td>
                                    <td id="checkout_total_price"></td>
                                </tr>
                                <tr id="checkout_advance_row" style="display: none;">
                                    <td><b>Advance Paid</b></td>
                                    <td id="checkout_advance_paid" style="color: #27ae60;"></td>
                                </tr>
                                <tr>
                                    <td><b>Remaining Amount to Pay</b></td>
                                    <td id="checkout_remaining_price" style="font-weight: bold; color: var(--color-primary);"></td>
                                </tr>
                            </tbody>
                        </table>
                        <form role="form" id="checkOutForm" data-toggle="validator">
                            <div class="checkout-response"></div>
                            <div class="form-group">
                                <label><b>Remaining Payment Amount</b> <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="checkout_remaining_amount"
                                       placeholder="Enter remaining payment amount" required min="0" step="0.01"
                                       data-error="Please enter the remaining amount">
                                <div class="help-block with-errors">
                                    <small><i class="fa fa-info-circle"></i> Enter the exact remaining amount shown above.</small>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><b>Balance Payment Method</b> <span class="text-danger">*</span></label>
                                <select class="form-control" id="checkout_balance_payment_method" required
                                        data-error="Please select payment method">
                                    <option value="">Select Payment Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                </select>
                                <div class="help-block with-errors"></div>
                            </div>
                            <input type="hidden" id="checkout_booking_id" value="">
                            <button type="submit" class="btn btn-primary pull-right">
                                <em class="fa fa-sign-out"></em> Proceed Check Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Booking Confirmation Modal -->
<div id="bookingConfirm" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title text-center"><b>Room Booking</b></h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="alert bg-success alert-dismissable" role="alert">
                            <em class="fa fa-lg fa-check-circle">&nbsp;</em>Room Successfully Booked
                        </div>
                        <table class="table table-striped table-bordered table-responsive">
                            <tbody>
                                <tr>
                                    <td><b>Customer Name</b></td>
                                    <td id="getCustomerName"></td>
                                </tr>
                                <tr>
                                    <td><b>Room Type</b></td>
                                    <td id="getRoomType"></td>
                                </tr>
                                <tr>
                                    <td><b>Room No</b></td>
                                    <td id="getRoomNo"></td>
                                </tr>
                                <tr>
                                    <td><b>Check In</b></td>
                                    <td id="getCheckIn"></td>
                                </tr>
                                <tr>
                                    <td><b>Check Out</b></td>
                                    <td id="getCheckOut"></td>
                                </tr>
                                <tr>
                                    <td><b>Total Amount</b></td>
                                    <td id="getTotalPrice"></td>
                                </tr>
                                <tr>
                                    <td><b>Payment Status</b></td>
                                    <td id="getPaymentStaus"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal" onclick="location.reload();">
                    <i class="fa fa-check-circle"></i> OK
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for jQuery to be loaded (from footer.php)
(function() {
    function initReservation() {
        if (typeof jQuery === 'undefined') {
            setTimeout(function() { initReservation(); }, 50);
            return;
        }
        
        var $ = jQuery;
        
        $(document).ready(function() {
            // Initialize DataTable only if table exists and has proper structure
            var $table = $('#reservationsTable');
            if ($table.length) {
                // Check if table has proper structure (thead and tbody with matching columns)
                var theadCols = $table.find('thead tr:first th').length;
                var firstRowCols = $table.find('tbody tr:first td').length;
                
                if (theadCols > 0 && (firstRowCols === theadCols || $table.find('tbody tr').length === 0)) {
                    try {
                        // Destroy existing DataTable instance if any
                        if ($.fn.DataTable.isDataTable($table)) {
                            $table.DataTable().destroy();
                        }
                        
                        // Determine the actions column index (last column)
                        var actionsColumnIndex = theadCols - 1;
                        
                        $table.DataTable({
                            "pageLength": 25,
                            "order": [[0, "desc"]],
                            "columnDefs": [
                                { "orderable": false, "targets": [actionsColumnIndex] } // Actions column not sortable
                            ]
                        });
                    } catch(e) {
                        console.error('DataTable initialization error:', e);
                    }
                }
            }
    
    // Initialize date pickers for modal
    $('#addReservationModal').on('shown.bs.modal', function() {
        // Initialize date pickers if they exist
        if (typeof $.fn.datepicker !== 'undefined') {
            $('.datepicker').datepicker({
                format: 'mm/dd/yyyy',
                autoclose: true,
                startDate: new Date()
            });
        }
        
        // Calculate days when dates change
        $('#modal_check_in_date, #modal_check_out_date').on('change', function() {
            calculateDaysModal();
        });
    });
    
    // View reservation details
    $(document).on('click', '.view-reservation', function() {
        var bookingId = $(this).data('booking-id');
        var status = $(this).data('status') || 'confirmed';
        
        $('#view_booking_id').text('#' + bookingId);
        $('#view_customer_name').text($(this).data('customer-name') || 'N/A');
        $('#view_contact').text($(this).data('contact') || 'N/A');
        $('#view_email').text($(this).data('email') || 'N/A');
        $('#view_address').text($(this).data('address') || 'N/A');
        $('#view_room_type').text($(this).data('room-type') || 'N/A');
        $('#view_room_no').text($(this).data('room-no') || 'N/A');
        $('#view_check_in').text($(this).data('check-in') || 'N/A');
        $('#view_check_out').text($(this).data('check-out') || 'N/A');
        $('#view_total_price').text('LKR ' + number_format($(this).data('total-price') || 0));
        $('#view_remaining_price').text('LKR ' + number_format($(this).data('remaining-price') || 0));
        $('#view_payment_status').html($(this).data('payment-status') == 1 ? 
            '<span class="label label-success">Paid</span>' : 
            '<span class="label label-warning">Pending</span>');
        
        // Set booking status badge
        var statusBadge = '';
        switch(status) {
            case 'pending':
                statusBadge = '<span class="label label-default">Pending</span>';
                break;
            case 'confirmed':
                statusBadge = '<span class="label label-info">Confirmed</span>';
                break;
            case 'checked_in':
                statusBadge = '<span class="label label-primary">Checked In</span>';
                break;
            case 'checked_out':
                statusBadge = '<span class="label label-success">Checked Out</span>';
                break;
            case 'cancelled':
                statusBadge = '<span class="label label-danger">Cancelled</span>';
                break;
            default:
                statusBadge = '<span class="label label-default">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
        }
        $('#view_status').html(statusBadge);
        
        $('#view_branch').text($(this).data('branch') || 'Not Assigned');
        
        $('#viewReservationModal').modal('show');
    });
    
    // Submit reservation
    $('#submitReservation').on('click', function() {
        var form = $('#addReservationForm');
        var responseDiv = $('.add-reservation-response');
        
        // Validate form
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        
        // Get form values
        var roomType = $('#modal_room_type').val();
        var roomNo = $('#modal_room_no').val();
        var checkIn = $('#modal_check_in_date').val();
        var checkOut = $('#modal_check_out_date').val();
        var totalPriceText = $('#modal_total_price').text();
        var totalPrice = totalPriceText.replace(/[^0-9.]/g, ''); // Remove all non-numeric characters except decimal
        var firstName = $('#modal_first_name').val();
        var lastName = $('#modal_last_name').val();
        var name = firstName + (lastName ? ' ' + lastName : '');
        var contactNo = $('#modal_contact_no').val();
        var email = $('#modal_email').val();
        var idCardId = $('#modal_id_card_id').val();
        var idCardNo = $('#modal_id_card_no').val();
        var address = $('#modal_address').val();
        
        // Validate required fields
        if (!roomType || !roomNo || !checkIn || !checkOut || !totalPrice || totalPrice == '0' || !firstName || !contactNo || !email || !idCardId || !idCardNo || !address) {
            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Please fill all required fields.</div>');
            return;
        }
        
        // Prepare form data (match ajax.php expected format)
        var formData = {
            booking: '',
            room_id: roomNo,
            check_in: checkIn,
            check_out: checkOut,
            total_price: totalPrice,
            name: name,
            contact_no: contactNo,
            email: email,
            id_card_id: idCardId,
            id_card_no: idCardNo,
            address: address
        };
        
        // Submit via AJAX
        $.ajax({
            type: 'POST',
            url: 'ajax.php',
            dataType: 'json',
            data: formData,
            success: function(response) {
                if (response.done == true) {
                    // Populate confirmation modal
                    $('#getCustomerName').text(name);
                    $('#getRoomType').text($('#modal_room_type option:selected').text());
                    $('#getRoomNo').text($('#modal_room_no option:selected').text());
                    $('#getCheckIn').text(checkIn);
                    $('#getCheckOut').text(checkOut);
                    $('#getTotalPrice').text('LKR ' + number_format(totalPrice));
                    $('#getPaymentStaus').html('<span class="label label-warning">Pending</span>');
                    
                    // Close add modal and show confirmation
                    $('#addReservationModal').modal('hide');
                    $('#bookingConfirm').modal('show');
                    
                    // Reset form
                    form[0].reset();
                    $('#modal_staying_day').text('0');
                    $('#modal_price').text('0');
                    $('#modal_total_price').text('0');
                } else {
                    responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + (response.data || 'Error creating reservation. Please try again.') + '</div>');
                }
            },
            error: function() {
                responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> An error occurred. Please try again.</div>');
            }
        });
    });
    
    // Reset form when modal is closed
    $('#addReservationModal').on('hidden.bs.modal', function() {
        $('#addReservationForm')[0].reset();
        $('#modal_room_no').html('<option value="" selected disabled>Select Room No</option>');
        $('#modal_staying_day').text('0');
        $('#modal_price').text('0');
        $('#modal_total_price').text('0');
        $('.add-reservation-response').html('');
    }); // Close hidden.bs.modal handler
    
    // Cancel reservation
    $(document).on('click', '.cancel-reservation', function() {
        var bookingId = $(this).data('booking-id');
        $('#cancel_booking_id').text(bookingId);
        $('#cancel_booking_id_input').val(bookingId);
        $('#cancelReservationModal').modal('show');
    });
    
    // Confirm cancel reservation
    $('#confirmCancelReservation').on('click', function() {
        var bookingId = $('#cancel_booking_id_input').val();
        var responseDiv = $('.cancel-reservation-response');
        
        if (!bookingId) {
            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Invalid booking ID.</div>');
            return;
        }
        
        // Submit via AJAX
        $.ajax({
            type: 'POST',
            url: 'ajax.php',
            dataType: 'json',
            data: {
                cancel_booking: '',
                booking_id: bookingId
            },
            success: function(response) {
                if (response.done == true) {
                    responseDiv.html('<div class="alert alert-success"><em class="fa fa-check-circle"></em> Reservation cancelled successfully!</div>');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + (response.data || 'Error cancelling reservation. Please try again.') + '</div>');
                }
            },
            error: function() {
                responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> An error occurred. Please try again.</div>');
            }
        });
    });
    
    // Reset cancel modal when closed
    $('#cancelReservationModal').on('hidden.bs.modal', function() {
        $('.cancel-reservation-response').html('');
    });
    
    // Check In button handler
    $(document).on('click', '.check-in-booking', function() {
        var bookingId = $(this).data('booking-id');
        var customerName = $(this).data('customer-name');
        var roomType = $(this).data('room-type');
        var roomNo = $(this).data('room-no');
        var checkIn = $(this).data('check-in');
        var checkOut = $(this).data('check-out');
        var remainingPrice = parseFloat($(this).data('remaining-price')) || 0;
        var totalPrice = $(this).data('total-price');
        var advancePayment = parseFloat($(this).data('advance-payment')) || 0;
        var advancePaymentMethod = $(this).data('advance-payment-method') || 'none';
        
        // Populate modal
        $('#checkin_customer_name').text(customerName);
        $('#checkin_room_type').text(roomType);
        $('#checkin_room_no').text(roomNo);
        $('#checkin_check_in').text(checkIn);
        $('#checkin_check_out').text(checkOut);
        $('#checkin_total_price').text('LKR ' + number_format(totalPrice));
        $('#checkin_booking_id').val(bookingId);
        $('#existing_advance_payment').val(advancePayment);
        
        // Calculate and display remaining amount (total - advance already paid)
        var remainingAmount = remainingPrice; // This should already be calculated correctly from the query
        if (remainingAmount < 0) remainingAmount = 0;
        
        // Update remaining price display in check-in modal if it exists
        if ($('#checkin_remaining_price').length) {
            $('#checkin_remaining_price').text('LKR ' + number_format(remainingAmount));
        }
        
        // Check if guest already paid advance
        if (advancePayment > 0) {
            // Guest already paid advance - show info and hide advance payment fields
            $('#guest_paid_advance').text(number_format(advancePayment));
            var paymentMethodDisplay = advancePaymentMethod.charAt(0).toUpperCase() + advancePaymentMethod.slice(1);
            $('#guest_payment_method_display').text(paymentMethodDisplay);
            $('#guest_advance_payment_info').show();
            
            // Hide advance payment input - guest already paid, don't ask again
            $('#no_advance_group').hide();
            $('#checkin_advance_payment').val('');
            $('#advance_payment_method_group').hide();
            $('#checkin_advance_payment_method').val('');
        } else {
            // No advance paid by guest - show advance payment option (optional)
            $('#guest_advance_payment_info').hide();
            $('#no_advance_group').show();
            $('#checkin_advance_payment').val('');
            $('#advance_payment_method_group').hide();
            $('#checkin_advance_payment_method').val('');
        }
        
        $('#checkInModal').modal('show');
    });
    
    // Show/hide payment method based on advance payment
    $('#checkin_advance_payment').on('input', function() {
        var advanceAmount = parseFloat($(this).val()) || 0;
        if (advanceAmount > 0) {
            $('#advance_payment_method_group').show();
        } else {
            $('#advance_payment_method_group').hide();
            $('#checkin_advance_payment_method').val('');
        }
    });
    
    // Check In form submission
    $('#checkInForm').on('submit', function(e) {
        e.preventDefault();
        
        var bookingId = $('#checkin_booking_id').val();
        var existingAdvance = parseFloat($('#existing_advance_payment').val()) || 0;
        var responseDiv = $('.checkin-response');
        
        var advancePayment = 0;
        var advancePaymentMethod = 'none';
        
        // Check if guest already paid or not
        if (existingAdvance > 0) {
            // Guest already paid advance during booking - use existing payment, don't collect more
            advancePayment = existingAdvance;
            advancePaymentMethod = 'card'; // Keep existing method from guest booking (will be overridden by server)
            // Don't send new advance payment - server will use existing one
        } else {
            // No existing advance - admin can optionally collect advance payment at check-in
            advancePayment = parseFloat($('#checkin_advance_payment').val()) || 0;
            if (advancePayment > 0) {
                advancePaymentMethod = $('#checkin_advance_payment_method').val();
                if (!advancePaymentMethod) {
                    responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Please select a payment method for advance payment.</div>');
                    return;
                }
            } else {
                advancePaymentMethod = 'none';
            }
        }
        
        if (!bookingId) {
            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Invalid booking ID.</div>');
            return;
        }
        
        // Submit via AJAX
        $.ajax({
            type: 'POST',
            url: 'ajax.php',
            dataType: 'json',
            data: {
                check_in_room: '',
                booking_id: bookingId,
                advance_payment: advancePayment,
                advance_payment_method: advancePaymentMethod
            },
            success: function(response) {
                if (response.done == true) {
                    responseDiv.html('<div class="alert alert-success"><em class="fa fa-check-circle"></em> Guest checked in successfully!</div>');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    var errorMsg = response.data || 'Error checking in guest. Please try again.';
                    console.error('Check-in error:', errorMsg);
                    responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + errorMsg + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {status: status, error: error, responseText: xhr.responseText});
                var errorMsg = 'An error occurred. Please try again.';
                try {
                    var jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse.data) {
                        errorMsg = jsonResponse.data;
                    }
                } catch(e) {
                    // If response is not JSON, show the raw response for debugging
                    if (xhr.responseText) {
                        console.error('Raw response:', xhr.responseText);
                    }
                }
                responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + errorMsg + '</div>');
            }
        });
    });
    
    // Check Out button handler
    $(document).on('click', '.check-out-booking', function() {
        var bookingId = $(this).data('booking-id');
        var customerName = $(this).data('customer-name');
        var roomType = $(this).data('room-type');
        var roomNo = $(this).data('room-no');
        var checkIn = $(this).data('check-in');
        var checkOut = $(this).data('check-out');
        var totalPrice = $(this).data('total-price');
        var remainingPrice = $(this).data('remaining-price');
        var advancePayment = parseFloat($(this).data('advance-payment')) || 0;
        
        // Populate modal
        $('#checkout_customer_name').text(customerName);
        $('#checkout_room_type').text(roomType);
        $('#checkout_room_no').text(roomNo);
        $('#checkout_check_in').text(checkIn);
        $('#checkout_check_out').text(checkOut);
        $('#checkout_total_price').text('LKR ' + number_format(totalPrice));
        
        // Show advance payment if guest paid during booking
        if (advancePayment > 0) {
            $('#checkout_advance_row').show();
            $('#checkout_advance_paid').text('LKR ' + number_format(advancePayment) + ' (Paid during booking)');
        } else {
            $('#checkout_advance_row').hide();
        }
        
        $('#checkout_remaining_price').text('LKR ' + number_format(remainingPrice));
        $('#checkout_booking_id').val(bookingId);
        $('#checkout_remaining_amount').val(remainingPrice);
        
        $('#checkOutModal').modal('show');
    });
    
    // Check Out form submission
    $('#checkOutForm').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var bookingId = $('#checkout_booking_id').val();
        var remainingAmount = $('#checkout_remaining_amount').val();
        var balancePaymentMethod = $('#checkout_balance_payment_method').val();
        var responseDiv = $('.checkout-response');
        
        // Validate form
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        
        if (!bookingId) {
            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Invalid booking ID.</div>');
            return;
        }
        
        if (!balancePaymentMethod) {
            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Please select a payment method.</div>');
            return;
        }
        
        // Submit via AJAX
        $.ajax({
            type: 'POST',
            url: 'ajax.php',
            dataType: 'json',
            data: {
                check_out_room: '',
                booking_id: bookingId,
                remaining_amount: remainingAmount,
                balance_payment_method: balancePaymentMethod
            },
            success: function(response) {
                if (response.done == true) {
                    responseDiv.html('<div class="alert alert-success"><em class="fa fa-check-circle"></em> Guest checked out successfully!</div>');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    var errorMsg = response.data || 'Error checking out guest. Please try again.';
                    console.error('Check-out error:', errorMsg);
                    responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + errorMsg + '</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {status: status, error: error, responseText: xhr.responseText});
                var errorMsg = 'An error occurred. Please try again.';
                try {
                    var jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse.data) {
                        errorMsg = jsonResponse.data;
                    }
                } catch(e) {
                    // If response is not JSON, show the raw response for debugging
                    if (xhr.responseText) {
                        console.error('Raw response:', xhr.responseText);
                    }
                }
                responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + errorMsg + '</div>');
            }
        });
    });
    
    // Reset check-in modal when closed
    $('#checkInModal').on('hidden.bs.modal', function() {
        $('#checkInForm')[0].reset();
        $('.checkin-response').html('');
    });
    
    // Reset check-out modal when closed
    $('#checkOutModal').on('hidden.bs.modal', function() {
        $('#checkOutForm')[0].reset();
        $('.checkout-response').html('');
    });
    }); // Close $(document).ready
    
    // Fetch rooms for modal
    window.fetch_room_modal = function(room_type) {
        if (!room_type) {
            $('#modal_room_no').html('<option value="" selected disabled>Select Room No</option>');
            return;
        }
        
        $.ajax({
            type: 'POST',
            url: 'ajax.php',
            data: { room_type: room_type },
            success: function(response) {
                $('#modal_room_no').html(response);
            }
        });
    };
    
    // Fetch price for modal
    window.fetch_price_modal = function(room_id) {
        if (!room_id) {
            $('#modal_price').text('0');
            calculateDaysModal();
            return;
        }
        
        $.ajax({
            type: 'POST',
            url: 'ajax.php',
            data: { room_price: '1', room_id: room_id },
            success: function(response) {
                $('#modal_price').text(response);
                calculateDaysModal();
            }
        });
    };
    
    // Calculate days and total price for modal
    window.calculateDaysModal = function() {
        var checkIn = $('#modal_check_in_date').val();
        var checkOut = $('#modal_check_out_date').val();
        var price = parseFloat($('#modal_price').text().replace(/,/g, '')) || 0;
        
        if (checkIn && checkOut) {
            // Parse date in mm/dd/yyyy format
            var parseDate = function(dateString) {
                var parts = dateString.split('/');
                return new Date(parts[2], parts[0] - 1, parts[1]);
            };
            
            var date1 = parseDate(checkIn);
            var date2 = parseDate(checkOut);
            var timeDiff = date2.getTime() - date1.getTime();
            var daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));
            
            if (daysDiff > 0) {
                $('#modal_staying_day').text(daysDiff);
                var totalPrice = daysDiff * price;
                $('#modal_total_price').text(totalPrice.toLocaleString());
            } else {
                $('#modal_staying_day').text('0');
                $('#modal_total_price').text('0');
            }
        } else {
            $('#modal_staying_day').text('0');
            $('#modal_total_price').text('0');
        }
    };
    
    // ID card validation for modal
    window.validId_modal = function(id_card_id) {
        var idCardNoField = document.getElementById('modal_id_card_no');
        if (id_card_id == 1) {
            idCardNoField.setAttribute('type', 'number');
            idCardNoField.setAttribute('data-minlength', '12');
            idCardNoField.setAttribute('placeholder', '647510001480');
            idCardNoField.setAttribute('data-error', 'Enter 12 Digit Valid National Identity Card No');
        } else if (id_card_id == 2) {
            idCardNoField.setAttribute('type', 'text');
            idCardNoField.setAttribute('data-minlength', '11');
            idCardNoField.setAttribute('placeholder', 'COA/2635100');
            idCardNoField.setAttribute('data-error', 'Enter 11 Character(include \'/\') Valid Voter ID Card No');
        } else if (id_card_id == 3) {
            idCardNoField.setAttribute('type', 'text');
            idCardNoField.setAttribute('data-minlength', '10');
            idCardNoField.setAttribute('placeholder', 'RKCS17878A');
            idCardNoField.setAttribute('data-error', 'Enter 10 Character Valid Pan Card No');
        } else if (id_card_id == 4) {
            idCardNoField.setAttribute('type', 'text');
            idCardNoField.setAttribute('data-minlength', '16');
            idCardNoField.setAttribute('placeholder', 'RJ29 20210040869');
            idCardNoField.setAttribute('data-error', 'Enter 16 Character(include space) Valid Licence Number');
        }
    };
    
    // Number format helper
    window.number_format = function(number) {
        return parseFloat(number).toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    };
    }
    initReservation();
})();
</script>
