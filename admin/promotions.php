<?php
/**
 * Promotions Management Interface
 * Smart Hotel Management System
 */

// Start output buffering to prevent any accidental output
ob_start();

// Get the directory of this file and build paths from there
// This ensures paths work whether file is accessed directly or included
$adminDir = __DIR__;
$rootDir = dirname($adminDir);

// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once $rootDir . "/db.php";
require_once $rootDir . "/includes/auth.php";
require_once $rootDir . "/includes/rbac.php";
require_once $rootDir . "/includes/security.php";
require_once $rootDir . "/includes/audit.php";

// Check if this is an AJAX request
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
          || (!empty($_POST['ajax']) && $_POST['ajax'] == '1')
          || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// Handle promotion actions for AJAX requests first
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $isAjax) {
    requireLogin();
    // Only super admin and administrators can manage promotions
    if (!hasRole('super_admin') && !hasRole('administrator')) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied. Admin access required.']);
        exit();
    }
    
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
        exit();
    }
    
    $action = $_POST['action'];
    
    // Handle AJAX promotion creation
    if ($action == 'create_promotion') {
        $branch_id = intval($_POST['branch_id']);
        $promotion_code = strtoupper(trim(sanitizeInput($_POST['promotion_code'])));
        $promotion_name = sanitizeInput($_POST['promotion_name']);
        $description = sanitizeInput($_POST['description'] ?? '');
        $discount_type = sanitizeInput($_POST['discount_type']);
        $discount_value = floatval($_POST['discount_value']);
        $min_purchase_amount = floatval($_POST['min_purchase_amount'] ?? 0);
        $max_discount_amount = !empty($_POST['max_discount_amount']) ? floatval($_POST['max_discount_amount']) : null;
        $start_date = sanitizeInput($_POST['start_date']);
        $end_date = sanitizeInput($_POST['end_date']);
        $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
        $applicable_to = sanitizeInput($_POST['applicable_to']);
        $room_type_id = !empty($_POST['room_type_id']) ? intval($_POST['room_type_id']) : null;
        $status = sanitizeInput($_POST['status'] ?? 'active');
        $created_by = $_SESSION['user_id'];
        
        // Validate dates
        if (strtotime($start_date) > strtotime($end_date)) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'End date must be after start date.']);
            exit();
        }
        
        // Check if promotion code already exists for this branch
        $checkQuery = "SELECT promotion_id FROM promotions WHERE branch_id = ? AND promotion_code = ?";
        $checkStmt = mysqli_prepare($connection, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, "is", $branch_id, $promotion_code);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($checkResult) > 0) {
            mysqli_stmt_close($checkStmt);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Promotion code already exists for this branch.']);
            exit();
        }
        mysqli_stmt_close($checkStmt);
        
        // Insert the promotion
        $insertQuery = "INSERT INTO promotions (branch_id, promotion_code, promotion_name, description, discount_type, discount_value, min_purchase_amount, max_discount_amount, start_date, end_date, usage_limit, applicable_to, room_type_id, status, created_by) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = mysqli_prepare($connection, $insertQuery);
        
        if (!$insertStmt) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error preparing statement: ' . mysqli_error($connection)]);
            exit();
        }
        
        mysqli_stmt_bind_param($insertStmt, "issssddddsssiss", $branch_id, $promotion_code, $promotion_name, $description, $discount_type, $discount_value, $min_purchase_amount, $max_discount_amount, $start_date, $end_date, $usage_limit, $applicable_to, $room_type_id, $status, $created_by);
        
        if (mysqli_stmt_execute($insertStmt)) {
            $new_promotion_id = mysqli_insert_id($connection);
            
            // Log audit event (don't fail if this fails)
            try {
                if (function_exists('logAuditEvent')) {
            logAuditEvent('promotion.created', 'promotions', 'promotion', $new_promotion_id, null, ['branch_id' => $branch_id, 'promotion_code' => $promotion_code]);
                }
            } catch (Exception $e) {
                error_log("Error logging audit event: " . $e->getMessage());
            }
            
            mysqli_stmt_close($insertStmt);
            
            // Send promotion notification emails if status is active and send_email is checked
            $emailsSent = 0;
            $emailError = null;
            if ($status == 'active' && isset($_POST['send_email']) && $_POST['send_email'] == '1') {
                try {
                // Use the NEW email notification system (PHPMailer-based)
                require_once $rootDir . "/includes/email_notifications.php";
                $emailResult = sendPromotionEmail($new_promotion_id);
                if ($emailResult['success']) {
                    $emailsSent = isset($emailResult['sent']) ? $emailResult['sent'] : 0;
                    } else {
                        $emailError = $emailResult['message'] ?? 'Unknown error sending emails';
                    }
                } catch (Exception $e) {
                    // Log error but don't fail promotion creation
                    $emailError = $e->getMessage();
                    error_log("Error sending promotion emails: " . $e->getMessage());
                }
            }
            
            $message = 'Promotion created successfully!';
            if ($emailsSent > 0) {
                $message .= " Notification emails queued for $emailsSent guests.";
            } elseif ($emailError) {
                $message .= " Note: Email sending failed - " . $emailError;
            }
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            exit();
        } else {
            $error_msg = mysqli_error($connection);
            $stmt_error = mysqli_stmt_error($insertStmt);
            mysqli_stmt_close($insertStmt);
            ob_clean();
            header('Content-Type: application/json');
            $full_error = !empty($error_msg) ? $error_msg : (!empty($stmt_error) ? $stmt_error : 'Unknown database error');
            echo json_encode(['success' => false, 'message' => 'Error creating promotion: ' . $full_error]);
            exit();
        }
    }
    
    // Handle AJAX promotion update
    if ($action == 'update_promotion') {
        $promotion_id = intval($_POST['promotion_id']);
        $branch_id = intval($_POST['branch_id']);
        $promotion_code = strtoupper(trim(sanitizeInput($_POST['promotion_code'])));
        $promotion_name = sanitizeInput($_POST['promotion_name']);
        $description = sanitizeInput($_POST['description'] ?? '');
        $discount_type = sanitizeInput($_POST['discount_type']);
        $discount_value = floatval($_POST['discount_value']);
        $min_purchase_amount = floatval($_POST['min_purchase_amount'] ?? 0);
        $max_discount_amount = !empty($_POST['max_discount_amount']) ? floatval($_POST['max_discount_amount']) : null;
        $start_date = sanitizeInput($_POST['start_date']);
        $end_date = sanitizeInput($_POST['end_date']);
        $usage_limit = !empty($_POST['usage_limit']) ? intval($_POST['usage_limit']) : null;
        $applicable_to = sanitizeInput($_POST['applicable_to']);
        $room_type_id = !empty($_POST['room_type_id']) ? intval($_POST['room_type_id']) : null;
        $status = sanitizeInput($_POST['status'] ?? 'active');
        
        // Validate dates
        if (strtotime($start_date) > strtotime($end_date)) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'End date must be after start date.']);
            exit();
        }
        
        // Check if promotion code already exists for this branch (excluding current promotion)
        $checkQuery = "SELECT promotion_id FROM promotions WHERE branch_id = ? AND promotion_code = ? AND promotion_id != ?";
        $checkStmt = mysqli_prepare($connection, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, "isi", $branch_id, $promotion_code, $promotion_id);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($checkResult) > 0) {
            mysqli_stmt_close($checkStmt);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Promotion code already exists for this branch.']);
            exit();
        }
        mysqli_stmt_close($checkStmt);
        
        // Get old values for audit
        $oldQuery = "SELECT * FROM promotions WHERE promotion_id = ?";
        $oldStmt = mysqli_prepare($connection, $oldQuery);
        mysqli_stmt_bind_param($oldStmt, "i", $promotion_id);
        mysqli_stmt_execute($oldStmt);
        $oldResult = mysqli_stmt_get_result($oldStmt);
        $oldPromotion = mysqli_fetch_assoc($oldResult);
        mysqli_stmt_close($oldStmt);
        
        // Update the promotion
        $updateQuery = "UPDATE promotions SET branch_id = ?, promotion_code = ?, promotion_name = ?, description = ?, discount_type = ?, discount_value = ?, min_purchase_amount = ?, max_discount_amount = ?, start_date = ?, end_date = ?, usage_limit = ?, applicable_to = ?, room_type_id = ?, status = ? WHERE promotion_id = ?";
        $updateStmt = mysqli_prepare($connection, $updateQuery);
        
        mysqli_stmt_bind_param($updateStmt, "issssddddsssissi", $branch_id, $promotion_code, $promotion_name, $description, $discount_type, $discount_value, $min_purchase_amount, $max_discount_amount, $start_date, $end_date, $usage_limit, $applicable_to, $room_type_id, $status, $promotion_id);
        
        if (mysqli_stmt_execute($updateStmt)) {
            logAuditEvent('promotion.updated', 'promotions', 'promotion', $promotion_id, $oldPromotion, [
                'promotion_code' => $promotion_code,
                'status' => $status
            ]);
            mysqli_stmt_close($updateStmt);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Promotion updated successfully!']);
            exit();
        } else {
            mysqli_stmt_close($updateStmt);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error updating promotion: ' . mysqli_error($connection)]);
            exit();
        }
    }
    
    // Handle AJAX promotion deletion
    if ($action == 'delete_promotion') {
        $promotion_id = intval($_POST['promotion_id']);
        
        // Get promotion info for audit
        $oldQuery = "SELECT * FROM promotions WHERE promotion_id = ?";
        $oldStmt = mysqli_prepare($connection, $oldQuery);
        mysqli_stmt_bind_param($oldStmt, "i", $promotion_id);
        mysqli_stmt_execute($oldStmt);
        $oldResult = mysqli_stmt_get_result($oldStmt);
        $oldPromotion = mysqli_fetch_assoc($oldResult);
        mysqli_stmt_close($oldStmt);
        
        $deleteQuery = "DELETE FROM promotions WHERE promotion_id = ?";
        $deleteStmt = mysqli_prepare($connection, $deleteQuery);
        mysqli_stmt_bind_param($deleteStmt, "i", $promotion_id);
        
        if (mysqli_stmt_execute($deleteStmt)) {
            logAuditEvent('promotion.deleted', 'promotions', 'promotion', $promotion_id, $oldPromotion, null);
            mysqli_stmt_close($deleteStmt);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Promotion deleted successfully!']);
            exit();
        } else {
            mysqli_stmt_close($deleteStmt);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error deleting promotion: ' . mysqli_error($connection)]);
            exit();
        }
    }
}

