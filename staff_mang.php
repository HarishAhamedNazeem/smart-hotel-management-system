<?php
// Handle create employee user form submission
$createUserMessage = '';
$createUserMessageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_employee_user'])) {
    require_once "includes/security.php";
    require_once "includes/audit.php";
    
    // Only super admin can create employee accounts
    if (!hasRole('super_admin')) {
        $createUserMessage = 'Access denied. Only super admin can create employee accounts.';
        $createUserMessageType = 'danger';
    } elseif (!verifyCSRFToken($_POST['csrf_token'])) {
        $createUserMessage = 'Security token mismatch.';
        $createUserMessageType = 'danger';
    } else {
        $staff_id = intval($_POST['emp_id']); // Note: form parameter name is 'emp_id', but column is now 'staff_id'
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($staff_id) || empty($username) || empty($email) || empty($password)) {
            $createUserMessage = 'Please fill all required fields.';
            $createUserMessageType = 'danger';
        } elseif ($password !== $confirm_password) {
            $createUserMessage = 'Passwords do not match.';
            $createUserMessageType = 'danger';
        } elseif (strlen($password) < 8) {
            $createUserMessage = 'Password must be at least 8 characters long.';
            $createUserMessageType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $createUserMessage = 'Invalid email address.';
            $createUserMessageType = 'danger';
        } else {
            // Check if email exists
            require_once "includes/auth.php";
            if (emailExists($email)) {
                $createUserMessage = 'Email already exists.';
                $createUserMessageType = 'danger';
            } elseif (usernameExists($username)) {
                $createUserMessage = 'Username already exists.';
                $createUserMessageType = 'danger';
            } else {
                // Check if staff exists
                $staffQuery = "SELECT * FROM staff WHERE staff_id = ?";
                $staffStmt = mysqli_prepare($connection, $staffQuery);
                mysqli_stmt_bind_param($staffStmt, "i", $staff_id);
                mysqli_stmt_execute($staffStmt);
                $staffResult = mysqli_stmt_get_result($staffStmt);
                $staff = mysqli_fetch_assoc($staffResult);
                mysqli_stmt_close($staffStmt);
                
                if (!$staff) {
                    $createUserMessage = 'Staff member not found.';
                    $createUserMessageType = 'danger';
                } elseif ($staff['user_id']) {
                    $createUserMessage = 'This employee already has a user account.';
                    $createUserMessageType = 'warning';
                } else {
                    // Create user account
                    $hashedPassword = hashPassword($password);
                    $userName = $staff['staff_name'];
                    
                    $insertQuery = "INSERT INTO user (name, username, email, phone, password, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, 'active', NOW())";
                    $insertStmt = mysqli_prepare($connection, $insertQuery);
                    $phone = $staff['contact_no'];
                    mysqli_stmt_bind_param($insertStmt, "sssss", $userName, $username, $email, $phone, $hashedPassword);
                    
                    if (mysqli_stmt_execute($insertStmt)) {
                        $user_id = mysqli_insert_id($connection);
                        
                        // Get branch_id from form if provided
                        $branch_id = isset($_POST['branch_id']) && !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
                        
                        // Link staff to user and update branch if provided
                        if ($branch_id) {
                            $updateStaffQuery = "UPDATE staff SET user_id = ?, branch_id = ? WHERE staff_id = ?";
                            $updateStaffStmt = mysqli_prepare($connection, $updateStaffQuery);
                            mysqli_stmt_bind_param($updateStaffStmt, "iii", $user_id, $branch_id, $staff_id);
                        } else {
                            $updateStaffQuery = "UPDATE staff SET user_id = ? WHERE staff_id = ?";
                            $updateStaffStmt = mysqli_prepare($connection, $updateStaffQuery);
                            mysqli_stmt_bind_param($updateStaffStmt, "ii", $user_id, $staff_id);
                        }
                        mysqli_stmt_execute($updateStaffStmt);
                        mysqli_stmt_close($updateStaffStmt);
                        
                        // Get role based on staff type
                        $roleMappingQuery = "SELECT role_id FROM staff_type_role_mapping WHERE staff_type_id = ? LIMIT 1";
                        $roleMappingStmt = mysqli_prepare($connection, $roleMappingQuery);
                        mysqli_stmt_bind_param($roleMappingStmt, "i", $staff['staff_type_id']);
                        mysqli_stmt_execute($roleMappingStmt);
                        $roleMappingResult = mysqli_stmt_get_result($roleMappingStmt);
                        $roleMapping = mysqli_fetch_assoc($roleMappingResult);
                        mysqli_stmt_close($roleMappingStmt);
                        
                        if ($roleMapping) {
                            // Assign role
                            $assignRoleQuery = "INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)";
                            $assignRoleStmt = mysqli_prepare($connection, $assignRoleQuery);
                            $assigned_by = $_SESSION['user_id'];
                            mysqli_stmt_bind_param($assignRoleStmt, "iii", $user_id, $roleMapping['role_id'], $assigned_by);
                            mysqli_stmt_execute($assignRoleStmt);
                            mysqli_stmt_close($assignRoleStmt);
                        }
                        
                        // Log audit event
                        if (function_exists('logAuditEvent')) {
                            logAuditEvent('employee_user_created', 'users', 'user', $user_id, null, [
                                'staff_id' => $staff_id,
                                'staff_type' => $staff['staff_type_id'],
                                'username' => $username,
                                'branch_id' => $branch_id
                            ]);
                        }
                        
                        mysqli_stmt_close($insertStmt);
                        $createUserMessage = 'Employee user account created successfully! Username: ' . htmlspecialchars($username);
                        $createUserMessageType = 'success';
                        
                        // Reload page after 2 seconds on success
                        echo '<script>setTimeout(function(){ window.location.href = "index.php?staff_mang"; }, 2000);</script>';
                    } else {
                        mysqli_stmt_close($insertStmt);
                        $createUserMessage = 'Error creating user account.';
                        $createUserMessageType = 'danger';
                    }
                }
            }
        }
    }
}

// Get staff members without user accounts for the dropdown
$staffWithoutUsersQuery = "SELECT s.*, b.branch_name, b.branch_code
                           FROM staff s
                           LEFT JOIN branches b ON s.branch_id = b.branch_id
                           WHERE s.user_id IS NULL
                           ORDER BY s.staff_name";
$staffWithoutUsersResult = mysqli_query($connection, $staffWithoutUsersQuery);

