<?php
/**
 * User Management Interface
 * Smart Hotel Management System
 */
include_once "../db.php";
require_once "../includes/auth.php";
require_once "../includes/rbac.php";
require_once "../includes/security.php";
require_once "../includes/audit.php";
session_start();

requireLogin();
// Super admin bypasses permission check
if (!hasRole('super_admin')) {
    requirePermission('user.read');
}

$user = getCurrentUser();
$page_title = "Guest Management";

$message = '';
$messageType = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        require_once "../includes/security.php";
        
        if (!verifyCSRFToken($_POST['csrf_token'])) {
            $message = 'Security token mismatch.';
            $messageType = 'danger';
        } else {
            $action = $_POST['action'];
            
            if ($action == 'update_guest' && hasPermission('user.update')) {
                $guest_id = intval($_POST['guest_id']);
                $name = sanitizeInput($_POST['name']);
                $email = sanitizeInput($_POST['email']);
                $phone = sanitizeInput($_POST['phone']);
                $status = sanitizeInput($_POST['status']);
                
                // Get old values for audit
                $oldQuery = "SELECT * FROM guests WHERE guest_id = ?";
                $oldStmt = mysqli_prepare($connection, $oldQuery);
                mysqli_stmt_bind_param($oldStmt, "i", $guest_id);
                mysqli_stmt_execute($oldStmt);
                $oldResult = mysqli_stmt_get_result($oldStmt);
                $oldGuest = mysqli_fetch_assoc($oldResult);
                mysqli_stmt_close($oldStmt);
                
                // Remove non-numeric characters from phone for contact_no
                $contact_no = !empty($phone) ? preg_replace('/[^0-9]/', '', $phone) : null;
                $contact_no = !empty($contact_no) && is_numeric($contact_no) ? intval($contact_no) : null;
                
                $updateQuery = "UPDATE guests SET name = ?, email = ?, phone = ?, contact_no = ?, status = ? WHERE guest_id = ?";
                $updateStmt = mysqli_prepare($connection, $updateQuery);
                mysqli_stmt_bind_param($updateStmt, "sssisi", $name, $email, $phone, $contact_no, $status, $guest_id);
                
                if (mysqli_stmt_execute($updateStmt)) {
                    logAuditEvent('guest_updated', 'guests', 'guest', $guest_id, $oldGuest, [
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'status' => $status
                    ]);
                    $message = 'Guest updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error updating guest.';
                    $messageType = 'danger';
                }
                mysqli_stmt_close($updateStmt);
            }
        }
    }
}

// Get all guests
$guestsQuery = "SELECT * FROM guests ORDER BY created_at DESC";
$guestsResult = mysqli_query($connection, $guestsQuery);

// Ensure audit functions are available
if (!function_exists('logAuditEvent')) {
    require_once "../includes/audit.php";
}

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
            <li class="active">Guest Management</li>
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
                <em class="fa fa-users"></em> Guest Management
            </h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <em class="fa fa-list"></em> All Guests
                    <?php if (hasPermission('user.create')): ?>
                    <div class="pull-right">
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addGuestModal" style="border-radius: 4px; font-weight: 500; padding: 8px 16px;">
                            <em class="fa fa-user-plus"></em> Add New Guest
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="guestsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($guestsResult && mysqli_num_rows($guestsResult) > 0):
                                    mysqli_data_seek($guestsResult, 0);
                                    while ($guestRow = mysqli_fetch_assoc($guestsResult)): 
                                ?>
                                <tr>
                                    <td><?php echo $guestRow['guest_id']; ?></td>
                                    <td><?php echo htmlspecialchars($guestRow['name']); ?></td>
                                    <td><?php echo htmlspecialchars($guestRow['username']); ?></td>
                                    <td><?php echo htmlspecialchars($guestRow['email']); ?></td>
                                    <td><?php echo htmlspecialchars($guestRow['phone'] ?? ($guestRow['contact_no'] ?? '-')); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = $guestRow['status'] == 'active' ? 'success' : 
                                                     ($guestRow['status'] == 'suspended' ? 'danger' : 'warning');
                                        echo '<span class="label label-' . $statusClass . '">' . 
                                             ucfirst($guestRow['status'] ?? 'active') . '</span>';
                                        ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($guestRow['created_at'])); ?></td>
                                    <td><?php echo $guestRow['last_login'] ? date('M j, Y g:i A', strtotime($guestRow['last_login'])) : 'Never'; ?></td>
                                    <td>
                                        <?php if (hasPermission('user.update')): ?>
                                        <button class="btn btn-info btn-sm edit-guest" 
                                                data-guest-id="<?php echo $guestRow['guest_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($guestRow['name']); ?>"
                                                data-email="<?php echo htmlspecialchars($guestRow['email']); ?>"
                                                data-phone="<?php echo htmlspecialchars($guestRow['phone'] ?? ($guestRow['contact_no'] ?? '')); ?>"
                                                data-status="<?php echo $guestRow['status'] ?? 'active'; ?>">
                                            <em class="fa fa-edit"></em> Edit
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No guests found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Guest Modal -->
<div id="editGuestModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-edit"></em> Edit Guest</h4>
            </div>
            <div class="modal-body">
                <form id="editGuestForm" method="post">
                    <input type="hidden" name="action" value="update_guest">
                    <input type="hidden" name="guest_id" id="edit_guest_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" id="edit_phone" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Status <span class="text-danger">*</span></label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    
                    <div class="response"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 4px;">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveGuestEdit">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addGuestModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-user-plus"></em> Add New Guest</h4>
            </div>
            <div class="modal-body">
                <div class="add-guest-response"></div>
                <form id="addGuestForm" data-toggle="validator">
                    <input type="hidden" name="register" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label>Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="add_guest_name" class="form-control" 
                               placeholder="Enter full name" required data-error="Enter full name" autocomplete="name">
                        <div class="help-block with-errors"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="add_guest_username" class="form-control" 
                               placeholder="Choose a username" required data-error="Enter a username" 
                               autocomplete="username" minlength="3" maxlength="15" pattern="[a-zA-Z0-9_]+"
                               data-error="Username must be 3-15 characters (letters, numbers, underscore only)">
                        <div class="help-block with-errors"></div>
                        <small class="help-block">3-15 characters, letters, numbers, and underscore only</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="add_guest_email" class="form-control" 
                               placeholder="Enter email address" required data-error="Enter a valid email address" 
                               autocomplete="email">
                        <div class="help-block with-errors"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" id="add_guest_phone" class="form-control" 
                               placeholder="Enter phone number" autocomplete="tel">
                        <div class="help-block with-errors"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="add_guest_password" class="form-control" 
                               placeholder="Enter password" required data-error="Password must be at least 8 characters" 
                               minlength="8" autocomplete="new-password">
                        <div class="help-block with-errors"></div>
                        <small class="help-block">Minimum 8 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="add_guest_confirm_password" class="form-control" 
                               placeholder="Confirm password" required data-match="#add_guest_password" 
                               data-match-error="Passwords do not match" autocomplete="new-password">
                        <div class="help-block with-errors"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="addGuestSave" style="border-radius: 4px; font-weight: 500;">
                    <em class="fa fa-save"></em> Save Guest
                </button>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-1.11.1.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
