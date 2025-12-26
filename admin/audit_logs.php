<?php
/**
 * Audit Log Viewer
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
    requirePermission('system.audit');
}

$user = getCurrentUser();
$page_title = "Audit Logs";

// Get filter parameters
$filter_user = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$filter_action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : null;
$filter_module = isset($_GET['module']) ? sanitizeInput($_GET['module']) : null;
$filter_date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : null;
$filter_date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : null;

$filters = [
    'user_id' => $filter_user,
    'action' => $filter_action,
    'module' => $filter_module,
    'date_from' => $filter_date_from,
    'date_to' => $filter_date_to,
    'limit' => 500
];

$auditLogs = getAuditLogs($filters);
$auditStats = getAuditStatistics(30);

// Get all users for filter
$usersQuery = "SELECT id, name, username FROM user ORDER BY name";
$usersResult = mysqli_query($connection, $usersQuery);

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
            <li class="active">Audit Logs</li>
        </ol>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <h1 class="page-header">
                <em class="fa fa-history"></em> Audit Logs
            </h1>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-md-3">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <em class="fa fa-list fa-5x"></em>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo number_format($auditStats['total_logs']); ?></div>
                            <div>Total Logs (30 days)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-success">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <em class="fa fa-users fa-5x"></em>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $auditStats['unique_users']; ?></div>
                            <div>Active Users</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <em class="fa fa-bolt fa-5x"></em>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $auditStats['unique_actions']; ?></div>
                            <div>Unique Actions</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-warning">
                <div class="panel-heading">
                    <div class="row">
                        <div class="col-xs-3">
                            <em class="fa fa-folder fa-5x"></em>
                        </div>
                        <div class="col-xs-9 text-right">
                            <div class="huge"><?php echo $auditStats['unique_modules']; ?></div>
                            <div>Modules</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <em class="fa fa-filter"></em> Filters
                </div>
                <div class="panel-body">
                    <form method="get" action="" class="form-inline">
                        <div class="form-group">
                            <label>User:</label>
                            <select name="user_id" class="form-control">
                                <option value="">All Users</option>
                                <?php
                                mysqli_data_seek($usersResult, 0);
                                while ($userRow = mysqli_fetch_assoc($usersResult)) {
                                    $selected = $filter_user == $userRow['id'] ? 'selected' : '';
                                    echo '<option value="' . $userRow['id'] . '" ' . $selected . '>' . 
                                         htmlspecialchars($userRow['name'] . ' (' . $userRow['username'] . ')') . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Action:</label>
                            <input type="text" name="action" class="form-control" 
                                   value="<?php echo htmlspecialchars($filter_action ?? ''); ?>" 
                                   placeholder="Search action...">
                        </div>
                        <div class="form-group">
                            <label>Module:</label>
                            <select name="module" class="form-control">
                                <option value="">All Modules</option>
                                <option value="users" <?php echo $filter_module == 'users' ? 'selected' : ''; ?>>Users</option>
                                <option value="bookings" <?php echo $filter_module == 'bookings' ? 'selected' : ''; ?>>Bookings</option>
                                <option value="rooms" <?php echo $filter_module == 'rooms' ? 'selected' : ''; ?>>Rooms</option>
                                <option value="staff" <?php echo $filter_module == 'staff' ? 'selected' : ''; ?>>Staff</option>
                                <option value="services" <?php echo $filter_module == 'services' ? 'selected' : ''; ?>>Services</option>
                                <option value="security" <?php echo $filter_module == 'security' ? 'selected' : ''; ?>>Security</option>
                                <option value="roles" <?php echo $filter_module == 'roles' ? 'selected' : ''; ?>>Roles</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>From Date:</label>
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($filter_date_from ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>To Date:</label>
                            <input type="date" name="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($filter_date_to ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <em class="fa fa-search"></em> Filter
                        </button>
                        <a href="audit_logs.php" class="btn btn-default">Clear</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <em class="fa fa-list"></em> Audit Log Entries
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="auditTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Module</th>
                                    <th>Resource</th>
                                    <th>IP Address</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($auditLogs as $log): ?>
                                <tr>
                                    <td><?php echo $log['log_id']; ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <?php if ($log['username']): ?>
                                            <?php echo htmlspecialchars($log['user_name'] ?? $log['username']); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($log['username']); ?></small>
                                        <?php else: ?>
                                            <em class="text-muted">System</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($log['action']); ?></strong></td>
                                    <td>
                                        <?php if ($log['module']): ?>
                                            <span class="label label-info"><?php echo htmlspecialchars($log['module']); ?></span>
                                        <?php else: ?>
                                            <em class="text-muted">-</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['resource_type']): ?>
                                            <?php echo htmlspecialchars($log['resource_type']); ?>
                                            <?php if ($log['resource_id']): ?>
                                                #<?php echo $log['resource_id']; ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <em class="text-muted">-</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($log['ip_address']); ?></small></td>
                                    <td>
                                        <button class="btn btn-info btn-sm view-details" 
                                                data-log-id="<?php echo $log['log_id']; ?>"
                                                data-old-values="<?php echo htmlspecialchars($log['old_values'] ?? ''); ?>"
                                                data-new-values="<?php echo htmlspecialchars($log['new_values'] ?? ''); ?>"
                                                data-user-agent="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                            <em class="fa fa-eye"></em> View
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-info-circle"></em> Audit Log Details</h4>
            </div>
            <div class="modal-body">
                <h5>Old Values:</h5>
                <pre id="old_values" class="well" style="max-height: 200px; overflow-y: auto;"></pre>
                
                <h5>New Values:</h5>
                <pre id="new_values" class="well" style="max-height: 200px; overflow-y: auto;"></pre>
                
                <h5>User Agent:</h5>
                <p id="user_agent" class="text-muted"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" style="border-radius: 4px;">Close</button>
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
    $('#auditTable').DataTable({
        "pageLength": 50,
        "order": [[0, "desc"]]
    });
    
    // View details
    $('.view-details').on('click', function() {
        var oldValues = $(this).data('old-values');
        var newValues = $(this).data('new-values');
        var userAgent = $(this).data('user-agent');
        
        // Try to format JSON
        try {
            if (oldValues) {
                var oldObj = JSON.parse(oldValues);
                $('#old_values').text(JSON.stringify(oldObj, null, 2));
            } else {
                $('#old_values').text('No old values');
            }
        } catch(e) {
            $('#old_values').text(oldValues || 'No old values');
        }
        
        try {
            if (newValues) {
                var newObj = JSON.parse(newValues);
                $('#new_values').text(JSON.stringify(newObj, null, 2));
            } else {
                $('#new_values').text('No new values');
            }
        } catch(e) {
            $('#new_values').text(newValues || 'No new values');
        }
        
        $('#user_agent').text(userAgent || 'Unknown');
        $('#detailsModal').modal('show');
    });
});
</script>

<?php include_once "../footer.php"; ?>