// Continue with normal page load
requireLogin();
// Only super admin and administrators can manage promotions
if (!hasRole('super_admin') && !hasRole('administrator')) {
    header('Location: index.php?dashboard');
    exit();
}

$user = getCurrentUser();
$page_title = "Promotions Management";

$message = '';
$messageType = '';

// Handle promotion actions (non-AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$isAjax) {
    if (isset($_POST['action'])) {
        require_once $rootDir . "/includes/security.php";
        
        if (!verifyCSRFToken($_POST['csrf_token'])) {
            $message = 'Security token mismatch.';
            $messageType = 'danger';
        } else {
            $action = $_POST['action'];
            
            if ($action == 'create_promotion') {
                // Handled via AJAX above
            } elseif ($action == 'update_promotion') {
                // Handled via AJAX above
            } elseif ($action == 'delete_promotion') {
                // Handled via AJAX above
            }
        }
    }
}

// Get user's branch if they're a branch admin
$userBranchId = null;
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

// Check if room_type table exists
$checkRoomTypeTable = "SHOW TABLES LIKE 'room_type'";
$roomTypeTableExists = (mysqli_num_rows(mysqli_query($connection, $checkRoomTypeTable)) > 0);

// Get all promotions with branch and room type info
$promotionsQuery = "SELECT p.*, b.branch_name, b.branch_code";
if ($roomTypeTableExists) {
    $promotionsQuery .= ", rt.room_type";
}
$promotionsQuery .= ",
                   CASE 
                       WHEN p.end_date < CURDATE() THEN 'expired'
                       WHEN p.start_date > CURDATE() THEN 'upcoming'
                       ELSE p.status
                   END as effective_status
                   FROM promotions p
                   LEFT JOIN branches b ON p.branch_id = b.branch_id";