// Get all branches for branch selection
$branchesQuery = "SELECT branch_id, branch_name, branch_code FROM branches WHERE status = 'active' ORDER BY branch_name";
$branchesResult = mysqli_query($connection, $branchesQuery);
?>
<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
        <ol class="breadcrumb">
            <li><a href="#">
                    <em class="fa fa-home"></em>
                </a></li>
            <li class="active">Manage Staffs</li>
        </ol>
    </div><!--/.row-->

   

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">Employee Details:
                    <div class="pull-right">
                        <button class="btn btn-success btn-sm" id="addStaffTypeBtn" data-toggle="modal" data-target="#addStaffTypeModal" style="border-radius:0%; margin-right: 5px;">
                            <em class="fa fa-plus"></em> Add Staff Type
                        </button>
                        <button class="btn btn-primary btn-sm" id="addStaffBtn" data-toggle="modal" data-target="#addStaffModal" style="border-radius:0%">
                            <em class="fa fa-user-plus"></em> Add Staff
                        </button>
                    </div>
                </div>
                <div class="panel-body">
                    <?php
                    if (isset($_GET['error'])) {
                        echo "<div class='alert alert-danger'>
                                <span class='glyphicon glyphicon-info-sign'></span> &nbsp; Error on Shift Change !
                            </div>";
                    }
                    if (isset($_GET['success'])) {
                        echo "<div class='alert alert-success'>
                                <span class='glyphicon glyphicon-info-sign'></span> &nbsp; Shift Successfully Changed!
                            </div>";
                    }
                    ?>
                    <table class="table table-striped table-bordered table-responsive" cellspacing="0" width="100%"
                           id="rooms">
                        <thead>
                        <tr>
                            <th>Sr. No</th>
                            <th>Employee Name</th>
                            <th>Staff</th>
                            <th>Shift</th>
                            <th>Joining Date</th>
                            <th>Salary</th>
                            <th>Branch</th>
                            <th>User Account</th>
                            <th>Change Shift</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
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
                        
                        // Get all staff members (including super admins who are staff)
                        $staff_query = "SELECT s.*, st.staff_type, u.username, u.email as user_email, u.id as user_id, b.branch_name, b.branch_code,
                                        (SELECT COUNT(*) FROM user_roles ur 
                                         INNER JOIN roles r ON ur.role_id = r.role_id 
                                         WHERE ur.user_id = u.id AND r.role_name = 'super_admin') as is_super_admin
                                        FROM staff s
                                        LEFT JOIN staff_type st ON s.staff_type_id = st.staff_type_id
                                        LEFT JOIN user u ON s.user_id = u.id
                                        LEFT JOIN branches b ON s.branch_id = b.branch_id
                                        WHERE 1=1";
                        
                        // Add branch filter for branch admins (super admins see all)
                        if ($userBranchId && !hasRole('super_admin')) {
                            $staff_query .= " AND s.branch_id = " . intval($userBranchId);
                        }
                        
                        $staff_query .= " ORDER BY s.staff_name";
                        $staff_result = mysqli_query($connection, $staff_query);

                        if (mysqli_num_rows($staff_result) > 0) {
                            while ($staff = mysqli_fetch_assoc($staff_result)) { ?>
                                <tr>

                                    <td><?php echo $staff['staff_id']; ?></td>
                                    <td><?php echo $staff['staff_name']; ?></td>
                                    <td><?php echo !empty($staff['staff_type']) ? htmlspecialchars($staff['staff_type']) : '<span class="text-muted">Not Assigned</span>'; ?></td>
                                    <td><?php echo $staff['shift'] . ' - ' . $staff['shift_timing']; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($staff['joining_date'])); ?></td>
                                    <td><?php echo $staff['salary']; ?></td>
                                    <td>
                                        <?php 
                                        // Check if user is super admin (using the is_super_admin field from query)
                                        $isSuperAdmin = isset($staff['is_super_admin']) && $staff['is_super_admin'] > 0;
                                        
                                        if ($isSuperAdmin || (empty($staff['branch_id']) && !empty($staff['user_id']))): ?>
                                            <span class="label label-primary">
                                                <em class="fa fa-globe"></em> All Branches
                                            </span>
                                        <?php elseif (!empty($staff['branch_name'])): ?>
                                            <span class="label label-info">
                                                <em class="fa fa-building"></em> <?php echo htmlspecialchars($staff['branch_name']); ?>
                                            </span>
                                            <?php if (!empty($staff['branch_code'])): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($staff['branch_code']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="label label-default">
                                                <em class="fa fa-question-circle"></em> Not Assigned
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($staff['username']): ?>
                                            <span class="label label-success">
                                                <em class="fa fa-check-circle"></em> Active
                                            </span>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($staff['username']); ?></small>
                                        <?php else: ?>
                                            <span class="label label-warning">
                                                <em class="fa fa-exclamation-circle"></em> No Account
                                            </span>
                                            <?php if (hasRole('super_admin')): ?>
                                            <br><button class="btn btn-xs btn-primary create-employee-user" 
                                                   data-emp-id="<?php echo $staff['staff_id']; ?>"
                                                   data-emp-name="<?php echo htmlspecialchars($staff['staff_name']); ?>"
                                                   data-staff-type="<?php echo htmlspecialchars($staff['staff_type']); ?>"
                                                   data-contact="<?php echo htmlspecialchars($staff['contact_no']); ?>"
                                                   data-branch-id="<?php echo $staff['branch_id'] ?? ''; ?>"
                                                   data-branch-name="<?php echo htmlspecialchars($staff['branch_name'] ?? 'Not Assigned'); ?>">
                                                <em class="fa fa-user-plus"></em> Create
                                            </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning change-shift-btn" style="border-radius:0%" data-toggle="modal" data-target="#changeShift"
                                                data-id="<?php echo $staff['staff_id']; ?>">Change Shift</button>
                                    </td>
                                    <td>

                                        <button data-toggle="modal"
                                                data-target="#empDetail<?php echo $staff['staff_id']; ?>"
                                                data-id="<?php echo $staff['staff_id']; ?>" id="editEmp"
                                                class="btn btn-info" style="border-radius:60px;"><i class="fa fa-pencil"></i></button>
                                        <a href='functionmis.php?empid=<?php echo $staff['staff_id']; ?>'
                                           class="btn btn-danger" onclick="return confirm('Are you Sure?')" style="border-radius:60px;"><i
                                                    class="fa fa-trash"></i></a>
                                        <a href='index.php?emp_history&empid=<?php echo $staff['staff_id']; ?>'
                                           class="btn btn-success" title="Employee Histery" style="border-radius:60px;"><i class="fa fa-eye"></i></a>
                                    </td>
                                </tr>


                                <?php
                            }
                        }
                        ?>


                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>    <!--/.main-->

<?php
//$staff_query = "SELECT * FROM staff  JOIN staff_type JOIN shift ON staff.staff_type_id =staff_type.staff_type_id ON shift.";
$staff_query = "SELECT s.*, u.username, u.email as user_email, u.id as user_id, b.branch_name, b.branch_id
                FROM staff s
                LEFT JOIN user u ON s.user_id = u.id
                LEFT JOIN branches b ON s.branch_id = b.branch_id";
$staff_result = mysqli_query($connection, $staff_query);

