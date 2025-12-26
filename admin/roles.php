<?php
/**
 * Role Management Interface
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
    requirePermission('user.manage_roles');
}

$user = getCurrentUser();
$page_title = "Role Management";

$message = '';
$messageType = '';

// Handle role actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    require_once "../includes/security.php";
    
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Security token mismatch.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'];
        
        if ($action == 'update_role') {
            $role_id = intval($_POST['role_id']);
            $role_name = sanitizeInput($_POST['role_name']);
            $role_description = sanitizeInput($_POST['role_description']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $updateQuery = "UPDATE roles SET role_name = ?, role_description = ?, is_active = ? WHERE role_id = ?";
            $updateStmt = mysqli_prepare($connection, $updateQuery);
            mysqli_stmt_bind_param($updateStmt, "ssii", $role_name, $role_description, $is_active, $role_id);
            
            if (mysqli_stmt_execute($updateStmt)) {
                logAuditEvent('role_updated', 'roles', 'role', $role_id);
                clearRBACCache();
                $message = 'Role updated successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error updating role.';
                $messageType = 'danger';
            }
            mysqli_stmt_close($updateStmt);
        } elseif ($action == 'update_permissions') {
            $role_id = intval($_POST['role_id']);
            $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
            
            // Remove all existing permissions
            $deleteQuery = "DELETE FROM role_permissions WHERE role_id = ?";
            $deleteStmt = mysqli_prepare($connection, $deleteQuery);
            mysqli_stmt_bind_param($deleteStmt, "i", $role_id);
            mysqli_stmt_execute($deleteStmt);
            mysqli_stmt_close($deleteStmt);
            
            // Add new permissions
            if (!empty($permissions)) {
                $insertQuery = "INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)";
                $insertStmt = mysqli_prepare($connection, $insertQuery);
                
                foreach ($permissions as $permission_id) {
                    mysqli_stmt_bind_param($insertStmt, "ii", $role_id, $permission_id);
                    mysqli_stmt_execute($insertStmt);
                }
                mysqli_stmt_close($insertStmt);
            }
            
            logAuditEvent('role_permissions_updated', 'roles', 'role', $role_id);
            clearRBACCache();
            $message = 'Permissions updated successfully!';
            $messageType = 'success';
        }
    }
}

// Get all roles
$rolesQuery = "SELECT r.*, 
              COUNT(DISTINCT ur.user_id) as user_count,
              COUNT(DISTINCT rp.permission_id) as permission_count
              FROM roles r
              LEFT JOIN user_roles ur ON r.role_id = ur.role_id
              LEFT JOIN role_permissions rp ON r.role_id = rp.role_id
              GROUP BY r.role_id
              ORDER BY r.role_name";
$rolesResult = mysqli_query($connection, $rolesQuery);

// Get all permissions grouped by module
// Ensure we get ALL permissions including newly added ones - no filters, no conditions
$permissionsQuery = "SELECT permission_id, permission_name, permission_description, module, created_at 
                     FROM permissions 
                     ORDER BY COALESCE(NULLIF(TRIM(module), ''), 'other'), permission_name ASC";
$permissionsResult = mysqli_query($connection, $permissionsQuery);

if (!$permissionsResult) {
    die("Error fetching permissions: " . mysqli_error($connection));
}

$permissionsByModule = [];
$permissionIds = []; // Track all permission IDs for verification

// Fetch all permissions
while ($permission = mysqli_fetch_assoc($permissionsResult)) {
    // Normalize module name - handle NULL, empty strings, and whitespace
    $module = (!empty($permission['module']) && trim($permission['module']) !== '') 
              ? trim($permission['module']) 
              : 'other';
    
    if (!isset($permissionsByModule[$module])) {
        $permissionsByModule[$module] = [];
    }
    
    $permissionsByModule[$module][] = $permission;
    $permissionIds[] = $permission['permission_id'];
}

// Count total permissions loaded
$totalPermissionsLoaded = count($permissionIds);

// Debug: Log if we're missing permissions (optional - can be removed in production)
// This helps identify if permissions exist in DB but aren't being displayed
if (defined('DEBUG_PERMISSIONS') && DEBUG_PERMISSIONS) {
    error_log("Permissions loaded: " . count($permissionIds) . " total permissions from " . count($permissionsByModule) . " modules");
}

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
            <li class="active">Role Management</li>
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
                <em class="fa fa-key"></em> Role Management
            </h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <em class="fa fa-list"></em> System Roles
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Role ID</th>
                                    <th>Role Name</th>
                                    <th>Description</th>
                                    <th>Users</th>
                                    <th>Permissions</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($role = mysqli_fetch_assoc($rolesResult)): ?>
                                <tr>
                                    <td><?php echo $role['role_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($role['role_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($role['role_description'] ?? '-'); ?></td>
                                    <td><span class="badge"><?php echo $role['user_count']; ?></span></td>
                                    <td><span class="badge"><?php echo $role['permission_count']; ?></span></td>
                                    <td>
                                        <?php
                                        $statusClass = $role['is_active'] ? 'success' : 'danger';
                                        echo '<span class="label label-' . $statusClass . '">' . 
                                             ($role['is_active'] ? 'Active' : 'Inactive') . '</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-info btn-sm edit-role" 
                                                data-role-id="<?php echo $role['role_id']; ?>"
                                                data-role-name="<?php echo htmlspecialchars($role['role_name']); ?>"
                                                data-role-description="<?php echo htmlspecialchars($role['role_description'] ?? ''); ?>"
                                                data-is-active="<?php echo $role['is_active']; ?>">
                                            <em class="fa fa-edit"></em> Edit
                                        </button>
                                        <button class="btn btn-success btn-sm manage-permissions" 
                                                data-role-id="<?php echo $role['role_id']; ?>"
                                                data-role-name="<?php echo htmlspecialchars($role['role_name']); ?>">
                                            <em class="fa fa-lock"></em> Permissions
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div id="editRoleModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-edit"></em> Edit Role</h4>
            </div>
            <div class="modal-body">
                <form id="editRoleForm" method="post">
                    <input type="hidden" name="action" value="update_role">
                    <input type="hidden" name="role_id" id="edit_role_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label>Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="role_name" id="edit_role_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="role_description" id="edit_role_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" id="edit_is_active" value="1"> Active
                        </label>
                    </div>
                    
                    <div class="response"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 4px;">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveRole">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Permissions Modal -->
<div id="managePermissionsModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">
                    <em class="fa fa-lock"></em> Manage Permissions for <span id="permissions_role_name"></span>
                    <small class="text-muted">
                        (Total: <?php 
                        $totalPerms = 0;
                        foreach ($permissionsByModule as $perms) {
                            $totalPerms += count($perms);
                        }
                        echo $totalPerms; 
                        ?> permissions from <?php echo count($permissionsByModule); ?> modules)
                    </small>
                </h4>
                <?php if (isset($totalPermissionsLoaded) && $totalPermissionsLoaded != $totalPerms): ?>
                <div class="alert alert-warning" style="margin-top: 10px; margin-bottom: 0;">
                    <small><em class="fa fa-info-circle"></em> Warning: Loaded <?php echo $totalPermissionsLoaded; ?> permissions but displaying <?php echo $totalPerms; ?>. Please refresh the page.</small>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                <form id="permissionsForm" method="post">
                    <input type="hidden" name="action" value="update_permissions">
                    <input type="hidden" name="role_id" id="permissions_role_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <?php if (empty($permissionsByModule)): ?>
                    <div class="alert alert-warning">
                        <em class="fa fa-exclamation-triangle"></em> No permissions found in the system.
                        <br><small>If you just added new permissions, please refresh this page.</small>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info" style="margin-bottom: 15px;">
                        <em class="fa fa-info-circle"></em> <strong>Note:</strong> If you just added new menu items and their permissions don't appear here, 
                        <a href="add_missing_permissions.php" target="_blank">run the permission script</a> first, then refresh this page.
                    </div>
                        <?php 
                        // Sort modules alphabetically for better organization
                        ksort($permissionsByModule);
                        foreach ($permissionsByModule as $module => $permissions): 
                            // Sort permissions within each module by name
                            usort($permissions, function($a, $b) {
                                return strcmp($a['permission_name'], $b['permission_name']);
                            });
                        ?>
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <strong><?php echo htmlspecialchars(ucfirst($module ?: 'Other')); ?></strong>
                                <span class="badge pull-right"><?php echo count($permissions); ?></span>
                            </div>
                            <div class="panel-body">
                                <?php foreach ($permissions as $permission): ?>
                                <div class="checkbox">
                                    <label>
                                        <input type="checkbox" name="permissions[]" 
                                               value="<?php echo intval($permission['permission_id']); ?>"
                                               class="permission-checkbox"
                                               data-permission-id="<?php echo intval($permission['permission_id']); ?>"
                                               id="perm_<?php echo intval($permission['permission_id']); ?>">
                                        <strong><?php echo htmlspecialchars($permission['permission_name']); ?></strong>
                                        <?php if (!empty($permission['permission_description'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($permission['permission_description']); ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <div class="response"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" onclick="window.location.reload();" style="border-radius: 4px;">
                    <em class="fa fa-refresh"></em> Refresh Page
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 4px;">Cancel</button>
                <button type="button" class="btn btn-primary" id="savePermissions">Save Permissions</button>
            </div>
        </div>
    </div>
</div>

<script src="../js/jquery-1.11.1.min.js"></script>
<script src="../js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    // Edit role
    $('.edit-role').on('click', function() {
        $('#edit_role_id').val($(this).data('role-id'));
        $('#edit_role_name').val($(this).data('role-name'));
        $('#edit_role_description').val($(this).data('role-description'));
        $('#edit_is_active').prop('checked', $(this).data('is-active') == 1);
        $('#editRoleModal').modal('show');
    });
    
    // Save role
    $('#saveRole').on('click', function() {
        $('#editRoleForm').submit();
    });
    
    // Manage permissions
    $('.manage-permissions').on('click', function() {
        var roleId = $(this).data('role-id');
        var roleName = $(this).data('role-name');
        $('#permissions_role_id').val(roleId);
        $('#permissions_role_name').text(roleName);
        
        // Show modal first to ensure DOM is ready
        $('#managePermissionsModal').modal('show');
        
        // Uncheck all first
        $('.permission-checkbox').prop('checked', false);
        
        // Load current permissions
        $.ajax({
            url: '../ajax.php',
            type: 'POST',
            data: {
                action: 'get_role_permissions',
                role_id: roleId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.permissions) {
                    // Check each permission
                    response.permissions.forEach(function(permId) {
                        var checkbox = $('.permission-checkbox[data-permission-id="' + permId + '"]');
                        if (checkbox.length > 0) {
                            checkbox.prop('checked', true);
                        } else {
                            console.warn('Permission checkbox not found for ID: ' + permId);
                        }
                    });
                } else {
                    console.error('Failed to load permissions:', response.message || 'Unknown error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error loading permissions:', error);
                alert('Error loading permissions. Please refresh the page and try again.');
            }
        });
    });
    
    // Save permissions
    $('#savePermissions').on('click', function() {
        $('#permissionsForm').submit();
    });
});
</script>

<?php include_once "../footer.php"; ?>