if ($roomTypeTableExists) {
    $promotionsQuery .= " LEFT JOIN room_type rt ON p.room_type_id = rt.room_type_id";
}
                   
if ($userBranchId && !hasRole('super_admin')) {
    $promotionsQuery .= " WHERE p.branch_id = " . intval($userBranchId);
} else {
    $promotionsQuery .= " WHERE 1=1";
}

$promotionsQuery .= " ORDER BY p.created_at DESC";
$promotionsResult = mysqli_query($connection, $promotionsQuery);

// Ensure audit functions are available
if (!function_exists('logAuditEvent')) {
    require_once $rootDir . "/includes/audit.php";
}

include_once $rootDir . "/header.php";

// Load appropriate sidebar
if (file_exists($rootDir . '/includes/sidebar_loader.php')) {
    include_once $rootDir . '/includes/sidebar_loader.php';
} else {
    include_once $rootDir . "/sidebars/sidebar.php";
}
?>

<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
        <ol class="breadcrumb">
            <li><a href="#"><em class="fa fa-home"></em></a></li>
            <li class="active">Promotions Management</li>
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
                <em class="fa fa-tag"></em> Promotions Management
            </h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <em class="fa fa-list"></em> All Promotions
                    <div class="pull-right">
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addPromotionModal">
                            <em class="fa fa-plus"></em> Add New Promotion
                        </button>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="promotionsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Promotion Code</th>
                                    <th>Promotion Name</th>
                                    <th>Branch</th>
                                    <th>Discount</th>
                                    <th>Valid Period</th>
                                    <th>Usage</th>
                                    <th>Applicable To</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($promotion = mysqli_fetch_assoc($promotionsResult)): 
                                    // Calculate discount display
                                    $discountDisplay = '';
                                    if ($promotion['discount_type'] == 'percentage') {
                                        $discountDisplay = $promotion['discount_value'] . '%';
                                        if ($promotion['max_discount_amount']) {
                                            $discountDisplay .= ' (Max: LKR ' . number_format($promotion['max_discount_amount'], 2) . ')';
                                        }
                                    } else {
                                        $discountDisplay = 'LKR ' . number_format($promotion['discount_value'], 2);
                                    }
                                    
                                    // Status badge
                                    $statusClass = 'default';
                                    if ($promotion['effective_status'] == 'active') {
                                        $statusClass = 'success';
                                    } elseif ($promotion['effective_status'] == 'expired') {
                                        $statusClass = 'danger';
                                    } elseif ($promotion['effective_status'] == 'upcoming') {
                                        $statusClass = 'info';
                                    } elseif ($promotion['effective_status'] == 'inactive') {
                                        $statusClass = 'warning';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $promotion['promotion_id']; ?></td>
                                    <td><strong><span class="label label-primary"><?php echo htmlspecialchars($promotion['promotion_code']); ?></span></strong></td>
                                    <td><?php echo htmlspecialchars($promotion['promotion_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($promotion['branch_name']); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($promotion['branch_code']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo $discountDisplay; ?></strong>
                                        <?php if ($promotion['min_purchase_amount'] > 0): ?>
                                            <br><small class="text-muted">Min: LKR <?php echo number_format($promotion['min_purchase_amount'], 2); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo date('M j, Y', strtotime($promotion['start_date'])); ?></small>
                                        <br><small class="text-muted">to</small>
                                        <br><small><?php echo date('M j, Y', strtotime($promotion['end_date'])); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($promotion['usage_limit']): ?>
                                            <?php echo $promotion['usage_count']; ?> / <?php echo $promotion['usage_limit']; ?>
                                        <?php else: ?>
                                            <?php echo $promotion['usage_count']; ?> (Unlimited)
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $applicableLabels = [
                                            'all' => 'All Services',
                                            'room_booking' => 'Room Booking' . (isset($promotion['room_type']) && $promotion['room_type'] ? ' (' . htmlspecialchars($promotion['room_type']) . ')' : ''),
                                            'meal_package' => 'Meal Package',
                                            'room_package' => 'Room Package',
                                            'service' => 'Service'
                                        ];
                                        echo $applicableLabels[$promotion['applicable_to']] ?? $promotion['applicable_to'];
                                        ?>
                                    </td>
                                    <td>
                                        <span class="label label-<?php echo $statusClass; ?>">
                                            <?php echo ucfirst($promotion['effective_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-info btn-sm edit-promotion" 
                                                data-promotion-id="<?php echo $promotion['promotion_id']; ?>"
                                                data-branch-id="<?php echo $promotion['branch_id']; ?>"
                                                data-promotion-code="<?php echo htmlspecialchars($promotion['promotion_code']); ?>"
                                                data-promotion-name="<?php echo htmlspecialchars($promotion['promotion_name']); ?>"
                                                data-description="<?php echo htmlspecialchars($promotion['description'] ?? ''); ?>"
                                                data-discount-type="<?php echo $promotion['discount_type']; ?>"
                                                data-discount-value="<?php echo $promotion['discount_value']; ?>"
                                                data-min-purchase="<?php echo $promotion['min_purchase_amount']; ?>"
                                                data-max-discount="<?php echo $promotion['max_discount_amount'] ?? ''; ?>"
                                                data-start-date="<?php echo $promotion['start_date']; ?>"
                                                data-end-date="<?php echo $promotion['end_date']; ?>"
                                                data-usage-limit="<?php echo $promotion['usage_limit'] ?? ''; ?>"
                                                data-applicable-to="<?php echo $promotion['applicable_to']; ?>"
                                                data-room-type-id="<?php echo $promotion['room_type_id'] ?? ''; ?>"
                                                data-status="<?php echo $promotion['status']; ?>">
                                            <em class="fa fa-edit"></em> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm delete-promotion" 
                                                data-promotion-id="<?php echo $promotion['promotion_id']; ?>"
                                                data-promotion-code="<?php echo htmlspecialchars($promotion['promotion_code']); ?>">
                                            <em class="fa fa-trash"></em> Delete
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

<!-- Add Promotion Modal -->
<div id="addPromotionModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-plus"></em> Add New Promotion</h4>
            </div>
            <div class="modal-body">
                <form id="addPromotionForm" method="post">
                    <input type="hidden" name="action" value="create_promotion">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="ajax" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch <span class="text-danger">*</span></label>
                                <select name="branch_id" id="add_branch_id" class="form-control" required>
                                    <option value="">Select Branch</option>
                                    <?php
                                    // Get user's branch if they're a branch admin, otherwise show all branches
                                    if ($userBranchId && !hasRole('super_admin')) {
                                        $query = "SELECT branch_id, branch_name, branch_code FROM branches WHERE branch_id = ? AND status = 'active' ORDER BY branch_name";
                                        $stmt = mysqli_prepare($connection, $query);
                                        mysqli_stmt_bind_param($stmt, "i", $userBranchId);
                                        mysqli_stmt_execute($stmt);
                                        $result = mysqli_stmt_get_result($stmt);
                                    } else {
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
                                <label>Promotion Code <span class="text-danger">*</span></label>
                                <input type="text" name="promotion_code" id="add_promotion_code" class="form-control" required maxlength="50" style="text-transform: uppercase;" placeholder="e.g., SUMMER2024">
                                <small class="text-muted">Unique code for this branch</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Promotion Name <span class="text-danger">*</span></label>
                        <input type="text" name="promotion_name" id="add_promotion_name" class="form-control" required maxlength="200" placeholder="e.g., Summer Special Discount">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="add_description" class="form-control" rows="3" placeholder="Enter promotion description..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Discount Type <span class="text-danger">*</span></label>
                                <select name="discount_type" id="add_discount_type" class="form-control" required>
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed_amount">Fixed Amount (LKR)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Discount Value <span class="text-danger">*</span></label>
                                <input type="number" name="discount_value" id="add_discount_value" class="form-control" required min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Max Discount Amount</label>
                                <input type="number" name="max_discount_amount" id="add_max_discount_amount" class="form-control" min="0" step="0.01" placeholder="Optional">
                                <small class="text-muted">Only for percentage discounts</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Minimum Purchase Amount</label>
                                <input type="number" name="min_purchase_amount" id="add_min_purchase_amount" class="form-control" min="0" step="0.01" value="0" placeholder="0.00">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Usage Limit</label>
                                <input type="number" name="usage_limit" id="add_usage_limit" class="form-control" min="1" placeholder="Leave empty for unlimited">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" id="add_start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>End Date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" id="add_end_date" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Applicable To <span class="text-danger">*</span></label>
                                <select name="applicable_to" id="add_applicable_to" class="form-control" required>
                                    <option value="all">All Services</option>
                                    <option value="room_booking">Room Booking</option>
                                    <option value="meal_package">Meal Package</option>
                                    <option value="room_package">Room Package</option>
                                    <option value="service">Service</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" id="add_room_type_group" style="display:none;">
                                <label>Room Type</label>
                                <select name="room_type_id" id="add_room_type_id" class="form-control">
                                    <option value="">All Room Types</option>
                                    <?php
                                    if ($roomTypeTableExists) {
                                        $roomTypesQuery = "SELECT * FROM room_type ORDER BY room_type";
                                        $roomTypesResult = mysqli_query($connection, $roomTypesQuery);
                                        if ($roomTypesResult) {
                                            while ($roomType = mysqli_fetch_assoc($roomTypesResult)) {
                                                echo '<option value="' . $roomType['room_type_id'] . '">' . 
                                                     htmlspecialchars($roomType['room_type']) . '</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                                <small class="text-muted">Only shown when applicable to room booking</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Status <span class="text-danger">*</span></label>
                        <select name="status" id="add_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox">
                            <label style="font-size: 16px;">
                                <input type="checkbox" name="send_email" id="add_send_email" value="1" checked> 
                                <strong>📧 Send Email to All Active Guests</strong>
                            </label>
                            <p class="help-block" style="margin-left: 20px;">
                                When checked, promotional emails will be queued and sent to all active guests in the system. 
                                Emails will be sent within the next 5 minutes (next cron job run).
                            </p>
                        </div>
                    </div>
                    
                    <div class="response"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="savePromotion">Create Promotion</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Promotion Modal -->
<div id="editPromotionModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-edit"></em> Edit Promotion</h4>
            </div>
            <div class="modal-body">
                <form id="editPromotionForm" method="post">
                    <input type="hidden" name="action" value="update_promotion">
                    <input type="hidden" name="promotion_id" id="edit_promotion_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="ajax" value="1">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch <span class="text-danger">*</span></label>
                                <select name="branch_id" id="edit_branch_id" class="form-control" required>
                                    <option value="">Select Branch</option>
                                    <?php
                                    // Re-query branches for edit modal
                                    if ($userBranchId && !hasRole('super_admin')) {
                                        $query = "SELECT branch_id, branch_name, branch_code FROM branches WHERE branch_id = ? AND status = 'active' ORDER BY branch_name";
                                        $stmt = mysqli_prepare($connection, $query);
                                        mysqli_stmt_bind_param($stmt, "i", $userBranchId);
                                        mysqli_stmt_execute($stmt);
                                        $result = mysqli_stmt_get_result($stmt);
                                    } else {
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
                                <label>Promotion Code <span class="text-danger">*</span></label>
                                <input type="text" name="promotion_code" id="edit_promotion_code" class="form-control" required maxlength="50" style="text-transform: uppercase;">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Promotion Name <span class="text-danger">*</span></label>
                        <input type="text" name="promotion_name" id="edit_promotion_name" class="form-control" required maxlength="200">
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Discount Type <span class="text-danger">*</span></label>
                                <select name="discount_type" id="edit_discount_type" class="form-control" required>
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed_amount">Fixed Amount (LKR)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Discount Value <span class="text-danger">*</span></label>
                                <input type="number" name="discount_value" id="edit_discount_value" class="form-control" required min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Max Discount Amount</label>
                                <input type="number" name="max_discount_amount" id="edit_max_discount_amount" class="form-control" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Minimum Purchase Amount</label>
                                <input type="number" name="min_purchase_amount" id="edit_min_purchase_amount" class="form-control" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Usage Limit</label>
                                <input type="number" name="usage_limit" id="edit_usage_limit" class="form-control" min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>End Date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" id="edit_end_date" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Applicable To <span class="text-danger">*</span></label>
                                <select name="applicable_to" id="edit_applicable_to" class="form-control" required>
                                    <option value="all">All Services</option>
                                    <option value="room_booking">Room Booking</option>
                                    <option value="meal_package">Meal Package</option>
                                    <option value="room_package">Room Package</option>
                                    <option value="service">Service</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" id="edit_room_type_group" style="display:none;">
                                <label>Room Type</label>
                                <select name="room_type_id" id="edit_room_type_id" class="form-control">
                                    <option value="">All Room Types</option>
                                    <?php
                                    if ($roomTypeTableExists) {
                                        $roomTypesQuery = "SELECT * FROM room_type ORDER BY room_type";
                                        $roomTypesResult = mysqli_query($connection, $roomTypesQuery);
                                        if ($roomTypesResult) {
                                            while ($roomType = mysqli_fetch_assoc($roomTypesResult)) {
                                                echo '<option value="' . $roomType['room_type_id'] . '">' . 
                                                     htmlspecialchars($roomType['room_type']) . '</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Status <span class="text-danger">*</span></label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="response"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="updatePromotion">Update Promotion</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Promotion Modal -->
<div id="deletePromotionModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-trash"></em> Delete Promotion</h4>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the promotion <strong id="delete_promotion_code"></strong>?</p>
                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone!</p>
                <form id="deletePromotionForm" method="post">
                    <input type="hidden" name="action" value="delete_promotion">
                    <input type="hidden" name="promotion_id" id="delete_promotion_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="ajax" value="1">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete Promotion</button>
            </div>
        </div>
    </div>
</div>

<script src="js/jquery-1.11.1.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#promotionsTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]]
    });
    
    // Show/hide room type field based on applicable_to
    $('#add_applicable_to, #edit_applicable_to').on('change', function() {
        var applicableTo = $(this).val();
        var isAdd = $(this).attr('id') == 'add_applicable_to';
        var roomTypeGroup = isAdd ? '#add_room_type_group' : '#edit_room_type_group';
        
        if (applicableTo == 'room_booking') {
            $(roomTypeGroup).show();
        } else {
            $(roomTypeGroup).hide();
            if (isAdd) {
                $('#add_room_type_id').val('');
            } else {
                $('#edit_room_type_id').val('');
            }
        }
    });
    
    // Auto-uppercase promotion code
    $('input[name="promotion_code"]').on('input', function() {
        this.value = this.value.toUpperCase();
    });
    
    // Save new promotion
    $('#savePromotion').on('click', function() {
        var form = $('#addPromotionForm');
        var submitBtn = $(this);
        var originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true).html('<em class="fa fa-spinner fa-spin"></em> Creating...');
        
        $.ajax({
            url: 'index.php?promotions',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            data: form.serialize(),
            success: function(response) {
                submitBtn.prop('disabled', false).html(originalText);
                
                if (response && response.success) {
                    form.find('.response').html('<div class="alert alert-success">' + (response.message || 'Promotion created successfully!') + '</div>');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    form.find('.response').html('<div class="alert alert-danger">' + (response ? (response.message || 'Error creating promotion') : 'Error creating promotion') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                submitBtn.prop('disabled', false).html(originalText);
                
                var errorMessage = 'Error creating promotion. Please try again.';
                try {
                    if (xhr.responseText) {
                        // Try to parse as JSON first
                        try {
                        var response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMessage = response.message;
                            }
                        } catch(jsonError) {
                            // If not JSON, check if it's HTML with error
                            if (xhr.responseText.includes('Error') || xhr.responseText.includes('error')) {
                                // Extract error message from HTML if possible
                                var match = xhr.responseText.match(/Error[^<]*/i);
                                if (match) {
                                    errorMessage = match[0];
                                } else {
                                    errorMessage = 'Server error occurred. Check console for details.';
                                }
                            }
                        }
                    }
                } catch(e) {
                    console.error('AJAX Error:', status, error);
                    console.error('Response:', xhr.responseText);
                }
                
                console.error('Full error details:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                form.find('.response').html('<div class="alert alert-danger">' + errorMessage + '</div>');
            }
        });
    });
    
    // Edit promotion
    $('.edit-promotion').on('click', function() {
        $('#edit_promotion_id').val($(this).data('promotion-id'));
        $('#edit_branch_id').val($(this).data('branch-id'));
        $('#edit_promotion_code').val($(this).data('promotion-code'));
        $('#edit_promotion_name').val($(this).data('promotion-name'));
        $('#edit_description').val($(this).data('description'));
        $('#edit_discount_type').val($(this).data('discount-type'));
        $('#edit_discount_value').val($(this).data('discount-value'));
        $('#edit_min_purchase_amount').val($(this).data('min-purchase'));
        $('#edit_max_discount_amount').val($(this).data('max-discount'));
        $('#edit_start_date').val($(this).data('start-date'));
        $('#edit_end_date').val($(this).data('end-date'));
        $('#edit_usage_limit').val($(this).data('usage-limit'));
        $('#edit_applicable_to').val($(this).data('applicable-to'));
        $('#edit_room_type_id').val($(this).data('room-type-id'));
        $('#edit_status').val($(this).data('status'));
        
        // Show/hide room type field
        if ($(this).data('applicable-to') == 'room_booking') {
            $('#edit_room_type_group').show();
        } else {
            $('#edit_room_type_group').hide();
        }
        
        $('#editPromotionModal').modal('show');
    });
    
    // Update promotion
    $('#updatePromotion').on('click', function() {
        var form = $('#editPromotionForm');
        var submitBtn = $(this);
        var originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true).html('<em class="fa fa-spinner fa-spin"></em> Updating...');
        
        $.ajax({
            url: 'index.php?promotions',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            data: form.serialize(),
            success: function(response) {
                submitBtn.prop('disabled', false).html(originalText);
                
                if (response && response.success) {
                    form.find('.response').html('<div class="alert alert-success">' + (response.message || 'Promotion updated successfully!') + '</div>');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    form.find('.response').html('<div class="alert alert-danger">' + (response ? (response.message || 'Error updating promotion') : 'Error updating promotion') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                submitBtn.prop('disabled', false).html(originalText);
                
                var errorMessage = 'Error updating promotion. Please try again.';
                try {
                    if (xhr.responseText) {
                        var response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMessage = response.message;
                        }
                    }
                } catch(e) {
                    console.error('AJAX Error:', status, error, xhr.responseText);
                }
                
                form.find('.response').html('<div class="alert alert-danger">' + errorMessage + '</div>');
            }
        });
    });
    
    // Delete promotion
    $('.delete-promotion').on('click', function() {
        var promotionId = $(this).data('promotion-id');
        var promotionCode = $(this).data('promotion-code');
        $('#delete_promotion_id').val(promotionId);
        $('#delete_promotion_code').text(promotionCode);
        $('#deletePromotionModal').modal('show');
    });
    
    // Confirm delete
    $('#confirmDelete').on('click', function() {
        var form = $('#deletePromotionForm');
        var submitBtn = $(this);
        var originalText = submitBtn.html();
        
        submitBtn.prop('disabled', true).html('<em class="fa fa-spinner fa-spin"></em> Deleting...');
        
        $.ajax({
            url: 'index.php?promotions',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            data: form.serialize(),
            success: function(response) {
                submitBtn.prop('disabled', false).html(originalText);
                
                if (response && response.success) {
                    $('#deletePromotionModal').modal('hide');
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                } else {
                    alert(response ? (response.message || 'Error deleting promotion') : 'Error deleting promotion');
                }
            },
            error: function(xhr, status, error) {
                submitBtn.prop('disabled', false).html(originalText);
                
                var errorMessage = 'Error deleting promotion. Please try again.';
                try {
                    if (xhr.responseText) {
                        var response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMessage = response.message;
                        }
                    }
                } catch(e) {
                    console.error('AJAX Error:', status, error, xhr.responseText);
                }
                
                alert(errorMessage);
            }
        });
    });
});
</script>

<?php include_once $rootDir . "/footer.php"; ?>