if (mysqli_num_rows($staff_result) > 0) {
    while ($staffGlobal = mysqli_fetch_assoc($staff_result)) {
        $fullname = explode(" ", $staffGlobal['staff_name']);
        $hasUserAccount = !empty($staffGlobal['user_id']);
        ?>

        <!-- Employee Detail-->
        <div id="empDetail<?php echo $staffGlobal['staff_id']; ?>" class="modal fade" role="dialog">
            <div class="modal-dialog modal-lg">

                <!-- Modal content-->
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><em class="fa fa-edit"></em> Edit Employee Details<?php if (!$hasUserAccount && hasRole('super_admin')): ?> <small class="text-muted">(with optional user account creation)</small><?php endif; ?></h4>
                    </div>
                    <div class="modal-body">

                        <div class="row">
                            <div class="col-lg-12">
                                <div class="panel panel-default">
                                    <div class="panel-heading">Employee Detail:</div>
                                    <div class="panel-body">
                                        <div class="edit-staff-response-<?php echo $staffGlobal['staff_id']; ?>" style="margin-bottom: 15px;"></div>
                                        <?php if ($hasUserAccount): ?>
                                        <div class="alert alert-success" style="margin-bottom: 15px;">
                                            <em class="fa fa-check-circle"></em> <strong>User Account:</strong> This employee has an active user account (<?php echo htmlspecialchars($staffGlobal['username'] ?? 'N/A'); ?>).
                                        </div>
                                        <?php elseif (hasRole('super_admin')): ?>
                                        <div class="alert alert-info" style="margin-bottom: 15px;">
                                            <em class="fa fa-info-circle"></em> <strong>Note:</strong> This employee doesn't have a user account. You can create one while editing below.
                                        </div>
                                        <?php endif; ?>
                                        <form data-toggle="validator" role="form" id="editStaffForm_<?php echo $staffGlobal['staff_id']; ?>" class="edit-staff-form" onsubmit="return false;">
                                            <div class="row">
                                                <div class="form-group col-lg-6">
                                                    <label>Staff Type</label>
                                                    <select class="form-control" id="staff_type_<?php echo $staffGlobal['staff_id']; ?>" name="staff_type"
                                                            required>
                                                        <option selected disabled>Select Staff Type</option>
                                                        <?php
                                                        // Get staff types from staff_type table
                                                        $query = "SELECT * FROM staff_type WHERE is_active = 1 ORDER BY staff_type";
                                                        $result = mysqli_query($connection, $query);
                                                        if (mysqli_num_rows($result) > 0) {
                                                            while ($row = mysqli_fetch_assoc($result)) {
                                                                $selected = ($row['staff_type_id'] == $staffGlobal['staff_type_id']) ? 'selected="selected"' : '';
                                                                echo '<option value="' . $row['staff_type_id'] . '" ' . $selected . '>' . htmlspecialchars($row['staff_type']) . '</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                </div>

                                                <div class="form-group col-lg-6">
                                                    <label>Shift</label>
                                                    <select class="form-control" id="shift_<?php echo $staffGlobal['staff_id']; ?>" name="shift" required>
                                                        <option value="" selected disabled>Select Shift</option>
                                                        <?php
                                                        $shifts = [
                                                            ['shift' => 'Morning/Day', 'shift_timing' => '7 AM-4 PM'],
                                                            ['shift' => 'Afternoon/Swing', 'shift_timing' => '3 PM-11 PM'],
                                                            ['shift' => 'Night/Graveyard', 'shift_timing' => '11 PM-8 AM']
                                                        ];
                                                        foreach ($shifts as $shift) {
                                                            $selected = ($shift['shift'] == $staffGlobal['shift'] && $shift['shift_timing'] == $staffGlobal['shift_timing']) ? 'selected="selected"' : '';
                                                            echo '<option value="' . htmlspecialchars($shift['shift']) . '|' . htmlspecialchars($shift['shift_timing']) . '" ' . $selected . '>' . htmlspecialchars($shift['shift'] . ' - ' . $shift['shift_timing']) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                    <div class="help-block with-errors"></div>
                                                </div>
                                                <input type="hidden" value="<?php echo $staffGlobal['staff_id']; ?>"
                                                       id="emp_id_<?php echo $staffGlobal['staff_id']; ?>" name="emp_id">

                                                <div class="form-group col-lg-6">
                                                    <label>First Name</label>
                                                    <input type="text" value="<?php echo $fullname[0]; ?>"
                                                           class="form-control" placeholder="First Name" id="first_name_<?php echo $staffGlobal['staff_id']; ?>"
                                                           name="first_name" required>
                                                </div>

                                                <div class="form-group col-lg-6">
                                                    <label>Last Name</label>
                                                    <input type="text" value="<?php echo $fullname[1]; ?>"
                                                           class="form-control" placeholder="Last Name" id="last_name_<?php echo $staffGlobal['staff_id']; ?>"
                                                           name="last_name" required>
                                                </div>

                                                <div class="form-group col-lg-6">
                                                    <label>ID Card Type</label>
                                                    <select class="form-control" id="id_card_id_<?php echo $staffGlobal['staff_id']; ?>" name="id_card_type"
                                                            required>
                                                        <option selected disabled>Select ID Card Type</option>
                                                        <?php
                                                        $query = "SELECT * FROM id_card_type";
                                                        $result = mysqli_query($connection, $query);

                                                        if (mysqli_num_rows($result) > 0) {
                                                            while ($id_card_type = mysqli_fetch_assoc($result)) {
                                                                //  echo '<option value="' . $id_card_type['id_card_type_id'] . '">' . $id_card_type['id_card_type'] . '</option>';
                                                                echo '<option  value="' . $id_card_type['id_card_type_id'] . '" ' . (($id_card_type['id_card_type_id'] == $staffGlobal['id_card_type']) ? 'selected="selected"' : "") . '>' . $id_card_type['id_card_type'] . '</option>';
                                                            }
                                                        }

                                                        ?>
                                                    </select>
                                                </div>

                                                <div class="form-group col-lg-6">
                                                    <label>ID Card No</label>
                                                    <input type="text" class="form-control" placeholder="ID Card No"
                                                           id="id_card_no_<?php echo $staffGlobal['staff_id']; ?>"
                                                           value="<?php echo $staffGlobal['id_card_no']; ?>"
                                                           name="id_card_no" required>
                                                </div>
                                                <div class="form-group col-lg-6">
                                                    <label>Contact Number</label>
                                                    <input type="number" class="form-control"
                                                           placeholder="Contact Number" id="contact_no_<?php echo $staffGlobal['staff_id']; ?>"
                                                           value="<?php echo $staffGlobal['contact_no']; ?>"
                                                           name="contact_no" required>
                                                </div>

                                                <div class="form-group col-lg-6">
                                                    <label>Address</label>
                                                    <input type="text" class="form-control" placeholder="address"
                                                           id="address_<?php echo $staffGlobal['staff_id']; ?>" value="<?php echo $staffGlobal['address']; ?>"
                                                           name="address">
                                                </div>

                                                <div class="form-group col-lg-6">
                                                    <label>Salary</label>
                                                    <input type="number" class="form-control" placeholder="Salary"
                                                           id="salary_<?php echo $staffGlobal['staff_id']; ?>" value="<?php echo $staffGlobal['salary']; ?>"
                                                           name="salary" required>
                                                </div>

                                                <div class="form-group col-lg-6">
                                                    <label>Branch</label>
                                                    <select class="form-control" id="edit_branch_<?php echo $staffGlobal['staff_id']; ?>" name="branch_id">
                                                        <option value="">Select a branch...</option>
                                                        <?php
                                                        // Get all branches for branch selection
                                                        $branchesQuery = "SELECT branch_id, branch_name, branch_code FROM branches WHERE status = 'active' ORDER BY branch_name";
                                                        $branchesResult = mysqli_query($connection, $branchesQuery);
                                                        if (mysqli_num_rows($branchesResult) > 0) {
                                                            while ($branch = mysqli_fetch_assoc($branchesResult)) {
                                                                $selected = ($branch['branch_id'] == ($staffGlobal['branch_id'] ?? '')) ? 'selected' : '';
                                                                echo '<option value="' . $branch['branch_id'] . '" ' . $selected . '>' . 
                                                                     htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')') . 
                                                                     '</option>';
                                                            }
                                                        }
                                                        ?>
                                                    </select>
                                                    <small class="help-block">Select which branch this employee is assigned to</small>
                                                </div>

                                            </div>

                                            <!-- User Account Editing Section (Show if user account exists and user is super_admin) -->
                                            <?php if ($hasUserAccount && hasRole('super_admin')): ?>
                                            <div class="panel panel-default" style="margin-top: 20px;">
                                                <div class="panel-heading">
                                                    <h5><strong>User Account Details</strong></h5>
                                                </div>
                                                <div class="panel-body">
                                                    <input type="hidden" id="edit_user_id_<?php echo $staffGlobal['staff_id']; ?>" value="<?php echo $staffGlobal['user_id']; ?>">
                                                    
                                                    <div class="form-group">
                                                        <label>Username <span class="text-danger">*</span></label>
                                                        <input type="text" name="edit_username" id="edit_username_<?php echo $staffGlobal['staff_id']; ?>" class="form-control" 
                                                               value="<?php echo htmlspecialchars($staffGlobal['username'] ?? ''); ?>"
                                                               placeholder="Enter username" minlength="3" maxlength="15"
                                                               pattern="[a-zA-Z0-9_]+" 
                                                               data-error="Username must be 3-15 characters (letters, numbers, underscore only)">
                                                        <div class="help-block with-errors"></div>
                                                        <small class="help-block">3-15 characters, letters, numbers, and underscore only</small>
                                                    </div>

                                                    <div class="form-group">
                                                        <label>Email Address <span class="text-danger">*</span></label>
                                                        <input type="email" name="edit_email" id="edit_email_<?php echo $staffGlobal['staff_id']; ?>" class="form-control" 
                                                               value="<?php echo htmlspecialchars($staffGlobal['user_email'] ?? ''); ?>"
                                                               placeholder="Enter email address">
                                                        <div class="help-block with-errors"></div>
                                                    </div>

                                                    <div class="form-group">
                                                        <label>Branch Assignment</label>
                                                        <select name="edit_user_branch_id" id="edit_user_branch_id_<?php echo $staffGlobal['staff_id']; ?>" class="form-control">
                                                            <option value="">Select a branch...</option>
                                                            <?php
                                                            mysqli_data_seek($branchesResult, 0);
                                                            while ($branch = mysqli_fetch_assoc($branchesResult)) {
                                                                $selected = ($branch['branch_id'] == ($staffGlobal['branch_id'] ?? '')) ? 'selected' : '';
                                                                echo '<option value="' . $branch['branch_id'] . '" ' . $selected . '>' . 
                                                                     htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')') . 
                                                                     '</option>';
                                                            }
                                                            ?>
                                                        </select>
                                                        <small class="help-block">Select which branch this user account is assigned to</small>
                                                    </div>

                                                    <div class="form-group">
                                                        <label>
                                                            <input type="checkbox" id="edit_change_password_<?php echo $staffGlobal['staff_id']; ?>" name="change_password" value="1">
                                                            Change Password
                                                        </label>
                                                    </div>

                                                    <div id="edit_passwordFields_<?php echo $staffGlobal['staff_id']; ?>" style="display: none;">
                                                        <div class="form-group">
                                                            <label>New Password <span class="text-danger">*</span></label>
                                                            <input type="password" name="edit_password" id="edit_password_<?php echo $staffGlobal['staff_id']; ?>" class="form-control" 
                                                                   placeholder="Enter new password" minlength="8"
                                                                   data-error="Password must be at least 8 characters long">
                                                            <div class="help-block with-errors"></div>
                                                            <small class="help-block">Minimum 8 characters</small>
                                                        </div>

                                                        <div class="form-group">
                                                            <label>Confirm New Password <span class="text-danger">*</span></label>
                                                            <input type="password" name="edit_confirm_password" id="edit_confirm_password_<?php echo $staffGlobal['staff_id']; ?>" class="form-control" 
                                                                   placeholder="Confirm new password" 
                                                                   data-match="#edit_password_<?php echo $staffGlobal['staff_id']; ?>" 
                                                                   data-match-error="Passwords do not match">
                                                            <div class="help-block with-errors"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <!-- User Account Section (Only show if no user account exists and user is super_admin) -->
                                            <?php if (!$hasUserAccount && hasRole('super_admin')): ?>
                                            <div class="panel panel-default" style="margin-top: 20px;">
                                                <div class="panel-heading">
                                                    <h5><strong>User Account Creation (Optional)</strong></h5>
                                                </div>
                                                <div class="panel-body">
                                                    <div class="form-group">
                                                        <label>
                                                            <input type="checkbox" id="edit_create_user_account_<?php echo $staffGlobal['staff_id']; ?>" name="create_user_account" value="1">
                                                            Create user account for this employee
                                                        </label>
                                                    </div>

                                                    <div id="edit_userAccountFields_<?php echo $staffGlobal['staff_id']; ?>" style="display: none;">
                                                        <div class="form-group">
                                                            <label>Assign to Branch <span class="text-danger">*</span></label>
                                                            <select name="branch_id" id="edit_branch_id_<?php echo $staffGlobal['staff_id']; ?>" class="form-control">
                                                                <option value="">Select a branch...</option>
                                                                <?php
                                                                mysqli_data_seek($branchesResult, 0);
                                                                while ($branch = mysqli_fetch_assoc($branchesResult)) {
                                                                    $selected = ($branch['branch_id'] == ($staffGlobal['branch_id'] ?? '')) ? 'selected' : '';
                                                                    echo '<option value="' . $branch['branch_id'] . '" ' . $selected . '>' . 
                                                                         htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')') . 
                                                                         '</option>';
                                                                }
                                                                ?>
                                                            </select>
                                                            <small class="help-block">Select which branch this employee will be assigned to</small>
                                                        </div>

                                                        <div class="form-group">
                                                            <label>Username <span class="text-danger">*</span></label>
                                                            <input type="text" name="username" id="edit_username_<?php echo $staffGlobal['staff_id']; ?>" class="form-control" 
                                                                   placeholder="Enter username" minlength="3" maxlength="15"
                                                                   pattern="[a-zA-Z0-9_]+" 
                                                                   data-error="Username must be 3-15 characters (letters, numbers, underscore only)">
                                                            <div class="help-block with-errors"></div>
                                                            <small class="help-block">3-15 characters, letters, numbers, and underscore only</small>
                                                        </div>

                                                        <div class="form-group">
                                                            <label>Email Address <span class="text-danger">*</span></label>
                                                            <input type="email" name="email" id="edit_email_<?php echo $staffGlobal['staff_id']; ?>" class="form-control" 
                                                                   placeholder="Enter email address">
                                                            <div class="help-block with-errors"></div>
                                                        </div>

                                                        <div class="form-group">
                                                            <label>Password <span class="text-danger">*</span></label>
                                                            <input type="password" name="password" id="edit_password_<?php echo $staffGlobal['staff_id']; ?>" class="form-control" 
                                                                   placeholder="Enter password" minlength="8"
                                                                   data-error="Password must be at least 8 characters">
                                                            <div class="help-block with-errors"></div>
                                                            <small class="help-block">Minimum 8 characters</small>
                                                        </div>

                                                        <div class="form-group">
                                                            <label>Confirm Password <span class="text-danger">*</span></label>
                                                            <input type="password" name="confirm_password" id="edit_confirm_password_<?php echo $staffGlobal['staff_id']; ?>" class="form-control" 
                                                                   placeholder="Confirm password"
                                                                   data-match="#edit_password_<?php echo $staffGlobal['staff_id']; ?>" data-match-error="Passwords do not match">
                                                            <div class="help-block with-errors"></div>
                                                        </div>

                                                        <div class="alert alert-info">
                                                            <strong>Note:</strong> The employee will be automatically assigned a role based on their staff type.
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <button type="button" class="btn btn-lg btn-primary submit-edit-staff" data-emp-id="<?php echo $staffGlobal['staff_id']; ?>">Submit
                                            </button>
                                            <button type="reset" class="btn btn-lg btn-danger">Reset</button>
                                        </form>
                                    </div>
                                </div>
                            </div>


                        </div>

                    </div>
                </div>

            </div>
        </div>
        <?php
    }
}
?>

