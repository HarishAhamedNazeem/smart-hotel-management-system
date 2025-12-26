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

// Get all branches for dropdown (super admin only)
$branches = [];
if (hasRole('super_admin')) {
    $branchesQuery = "SELECT branch_id, branch_name, branch_code FROM branches WHERE status = 'active' ORDER BY branch_name";
    $branchesResult = mysqli_query($connection, $branchesQuery);
    while ($branch = mysqli_fetch_assoc($branchesResult)) {
        $branches[] = $branch;
    }
}

// Get facilities with branch filtering
$facilitiesQuery = "SELECT f.*, b.branch_name, b.branch_code,
                    (SELECT COUNT(*) FROM facility_bookings fb 
                     WHERE fb.facility_id = f.facility_id 
                     AND fb.booking_date >= CURDATE() 
                     AND fb.status IN ('pending', 'confirmed')) as upcoming_bookings
                    FROM facilities f
                    LEFT JOIN branches b ON f.branch_id = b.branch_id
                    WHERE 1=1";

if ($userBranchId && !hasRole('super_admin')) {
    // Branch admin - only see their branch facilities
    $facilitiesQuery .= " AND f.branch_id = " . intval($userBranchId);
}

$facilitiesQuery .= " ORDER BY b.branch_name, f.facility_name";
$facilitiesResult = mysqli_query($connection, $facilitiesQuery);

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
        <ol class="breadcrumb">
            <li><a href="#">
                    <em class="fa fa-home"></em>
                </a></li>
            <li class="active">Facility Management</li>
        </ol>
    </div><!--/.row-->

    <br>

    <div class="row">
        <div class="col-lg-12">
            <div id="facilities-response"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <em class="fa fa-building-o"></em> Facility Management
                    <div class="pull-right">
                        <?php if (hasPermission('facility.create') || hasRole('super_admin') || hasRole('administrator')): ?>
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addFacilityModal" style="border-radius: 4px; font-weight: 500;">
                            <em class="fa fa-plus"></em> Add Facility
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="facilitiesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Facility Name</th>
                                    <th>Type</th>
                                    <th>Capacity</th>
                                    <th>Hourly Rate</th>
                                    <th>Branch</th>
                                    <th>Upcoming</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (mysqli_num_rows($facilitiesResult) > 0) {
                                    while ($facility = mysqli_fetch_assoc($facilitiesResult)) {
                                        $statusBadge = $facility['status'] == 'active' 
                                            ? '<span class="label label-success">Active</span>' 
                                            : '<span class="label label-danger">Inactive</span>';
                                        
                                        $branchText = $facility['branch_id'] 
                                            ? htmlspecialchars($facility['branch_name'] . ' (' . $facility['branch_code'] . ')')
                                            : '<span class="text-muted">No Branch</span>';
                                        
                                        $typeLabels = [
                                            'event_hall' => '<span class="label label-primary">Event Hall</span>',
                                            'conference_room' => '<span class="label label-info">Conference Room</span>',
                                            'banquet_hall' => '<span class="label label-success">Banquet Hall</span>',
                                            'meeting_room' => '<span class="label label-warning">Meeting Room</span>',
                                            'recreation' => '<span class="label label-default">Recreation</span>',
                                            'other' => '<span class="label">Other</span>'
                                        ];
                                        
                                        $typeBadge = $typeLabels[$facility['facility_type']] ?? $facility['facility_type'];
                                        
                                        echo '<tr>';
                                        echo '<td>#' . $facility['facility_id'] . '</td>';
                                        echo '<td><strong>' . htmlspecialchars($facility['facility_name']) . '</strong></td>';
                                        echo '<td>' . $typeBadge . '</td>';
                                        echo '<td><em class="fa fa-users"></em> ' . $facility['capacity'] . ' people</td>';
                                        echo '<td><strong>LKR ' . number_format($facility['hourly_rate'], 2) . '</strong>/hr</td>';
                                        echo '<td>' . $branchText . '</td>';
                                        echo '<td><span class="badge badge-info">' . $facility['upcoming_bookings'] . '</span> bookings</td>';
                                        echo '<td>' . $statusBadge . '</td>';
                                        echo '<td>';
                                        
                                        if (hasPermission('facility.update') || hasRole('super_admin') || hasRole('administrator')) {
                                            echo '<button class="btn btn-info btn-sm edit-facility" 
                                                    data-facility-id="' . $facility['facility_id'] . '"
                                                    data-facility-name="' . htmlspecialchars($facility['facility_name']) . '"
                                                    data-facility-type="' . $facility['facility_type'] . '"
                                                    data-description="' . htmlspecialchars($facility['description'] ?? '') . '"
                                                    data-capacity="' . $facility['capacity'] . '"
                                                    data-hourly-rate="' . $facility['hourly_rate'] . '"
                                                    data-full-day-rate="' . $facility['full_day_rate'] . '"
                                                    data-features="' . htmlspecialchars($facility['features'] ?? '') . '"
                                                    data-branch-id="' . ($facility['branch_id'] ?? '') . '"
                                                    data-status="' . $facility['status'] . '"
                                                    style="margin-right: 5px;">
                                                    <em class="fa fa-edit"></em> Edit
                                                  </button> ';
                                            
                                            echo '<a href="index.php?facility_bookings&facility_id=' . $facility['facility_id'] . '" class="btn btn-success btn-sm" style="margin-right: 5px;">
                                                    <em class="fa fa-calendar"></em> Bookings
                                                  </a>';
                                            
                                            if (hasPermission('facility.delete') || hasRole('super_admin')) {
                                                echo '<button class="btn btn-danger btn-sm delete-facility" 
                                                        data-facility-id="' . $facility['facility_id'] . '"
                                                        data-facility-name="' . htmlspecialchars($facility['facility_name']) . '">
                                                        <em class="fa fa-trash"></em> Delete
                                                      </button>';
                                            }
                                        }
                                        
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="9" class="text-center">No facilities found. Add your first facility to get started!</td></tr>';
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

<!-- Add Facility Modal -->
<div class="modal fade" id="addFacilityModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><em class="fa fa-plus"></em> Add New Facility</h4>
            </div>
            <form id="addFacilityForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Facility Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="facility_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Facility Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="facility_type" required>
                                    <option value="">Select Type</option>
                                    <option value="event_hall">Event Hall</option>
                                    <option value="conference_room">Conference Room</option>
                                    <option value="banquet_hall">Banquet Hall</option>
                                    <option value="meeting_room">Meeting Room</option>
                                    <option value="recreation">Recreation Area</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Capacity (People) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="capacity" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Hourly Rate (LKR) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="hourly_rate" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Full Day Rate (LKR)</label>
                                <input type="number" class="form-control" name="full_day_rate" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Features (e.g., Projector, WiFi, AC)</label>
                        <input type="text" class="form-control" name="features" placeholder="Comma-separated list">
                        <small class="text-muted">Example: Projector, WiFi, Air Conditioning, Sound System</small>
                    </div>
                    
                    <?php if (hasRole('super_admin')): ?>
                    <div class="form-group">
                        <label>Branch</label>
                        <select class="form-control" name="branch_id">
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['branch_id']; ?>">
                                <?php echo htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><em class="fa fa-save"></em> Save Facility</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Facility Modal -->
<div class="modal fade" id="editFacilityModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><em class="fa fa-edit"></em> Edit Facility</h4>
            </div>
            <form id="editFacilityForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="facility_id" id="edit_facility_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Facility Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="facility_name" id="edit_facility_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Facility Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="facility_type" id="edit_facility_type" required>
                                    <option value="">Select Type</option>
                                    <option value="event_hall">Event Hall</option>
                                    <option value="conference_room">Conference Room</option>
                                    <option value="banquet_hall">Banquet Hall</option>
                                    <option value="meeting_room">Meeting Room</option>
                                    <option value="recreation">Recreation Area</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Capacity (People) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="capacity" id="edit_capacity" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Hourly Rate (LKR) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="hourly_rate" id="edit_hourly_rate" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Full Day Rate (LKR)</label>
                                <input type="number" class="form-control" name="full_day_rate" id="edit_full_day_rate" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Features</label>
                        <input type="text" class="form-control" name="features" id="edit_features" placeholder="Comma-separated list">
                    </div>
                    
                    <?php if (hasRole('super_admin')): ?>
                    <div class="form-group">
                        <label>Branch</label>
                        <select class="form-control" name="branch_id" id="edit_branch_id">
                            <option value="">Select Branch</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['branch_id']; ?>">
                                <?php echo htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Status</label>
                        <select class="form-control" name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><em class="fa fa-save"></em> Update Facility</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Add Facility
    $('#addFacilityForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            data: $(this).serialize() + '&action=add_facility',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#facilities-response').html('<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + response.message + '</div>');
                    $('#addFacilityModal').modal('hide');
                    $('#addFacilityForm')[0].reset();
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $('#facilities-response').html('<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + response.message + '</div>');
                }
            },
            error: function() {
                $('#facilities-response').html('<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>An error occurred. Please try again.</div>');
            }
        });
    });
    
    // Edit Facility - Populate modal
    $('.edit-facility').click(function() {
        $('#edit_facility_id').val($(this).data('facility-id'));
        $('#edit_facility_name').val($(this).data('facility-name'));
        $('#edit_facility_type').val($(this).data('facility-type'));
        $('#edit_description').val($(this).data('description'));
        $('#edit_capacity').val($(this).data('capacity'));
        $('#edit_hourly_rate').val($(this).data('hourly-rate'));
        $('#edit_full_day_rate').val($(this).data('full-day-rate'));
        $('#edit_features').val($(this).data('features'));
        $('#edit_branch_id').val($(this).data('branch-id'));
        $('#edit_status').val($(this).data('status'));
        $('#editFacilityModal').modal('show');
    });
    
    // Update Facility
    $('#editFacilityForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            data: $(this).serialize() + '&action=update_facility',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#facilities-response').html('<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + response.message + '</div>');
                    $('#editFacilityModal').modal('hide');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $('#facilities-response').html('<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + response.message + '</div>');
                }
            },
            error: function() {
                $('#facilities-response').html('<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>An error occurred. Please try again.</div>');
            }
        });
    });
    
    // Delete Facility
    $('.delete-facility').click(function() {
        var facilityId = $(this).data('facility-id');
        var facilityName = $(this).data('facility-name');
        
        if (confirm('Are you sure you want to delete "' + facilityName + '"? This action cannot be undone.')) {
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: {
                    action: 'delete_facility',
                    facility_id: facilityId,
                    csrf_token: '<?php echo $csrf_token; ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#facilities-response').html('<div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + response.message + '</div>');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $('#facilities-response').html('<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>' + response.message + '</div>');
                    }
                },
                error: function() {
                    $('#facilities-response').html('<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert">&times;</button>An error occurred. Please try again.</div>');
                }
            });
        }
    });
});
</script>
