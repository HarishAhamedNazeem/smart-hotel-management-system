<?php
/**
 * Create Employee User Account
 * Super Admin can create login credentials for employees
 * Smart Hotel Management System
 */
include_once "../db.php";
require_once "../includes/auth.php";
require_once "../includes/rbac.php";
require_once "../includes/security.php";
require_once "../includes/audit.php";
session_start();

requireLogin();
// Only super admin can create employee accounts
if (!hasRole('super_admin')) {
    header('Location: ../index.php?dashboard&error=access_denied');
    exit();
}

$user = getCurrentUser();
$page_title = "Create Employee User Account";

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_employee_user'])) {
    require_once "../includes/security.php";
    
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Security token mismatch.';
        $messageType = 'danger';
    } else {
        $staff_id = intval($_POST['emp_id']); // Note: form parameter name is 'emp_id', but column is now 'staff_id'
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($staff_id) || empty($username) || empty($email) || empty($password)) {
            $message = 'Please fill all required fields.';
            $messageType = 'danger';
        } elseif ($password !== $confirm_password) {
            $message = 'Passwords do not match.';
            $messageType = 'danger';
        } elseif (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters long.';
            $messageType = 'danger';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email address.';
            $messageType = 'danger';
        } else {
            // Check if email exists using function from auth.php
            require_once "../includes/auth.php";
            if (emailExists($email)) {
                $message = 'Email already exists.';
                $messageType = 'danger';
            } elseif (usernameExists($username)) {
                $message = 'Username already exists.';
                $messageType = 'danger';
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
                    $message = 'Staff member not found.';
                    $messageType = 'danger';
                } elseif ($staff['user_id']) {
                    $message = 'This employee already has a user account.';
                    $messageType = 'warning';
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
                        logAuditEvent('employee_user_created', 'users', 'user', $user_id, null, [
                            'staff_id' => $staff_id,
                            'staff_type' => $staff['staff_type_id'],
                            'username' => $username,
                            'branch_id' => $branch_id
                        ]);
                        
                        mysqli_stmt_close($insertStmt);
                        $message = 'Employee user account created successfully! Username: ' . htmlspecialchars($username);
                        $messageType = 'success';
                    } else {
                        mysqli_stmt_close($insertStmt);
                        $message = 'Error creating user account.';
                        $messageType = 'danger';
                    }
                }
            }
        }
    }
}

// Get staff members without user accounts
$staffQuery = "SELECT s.*, st.staff_type, sh.shift, b.branch_name, b.branch_code
               FROM staff s
               JOIN staff_type st ON s.staff_type_id = st.staff_type_id
               JOIN shift sh ON s.shift_id = sh.shift_id
               LEFT JOIN branches b ON s.branch_id = b.branch_id
               WHERE s.user_id IS NULL
               ORDER BY s.staff_name";
$staffResult = mysqli_query($connection, $staffQuery);

// Get all branches for branch selection
$branchesQuery = "SELECT branch_id, branch_name, branch_code FROM branches WHERE status = 'active' ORDER BY branch_name";
$branchesResult = mysqli_query($connection, $branchesQuery);

include_once "../header.php";

// Load appropriate sidebar
if (file_exists('../includes/sidebar_loader.php')) {
    include_once '../includes/sidebar_loader.php';
} else {
    include_once "../sidebars/sidebar.php";
}
?>

<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
        <ol class="breadcrumb">
            <li><a href="#"><em class="fa fa-home"></em></a></li>
            <li><a href="users.php">User Management</a></li>
            <li class="active">Create Employee User</li>
        </ol>
    </div>

    <?php if ($message): ?>
    <div class="row">
        <div class="col-lg-12">
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo htmlspecialchars($message); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">
                <em class="fa fa-user-plus"></em> Create Employee User Account
            </h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <em class="fa fa-plus-circle"></em> Create Login Credentials for Employee
                </div>
                <div class="panel-body">
                    <form method="post" action="" data-toggle="validator" id="createEmployeeUserForm">
                        <?php
                        $csrf_token = generateCSRFToken();
                        ?>
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-group">
                            <label>Select Employee <span class="text-danger">*</span></label>
                            <select name="emp_id" id="emp_id" class="form-control" required onchange="loadEmployeeDetails()">
                                <option value="">Select an employee...</option>
                                <?php
                                mysqli_data_seek($staffResult, 0); // Reset pointer
                                while ($staff = mysqli_fetch_assoc($staffResult)) {
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
                            <label>Assign to Branch <span class="text-danger">*</span></label>
                            <select name="branch_id" id="branch_id" class="form-control" required>
                                <option value="">Select a branch...</option>
                                <?php
                                mysqli_data_seek($branchesResult, 0); // Reset pointer
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
                        
                        <button type="submit" name="create_employee_user" class="btn btn-primary btn-lg">
                            <em class="fa fa-user-plus"></em> Create User Account
                        </button>
                        <a href="users.php" class="btn btn-secondary btn-lg" style="border-radius: 4px;">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <em class="fa fa-info-circle"></em> Instructions
                </div>
                <div class="panel-body">
                    <h5>Creating Employee Accounts</h5>
                    <ol>
                        <li>Select an employee from the list</li>
                        <li>Select the branch to assign the employee to</li>
                        <li>Enter a unique username</li>
                        <li>Enter email address</li>
                        <li>Set a secure password</li>
                        <li>Role will be assigned automatically based on staff type</li>
                    </ol>
                    
                    <hr>
                    
                    <h5>Role Mapping</h5>
                    <ul>
                        <li><strong>branch admin</strong> → Administrator</li>
                        <li><strong>receptionist</strong> → Receptionist</li>
                        <li><strong>housekeeping attendant</strong> → Housekeeping Staff</li>
                        <li><strong>Concierge</strong> → Concierge</li>
                    </ul>
                    
                    <hr>
                    
                    <h5>After Creation</h5>
                    <p>The employee can login using the username and password you create. 
                    Their menu access will be based on their assigned role.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-1.11.1.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
<script src="../js/validator.min.js"></script>
<script>
function loadEmployeeDetails() {
    var select = document.getElementById('emp_id');
    var option = select.options[select.selectedIndex];
    
    if (option.value) {
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
            // Pre-select the branch in the dropdown
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
        document.getElementById('branch_id').value = '';
    }
}

// Auto-load if emp_id is in URL
$(document).ready(function() {
    <?php if (isset($_GET['emp_id'])): ?>
    loadEmployeeDetails();
    <?php endif; ?>
});
</script>

<?php include_once "../footer.php"; ?>