<!-- Change Shift Modal (shared for all employees) -->
<div id="changeShift" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Change Shift</h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="panel panel-default">
                            <div class="panel-body">
                                <form data-toggle="validator" role="form" action="ajax.php" method="post">
                                    <div class="row">
                                        <div class="form-group col-lg-12">
                                            <label>Shift</label>
                                            <select class="form-control" id="shift" name="shift" required>
                                                <option selected disabled>Select Shift</option>
                                                <?php
                                                $shifts = [
                                                    ['shift' => 'Morning/Day', 'shift_timing' => '7 AM-4 PM'],
                                                    ['shift' => 'Afternoon/Swing', 'shift_timing' => '3 PM-11 PM'],
                                                    ['shift' => 'Night/Graveyard', 'shift_timing' => '11 PM-8 AM']
                                                ];
                                                foreach ($shifts as $shift) {
                                                    echo '<option value="' . htmlspecialchars($shift['shift']) . '|' . htmlspecialchars($shift['shift_timing']) . '">' . htmlspecialchars($shift['shift'] . ' - ' . $shift['shift_timing']) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    <input type="hidden" name="emp_id" value="" id="getEmpId">
                                    <button type="submit" class="btn btn-lg btn-primary" name="change_shift">Submit</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Employee User Modal -->
<div id="createEmployeeUserModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-user-plus"></em> Create Employee User Account</h4>
            </div>
            <div class="modal-body">
                <form method="post" action="index.php?staff_mang" data-toggle="validator" id="createEmployeeUserForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="emp_id" id="create_emp_id">
                    <input type="hidden" name="create_employee_user" value="1">
                    
                    <div id="employeeInfo" style="display: none; margin-bottom: 20px;">
                        <div class="alert alert-info">
                            <h5>Employee Information:</h5>
                            <p><strong>Name:</strong> <span id="emp_name_display"></span></p>
                            <p><strong>Staff Type:</strong> <span id="emp_staff_type"></span></p>
                            <p><strong>Contact:</strong> <span id="emp_contact"></span></p>
                            <p><strong>Current Branch:</strong> <span id="emp_current_branch" class="label label-default">Not Assigned</span></p>
                            <p><strong>Assigned Role:</strong> <span id="emp_role" class="label label-info"></span></p>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Employee <span class="text-danger">*</span></label>
                        <select name="emp_id_select" id="emp_id_select" class="form-control" required onchange="loadEmployeeDetails()">
                            <option value="">Select an employee...</option>
                            <?php
                            mysqli_data_seek($staffWithoutUsersResult, 0);
                            while ($staff = mysqli_fetch_assoc($staffWithoutUsersResult)) {
                                $branchInfo = $staff['branch_name'] ? ' (' . htmlspecialchars($staff['branch_name']) . ')' : ' (No Branch)';
                                echo '<option value="' . $staff['staff_id'] . '" 
                                      data-name="' . htmlspecialchars($staff['staff_name']) . '"
                                      data-email="' . htmlspecialchars($staff['contact_no'] . '@hotel.local') . '"
                                      data-staff-type="' . htmlspecialchars($staff['staff_type']) . '"
                                      data-contact="' . htmlspecialchars($staff['contact_no']) . '"
                                      data-branch-id="' . ($staff['branch_id'] ?? '') . '"
                                      data-branch-name="' . htmlspecialchars($staff['branch_name'] ?? 'Not Assigned') . '">' . 
                                     htmlspecialchars($staff['staff_name'] . ' - ' . $staff['staff_type'] . $branchInfo) . 
                                     '</option>';
                            }
                            ?>
                        </select>
                        <small class="help-block">Only employees without user accounts are shown</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Assign to Branch <span class="text-danger">*</span></label>
                        <select name="branch_id" id="branch_id" class="form-control" required>
                            <option value="">Select a branch...</option>
                            <?php
                            mysqli_data_seek($branchesResult, 0);
                            while ($branch = mysqli_fetch_assoc($branchesResult)) {
                                echo '<option value="' . $branch['branch_id'] . '">' . 
                                     htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')') . 
                                     '</option>';
                            }
                            ?>
                        </select>
                        <small class="help-block">Select which branch this employee will be assigned to</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="username" class="form-control" 
                               placeholder="Enter username" required minlength="3" maxlength="15"
                               pattern="[a-zA-Z0-9_]+" 
                               data-error="Username must be 3-15 characters (letters, numbers, underscore only)">
                        <div class="help-block with-errors"></div>
                        <small class="help-block">3-15 characters, letters, numbers, and underscore only</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="email" class="form-control" 
                               placeholder="Enter email address" required>
                        <div class="help-block with-errors"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="password" class="form-control" 
                               placeholder="Enter password" required minlength="8"
                               data-error="Password must be at least 8 characters">
                        <div class="help-block with-errors"></div>
                        <small class="help-block">Minimum 8 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" 
                               placeholder="Confirm password" required
                               data-match="#password" data-match-error="Passwords do not match">
                        <div class="help-block with-errors"></div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Note:</strong> The employee will be automatically assigned a role based on their staff type. 
                        You can modify roles later from User Management.
                    </div>
                    
                    <div class="response"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitCreateEmployeeUser">
                    <em class="fa fa-user-plus"></em> Create User Account
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Modal (Combined Employee + User Account Creation) -->
<div id="addStaffModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-user-plus"></em> Add New Staff</h4>
            </div>
            <div class="modal-body">
                <div class="add-staff-response"></div>
                <form role="form" id="addStaffForm" data-toggle="validator">
                    <!-- Employee Details Section -->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h5><strong>Employee Information</strong></h5>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="form-group col-lg-6">
                                    <label>Staff Type <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <select class="form-control" id="add_staff_type" name="staff_type" required data-error="Select Staff Type">
                                            <option value="" selected disabled>Select Staff Type</option>
                                            <?php
                                            // Get staff types from staff_type table
                                            $query = "SELECT * FROM staff_type WHERE is_active = 1 ORDER BY staff_type";
                                            $result = mysqli_query($connection, $query);
                                            if (mysqli_num_rows($result) > 0) {
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    echo '<option value="' . $row['staff_type_id'] . '">' . htmlspecialchars($row['staff_type']) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-success" id="addStaffTypeBtn" data-toggle="modal" data-target="#addStaffTypeModal" title="Add New Staff Type">
                                                <em class="fa fa-plus"></em>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>Shift <span class="text-danger">*</span></label>
                                    <select class="form-control" id="add_shift" name="shift" required data-error="Select Shift">
                                        <option value="" selected disabled>Select Shift</option>
                                        <?php
                                        $shifts = [
                                            ['shift' => 'Morning/Day', 'shift_timing' => '7 AM-4 PM'],
                                            ['shift' => 'Afternoon/Swing', 'shift_timing' => '3 PM-11 PM'],
                                            ['shift' => 'Night/Graveyard', 'shift_timing' => '11 PM-8 AM']
                                        ];
                                        foreach ($shifts as $shift) {
                                            echo '<option value="' . htmlspecialchars($shift['shift']) . '|' . htmlspecialchars($shift['shift_timing']) . '">' . htmlspecialchars($shift['shift'] . ' - ' . $shift['shift_timing']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" placeholder="First Name" id="add_first_name" name="first_name" required data-error="Enter First Name">
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>Last Name</label>
                                    <input type="text" class="form-control" placeholder="Last Name" id="add_last_name" name="last_name">
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>ID Card Type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="add_id_card_id" name="id_card_type" required>
                                        <option value="" selected disabled>Select ID Card Type</option>
                                        <?php
                                        $query = "SELECT * FROM id_card_type";
                                        $result = mysqli_query($connection, $query);
                                        if (mysqli_num_rows($result) > 0) {
                                            while ($id_card_type = mysqli_fetch_assoc($result)) {
                                                echo '<option value="' . $id_card_type['id_card_type_id'] . '">' . htmlspecialchars($id_card_type['id_card_type']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>ID Card Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" placeholder="ID Card No" id="add_id_card_no" name="id_card_no" required>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>Contact Number <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" placeholder="Contact Number" id="add_contact_no" name="contact_no" required>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>Residential Address <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" placeholder="Residential Address" id="add_address" name="address" required>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group col-lg-6">
                                    <label>Salary <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" placeholder="Salary" id="add_salary" name="salary" required data-error="Enter Salary">
                                    <div class="help-block with-errors"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- User Account Section (Only for Super Admin) -->
                    <?php if (hasRole('super_admin')): ?>
                    <div class="panel panel-default" id="userAccountSection" style="display: none;">
                        <div class="panel-heading">
                            <h5><strong>User Account Creation (Optional)</strong></h5>
                        </div>
                        <div class="panel-body">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="create_user_account" name="create_user_account" value="1">
                                    Create user account for this employee
                                </label>
                            </div>

                            <div id="userAccountFields" style="display: none;">
                                <div class="form-group">
                                    <label>Assign to Branch <span class="text-danger">*</span></label>
                                    <select name="branch_id" id="add_branch_id" class="form-control">
                                        <option value="">Select a branch...</option>
                                        <?php
                                        mysqli_data_seek($branchesResult, 0);
                                        while ($branch = mysqli_fetch_assoc($branchesResult)) {
                                            echo '<option value="' . $branch['branch_id'] . '">' . 
                                                 htmlspecialchars($branch['branch_name'] . ' (' . $branch['branch_code'] . ')') . 
                                                 '</option>';
                                        }
                                        ?>
                                    </select>
                                    <small class="help-block">Select which branch this employee will be assigned to</small>
                                </div>

                                <div class="form-group">
                                    <label>Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" id="add_username" class="form-control" 
                                           placeholder="Enter username" minlength="3" maxlength="15"
                                           pattern="[a-zA-Z0-9_]+" 
                                           data-error="Username must be 3-15 characters (letters, numbers, underscore only)">
                                    <div class="help-block with-errors"></div>
                                    <small class="help-block">3-15 characters, letters, numbers, and underscore only</small>
                                </div>

                                <div class="form-group">
                                    <label>Email Address <span class="text-danger">*</span></label>
                                    <input type="email" name="email" id="add_email" class="form-control" 
                                           placeholder="Enter email address">
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group">
                                    <label>Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" id="add_password" class="form-control" 
                                           placeholder="Enter password" minlength="8"
                                           data-error="Password must be at least 8 characters">
                                    <div class="help-block with-errors"></div>
                                    <small class="help-block">Minimum 8 characters</small>
                                </div>

                                <div class="form-group">
                                    <label>Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="confirm_password" id="add_confirm_password" class="form-control" 
                                           placeholder="Confirm password"
                                           data-match="#add_password" data-match-error="Passwords do not match">
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="alert alert-info">
                                    <strong>Note:</strong> The employee will be automatically assigned a role based on their staff type.
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitAddStaff">
                    <em class="fa fa-user-plus"></em> Add Staff
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Type Modal -->
<div id="addStaffTypeModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-plus"></em> Add New Staff Type</h4>
            </div>
            <div class="modal-body">
                <div class="add-staff-type-response"></div>
                <form role="form" id="addStaffTypeForm" data-toggle="validator">
                    <div class="form-group">
                        <label>Staff Type Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="staff_type_name" name="staff_type_name" 
                               placeholder="e.g., Branch Admin, Receptionist, Housekeeping Attendant" required 
                               data-error="Enter Staff Type Name" pattern="[A-Za-z0-9\s]+" 
                               data-pattern-error="Only letters, numbers, and spaces allowed">
                        <div class="help-block with-errors"></div>
                        <small class="help-block">This staff type will be available when adding new staff members.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="submitAddStaffType">
                    <em class="fa fa-plus"></em> Add Staff Type
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for jQuery to be loaded (from footer.php)
(function() {
    function initStaffManagement() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initStaffManagement, 50);
            return;
        }
        
        var $ = jQuery;
        
        function loadEmployeeDetails() {
            var select = document.getElementById('emp_id_select');
            var option = select.options[select.selectedIndex];
            
            if (option.value) {
                document.getElementById('create_emp_id').value = option.value;
                document.getElementById('emp_name_display').textContent = option.getAttribute('data-name');
                document.getElementById('emp_staff_type').textContent = option.getAttribute('data-staff-type');
                document.getElementById('emp_contact').textContent = option.getAttribute('data-contact');
                
                // Display current branch
                var currentBranch = option.getAttribute('data-branch-name');
                var currentBranchId = option.getAttribute('data-branch-id');
                var branchDisplay = document.getElementById('emp_current_branch');
                branchDisplay.textContent = currentBranch;
                if (currentBranchId) {
                    branchDisplay.className = 'label label-info';
                    document.getElementById('branch_id').value = currentBranchId;
                } else {
                    branchDisplay.className = 'label label-default';
                    document.getElementById('branch_id').value = '';
                }
                
                // Set suggested email
                var suggestedEmail = option.getAttribute('data-email');
                document.getElementById('email').value = suggestedEmail;
                
                // Determine role based on staff type
                var staffType = option.getAttribute('data-staff-type');
                var roleMap = {
                    'branch admin': 'Administrator',
                    'receptionist': 'Receptionist',
                    'housekeeping attendant': 'Housekeeping Staff',
                    'Concierge': 'Concierge'
                };
                var role = roleMap[staffType] || 'Staff';
                document.getElementById('emp_role').textContent = role;
                
                // Suggest username (first name + last initial)
                var name = option.getAttribute('data-name').toLowerCase();
                var nameParts = name.split(' ');
                var suggestedUsername = nameParts[0];
                if (nameParts.length > 1) {
                    suggestedUsername += nameParts[nameParts.length - 1].charAt(0);
                }
                document.getElementById('username').value = suggestedUsername;
                
                document.getElementById('employeeInfo').style.display = 'block';
            } else {
                document.getElementById('employeeInfo').style.display = 'none';
                document.getElementById('create_emp_id').value = '';
                document.getElementById('branch_id').value = '';
            }
        }
        
        // Make loadEmployeeDetails available globally
        window.loadEmployeeDetails = loadEmployeeDetails;
        
        $(document).ready(function() {
            // Handle create employee user button click
            $(document).on('click', '.create-employee-user', function() {
                var empId = $(this).data('emp-id');
                var empName = $(this).data('emp-name');
                var staffType = $(this).data('staff-type');
                var contact = $(this).data('contact');
                var branchId = $(this).data('branch-id');
                var branchName = $(this).data('branch-name');
                
                // Reset form
                $('#createEmployeeUserForm')[0].reset();
                $('#create_emp_id').val('');
                $('#employeeInfo').hide();
                
                // If button clicked from header (no empId), just show modal with empty form
                if (!empId || empId === '') {
                    $('#emp_id_select').val('');
                    $('#createEmployeeUserModal').modal('show');
                    return;
                }
                
                // Set form values for row button click
                $('#create_emp_id').val(empId);
                $('#emp_id_select').val(empId);
                
                // Trigger the loadEmployeeDetails function to populate all fields
                if ($('#emp_id_select option[value="' + empId + '"]').length > 0) {
                    loadEmployeeDetails();
                } else {
                    // Manual fill if employee not in dropdown
                    $('#emp_name_display').text(empName);
                    $('#emp_staff_type').text(staffType);
                    $('#emp_contact').text(contact);
                    
                    // Set branch
                    if (branchId) {
                        $('#branch_id').val(branchId);
                        $('#emp_current_branch').text(branchName).removeClass('label-default').addClass('label label-info');
                    } else {
                        $('#branch_id').val('');
                        $('#emp_current_branch').text('Not Assigned').removeClass('label-info').addClass('label label-default');
                    }
                    
                    // Set suggested email and username
                    var suggestedEmail = contact + '@hotel.local';
                    $('#email').val(suggestedEmail);
                    
                    var nameParts = empName.toLowerCase().split(' ');
                    var suggestedUsername = nameParts[0];
                    if (nameParts.length > 1) {
                        suggestedUsername += nameParts[nameParts.length - 1].charAt(0);
                    }
                    $('#username').val(suggestedUsername);
                    
                    // Determine role
                    var roleMap = {
                        'branch admin': 'Administrator',
                        'receptionist': 'Receptionist',
                        'housekeeping attendant': 'Housekeeping Staff',
                        'Concierge': 'Concierge'
                    };
                    var role = roleMap[staffType] || 'Staff';
                    $('#emp_role').text(role);
                    
                    $('#employeeInfo').show();
                }
                
                $('#createEmployeeUserModal').modal('show');
            });
            
            // Handle form submission
            $('#submitCreateEmployeeUser').on('click', function() {
                // Update the hidden emp_id field with the selected value from dropdown
                var selectedEmpId = $('#emp_id_select').val();
                if (!selectedEmpId && $('#create_emp_id').val()) {
                    // If dropdown not selected but hidden field has value (from button click), use that
                    selectedEmpId = $('#create_emp_id').val();
                    $('#emp_id_select').val(selectedEmpId);
                }
                $('#create_emp_id').val(selectedEmpId);
                
                if ($('#createEmployeeUserForm')[0].checkValidity()) {
                    $('#createEmployeeUserForm').submit();
                } else {
                    $('#createEmployeeUserForm')[0].reportValidity();
                }
            });
            
            // Also handle form direct submission
            $('#createEmployeeUserForm').on('submit', function(e) {
                // Ensure emp_id is set from dropdown
                var selectedEmpId = $('#emp_id_select').val();
                if (selectedEmpId) {
                    $('#create_emp_id').val(selectedEmpId);
                }
            });
            
            // Handle "Create user account" checkbox toggle
            $('#create_user_account').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#userAccountFields').slideDown();
                    $('#add_branch_id').prop('required', true);
                    $('#add_username').prop('required', true);
                    $('#add_email').prop('required', true);
                    $('#add_password').prop('required', true);
                    $('#add_confirm_password').prop('required', true);
                    
                    // Auto-suggest username and email
                    var firstName = $('#add_first_name').val().toLowerCase();
                    var lastName = $('#add_last_name').val().toLowerCase();
                    var contact = $('#add_contact_no').val();
                    
                    if (firstName && !$('#add_username').val()) {
                        var suggestedUsername = firstName;
                        if (lastName) {
                            suggestedUsername += lastName.charAt(0);
                        }
                        $('#add_username').val(suggestedUsername);
                    }
                    
                    if (contact && !$('#add_email').val()) {
                        $('#add_email').val(contact + '@hotel.local');
                    }
                } else {
                    $('#userAccountFields').slideUp();
                    $('#add_branch_id').prop('required', false);
                    $('#add_username').prop('required', false);
                    $('#add_email').prop('required', false);
                    $('#add_password').prop('required', false);
                    $('#add_confirm_password').prop('required', false);
                }
            });
            
            // Show user account section when modal opens (only for super_admin)
            <?php if (hasRole('super_admin')): ?>
            $('#addStaffModal').on('show.bs.modal', function() {
                $('#userAccountSection').show();
            });
            
            // Auto-suggest username and email when name/contact changes
            $('#add_first_name, #add_last_name').on('blur', function() {
                if ($('#create_user_account').is(':checked') && !$('#add_username').val()) {
                    var firstName = $('#add_first_name').val().toLowerCase();
                    var lastName = $('#add_last_name').val().toLowerCase();
                    if (firstName) {
                        var suggestedUsername = firstName;
                        if (lastName) {
                            suggestedUsername += lastName.charAt(0);
                        }
                        $('#add_username').val(suggestedUsername);
                    }
                }
            });
            
            $('#add_contact_no').on('blur', function() {
                if ($('#create_user_account').is(':checked') && !$('#add_email').val()) {
                    var contact = $(this).val();
                    if (contact) {
                        $('#add_email').val(contact + '@hotel.local');
                    }
                }
            });
            <?php else: ?>
            // For non-super-admin users, hide user account section completely
            $('#addStaffModal').on('show.bs.modal', function() {
                $('#userAccountSection').hide();
            });
            <?php endif; ?>
            
            // Handle Add Staff form submission
            $('#submitAddStaff').on('click', function() {
                var form = $('#addStaffForm');
                
                // Validate employee fields first
                var staffType = $('#add_staff_type').val();
                var shift = $('#add_shift').val();
                var firstName = $('#add_first_name').val();
                var contactNo = $('#add_contact_no').val();
                var salary = $('#add_salary').val();
                
                if (!staffType || !shift || !firstName || !contactNo || !salary) {
                    $('.add-staff-response').html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Please fill all required employee fields.</div>');
                    return;
                }
                
                // If creating user account, validate user fields (only for super_admin)
                var createUserAccount = false;
                <?php if (hasRole('super_admin')): ?>
                createUserAccount = $('#create_user_account').is(':checked');
                <?php endif; ?>
                
                if (createUserAccount) {
                    var username = $('#add_username').val();
                    var email = $('#add_email').val();
                    var password = $('#add_password').val();
                    var confirmPassword = $('#add_confirm_password').val();
                    var branchId = $('#add_branch_id').val();
                    
                    if (!username || !email || !password || !confirmPassword || !branchId) {
                        $('.add-staff-response').html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Please fill all required user account fields.</div>');
                        return;
                    }
                    
                    if (password !== confirmPassword) {
                        $('.add-staff-response').html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Passwords do not match.</div>');
                        return;
                    }
                    
                    if (password.length < 8) {
                        $('.add-staff-response').html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Password must be at least 8 characters long.</div>');
                        return;
                    }
                }
                
                // Prepare form data
                var formData = {
                    add_employee: '1',
                    staff_type: staffType,
                    shift: shift,
                    first_name: firstName,
                    last_name: $('#add_last_name').val(),
                    id_card_id: $('#add_id_card_id').val(),
                    id_card_no: $('#add_id_card_no').val(),
                    contact_no: contactNo,
                    address: $('#add_address').val(),
                    salary: salary,
                    create_user_account: createUserAccount ? '1' : '0'
                };
                
                if (createUserAccount) {
                    formData.username = $('#add_username').val();
                    formData.email = $('#add_email').val();
                    formData.password = $('#add_password').val();
                    formData.confirm_password = $('#add_confirm_password').val();
                    formData.branch_id = $('#add_branch_id').val();
                    formData.csrf_token = '<?php echo generateCSRFToken(); ?>';
                }
                
                // Submit via AJAX
                $.ajax({
                    type: 'POST',
                    url: 'ajax.php',
                    dataType: 'JSON',
                    data: formData,
                    success: function(response) {
                        if (response.done == true || response.success == true) {
                            var successMessage = response.message || response.data || 'Staff added successfully!';
                            $('.add-staff-response').html('<div class="alert alert-success"><em class="fa fa-check-circle"></em> ' + successMessage + '</div>');
                            
                            // Reset form
                            form[0].reset();
                            <?php if (hasRole('super_admin')): ?>
                            $('#create_user_account').prop('checked', false);
                            $('#userAccountFields').hide();
                            <?php endif; ?>
                            
                            // Reload page after 2 seconds
                            setTimeout(function() {
                                window.location.href = 'index.php?staff_mang';
                            }, 2000);
                        } else {
                            var errorMessage = response.message || response.data || 'Error adding staff. Please try again.';
                            $('.add-staff-response').html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + errorMessage + '</div>');
                        }
                    },
                    error: function() {
                        $('.add-staff-response').html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> An error occurred. Please try again.</div>');
                    }
                });
            });
            
            // Reset form when modal is closed
            $('#addStaffModal').on('hidden.bs.modal', function() {
                $('#addStaffForm')[0].reset();
                <?php if (hasRole('super_admin')): ?>
                $('#create_user_account').prop('checked', false);
                $('#userAccountFields').hide();
                // Reset required attributes
                $('#add_branch_id, #add_username, #add_email, #add_password, #add_confirm_password').prop('required', false);
                <?php endif; ?>
                $('.add-staff-response').html('');
            });
            
            // Handle Add Staff Type form submission
            $('#submitAddStaffType').on('click', function() {
                var staffTypeName = $('#staff_type_name').val().trim();
                
                if (!staffTypeName) {
                    $('.add-staff-type-response').html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Please enter a staff type name.</div>');
                    return;
                }
                
                // Check if staff type already exists in dropdown
                var exists = false;
                $('#add_staff_type option').each(function() {
                    if ($(this).val().toLowerCase() === staffTypeName.toLowerCase()) {
                        exists = true;
                        return false;
                    }
                });
                
                if (exists) {
                    $('.add-staff-type-response').html('<div class="alert alert-warning"><em class="fa fa-exclamation-circle"></em> This staff type already exists.</div>');
                    return;
                }
                
                // Add to add staff dropdown
                var newOption = $('<option></option>').attr('value', staffTypeName).text(staffTypeName);
                $('#add_staff_type').append(newOption);
                $('#add_staff_type').val(staffTypeName);
                
                // Also add to all edit staff type dropdowns
                $('[id^="staff_type_"]').each(function() {
                    if (!$(this).find('option[value="' + staffTypeName + '"]').length) {
                        $(this).append(newOption.clone());
                    }
                });
                
                // Show success and close modal
                $('.add-staff-type-response').html('<div class="alert alert-success"><em class="fa fa-check-circle"></em> Staff type "' + staffTypeName + '" added successfully! You can now select it when adding staff.</div>');
                $('#staff_type_name').val('');
                
                setTimeout(function() {
                    $('#addStaffTypeModal').modal('hide');
                    $('.add-staff-type-response').html('');
                }, 1500);
            });
            
            // Reset staff type form when modal is closed
            $('#addStaffTypeModal').on('hidden.bs.modal', function() {
                $('#addStaffTypeForm')[0].reset();
                $('.add-staff-type-response').html('');
            });
            
            // Handle "Change Password" checkbox toggle for existing user accounts
            $(document).on('change', '[id^="edit_change_password_"]', function() {
                var empId = $(this).attr('id').replace('edit_change_password_', '');
                var passwordFields = $('#edit_passwordFields_' + empId);
                
                if (passwordFields.length === 0) {
                    return;
                }
                
                if ($(this).is(':checked')) {
                    passwordFields.slideDown();
                    $('#edit_password_' + empId).prop('required', true);
                    $('#edit_confirm_password_' + empId).prop('required', true);
                } else {
                    passwordFields.slideUp();
                    $('#edit_password_' + empId).prop('required', false).val('');
                    $('#edit_confirm_password_' + empId).prop('required', false).val('');
                }
            });
            
            // Handle "Create user account" checkbox toggle for edit forms
            $(document).on('change', '[id^="edit_create_user_account_"]', function() {
                var empId = $(this).attr('id').replace('edit_create_user_account_', '');
                var userFields = $('#edit_userAccountFields_' + empId);
                
                if (userFields.length === 0) {
                    return; // User account fields don't exist for this employee
                }
                
                if ($(this).is(':checked')) {
                    $('#edit_userAccountFields_' + empId).slideDown();
                    $('#edit_branch_id_' + empId).prop('required', true);
                    $('#edit_username_' + empId).prop('required', true);
                    $('#edit_email_' + empId).prop('required', true);
                    $('#edit_password_' + empId).prop('required', true);
                    $('#edit_confirm_password_' + empId).prop('required', true);
                    
                    // Auto-suggest username and email
                    var firstName = $('#first_name_' + empId).val().toLowerCase();
                    var lastName = $('#last_name_' + empId).val().toLowerCase();
                    var contact = $('#contact_no_' + empId).val();
                    
                    if (firstName && !$('#edit_username_' + empId).val()) {
                        var suggestedUsername = firstName;
                        if (lastName) {
                            suggestedUsername += lastName.charAt(0);
                        }
                        $('#edit_username_' + empId).val(suggestedUsername);
                    }
                    
                    if (contact && !$('#edit_email_' + empId).val()) {
                        $('#edit_email_' + empId).val(contact + '@hotel.local');
                    }
                } else {
                    $('#edit_userAccountFields_' + empId).slideUp();
                    $('#edit_branch_id_' + empId).prop('required', false);
                    $('#edit_username_' + empId).prop('required', false);
                    $('#edit_email_' + empId).prop('required', false);
                    $('#edit_password_' + empId).prop('required', false);
                    $('#edit_confirm_password_' + empId).prop('required', false);
                }
            });
            
            // Auto-suggest username and email when name/contact changes in edit form
            $(document).on('blur', '[id^="first_name_"], [id^="last_name_"]', function() {
                var id = $(this).attr('id');
                var empId = id.replace('first_name_', '').replace('last_name_', '');
                if ($('#edit_create_user_account_' + empId).is(':checked') && !$('#edit_username_' + empId).val()) {
                    var firstName = $('#first_name_' + empId).val().toLowerCase();
                    var lastName = $('#last_name_' + empId).val().toLowerCase();
                    if (firstName) {
                        var suggestedUsername = firstName;
                        if (lastName) {
                            suggestedUsername += lastName.charAt(0);
                        }
                        $('#edit_username_' + empId).val(suggestedUsername);
                    }
                }
            });
            
            $(document).on('blur', '[id^="contact_no_"]', function() {
                var id = $(this).attr('id');
                var empId = id.replace('contact_no_', '');
                if ($('#edit_create_user_account_' + empId).is(':checked') && !$('#edit_email_' + empId).val()) {
                    var contact = $(this).val();
                    if (contact) {
                        $('#edit_email_' + empId).val(contact + '@hotel.local');
                    }
                }
            });
            
            // Prevent form submission for edit forms
            $(document).on('submit', '.edit-staff-form', function(e) {
                e.preventDefault();
                return false;
            });
            
            // Handle Edit Staff form submission
            $(document).on('click', '.submit-edit-staff', function() {
                var empId = $(this).data('emp-id');
                var form = $('#editStaffForm_' + empId);
                var responseDiv = $('.edit-staff-response-' + empId);
                
                // Validate employee fields
                var staffType = $('#staff_type_' + empId).val();
                var shift = $('#shift_' + empId).val();
                var firstName = $('#first_name_' + empId).val();
                var contactNo = $('#contact_no_' + empId).val();
                var salary = $('#salary_' + empId).val();
                
                if (!staffType || !shift || !firstName || !contactNo || !salary) {
                    responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Please fill all required employee fields.</div>');
                    return;
                }
                
                // Check if editing existing user account
                var userId = $('#edit_user_id_' + empId).val();
                var hasUserAccount = userId && userId !== '';
                var updateUserAccount = false;
                var changePassword = false;
                
                // If creating user account, validate user fields
                var createUserAccount = $('#edit_create_user_account_' + empId).is(':checked');
                if (createUserAccount) {
                    var username = $('#edit_username_' + empId).val();
                    var email = $('#edit_email_' + empId).val();
                    var password = $('#edit_password_' + empId).val();
                    var confirmPassword = $('#edit_confirm_password_' + empId).val();
                    var branchId = $('#edit_branch_id_' + empId).val();
                    
                    if (!username || !email || !password || !confirmPassword || !branchId) {
                        responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Please fill all required user account fields.</div>');
                        return;
                    }
                    
                    if (password !== confirmPassword) {
                        responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Passwords do not match.</div>');
                        return;
                    }
                    
                    if (password.length < 8) {
                        responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Password must be at least 8 characters long.</div>');
                        return;
                    }
                } else if (hasUserAccount) {
                    // Validate user account fields for editing
                    var editUsername = $('#edit_username_' + empId).val();
                    var editEmail = $('#edit_email_' + empId).val();
                    
                    if (!editUsername || !editEmail) {
                        responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Please fill all required user account fields.</div>');
                        return;
                    }
                    
                    changePassword = $('#edit_change_password_' + empId).is(':checked');
                    if (changePassword) {
                        var editPassword = $('#edit_password_' + empId).val();
                        var editConfirmPassword = $('#edit_confirm_password_' + empId).val();
                        
                        if (!editPassword || !editConfirmPassword) {
                            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Please enter and confirm new password.</div>');
                            return;
                        }
                        
                        if (editPassword !== editConfirmPassword) {
                            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Passwords do not match.</div>');
                            return;
                        }
                        
                        if (editPassword.length < 8) {
                            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Password must be at least 8 characters long.</div>');
                            return;
                        }
                    }
                    
                    updateUserAccount = true;
                }
                
                // Get branch_id - use main branch field, or user account branch field if creating/updating account
                var branchId = $('#edit_branch_' + empId).val() || '';
                if (createUserAccount) {
                    // If creating user account, use the branch from user account section if provided
                    var userAccountBranchId = $('#edit_branch_id_' + empId).val();
                    if (userAccountBranchId) {
                        branchId = userAccountBranchId;
                    }
                } else if (updateUserAccount) {
                    // If updating user account, use the branch from user account section if provided
                    var userAccountBranchId = $('#edit_user_branch_id_' + empId).val();
                    if (userAccountBranchId) {
                        branchId = userAccountBranchId;
                    }
                }
                
                // Prepare form data
                var formData = {
                    update_employee: '1',
                    emp_id: empId,
                    staff_type: staffType,
                    shift: shift,
                    first_name: firstName,
                    last_name: $('#last_name_' + empId).val(),
                    id_card_type: $('#id_card_id_' + empId).val(),
                    id_card_no: $('#id_card_no_' + empId).val(),
                    contact_no: contactNo,
                    address: $('#address_' + empId).val(),
                    salary: salary,
                    branch_id: branchId,
                    create_user_account: createUserAccount ? '1' : '0',
                    update_user_account: updateUserAccount ? '1' : '0'
                };
                
                if (createUserAccount) {
                    formData.username = username;
                    formData.email = email;
                    formData.password = password;
                    formData.confirm_password = confirmPassword;
                    formData.csrf_token = '<?php echo generateCSRFToken(); ?>';
                } else if (updateUserAccount) {
                    formData.user_id = userId;
                    formData.edit_username = $('#edit_username_' + empId).val();
                    formData.edit_email = $('#edit_email_' + empId).val();
                    formData.edit_user_branch_id = $('#edit_user_branch_id_' + empId).val() || '';
                    formData.change_password = changePassword ? '1' : '0';
                    if (changePassword) {
                        formData.edit_password = $('#edit_password_' + empId).val();
                        formData.edit_confirm_password = $('#edit_confirm_password_' + empId).val();
                    }
                    formData.csrf_token = '<?php echo generateCSRFToken(); ?>';
                }
                
                // Submit via AJAX
                $.ajax({
                    type: 'POST',
                    url: 'ajax.php',
                    dataType: 'JSON',
                    data: formData,
                    success: function(response) {
                        if (response.done == true || response.success == true) {
                            var successMessage = response.message || response.data || 'Staff updated successfully!';
                            responseDiv.html('<div class="alert alert-success"><em class="fa fa-check-circle"></em> ' + successMessage + '</div>');
                            
                            // Reload page after 2 seconds
                            setTimeout(function() {
                                window.location.href = 'index.php?staff_mang';
                            }, 2000);
                        } else {
                            var errorMessage = response.message || response.data || 'Error updating staff. Please try again.';
                            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + errorMessage + '</div>');
                        }
                    },
                    error: function() {
                        responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> An error occurred. Please try again.</div>');
                    }
                });
            });
        });
    }
    initStaffManagement();
})();
</script>