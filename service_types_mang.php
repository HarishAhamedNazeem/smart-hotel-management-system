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

// Get all branches for dropdown (super admin only)
$branches = [];
if (hasRole('super_admin')) {
    $branchesQuery = "SELECT branch_id, branch_name, branch_code FROM branches WHERE status = 'active' ORDER BY branch_name";
    $branchesResult = mysqli_query($connection, $branchesQuery);
    while ($branch = mysqli_fetch_assoc($branchesResult)) {
        $branches[] = $branch;
    }
}

// Get service types with branch filtering
$serviceTypesQuery = "SELECT st.*, b.branch_name, b.branch_code 
                      FROM service_types st
                      LEFT JOIN branches b ON st.branch_id = b.branch_id
                      WHERE 1=1";

if ($userBranchId && !hasRole('super_admin')) {
    // Branch admin - only see their branch services and global services
    $serviceTypesQuery .= " AND (st.branch_id = " . intval($userBranchId) . " OR st.branch_id IS NULL)";
}

$serviceTypesQuery .= " ORDER BY st.branch_id IS NULL DESC, b.branch_name, st.service_name";
$serviceTypesResult = mysqli_query($connection, $serviceTypesQuery);

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
        <ol class="breadcrumb">
            <li><a href="#">
                    <em class="fa fa-home"></em>
                </a></li>
            <li class="active">Service Types Management</li>
        </ol>
    </div><!--/.row-->

    <br>

    <div class="row">
        <div class="col-lg-12">
            <div id="service-types-response"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <em class="fa fa-cog"></em> Service Types Management
                    <div class="pull-right">
                        <?php if (hasPermission('service.assign') || hasRole('super_admin') || hasRole('administrator')): ?>
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addServiceTypeModal" style="border-radius: 4px; font-weight: 500;">
                            <em class="fa fa-plus"></em> Add Service Type
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="serviceTypesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Service Name</th>
                                    <th>Description</th>
                                    <th>Category</th>
                                    <th>Branch</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (mysqli_num_rows($serviceTypesResult) > 0) {
                                    while ($serviceType = mysqli_fetch_assoc($serviceTypesResult)) {
                                        $statusBadge = $serviceType['is_active'] == 1 
                                            ? '<span class="label label-success">Active</span>' 
                                            : '<span class="label label-danger">Inactive</span>';
                                        
                                        $branchText = $serviceType['branch_id'] 
                                            ? htmlspecialchars($serviceType['branch_name'] . ' (' . $serviceType['branch_code'] . ')')
                                            : '<span class="text-info"><em class="fa fa-globe"></em> All Branches</span>';
                                        
                                        $categoryLabels = [
                                            'room_service' => '<span class="label label-primary">Room Service</span>',
                                            'housekeeping' => '<span class="label label-info">Housekeeping</span>',
                                            'maintenance' => '<span class="label label-warning">Maintenance</span>',
                                            'dining' => '<span class="label label-success">Dining</span>',
                                            'transport' => '<span class="label label-default">Transport</span>',
                                            'concierge' => '<span class="label label-default">Concierge</span>',
                                            'other' => '<span class="label">Other</span>'
                                        ];
                                        
                                        $categoryBadge = $categoryLabels[$serviceType['category']] ?? $serviceType['category'];
                                        
                                        echo '<tr>';
                                        echo '<td>#' . $serviceType['service_type_id'] . '</td>';
                                        echo '<td><strong>' . htmlspecialchars($serviceType['service_name']) . '</strong></td>';
                                        echo '<td>' . htmlspecialchars(substr($serviceType['service_description'] ?? '', 0, 50)) . 
                                             (strlen($serviceType['service_description'] ?? '') > 50 ? '...' : '') . '</td>';
                                        echo '<td>' . $categoryBadge . '</td>';
                                        echo '<td>' . $branchText . '</td>';
                                        echo '<td>' . $statusBadge . '</td>';
                                        echo '<td>';
                                        
                                        if (hasPermission('service.assign') || hasRole('super_admin') || hasRole('administrator')) {
                                            echo '<button class="btn btn-info btn-sm edit-service-type" 
                                                    data-service-type-id="' . $serviceType['service_type_id'] . '"
                                                    data-service-name="' . htmlspecialchars($serviceType['service_name']) . '"
                                                    data-service-description="' . htmlspecialchars($serviceType['service_description'] ?? '') . '"
                                                    data-category="' . $serviceType['category'] . '"
                                                    data-is-active="' . $serviceType['is_active'] . '"
                                                    data-branch-id="' . ($serviceType['branch_id'] ?? '') . '"
                                                    style="margin-right: 5px;">
                                                    <em class="fa fa-edit"></em> Edit
                                                  </button> ';
                                            
                                            echo '<button class="btn btn-danger btn-sm delete-service-type" 
                                                    data-service-type-id="' . $serviceType['service_type_id'] . '"
                                                    data-service-name="' . htmlspecialchars($serviceType['service_name']) . '">
                                                    <em class="fa fa-trash"></em> Delete
                                                  </button>';
                                        }
                                        
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="7" class="text-center">No service types found.</td></tr>';
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

<!-- Add Service Type Modal -->
<div id="addServiceTypeModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-plus"></em> Add New Service Type</h4>
            </div>
            <div class="modal-body">
                <div class="add-service-type-response"></div>
                <form id="addServiceTypeForm">
                    <input type="hidden" name="action" value="add_service_type">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label>Service Name <span class="text-danger">*</span></label>
                        <input type="text" name="service_name" id="add_service_name" class="form-control" 
                               placeholder="e.g., Room Cleaning" required maxlength="100">
                        <small class="help-block">Service name must be unique within the branch (or globally if no branch selected)</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="service_description" id="add_service_description" class="form-control" 
                                  rows="3" placeholder="Describe the service..."></textarea>
                    </div>
                    
                            <div class="form-group">
                                <label>Category <span class="text-danger">*</span></label>
                                <select name="category" id="add_category" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <option value="room_service">Room Service</option>
                                    <option value="housekeeping">Housekeeping</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="dining">Dining</option>
                                    <option value="transport">Transport</option>
                                    <option value="concierge">Concierge</option>
                                    <option value="other">Other</option>
                                </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Branch</label>
                        <select name="branch_id" id="add_branch_id" class="form-control">
                            <option value="">All Branches (Global Service)</option>
                            <?php
                            if (hasRole('super_admin')) {
                                // Super admin can assign to any branch or make it global
                                foreach ($branches as $branch) {
                                    echo '<option value="' . $branch['branch_id'] . '">' . 
                                         htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')') . 
                                         '</option>';
                                }
                            } elseif ($userBranchId) {
                                // Branch admin - show their branch only
                                $branchQuery = "SELECT branch_id, branch_name, branch_code FROM branches WHERE branch_id = ?";
                                $branchStmt = mysqli_prepare($connection, $branchQuery);
                                mysqli_stmt_bind_param($branchStmt, "i", $userBranchId);
                                mysqli_stmt_execute($branchStmt);
                                $branchResult = mysqli_stmt_get_result($branchStmt);
                                if ($branch = mysqli_fetch_assoc($branchResult)) {
                                    echo '<option value="' . $branch['branch_id'] . '">' . 
                                         htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')') . 
                                         '</option>';
                                }
                                mysqli_stmt_close($branchStmt);
                            }
                            ?>
                        </select>
                        <small class="help-block">
                            <?php if (hasRole('super_admin')): ?>
                                Leave empty to make this service available for all branches (global service)
                            <?php else: ?>
                                Service will be assigned to your branch
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" id="add_is_active" value="1" checked> 
                            Active
                        </label>
                        <small class="help-block">Inactive services won't appear in service request forms</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveServiceType" style="border-radius: 4px; font-weight: 500;">
                    <em class="fa fa-save"></em> Save Service Type
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Service Type Modal -->
<div id="editServiceTypeModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-edit"></em> Edit Service Type</h4>
            </div>
            <div class="modal-body">
                <div class="edit-service-type-response"></div>
                <form id="editServiceTypeForm">
                    <input type="hidden" name="action" value="update_service_type">
                    <input type="hidden" name="service_type_id" id="edit_service_type_id">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label>Service Name <span class="text-danger">*</span></label>
                        <input type="text" name="service_name" id="edit_service_name" class="form-control" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="service_description" id="edit_service_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                            <div class="form-group">
                                <label>Category <span class="text-danger">*</span></label>
                                <select name="category" id="edit_category" class="form-control" required>
                                    <option value="room_service">Room Service</option>
                                    <option value="housekeeping">Housekeeping</option>
                                    <option value="maintenance">Maintenance</option>
                                    <option value="dining">Dining</option>
                                    <option value="transport">Transport</option>
                                    <option value="concierge">Concierge</option>
                                    <option value="other">Other</option>
                                </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Branch</label>
                        <select name="branch_id" id="edit_branch_id" class="form-control">
                            <option value="">All Branches (Global Service)</option>
                            <?php
                            if (hasRole('super_admin')) {
                                foreach ($branches as $branch) {
                                    echo '<option value="' . $branch['branch_id'] . '">' . 
                                         htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')') . 
                                         '</option>';
                                }
                            } elseif ($userBranchId) {
                                $branchQuery = "SELECT branch_id, branch_name, branch_code FROM branches WHERE branch_id = ?";
                                $branchStmt = mysqli_prepare($connection, $branchQuery);
                                mysqli_stmt_bind_param($branchStmt, "i", $userBranchId);
                                mysqli_stmt_execute($branchStmt);
                                $branchResult = mysqli_stmt_get_result($branchStmt);
                                if ($branch = mysqli_fetch_assoc($branchResult)) {
                                    echo '<option value="' . $branch['branch_id'] . '">' . 
                                         htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')') . 
                                         '</option>';
                                }
                                mysqli_stmt_close($branchStmt);
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1"> 
                            Active
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="updateServiceType" style="border-radius: 4px; font-weight: 500;">
                    <em class="fa fa-save"></em> Update Service Type
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function initServiceTypes() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initServiceTypes, 50);
            return;
        }
        
        var $ = jQuery;
        
        $(document).ready(function() {
            // Initialize DataTable
            if ($.fn.DataTable) {
                $('#serviceTypesTable').DataTable({
                    "pageLength": 25,
                    "order": [[0, "desc"]]
                });
            }
            
            // Save new service type
            $('#saveServiceType').on('click', function() {
                var formData = $('#addServiceTypeForm').serialize();
                var responseDiv = $('.add-service-type-response');
                
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            responseDiv.html('<div class="alert alert-success"><em class="fa fa-check-circle"></em> ' + response.message + '</div>');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + (response.message || 'Error adding service type') + '</div>');
                        }
                    },
                    error: function() {
                        responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> An error occurred. Please try again.</div>');
                    }
                });
            });
            
            // Edit service type
            $('.edit-service-type').on('click', function() {
                $('#edit_service_type_id').val($(this).data('service-type-id'));
                $('#edit_service_name').val($(this).data('service-name'));
                $('#edit_service_description').val($(this).data('service-description'));
                $('#edit_category').val($(this).data('category'));
                $('#edit_is_active').prop('checked', $(this).data('is-active') == 1);
                $('#edit_branch_id').val($(this).data('branch-id') || '');
                $('#editServiceTypeModal').modal('show');
            });
            
            // Update service type
            $('#updateServiceType').on('click', function() {
                var formData = $('#editServiceTypeForm').serialize();
                var responseDiv = $('.edit-service-type-response');
                
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            responseDiv.html('<div class="alert alert-success"><em class="fa fa-check-circle"></em> ' + response.message + '</div>');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + (response.message || 'Error updating service type') + '</div>');
                        }
                    },
                    error: function() {
                        responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> An error occurred. Please try again.</div>');
                    }
                });
            });
            
            // Delete service type
            $('.delete-service-type').on('click', function() {
                var serviceTypeId = $(this).data('service-type-id');
                var serviceName = $(this).data('service-name');
                
                if (!confirm('Are you sure you want to delete "' + serviceName + '"?\n\nThis action cannot be undone.')) {
                    return;
                }
                
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: {
                        action: 'delete_service_type',
                        service_type_id: serviceTypeId,
                        csrf_token: '<?php echo $csrf_token; ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#service-types-response').html('<div class="alert alert-success"><em class="fa fa-check-circle"></em> ' + response.message + '</div>');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            $('#service-types-response').html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + (response.message || 'Error deleting service type') + '</div>');
                        }
                    },
                    error: function() {
                        $('#service-types-response').html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> An error occurred. Please try again.</div>');
                    }
                });
            });
            
            // Reset modals when closed
            $('#addServiceTypeModal').on('hidden.bs.modal', function() {
                $('#addServiceTypeForm')[0].reset();
                $('.add-service-type-response').html('');
            });
            
            $('#editServiceTypeModal').on('hidden.bs.modal', function() {
                $('.edit-service-type-response').html('');
            });
        });
    }
    initServiceTypes();
})();
</script>

