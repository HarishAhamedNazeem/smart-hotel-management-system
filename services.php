<?php
// Get staff ID and branch ID if exists - use user_id from session for reliable matching
$staff_id = null;
$staff_branch_id = null;
$is_super_admin = hasRole('super_admin');

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $staffQuery = "SELECT staff_id, branch_id FROM staff WHERE user_id = ? LIMIT 1";
    $staffStmt = mysqli_prepare($connection, $staffQuery);
    if ($staffStmt) {
        mysqli_stmt_bind_param($staffStmt, "i", $user_id);
        mysqli_stmt_execute($staffStmt);
        $staffResult = mysqli_stmt_get_result($staffStmt);
        $staff = mysqli_fetch_assoc($staffResult);
        if ($staff) {
            $staff_id = $staff['staff_id'];
            $staff_branch_id = $staff['branch_id'];
        }
        mysqli_stmt_close($staffStmt);
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$assigned_only = isset($_GET['assigned_only']) && $_GET['assigned_only'] == '1';
?>
<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
        <ol class="breadcrumb">
            <li><a href="#">
                    <em class="fa fa-home"></em>
                </a></li>
            <li class="active">Service Requests</li>
        </ol>
    </div><!--/.row-->

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <em class="fa fa-bell"></em> Service Request Management
                    <div class="pull-right">
                        <?php if (hasPermission('service.assign')): ?>
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#assignTaskModal" style="border-radius: 4px; font-weight: 500; padding: 8px 16px;">
                            <em class="fa fa-user-plus"></em> Assign Task
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel-body">
                    <ul class="nav nav-tabs">
                        <li class="<?php echo $filter == 'all' ? 'active' : ''; ?>">
                            <a href="index.php?services&filter=all">All Requests</a>
                        </li>
                        <li class="<?php echo $filter == 'pending' ? 'active' : ''; ?>">
                            <a href="index.php?services&filter=pending">Pending</a>
                        </li>
                        <li class="<?php echo $filter == 'assigned' ? 'active' : ''; ?>">
                            <a href="index.php?services&filter=assigned">Assigned</a>
                        </li>
                        <li class="<?php echo $filter == 'in_progress' ? 'active' : ''; ?>">
                            <a href="index.php?services&filter=in_progress">In Progress</a>
                        </li>
                        <li class="<?php echo $filter == 'completed' ? 'active' : ''; ?>">
                            <a href="index.php?services&filter=completed">Completed</a>
                        </li>
                        <?php if ($staff_id): ?>
                        <li class="<?php echo $assigned_only ? 'active' : ''; ?>">
                            <a href="index.php?services&assigned_only=1">My Tasks</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <br>
                    
                    <?php
                    // Determine user role for filtering
                    $is_concierge = hasRole('concierge');
                    $is_housekeeping = hasRole('housekeeping_staff');
                    $is_admin = hasRole('super_admin') || hasRole('administrator');
                    
                    // Build query
                    $query = "SELECT sr.*, st.service_name, st.category,
                             g.name as customer_name, g.contact_no, g.email,
                             b.booking_id, r.room_no, rt.room_type, r.branch_id as request_branch_id,
                             s.staff_name as assigned_staff_name
                             FROM service_requests sr
                             JOIN service_types st ON sr.service_type_id = st.service_type_id
                             JOIN guests g ON sr.guest_id = g.guest_id
                             LEFT JOIN booking b ON sr.booking_id = b.booking_id
                             LEFT JOIN room r ON b.room_id = r.room_id
                             LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id
                             LEFT JOIN staff s ON sr.assigned_to = s.staff_id
                             WHERE 1=1";
                    
                    // CRITICAL: Filter by branch - staff only see requests from their branch
                    // Super admin sees all requests
                    if (!$is_super_admin && $staff_branch_id) {
                        $query .= " AND r.branch_id = " . intval($staff_branch_id);
                    }
                    
                    // Filter by role: Staff should only see requests for their category
                    // Concierge: transport, dining, concierge
                    // Housekeeping: housekeeping, maintenance, room_service
                    // Admins: see all
                    if (!$is_admin && $staff_id) {
                        if ($is_concierge) {
                            // Concierge only sees transport, dining, and concierge requests
                            $query .= " AND st.category IN ('transport', 'dining', 'concierge')";
                        } elseif ($is_housekeeping) {
                            // Housekeeping only sees housekeeping, maintenance, and room_service requests
                            $query .= " AND st.category IN ('housekeeping', 'maintenance', 'room_service')";
                        }
                    }
                    
                    if ($filter == 'pending') {
                        $query .= " AND sr.status = 'pending'";
                    } elseif ($filter == 'assigned') {
                        $query .= " AND sr.status = 'assigned'";
                    } elseif ($filter == 'in_progress') {
                        $query .= " AND sr.status = 'in_progress'";
                    } elseif ($filter == 'completed') {
                        $query .= " AND sr.status = 'completed'";
                    }
                    
                    if ($assigned_only && $staff_id) {
                        // "My Tasks": Show all tasks assigned to this staff member (assigned, in_progress, completed)
                        $query .= " AND sr.assigned_to = " . intval($staff_id);
                    }
                    
                    $query .= " ORDER BY 
                        CASE sr.priority 
                            WHEN 'urgent' THEN 1 
                            WHEN 'high' THEN 2 
                            WHEN 'normal' THEN 3 
                            WHEN 'low' THEN 4 
                        END,
                        sr.requested_at DESC";
                    
                    $result = mysqli_query($connection, $query);
                    
                    if (mysqli_num_rows($result) > 0) {
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-bordered table-hover">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th>Request ID</th>';
                        echo '<th>Service</th>';
                        echo '<th>Customer</th>';
                        echo '<th>Room</th>';
                        echo '<th>Priority</th>';
                        echo '<th>Status</th>';
                        echo '<th>Assigned To</th>';
                        echo '<th>Requested</th>';
                        echo '<th>Actions</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';
                        
                        while ($request = mysqli_fetch_assoc($result)) {
                            // Status badge
                            $statusBadges = [
                                'pending' => '<span class="label label-warning">Pending</span>',
                                'assigned' => '<span class="label label-info">Assigned</span>',
                                'in_progress' => '<span class="label label-primary">In Progress</span>',
                                'completed' => '<span class="label label-success">Completed</span>',
                                'cancelled' => '<span class="label label-danger">Cancelled</span>'
                            ];
                            
                            // Priority badge
                            $priorityBadges = [
                                'low' => '<span class="label label-default">Low</span>',
                                'normal' => '<span class="label label-info">Normal</span>',
                                'high' => '<span class="label label-warning">High</span>',
                                'urgent' => '<span class="label label-danger">Urgent</span>'
                            ];
                            
                            $statusBadge = $statusBadges[$request['status']] ?? '<span class="label">' . $request['status'] . '</span>';
                            $priorityBadge = $priorityBadges[$request['priority']] ?? '<span class="label">' . $request['priority'] . '</span>';
                            
                            $roomInfo = $request['room_no'] ? 
                                $request['room_type'] . ' - ' . $request['room_no'] : '-';
                            
                            echo '<tr>';
                            echo '<td>#' . $request['request_id'] . '</td>';
                            echo '<td>' . htmlspecialchars($request['service_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['customer_name']) . '<br>';
                            echo '<small>' . htmlspecialchars($request['contact_no']) . '</small></td>';
                            echo '<td>' . htmlspecialchars($roomInfo) . '</td>';
                            echo '<td>' . $priorityBadge . '</td>';
                            echo '<td>' . $statusBadge . '</td>';
                            echo '<td>' . ($request['assigned_staff_name'] ? htmlspecialchars($request['assigned_staff_name']) : '<em class="text-muted">Unassigned</em>') . '</td>';
                            echo '<td>' . date('M j, Y g:i A', strtotime($request['requested_at'])) . '</td>';
                            echo '<td>';
                            echo '<button class="btn btn-info btn-sm view-request" 
                                    data-request-id="' . $request['request_id'] . '">
                                    <em class="fa fa-eye"></em> View
                                  </button> ';
                            
                            if (hasPermission('service.assign') && $request['status'] == 'pending') {
                                echo '<button class="btn btn-success btn-sm assign-request" 
                                        data-request-id="' . $request['request_id'] . '">
                                        <em class="fa fa-user-plus"></em> Assign
                                      </button> ';
                            }
                            
                            if ($request['status'] == 'assigned' || $request['status'] == 'in_progress') {
                                if ($request['assigned_to'] == $staff_id || hasPermission('service.update')) {
                                    echo '<button class="btn btn-primary btn-sm update-status" 
                                            data-request-id="' . $request['request_id'] . '"
                                            data-current-status="' . $request['status'] . '">
                                            <em class="fa fa-edit"></em> Update
                                          </button> ';
                                }
                            }
                            
                            if (hasPermission('service.complete') && $request['status'] != 'completed' && $request['status'] != 'cancelled') {
                                echo '<button class="btn btn-success btn-sm complete-request" 
                                        data-request-id="' . $request['request_id'] . '">
                                        <em class="fa fa-check"></em> Complete
                                      </button>';
                            }
                            
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';
                    } else {
                        echo '<div class="alert alert-info">';
                        echo '<h4>No service requests found</h4>';
                        echo '<p>No ' . ($filter == 'all' ? '' : $filter) . ' service requests at this time.</p>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div id="requestDetailsModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><em class="fa fa-bell"></em> Service Request Details</h4>
                </div>
                <div class="modal-body" id="requestDetailsContent">
                    <p class="text-center"><em class="fa fa-spinner fa-spin fa-2x"></em> Loading...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 4px;">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Request Modal -->
    <div id="assignModal" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><em class="fa fa-user-plus"></em> Assign Service Request</h4>
                </div>
                <div class="modal-body">
                    <form id="assignForm">
                        <input type="hidden" name="action" value="assign_service_request">
                        <input type="hidden" name="request_id" id="assign_request_id">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-group">
                            <label>Assign To Staff <span class="text-danger">*</span></label>
                            <select name="staff_id" id="assign_staff_id" class="form-control" required>
                                <option value="">Select staff member...</option>
                                <?php
                                $staffQuery = "SELECT staff_id, staff_name, staff_type_id FROM staff ORDER BY staff_name";
                                $staffResult = mysqli_query($connection, $staffQuery);
                                while ($staffMember = mysqli_fetch_assoc($staffResult)) {
                                    echo '<option value="' . $staffMember['staff_id'] . '">' . 
                                         htmlspecialchars($staffMember['staff_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="response"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmAssign" style="border-radius: 4px; font-weight: 500;">
                        <em class="fa fa-user-plus"></em> Assign
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><em class="fa fa-edit"></em> Update Request Status</h4>
                </div>
                <div class="modal-body">
                    <form id="updateStatusForm">
                        <input type="hidden" name="action" value="update_service_status">
                        <input type="hidden" name="request_id" id="update_request_id">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-group">
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" id="update_status" class="form-control" required>
                                <option value="assigned">Assigned</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Add any notes or comments..."></textarea>
                        </div>
                        
                        <div class="response"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 4px;">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmUpdate">Update</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Task Modal (Select Request and Assign) -->
    <div id="assignTaskModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><em class="fa fa-user-plus"></em> Assign Task</h4>
                </div>
                <div class="modal-body">
                    <div class="assign-task-response"></div>
                    <form id="assignTaskForm">
                        <input type="hidden" name="action" value="assign_service_request">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-group">
                            <label>Select Service Request <span class="text-danger">*</span></label>
                            <select name="request_id" id="assign_task_request_id" class="form-control" required>
                                <option value="">Loading service requests...</option>
                            </select>
                            <small class="help-block">Only pending service requests are shown</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Assign To Staff <span class="text-danger">*</span></label>
                            <select name="staff_id" id="assign_task_staff_id" class="form-control" required>
                                <option value="">Select staff member...</option>
                                <?php
                                $staffQuery = "SELECT staff_id, staff_name, staff_type_id FROM staff ORDER BY staff_name";
                                $staffResult = mysqli_query($connection, $staffQuery);
                                while ($staffMember = mysqli_fetch_assoc($staffResult)) {
                                    echo '<option value="' . $staffMember['staff_id'] . '">' . 
                                         htmlspecialchars($staffMember['staff_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmAssignTask" style="border-radius: 4px; font-weight: 500;">
                        <em class="fa fa-save"></em> Assign Task
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for jQuery to be loaded (from footer.php)
(function() {
    function initServices() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initServices, 50);
            return;
        }
        
        var $ = jQuery;
        
        $(document).ready(function() {
            // View request details
            $('.view-request').on('click', function() {
            var requestId = $(this).data('request-id');
            loadRequestDetails(requestId);
        });
        
        // Assign request
        $('.assign-request').on('click', function() {
            var requestId = $(this).data('request-id');
            $('#assign_request_id').val(requestId);
            $('#assignModal').modal('show');
        });
        
        // Update status
        $('.update-status').on('click', function() {
            var requestId = $(this).data('request-id');
            var currentStatus = $(this).data('current-status');
            $('#update_request_id').val(requestId);
            $('#update_status').val(currentStatus);
            $('#updateStatusModal').modal('show');
        });
        
        // Complete request
        $('.complete-request').on('click', function() {
            if (!confirm('Mark this service request as completed?')) {
                return;
            }
            
            var requestId = $(this).data('request-id');
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: {
                    action: 'complete_service_request',
                    request_id: requestId,
                    csrf_token: '<?php echo generateCSRFToken(); ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Service request marked as completed.');
                        location.reload();
                    } else {
                        alert(response.message || 'Error completing request.');
                    }
                }
            });
        });
        
        // Confirm assign
        $('#confirmAssign').on('click', function() {
            var formData = $('#assignForm').serialize();
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Service request assigned successfully.');
                        location.reload();
                    } else {
                        $('#assignModal .response').html('<div class="alert alert-danger">' + (response.message || response.data) + '</div>');
                    }
                },
                error: function() {
                    $('#assignModal .response').html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
                }
            });
        });
        
        // Confirm assign task (from Assign Task button)
        $('#confirmAssignTask').on('click', function() {
            var requestId = $('#assign_task_request_id').val();
            var staffId = $('#assign_task_staff_id').val();
            var responseDiv = $('.assign-task-response');
            
            if (!requestId || !staffId) {
                responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Please select both a service request and staff member.</div>');
                return;
            }
            
            var formData = {
                action: 'assign_service_request',
                request_id: requestId,
                staff_id: staffId,
                csrf_token: $('input[name="csrf_token"]').val()
            };
            
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        responseDiv.html('<div class="alert alert-success"><em class="fa fa-check-circle"></em> Task assigned successfully!</div>');
                        
                        // Reset form
                        $('#assignTaskForm')[0].reset();
                        
                        // Reload page after 2 seconds
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + (response.message || response.data || 'Error assigning task. Please try again.') + '</div>');
                    }
                },
                error: function() {
                    responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> An error occurred. Please try again.</div>');
                }
            });
        });
        
        // Load service requests when assign task modal is shown
        $('#assignTaskModal').on('show.bs.modal', function() {
            loadPendingServiceRequests();
        });
        
        // Also load when modal is fully shown (backup trigger)
        $('#assignTaskModal').on('shown.bs.modal', function() {
            // Double-check if dropdown is still empty or has only loading message
            var select = $('#assign_task_request_id');
            var currentVal = select.val();
            var options = select.find('option').length;
            if (options <= 1 || (currentVal === '' && options === 1)) {
                loadPendingServiceRequests();
            }
        });
        
        // Reset form when assign task modal is closed
        $('#assignTaskModal').on('hidden.bs.modal', function() {
            $('#assignTaskForm')[0].reset();
            $('.assign-task-response').html('');
            $('#assign_task_request_id').html('<option value="">Loading service requests...</option>');
        });
        
        // Function to load pending service requests
        function loadPendingServiceRequests() {
            var select = $('#assign_task_request_id');
            if (select.length === 0) {
                return; // Element doesn't exist
            }
            
            select.html('<option value="">Loading...</option>');
            
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: {
                    action: 'get_pending_service_requests'
                },
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        if (response.requests && response.requests.length > 0) {
                            select.html('<option value="">Select a pending service request...</option>');
                            $.each(response.requests, function(index, request) {
                                var displayText = '#' + request.request_id + ' - ' + request.service_name + 
                                                ' (' + request.customer_name + ') - ' + request.room_info;
                                select.append('<option value="' + request.request_id + '">' + displayText + '</option>');
                            });
                        } else {
                            select.html('<option value="" disabled>No pending requests available</option>');
                        }
                    } else {
                        select.html('<option value="" disabled>Error: ' + (response.message || 'Failed to load service requests') + '</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading service requests:', error, xhr.responseText);
                    var errorMessage = 'Error loading service requests. Please try again.';
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMessage = response.message;
                        }
                    } catch (e) {
                        // Use default error message
                    }
                    select.html('<option value="" disabled>Error: ' + errorMessage + '</option>');
                }
            });
        }
        
        // Confirm update
        $('#confirmUpdate').on('click', function() {
            var formData = $('#updateStatusForm').serialize();
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Status updated successfully.');
                        location.reload();
                    } else {
                        $('.response').html('<div class="alert alert-danger">' + (response.message || response.data) + '</div>');
                    }
                }
            });
        });
        
        function loadRequestDetails(requestId) {
            $('#requestDetailsModal').modal('show');
            $('#requestDetailsContent').html('<p class="text-center"><em class="fa fa-spinner fa-spin fa-2x"></em> Loading...</p>');
            
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: {
                    action: 'get_service_request_details',
                    request_id: requestId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var html = buildRequestDetailsHTML(response);
                        $('#requestDetailsContent').html(html);
                    } else {
                        $('#requestDetailsContent').html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                }
            });
        }
        
        function buildRequestDetailsHTML(response) {
            var html = '<div class="row">';
            html += '<div class="col-md-6"><strong>Request ID:</strong></div>';
            html += '<div class="col-md-6">#' + response.request_id + '</div>';
            html += '</div><hr>';
            html += '<div class="row">';
            html += '<div class="col-md-6"><strong>Service:</strong></div>';
            html += '<div class="col-md-6">' + response.service_name + '</div>';
            html += '</div><hr>';
            html += '<div class="row">';
            html += '<div class="col-md-6"><strong>Title:</strong></div>';
            html += '<div class="col-md-6">' + response.request_title + '</div>';
            html += '</div><hr>';
            html += '<div class="row">';
            html += '<div class="col-md-6"><strong>Description:</strong></div>';
            html += '<div class="col-md-6">' + response.request_description + '</div>';
            html += '</div><hr>';
            html += '<div class="row">';
            html += '<div class="col-md-6"><strong>Priority:</strong></div>';
            html += '<div class="col-md-6">' + response.priority + '</div>';
            html += '</div><hr>';
            html += '<div class="row">';
            html += '<div class="col-md-6"><strong>Status:</strong></div>';
            html += '<div class="col-md-6">' + response.status + '</div>';
            html += '</div><hr>';
            html += '<div class="row">';
            html += '<div class="col-md-6"><strong>Customer:</strong></div>';
            html += '<div class="col-md-6">' + response.customer_name + '</div>';
            html += '</div><hr>';
            html += '<div class="row">';
            html += '<div class="col-md-6"><strong>Requested:</strong></div>';
            html += '<div class="col-md-6">' + response.requested_at + '</div>';
            html += '</div>';
            if (response.assigned_staff) {
                html += '<hr><div class="row">';
                html += '<div class="col-md-6"><strong>Assigned To:</strong></div>';
                html += '<div class="col-md-6">' + response.assigned_staff + '</div>';
                html += '</div>';
            }
            if (response.notes) {
                html += '<hr><div class="row">';
                html += '<div class="col-md-6"><strong>Notes:</strong></div>';
                html += '<div class="col-md-6">' + response.notes + '</div>';
                html += '</div>';
            }
            return html;
        }
        });
    }
    initServices();
})();
</script>