<script src="../js/jquery.dataTables.min.js"></script>
<script src="../js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#guestsTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]]
    });
    
    // Edit guest
    $(document).on('click', '.edit-guest', function() {
        $('#edit_guest_id').val($(this).data('guest-id'));
        $('#edit_name').val($(this).data('name'));
        $('#edit_email').val($(this).data('email'));
        $('#edit_phone').val($(this).data('phone'));
        $('#edit_status').val($(this).data('status'));
        $('#editGuestModal').modal('show');
    });
    
    // Save guest (edit)
    $('#saveGuestEdit').on('click', function() {
        var form = $('#editGuestForm');
        var responseDiv = form.find('.response');
        
        // Validate form
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        
        // Submit form
        $.ajax({
            type: 'POST',
            url: window.location.href,
            data: form.serialize(),
            success: function(response) {
                // Reload page to show updated data
                window.location.reload();
            },
            error: function() {
                responseDiv.html('<div class="alert alert-danger">Error updating guest. Please try again.</div>');
            }
        });
    });
    
    // Handle Add Guest form submission
    $('#addGuestSave').on('click', function() {
        var form = $('#addGuestForm');
        var responseDiv = $('.add-guest-response');
        
        // Validate form
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        
        // Get form data
        var formData = {
            guest_register: '1',
            csrf_token: $('#addGuestForm input[name="csrf_token"]').val(),
            name: $('#add_guest_name').val(),
            username: $('#add_guest_username').val(),
            email: $('#add_guest_email').val(),
            phone: $('#add_guest_phone').val() || '',
            password: $('#add_guest_password').val(),
            password_confirm: $('#add_guest_confirm_password').val()
        };
        
        // Validate passwords match
        if (formData.password !== formData.password_confirm) {
            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Passwords do not match.</div>');
            return;
        }
        
        // Validate password length
        if (formData.password.length < 8) {
            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Password must be at least 8 characters long.</div>');
            return;
        }
        
        // Submit via AJAX
        $.ajax({
            type: 'POST',
            url: '../ajax.php',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: formData,
            success: function(response) {
                if (response.success || response.done) {
                    responseDiv.html('<div class="alert alert-success"><em class="fa fa-check-circle"></em> Guest added successfully!</div>');
                    
                    // Reset form
                    form[0].reset();
                    
                    // Reload page after 2 seconds
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    var errorMsg = response.message || response.error || 'Error adding user. Please try again.';
                    responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + errorMsg + '</div>');
                }
            },
            error: function(xhr, status, error) {
                // Handle non-JSON responses (like redirects)
                if (xhr.responseText) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.message || response.error) {
                            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> ' + (response.message || response.error) + '</div>');
                        } else {
                            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> An error occurred. Please try again.</div>');
                        }
                    } catch(e) {
                        // If response is not JSON, check for common error patterns
                        if (xhr.responseText.includes('email_exists') || xhr.responseText.includes('username_exists')) {
                            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> Email or username already exists.</div>');
                        } else {
                            responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> An error occurred. Please try again.</div>');
                        }
                    }
                } else {
                    responseDiv.html('<div class="alert alert-danger"><em class="fa fa-exclamation-circle"></em> An error occurred. Please try again.</div>');
                }
            }
        });
    });
    
    // Reset form when modal is closed
    $('#addGuestModal').on('hidden.bs.modal', function() {
        $('#addGuestForm')[0].reset();
        $('.add-guest-response').html('');
    });
});
</script>

<?php include_once "../footer.php"; ?>

