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
?>
<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
        <ol class="breadcrumb">
            <li><a href="#">
                    <em class="fa fa-home"></em>
                </a></li>
            <li class="active">Meal Packages</li>
        </ol>
    </div><!--/.row-->

    <br>

    <div class="row">
        <div class="col-lg-12">
            <div id="success"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <em class="fa fa-cutlery"></em> Meal Packages Management
                    <div class="pull-right">
                        <?php if (hasPermission('package.create') || hasRole('super_admin')): ?>
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addPackageModal" style="border-radius: 4px; font-weight: 500; padding: 8px 16px;">
                            <em class="fa fa-plus"></em> Add Meal Package
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="panel-body">
                    <?php
                    if (isset($_GET['error'])) {
                        echo "<div class='alert alert-danger'>
                                <span class='glyphicon glyphicon-info-sign'></span> &nbsp; Error occurred!
                            </div>";
                    }
                    if (isset($_GET['success'])) {
                        echo "<div class='alert alert-success'>
                                <span class='glyphicon glyphicon-info-sign'></span> &nbsp; Operation successful!
                            </div>";
                    }
                    ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover" id="packagesTable">
                            <thead>
                                <tr>
                                    <th>Package Name</th>
                                    <th>Branch</th>
                                    <th>Meal Type</th>
                                    <th>Number of Meals</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Build query with branch filter for branch admins
                                $package_query = "SELECT mp.*, b.branch_name, b.branch_code 
                                                FROM meal_packages mp 
                                                LEFT JOIN branches b ON mp.branch_id = b.branch_id 
                                                WHERE 1=1";
                                
                                if ($userBranchId && !hasRole('super_admin')) {
                                    // Branch admin - only show packages from their branch
                                    $package_query .= " AND mp.branch_id = " . intval($userBranchId);
                                }
                                
                                $package_query .= " ORDER BY mp.created_at DESC";
                                
                                $packages_result = mysqli_query($connection, $package_query);
                                if (mysqli_num_rows($packages_result) > 0) {
                                    while ($package = mysqli_fetch_assoc($packages_result)) {
                                        $statusBadge = $package['status'] == 'active' 
                                            ? '<span class="label label-success">Active</span>' 
                                            : '<span class="label label-default">Inactive</span>';
                                        
                                        $branchInfo = $package['branch_name'] 
                                            ? htmlspecialchars($package['branch_name'] . ' (' . $package['branch_code'] . ')') 
                                            : '<em class="text-muted">All Branches</em>';
                                        
                                        $mealTypeLabels = [
                                            'breakfast' => 'Breakfast',
                                            'lunch' => 'Lunch',
                                            'dinner' => 'Dinner',
                                            'breakfast_dinner' => 'Breakfast & Dinner',
                                            'breakfast_lunch_dinner' => 'Breakfast, Lunch & Dinner',
                                            'all_day' => 'All Day',
                                            'custom' => 'Custom'
                                        ];
                                        $mealTypeInfo = isset($mealTypeLabels[$package['meal_type']]) 
                                            ? $mealTypeLabels[$package['meal_type']] 
                                            : ucfirst($package['meal_type']);
                                        
                                        echo '<tr>';
                                        echo '<td><strong>' . htmlspecialchars($package['package_name']) . '</strong>';
                                        if ($package['package_description']) {
                                            echo '<br><small class="text-muted">' . htmlspecialchars(substr($package['package_description'], 0, 50)) . '...</small>';
                                        }
                                        echo '</td>';
                                        echo '<td>' . $branchInfo . '</td>';
                                        echo '<td>' . $mealTypeInfo . '</td>';
                                        echo '<td>' . $package['number_of_meals'] . '</td>';
                                        echo '<td>LKR ' . number_format($package['package_price'], 2) . '</td>';
                                        echo '<td>' . $statusBadge . '</td>';
                                        echo '<td>';
                                        echo '<button class="btn btn-info btn-sm view-package" 
                                                data-package-id="' . $package['package_id'] . '" 
                                                title="View Details">
                                                <em class="fa fa-eye"></em>
                                              </button> ';
                                        
                                        if (hasPermission('package.update') || hasRole('super_admin')) {
                                            echo '<button class="btn btn-warning btn-sm edit-package" 
                                                    data-package-id="' . $package['package_id'] . '" 
                                                    title="Edit Package">
                                                    <em class="fa fa-pencil"></em>
                                                  </button> ';
                                        }
                                        
                                        if (hasPermission('package.delete') || hasRole('super_admin')) {
                                            echo '<a href="ajax.php?delete_meal_package=' . $package['package_id'] . '" 
                                                    class="btn btn-danger btn-sm" 
                                                    onclick="return confirm(\'Are you sure you want to delete this package?\')" 
                                                    title="Delete Package">
                                                    <em class="fa fa-trash"></em>
                                                  </a>';
                                        }
                                        
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="7" class="text-center">No packages found. Create your first meal package!</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Package Modal -->
    <div id="addPackageModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><em class="fa fa-plus"></em> Add New Meal Package</h4>
                </div>
                <div class="modal-body">
                    <form id="addPackageForm" data-toggle="validator" role="form">
                        <div class="response"></div>
                        
                        <div class="form-group">
                            <label>Package Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="package_name" 
                                   placeholder="e.g., Breakfast Buffet, Lunch Special, Dinner Package" 
                                   required maxlength="200">
                            <div class="help-block with-errors"></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Package Description</label>
                            <textarea class="form-control" id="package_description" 
                                      rows="3" placeholder="Describe what's included in this meal package..."></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Branch</label>
                                    <select class="form-control" id="package_branch_id">
                                        <option value="">All Branches</option>
                                        <?php
                                        // Get user's branch if they're a branch admin, otherwise show all branches
                                        if ($userBranchId && !hasRole('super_admin')) {
                                            // Branch admin - only show their branch
                                            $query = "SELECT branch_id, branch_name, branch_code FROM branches WHERE branch_id = ? AND status = 'active' ORDER BY branch_name";
                                            $stmt = mysqli_prepare($connection, $query);
                                            mysqli_stmt_bind_param($stmt, "i", $userBranchId);
                                            mysqli_stmt_execute($stmt);
                                            $result = mysqli_stmt_get_result($stmt);
                                        } else {
                                            // Super admin - show all active branches
                                            $query = "SELECT branch_id, branch_name, branch_code FROM branches WHERE status = 'active' ORDER BY branch_name";
                                            $result = mysqli_query($connection, $query);
                                        }
                                        
                                        if (mysqli_num_rows($result) > 0) {
                                            while ($branch = mysqli_fetch_assoc($result)) {
                                                echo '<option value="' . $branch['branch_id'] . '">' . 
                                                     htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')') . 
                                                     '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Meal Type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="package_meal_type" required>
                                        <option value="breakfast">Breakfast</option>
                                        <option value="lunch">Lunch</option>
                                        <option value="dinner">Dinner</option>
                                        <option value="breakfast_dinner">Breakfast & Dinner</option>
                                        <option value="breakfast_lunch_dinner">Breakfast, Lunch & Dinner</option>
                                        <option value="all_day">All Day</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Number of Meals <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="package_number_of_meals" 
                                           value="1" min="1" max="10" required>
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Package Price (LKR) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="package_price" 
                                           step="0.01" min="0" required placeholder="0.00">
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Included Items</label>
                            <textarea class="form-control" id="package_included_items" 
                                      rows="2" placeholder="e.g., Main course, Dessert, Beverages, Appetizer"></textarea>
                            <small class="help-block">List food items included in this package (comma-separated)</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Dietary Options</label>
                            <textarea class="form-control" id="package_dietary_options" 
                                      rows="2" placeholder="e.g., Vegetarian, Vegan, Halal, Gluten-free"></textarea>
                            <small class="help-block">List dietary options available (comma-separated)</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Status <span class="text-danger">*</span></label>
                            <select class="form-control" id="package_status" required>
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary pull-right" style="border-radius: 4px; font-weight: 500;">
                            <em class="fa fa-save"></em> Save Package
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Package Modal -->
    <div id="editPackageModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><em class="fa fa-pencil"></em> Edit Meal Package</h4>
                </div>
                <div class="modal-body">
                    <form id="editPackageForm" data-toggle="validator" role="form">
                        <div class="edit_response"></div>
                        <input type="hidden" id="edit_package_id">
                        
                        <div class="form-group">
                            <label>Package Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_package_name" 
                                   required maxlength="200">
                            <div class="help-block with-errors"></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Package Description</label>
                            <textarea class="form-control" id="edit_package_description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Branch</label>
                                    <select class="form-control" id="edit_package_branch_id">
                                        <option value="">All Branches</option>
                                        <?php
                                        // Get user's branch if they're a branch admin, otherwise show all branches
                                        if ($userBranchId && !hasRole('super_admin')) {
                                            // Branch admin - only show their branch
                                            $query = "SELECT branch_id, branch_name, branch_code FROM branches WHERE branch_id = ? AND status = 'active' ORDER BY branch_name";
                                            $stmt = mysqli_prepare($connection, $query);
                                            mysqli_stmt_bind_param($stmt, "i", $userBranchId);
                                            mysqli_stmt_execute($stmt);
                                            $result = mysqli_stmt_get_result($stmt);
                                        } else {
                                            // Super admin - show all active branches
                                            $query = "SELECT branch_id, branch_name, branch_code FROM branches WHERE status = 'active' ORDER BY branch_name";
                                            $result = mysqli_query($connection, $query);
                                        }
                                        
                                        if (mysqli_num_rows($result) > 0) {
                                            while ($branch = mysqli_fetch_assoc($result)) {
                                                echo '<option value="' . $branch['branch_id'] . '">' . 
                                                     htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')') . 
                                                     '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Meal Type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="edit_package_meal_type" required>
                                        <option value="breakfast">Breakfast</option>
                                        <option value="lunch">Lunch</option>
                                        <option value="dinner">Dinner</option>
                                        <option value="breakfast_dinner">Breakfast & Dinner</option>
                                        <option value="breakfast_lunch_dinner">Breakfast, Lunch & Dinner</option>
                                        <option value="all_day">All Day</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Number of Meals <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="edit_package_number_of_meals" 
                                           min="1" max="10" required>
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Package Price (LKR) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="edit_package_price" 
                                           step="0.01" min="0" required>
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Included Items</label>
                            <textarea class="form-control" id="edit_package_included_items" rows="2"></textarea>
                            <small class="help-block">List food items included in this package (comma-separated)</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Dietary Options</label>
                            <textarea class="form-control" id="edit_package_dietary_options" rows="2"></textarea>
                            <small class="help-block">List dietary options available (comma-separated)</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Status <span class="text-danger">*</span></label>
                            <select class="form-control" id="edit_package_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary pull-right" style="border-radius: 4px; font-weight: 500;">
                            <em class="fa fa-save"></em> Update Package
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Package Details Modal -->
    <div id="viewPackageModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><em class="fa fa-eye"></em> Package Details</h4>
                </div>
                <div class="modal-body" id="packageDetailsContent">
                    <p class="text-center"><em class="fa fa-spinner fa-spin fa-2x"></em> Loading...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 4px;">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for jQuery to be loaded
(function() {
    function initMealPackages() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initMealPackages, 50);
            return;
        }
        
        var $ = jQuery;
        
        $(document).ready(function() {
            // Initialize DataTable
            if ($.fn.DataTable) {
                $('#packagesTable').DataTable({
                    "order": [[0, "asc"]],
                    "pageLength": 25
                });
            }
            
            // Add Package Form
            $('#addPackageForm').on('submit', function(e) {
                e.preventDefault();
                var formData = {
                    action: 'add_meal_package',
                    package_name: $('#package_name').val(),
                    package_description: $('#package_description').val(),
                    branch_id: $('#package_branch_id').val() || null,
                    meal_type: $('#package_meal_type').val(),
                    number_of_meals: $('#package_number_of_meals').val(),
                    package_price: $('#package_price').val(),
                    included_items: $('#package_included_items').val(),
                    dietary_options: $('#package_dietary_options').val(),
                    status: $('#package_status').val(),
                    csrf_token: '<?php echo generateCSRFToken(); ?>'
                };
                
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.done || response.success) {
                            alert('Meal package added successfully!');
                            location.reload();
                        } else {
                            $('#addPackageForm .response').html('<div class="alert alert-danger">' + (response.data || response.message || 'Error adding package') + '</div>');
                        }
                    },
                    error: function() {
                        $('#addPackageForm .response').html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
                    }
                });
            });
            
            // Edit Package Form
            $('#editPackageForm').on('submit', function(e) {
                e.preventDefault();
                var formData = {
                    action: 'update_meal_package',
                    package_id: $('#edit_package_id').val(),
                    package_name: $('#edit_package_name').val(),
                    package_description: $('#edit_package_description').val(),
                    branch_id: $('#edit_package_branch_id').val() || null,
                    meal_type: $('#edit_package_meal_type').val(),
                    number_of_meals: $('#edit_package_number_of_meals').val(),
                    package_price: $('#edit_package_price').val(),
                    included_items: $('#edit_package_included_items').val(),
                    dietary_options: $('#edit_package_dietary_options').val(),
                    status: $('#edit_package_status').val(),
                    csrf_token: '<?php echo generateCSRFToken(); ?>'
                };
                
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.done || response.success) {
                            alert('Meal package updated successfully!');
                            location.reload();
                        } else {
                            $('#editPackageForm .edit_response').html('<div class="alert alert-danger">' + (response.data || response.message || 'Error updating package') + '</div>');
                        }
                    },
                    error: function() {
                        $('#editPackageForm .edit_response').html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
                    }
                });
            });
            
            // View Package
            $('.view-package').on('click', function() {
                var packageId = $(this).data('package-id');
                loadPackageDetails(packageId);
            });
            
            // Edit Package
            $('.edit-package').on('click', function() {
                var packageId = $(this).data('package-id');
                loadPackageForEdit(packageId);
            });
            
            function loadPackageDetails(packageId) {
                $('#viewPackageModal').modal('show');
                $('#packageDetailsContent').html('<p class="text-center"><em class="fa fa-spinner fa-spin fa-2x"></em> Loading...</p>');
                
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: {
                        action: 'get_meal_package_details',
                        package_id: packageId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            var html = buildPackageDetailsHTML(response);
                            $('#packageDetailsContent').html(html);
                        } else {
                            $('#packageDetailsContent').html('<div class="alert alert-danger">' + (response.message || 'Error loading package details') + '</div>');
                        }
                    },
                    error: function() {
                        $('#packageDetailsContent').html('<div class="alert alert-danger">An error occurred. Please try again.</div>');
                    }
                });
            }
            
            function loadPackageForEdit(packageId) {
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: {
                        action: 'get_meal_package_details',
                        package_id: packageId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#edit_package_id').val(response.package_id);
                            $('#edit_package_name').val(response.package_name);
                            $('#edit_package_description').val(response.package_description || '');
                            $('#edit_package_branch_id').val(response.branch_id || '');
                            $('#edit_package_meal_type').val(response.meal_type);
                            $('#edit_package_number_of_meals').val(response.number_of_meals);
                            $('#edit_package_price').val(response.package_price);
                            $('#edit_package_included_items').val(response.included_items || '');
                            $('#edit_package_dietary_options').val(response.dietary_options || '');
                            $('#edit_package_status').val(response.status);
                            $('#editPackageModal').modal('show');
                        } else {
                            alert('Error loading package: ' + (response.message || 'Unknown error'));
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            }
            
            function buildPackageDetailsHTML(response) {
                var mealTypeLabels = {
                    'breakfast': 'Breakfast',
                    'lunch': 'Lunch',
                    'dinner': 'Dinner',
                    'breakfast_dinner': 'Breakfast & Dinner',
                    'breakfast_lunch_dinner': 'Breakfast, Lunch & Dinner',
                    'all_day': 'All Day',
                    'custom': 'Custom'
                };
                
                var html = '<div class="row">';
                html += '<div class="col-md-6"><strong>Package Name:</strong></div>';
                html += '<div class="col-md-6">' + response.package_name + '</div>';
                html += '</div><hr>';
                
                if (response.package_description) {
                    html += '<div class="row">';
                    html += '<div class="col-md-6"><strong>Description:</strong></div>';
                    html += '<div class="col-md-6">' + response.package_description + '</div>';
                    html += '</div><hr>';
                }
                
                html += '<div class="row">';
                html += '<div class="col-md-6"><strong>Branch:</strong></div>';
                html += '<div class="col-md-6">' + (response.branch_name || 'All Branches') + '</div>';
                html += '</div><hr>';
                
                html += '<div class="row">';
                html += '<div class="col-md-6"><strong>Meal Type:</strong></div>';
                html += '<div class="col-md-6">' + (mealTypeLabels[response.meal_type] || response.meal_type) + '</div>';
                html += '</div><hr>';
                
                html += '<div class="row">';
                html += '<div class="col-md-6"><strong>Number of Meals:</strong></div>';
                html += '<div class="col-md-6">' + response.number_of_meals + '</div>';
                html += '</div><hr>';
                
                html += '<div class="row">';
                html += '<div class="col-md-6"><strong>Package Price:</strong></div>';
                html += '<div class="col-md-6">LKR ' + parseFloat(response.package_price).toFixed(2) + '</div>';
                html += '</div><hr>';
                
                if (response.included_items) {
                    html += '<div class="row">';
                    html += '<div class="col-md-6"><strong>Included Items:</strong></div>';
                    html += '<div class="col-md-6">' + response.included_items + '</div>';
                    html += '</div><hr>';
                }
                
                if (response.dietary_options) {
                    html += '<div class="row">';
                    html += '<div class="col-md-6"><strong>Dietary Options:</strong></div>';
                    html += '<div class="col-md-6">' + response.dietary_options + '</div>';
                    html += '</div><hr>';
                }
                
                html += '<div class="row">';
                html += '<div class="col-md-6"><strong>Status:</strong></div>';
                var statusBadge = response.status == 'active' 
                    ? '<span class="label label-success">Active</span>' 
                    : '<span class="label label-default">Inactive</span>';
                html += '<div class="col-md-6">' + statusBadge + '</div>';
                html += '</div>';
                
                html += '<hr><div class="row">';
                html += '<div class="col-md-6"><strong>Created:</strong></div>';
                html += '<div class="col-md-6">' + (response.created_at || 'N/A') + '</div>';
                html += '</div>';
                
                if (response.updated_at && response.updated_at != response.created_at) {
                    html += '<div class="row">';
                    html += '<div class="col-md-6"><strong>Last Updated:</strong></div>';
                    html += '<div class="col-md-6">' + response.updated_at + '</div>';
                    html += '</div>';
                }
                
                return html;
            }
        });
    }
    initMealPackages();
})();
</script>
