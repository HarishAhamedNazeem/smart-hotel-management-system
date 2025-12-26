<?php
/**
 * Branch Management Interface
 * Smart Hotel Management System
 */

// Start output buffering to prevent any accidental output
ob_start();

session_start();
include_once "../db.php";
require_once "../includes/auth.php";
require_once "../includes/rbac.php";
require_once "../includes/security.php";
require_once "../includes/audit.php";

// Check if this is an AJAX request (multiple methods for compatibility)
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
          || (!empty($_POST['ajax']) && $_POST['ajax'] == '1')
          || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// Check for optional columns in room table
$checkDeleteStatus = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'deleteStatus'");
$hasDeleteStatus = mysqli_num_rows($checkDeleteStatus) > 0;

$checkCheckinStatus = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'check_in_status'");
$hasCheckinStatus = mysqli_num_rows($checkCheckinStatus) > 0;

// Handle branch actions for AJAX requests first (before any HTML output)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $isAjax) {
    requireLogin();
    // Only super admin can manage branches
    if (!hasRole('super_admin')) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied. Super Admin access required.']);
        exit();
    }
    
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
        exit();
    }
    
    $action = $_POST['action'];
    
    // Handle AJAX room creation
    if ($action == 'create_room_for_branch') {
        $branch_id = intval($_POST['branch_id']);
        $room_type_id = intval($_POST['room_type_id']);
        $room_no = trim(sanitizeInput($_POST['room_no']));
        
        // Validate inputs
        if (empty($room_no)) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Room number is required.']);
            exit();
        }
        
        if (empty($room_type_id) || $room_type_id <= 0) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Please select a valid room type.']);
            exit();
        }
        
        // Check if room number already exists
        $checkQuery = "SELECT room_id FROM room WHERE room_no = ?";
        if ($hasDeleteStatus) {
            $checkQuery .= " AND deleteStatus = 0";
        }
        $checkStmt = mysqli_prepare($connection, $checkQuery);
        if (!$checkStmt) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)]);
            exit();
        }
        
        mysqli_stmt_bind_param($checkStmt, "s", $room_no);
        mysqli_stmt_execute($checkStmt);
        $checkResult = mysqli_stmt_get_result($checkStmt);
        
        if (mysqli_num_rows($checkResult) > 0) {
            mysqli_stmt_close($checkStmt);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Room number already exists. Please use a different room number.']);
            exit();
        }
        mysqli_stmt_close($checkStmt);
        
        // Insert the room - build query based on available columns
        $insertColumns = "branch_id, room_type_id, room_no, status";
        $insertValues = "?, ?, ?, 0";
        $bindTypes = "iis";
        $bindParams = [$branch_id, $room_type_id, $room_no];
        
        if ($hasCheckinStatus) {
            $insertColumns .= ", check_in_status, check_out_status";
            $insertValues .= ", 0, 0";
        }
        
        if ($hasDeleteStatus) {
            $insertColumns .= ", deleteStatus";
            $insertValues .= ", 0";
        }
        
        $insertQuery = "INSERT INTO room ($insertColumns) VALUES ($insertValues)";
        $insertStmt = mysqli_prepare($connection, $insertQuery);
        if (!$insertStmt) {
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)]);
            exit();
        }
        
        mysqli_stmt_bind_param($insertStmt, $bindTypes, ...$bindParams);
        
        if (mysqli_stmt_execute($insertStmt)) {
            $new_room_id = mysqli_insert_id($connection);
            logAuditEvent('room.created_for_branch', 'branches', 'room', $new_room_id, null, ['branch_id' => $branch_id, 'room_no' => $room_no]);
            mysqli_stmt_close($insertStmt);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Room created successfully!']);
            exit();
        } else {
            $error_msg = mysqli_error($connection);
            mysqli_stmt_close($insertStmt);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error creating room: ' . $error_msg]);
            exit();
        }
    }
    
    // Handle AJAX room assignment
    if ($action == 'assign_room_to_branch') {
        $branch_id = intval($_POST['branch_id']);
        $room_id = intval($_POST['room_id']);
        
        $updateQuery = "UPDATE room SET branch_id = ? WHERE room_id = ?";
        $updateStmt = mysqli_prepare($connection, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, "ii", $branch_id, $room_id);
        
        if (mysqli_stmt_execute($updateStmt)) {
            logAuditEvent('room.assigned_to_branch', 'branches', 'room', $room_id, null, ['branch_id' => $branch_id]);
            mysqli_stmt_close($updateStmt);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Room assigned to branch successfully!']);
            exit();
        } else {
            mysqli_stmt_close($updateStmt);
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error assigning room: ' . mysqli_error($connection)]);
            exit();
        }
    }
}

// Continue with normal page load
requireLogin();
// Only super admin can manage branches
if (!hasRole('super_admin')) {
    header('Location: ../index.php?dashboard');
    exit();
}

$user = getCurrentUser();
$page_title = "Branch Management";

$message = '';
$messageType = '';

// Handle branch actions (non-AJAX)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        require_once "../includes/security.php";
        
        // Check if this is an AJAX request (multiple methods for compatibility)
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') 
                  || (!empty($_POST['ajax']) && $_POST['ajax'] == '1')
                  || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        
        if (!verifyCSRFToken($_POST['csrf_token'])) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
                exit();
            }
            $message = 'Security token mismatch.';
            $messageType = 'danger';
        } else {
            $action = $_POST['action'];
            
            if ($action == 'create_branch') {
                $branch_name = sanitizeInput($_POST['branch_name']);
                $branch_code = strtoupper(sanitizeInput($_POST['branch_code']));
                $address = sanitizeInput($_POST['address']);
                $city = sanitizeInput($_POST['city']);
                $state = sanitizeInput($_POST['state'] ?? '');
                $country = sanitizeInput($_POST['country']);
                $postal_code = sanitizeInput($_POST['postal_code'] ?? '');
                $phone = sanitizeInput($_POST['phone'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $manager_name = sanitizeInput($_POST['manager_name'] ?? '');
                $manager_contact = sanitizeInput($_POST['manager_contact'] ?? '');
                $status = sanitizeInput($_POST['status'] ?? 'active');
                
                // Check if branch code already exists
                $checkQuery = "SELECT branch_id FROM branches WHERE branch_code = ?";
                $checkStmt = mysqli_prepare($connection, $checkQuery);
                mysqli_stmt_bind_param($checkStmt, "s", $branch_code);
                mysqli_stmt_execute($checkStmt);
                $checkResult = mysqli_stmt_get_result($checkStmt);
                
                if (mysqli_num_rows($checkResult) > 0) {
                    $message = 'Branch code already exists. Please use a different code.';
                    $messageType = 'danger';
                } else {
                    $insertQuery = "INSERT INTO branches (branch_name, branch_code, address, city, state, country, postal_code, phone, email, manager_name, manager_contact, status) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = mysqli_prepare($connection, $insertQuery);
                    mysqli_stmt_bind_param($insertStmt, "ssssssssssss", $branch_name, $branch_code, $address, $city, $state, $country, $postal_code, $phone, $email, $manager_name, $manager_contact, $status);
                    
                    if (mysqli_stmt_execute($insertStmt)) {
                        $branch_id = mysqli_insert_id($connection);
                        logAuditEvent('branch.created', 'branches', 'branch', $branch_id, null, [
                            'branch_name' => $branch_name,
                            'branch_code' => $branch_code
                        ]);
                        $message = 'Branch created successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error creating branch: ' . mysqli_error($connection);
                        $messageType = 'danger';
                    }
                    mysqli_stmt_close($insertStmt);
                }
                mysqli_stmt_close($checkStmt);
            } elseif ($action == 'update_branch') {
                $branch_id = intval($_POST['branch_id']);
                $branch_name = sanitizeInput($_POST['branch_name']);
                $branch_code = strtoupper(sanitizeInput($_POST['branch_code']));
                $address = sanitizeInput($_POST['address']);
                $city = sanitizeInput($_POST['city']);
                $state = sanitizeInput($_POST['state'] ?? '');
                $country = sanitizeInput($_POST['country']);
                $postal_code = sanitizeInput($_POST['postal_code'] ?? '');
                $phone = sanitizeInput($_POST['phone'] ?? '');
                $email = sanitizeInput($_POST['email'] ?? '');
                $manager_name = sanitizeInput($_POST['manager_name'] ?? '');
                $manager_contact = sanitizeInput($_POST['manager_contact'] ?? '');
                $status = sanitizeInput($_POST['status'] ?? 'active');
                
                // Get old values for audit
                $oldQuery = "SELECT * FROM branches WHERE branch_id = ?";
                $oldStmt = mysqli_prepare($connection, $oldQuery);
                mysqli_stmt_bind_param($oldStmt, "i", $branch_id);
                mysqli_stmt_execute($oldStmt);
                $oldResult = mysqli_stmt_get_result($oldStmt);
                $oldBranch = mysqli_fetch_assoc($oldResult);
                mysqli_stmt_close($oldStmt);
                
                // Check if branch code already exists (excluding current branch)
                $checkQuery = "SELECT branch_id FROM branches WHERE branch_code = ? AND branch_id != ?";
                $checkStmt = mysqli_prepare($connection, $checkQuery);
                mysqli_stmt_bind_param($checkStmt, "si", $branch_code, $branch_id);
                mysqli_stmt_execute($checkStmt);
                $checkResult = mysqli_stmt_get_result($checkStmt);
                
                if (mysqli_num_rows($checkResult) > 0) {
                    $message = 'Branch code already exists. Please use a different code.';
                    $messageType = 'danger';
                } else {
                    $updateQuery = "UPDATE branches SET branch_name = ?, branch_code = ?, address = ?, city = ?, state = ?, country = ?, postal_code = ?, phone = ?, email = ?, manager_name = ?, manager_contact = ?, status = ? WHERE branch_id = ?";
                    $updateStmt = mysqli_prepare($connection, $updateQuery);
                    mysqli_stmt_bind_param($updateStmt, "ssssssssssssi", $branch_name, $branch_code, $address, $city, $state, $country, $postal_code, $phone, $email, $manager_name, $manager_contact, $status, $branch_id);
                    
                    if (mysqli_stmt_execute($updateStmt)) {
                        logAuditEvent('branch.updated', 'branches', 'branch', $branch_id, $oldBranch, [
                            'branch_name' => $branch_name,
                            'branch_code' => $branch_code,
                            'status' => $status
                        ]);
                        $message = 'Branch updated successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error updating branch: ' . mysqli_error($connection);
                        $messageType = 'danger';
                    }
                    mysqli_stmt_close($updateStmt);
                }
                mysqli_stmt_close($checkStmt);
            } elseif ($action == 'delete_branch') {
                $branch_id = intval($_POST['branch_id']);
                
                // Check if branch has associated data
                $checkBookings = "SELECT COUNT(*) as count FROM booking WHERE branch_id = ?";
                $checkStmt = mysqli_prepare($connection, $checkBookings);
                mysqli_stmt_bind_param($checkStmt, "i", $branch_id);
                mysqli_stmt_execute($checkStmt);
                $result = mysqli_stmt_get_result($checkStmt);
                $row = mysqli_fetch_assoc($result);
                mysqli_stmt_close($checkStmt);
                
                if ($row['count'] > 0) {
                    $message = 'Cannot delete branch. It has associated bookings. You can deactivate it instead.';
                    $messageType = 'warning';
                } else {
                    // Get branch info for audit
                    $oldQuery = "SELECT * FROM branches WHERE branch_id = ?";
                    $oldStmt = mysqli_prepare($connection, $oldQuery);
                    mysqli_stmt_bind_param($oldStmt, "i", $branch_id);
                    mysqli_stmt_execute($oldStmt);
                    $oldResult = mysqli_stmt_get_result($oldStmt);
                    $oldBranch = mysqli_fetch_assoc($oldResult);
                    mysqli_stmt_close($oldStmt);
                    
                    $deleteQuery = "DELETE FROM branches WHERE branch_id = ?";
                    $deleteStmt = mysqli_prepare($connection, $deleteQuery);
                    mysqli_stmt_bind_param($deleteStmt, "i", $branch_id);
                    
                    if (mysqli_stmt_execute($deleteStmt)) {
                        logAuditEvent('branch.deleted', 'branches', 'branch', $branch_id, $oldBranch, null);
                        $message = 'Branch deleted successfully!';
                        $messageType = 'success';
                    } else {
                        $message = 'Error deleting branch: ' . mysqli_error($connection);
                        $messageType = 'danger';
                    }
                    mysqli_stmt_close($deleteStmt);
                }
            } elseif ($action == 'assign_room_to_branch') {
                $branch_id = intval($_POST['branch_id']);
                $room_id = intval($_POST['room_id']);
                
                $updateQuery = "UPDATE room SET branch_id = ? WHERE room_id = ?";
                $updateStmt = mysqli_prepare($connection, $updateQuery);
                mysqli_stmt_bind_param($updateStmt, "ii", $branch_id, $room_id);
                
                if (mysqli_stmt_execute($updateStmt)) {
                    logAuditEvent('room.assigned_to_branch', 'branches', 'room', $room_id, null, ['branch_id' => $branch_id]);
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Room assigned to branch successfully!']);
                        exit();
                    }
                    $message = 'Room assigned to branch successfully!';
                    $messageType = 'success';
                } else {
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error assigning room: ' . mysqli_error($connection)]);
                        exit();
                    }
                    $message = 'Error assigning room: ' . mysqli_error($connection);
                    $messageType = 'danger';
                }
                mysqli_stmt_close($updateStmt);
            } elseif ($action == 'remove_room_from_branch') {
                $room_id = intval($_POST['room_id']);
                
                $updateQuery = "UPDATE room SET branch_id = NULL WHERE room_id = ?";
                $updateStmt = mysqli_prepare($connection, $updateQuery);
                mysqli_stmt_bind_param($updateStmt, "i", $room_id);
                
                if (mysqli_stmt_execute($updateStmt)) {
                    logAuditEvent('room.removed_from_branch', 'branches', 'room', $room_id, null, null);
                    $message = 'Room removed from branch successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Error removing room: ' . mysqli_error($connection);
                    $messageType = 'danger';
                }
                mysqli_stmt_close($updateStmt);
            } elseif ($action == 'create_room_for_branch') {
                $branch_id = intval($_POST['branch_id']);
                $room_type_id = intval($_POST['room_type_id']);
                $room_no = trim(sanitizeInput($_POST['room_no']));
                
                // Validate inputs
                if (empty($room_no)) {
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Room number is required.']);
                        exit();
                    }
                    $message = 'Room number is required.';
                    $messageType = 'danger';
                } elseif (empty($room_type_id) || $room_type_id <= 0) {
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Please select a valid room type.']);
                        exit();
                    }
                    $message = 'Please select a valid room type.';
                    $messageType = 'danger';
                } else {
                    // Check if room number already exists
                    $checkQuery = "SELECT room_id FROM room WHERE room_no = ?";
                    if ($hasDeleteStatus) {
                        $checkQuery .= " AND deleteStatus = 0";
                    }
                    $checkStmt = mysqli_prepare($connection, $checkQuery);
                    if (!$checkStmt) {
                        if ($isAjax) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)]);
                            exit();
                        }
                        $message = 'Database error: ' . mysqli_error($connection);
                        $messageType = 'danger';
                    } else {
                        mysqli_stmt_bind_param($checkStmt, "s", $room_no);
                        mysqli_stmt_execute($checkStmt);
                        $checkResult = mysqli_stmt_get_result($checkStmt);
                        
                        if (mysqli_num_rows($checkResult) > 0) {
                            if ($isAjax) {
                                header('Content-Type: application/json');
                                echo json_encode(['success' => false, 'message' => 'Room number already exists. Please use a different room number.']);
                                exit();
                            }
                            $message = 'Room number already exists. Please use a different room number.';
                            $messageType = 'danger';
                        } else {
                            // Build INSERT query based on available columns
                            $insertColumns = "branch_id, room_type_id, room_no, status";
                            $insertValues = "?, ?, ?, 0";
                            $bindTypes = "iis";
                            $bindParams = [$branch_id, $room_type_id, $room_no];
                            
                            if ($hasCheckinStatus) {
                                $insertColumns .= ", check_in_status, check_out_status";
                                $insertValues .= ", 0, 0";
                            }
                            
                            if ($hasDeleteStatus) {
                                $insertColumns .= ", deleteStatus";
                                $insertValues .= ", 0";
                            }
                            
                            $insertQuery = "INSERT INTO room ($insertColumns) VALUES ($insertValues)";
                            $insertStmt = mysqli_prepare($connection, $insertQuery);
                            if (!$insertStmt) {
                                if ($isAjax) {
                                    header('Content-Type: application/json');
                                    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($connection)]);
                                    exit();
                                }
                                $message = 'Database error: ' . mysqli_error($connection);
                                $messageType = 'danger';
                            } else {
                                mysqli_stmt_bind_param($insertStmt, $bindTypes, ...$bindParams);
                                
                                if (mysqli_stmt_execute($insertStmt)) {
                                    $new_room_id = mysqli_insert_id($connection);
                                    logAuditEvent('room.created_for_branch', 'branches', 'room', $new_room_id, null, ['branch_id' => $branch_id, 'room_no' => $room_no]);
                                    if ($isAjax) {
                                        header('Content-Type: application/json');
                                        echo json_encode(['success' => true, 'message' => 'Room created successfully!']);
                                        exit();
                                    }
                                    $message = 'Room created successfully!';
                                    $messageType = 'success';
                                } else {
                                    $error_msg = mysqli_error($connection);
                                    if ($isAjax) {
                                        header('Content-Type: application/json');
                                        echo json_encode(['success' => false, 'message' => 'Error creating room: ' . $error_msg]);
                                        exit();
                                    }
                                    $message = 'Error creating room: ' . $error_msg;
                                    $messageType = 'danger';
                                }
                                mysqli_stmt_close($insertStmt);
                            }
                        }
                        mysqli_stmt_close($checkStmt);
                    }
                }
            }
        }
    }
}

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

// Get branches with statistics (filtered for branch admins)
$roomCountCondition = $hasDeleteStatus ? " AND deleteStatus = 0" : "";
$branchesQuery = "SELECT b.*, 
                  (SELECT COUNT(*) FROM room WHERE branch_id = b.branch_id" . $roomCountCondition . ") as total_rooms,
                  (SELECT COUNT(*) FROM booking WHERE branch_id = b.branch_id) as total_bookings,
                  (SELECT COUNT(*) FROM staff WHERE branch_id = b.branch_id) as total_staff
                  FROM branches b
                  WHERE 1=1";

// Add branch filter for branch admins
if ($userBranchId && !hasRole('super_admin')) {
    $branchesQuery .= " AND b.branch_id = " . intval($userBranchId);
}

$branchesQuery .= " ORDER BY b.created_at DESC";
$branchesResult = mysqli_query($connection, $branchesQuery);

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
            <li class="active">Branch Management</li>
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
                <em class="fa fa-building"></em> Branch Management
            </h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <em class="fa fa-list"></em> All Branches
                    <div class="pull-right">
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addBranchModal">
                            <em class="fa fa-plus"></em> Add New Branch
                        </button>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="branchesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Branch Name</th>
                                    <th>Branch Code</th>
                                    <th>Location</th>
                                    <th>Contact</th>
                                    <th>Manager</th>
                                    <th>Statistics</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($branch = mysqli_fetch_assoc($branchesResult)): ?>
                                <tr>
                                    <td><?php echo $branch['branch_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($branch['branch_name']); ?></strong></td>
                                    <td><span class="label label-default"><?php echo htmlspecialchars($branch['branch_code']); ?></span></td>
                                    <td>
                                        <?php echo htmlspecialchars($branch['city']); ?>, <?php echo htmlspecialchars($branch['state'] ? $branch['state'] . ', ' : ''); ?><?php echo htmlspecialchars($branch['country']); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($branch['address']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($branch['phone']): ?>
                                            <em class="fa fa-phone"></em> <?php echo htmlspecialchars($branch['phone']); ?><br>
                                        <?php endif; ?>
                                        <?php if ($branch['email']): ?>
                                            <em class="fa fa-envelope"></em> <?php echo htmlspecialchars($branch['email']); ?>
                                        <?php endif; ?>
                                        <?php if (!$branch['phone'] && !$branch['email']): ?>
                                            <em class="text-muted">-</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($branch['manager_name']): ?>
                                            <strong><?php echo htmlspecialchars($branch['manager_name']); ?></strong>
                                            <?php if ($branch['manager_contact']): ?>
                                                <br><small><em class="fa fa-phone"></em> <?php echo htmlspecialchars($branch['manager_contact']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <em class="text-muted">Not assigned</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge" title="Total Rooms"><em class="fa fa-bed"></em> <?php echo $branch['total_rooms']; ?></span>
                                        <span class="badge" title="Total Bookings"><em class="fa fa-calendar"></em> <?php echo $branch['total_bookings']; ?></span>
                                        <span class="badge" title="Total Staff"><em class="fa fa-users"></em> <?php echo $branch['total_staff']; ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = $branch['status'] == 'active' ? 'success' : 'danger';
                                        echo '<span class="label label-' . $statusClass . '">' . 
                                             ucfirst($branch['status']) . '</span>';
                                        ?>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($branch['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-success btn-sm manage-rooms" 
                                                data-branch-id="<?php echo $branch['branch_id']; ?>"
                                                data-branch-name="<?php echo htmlspecialchars($branch['branch_name']); ?>"
                                                title="Manage Rooms">
                                            <em class="fa fa-bed"></em> Manage Rooms
                                        </button>
                                        <button class="btn btn-info btn-sm edit-branch" 
                                                data-branch-id="<?php echo $branch['branch_id']; ?>"
                                                data-branch-name="<?php echo htmlspecialchars($branch['branch_name']); ?>"
                                                data-branch-code="<?php echo htmlspecialchars($branch['branch_code']); ?>"
                                                data-address="<?php echo htmlspecialchars($branch['address']); ?>"
                                                data-city="<?php echo htmlspecialchars($branch['city']); ?>"
                                                data-state="<?php echo htmlspecialchars($branch['state'] ?? ''); ?>"
                                                data-country="<?php echo htmlspecialchars($branch['country']); ?>"
                                                data-postal-code="<?php echo htmlspecialchars($branch['postal_code'] ?? ''); ?>"
                                                data-phone="<?php echo htmlspecialchars($branch['phone'] ?? ''); ?>"
                                                data-email="<?php echo htmlspecialchars($branch['email'] ?? ''); ?>"
                                                data-manager-name="<?php echo htmlspecialchars($branch['manager_name'] ?? ''); ?>"
                                                data-manager-contact="<?php echo htmlspecialchars($branch['manager_contact'] ?? ''); ?>"
                                                data-status="<?php echo $branch['status']; ?>">
                                            <em class="fa fa-edit"></em> Edit
                                        </button>
                                        <?php if ($branch['total_bookings'] == 0): ?>
                                        <button class="btn btn-danger btn-sm delete-branch" 
                                                data-branch-id="<?php echo $branch['branch_id']; ?>"
                                                data-branch-name="<?php echo htmlspecialchars($branch['branch_name']); ?>">
                                            <em class="fa fa-trash"></em> Delete
                                        </button>
                                        <?php endif; ?>
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

<!-- Add Branch Modal -->
<div id="addBranchModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-plus"></em> Add New Branch</h4>
            </div>
            <div class="modal-body">
                <form id="addBranchForm" method="post">
                    <input type="hidden" name="action" value="create_branch">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch Name <span class="text-danger">*</span></label>
                                <input type="text" name="branch_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch Code <span class="text-danger">*</span></label>
                                <input type="text" name="branch_code" class="form-control" required maxlength="20" style="text-transform: uppercase;">
                                <small class="text-muted">Unique code for the branch (e.g., MAIN, KDY, CMB)</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Address <span class="text-danger">*</span></label>
                        <textarea name="address" class="form-control" rows="2" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>City <span class="text-danger">*</span></label>
                                <input type="text" name="city" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>State/Province</label>
                                <input type="text" name="state" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Country <span class="text-danger">*</span></label>
                                <input type="text" name="country" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Postal Code</label>
                                <input type="text" name="postal_code" class="form-control" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-control" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <h5>Contact Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <h5>Manager Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Manager Name</label>
                                <input type="text" name="manager_name" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Manager Contact</label>
                                <input type="tel" name="manager_contact" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="response"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveBranch">Create Branch</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Branch Modal -->
<div id="editBranchModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-edit"></em> Edit Branch</h4>
            </div>
            <div class="modal-body">
                <form id="editBranchForm" method="post">
                    <input type="hidden" name="action" value="update_branch">
                    <input type="hidden" name="branch_id" id="edit_branch_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch Name <span class="text-danger">*</span></label>
                                <input type="text" name="branch_name" id="edit_branch_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Branch Code <span class="text-danger">*</span></label>
                                <input type="text" name="branch_code" id="edit_branch_code" class="form-control" required maxlength="20" style="text-transform: uppercase;">
                                <small class="text-muted">Unique code for the branch</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Address <span class="text-danger">*</span></label>
                        <textarea name="address" id="edit_address" class="form-control" rows="2" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>City <span class="text-danger">*</span></label>
                                <input type="text" name="city" id="edit_city" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>State/Province</label>
                                <input type="text" name="state" id="edit_state" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Country <span class="text-danger">*</span></label>
                                <input type="text" name="country" id="edit_country" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Postal Code</label>
                                <input type="text" name="postal_code" id="edit_postal_code" class="form-control" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status <span class="text-danger">*</span></label>
                                <select name="status" id="edit_status" class="form-control" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <h5>Contact Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" name="phone" id="edit_phone" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" id="edit_email" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    <h5>Manager Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Manager Name</label>
                                <input type="text" name="manager_name" id="edit_manager_name" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Manager Contact</label>
                                <input type="tel" name="manager_contact" id="edit_manager_contact" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="response"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="updateBranch">Update Branch</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Branch Modal -->
<div id="deleteBranchModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-trash"></em> Delete Branch</h4>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the branch <strong id="delete_branch_name"></strong>?</p>
                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone!</p>
                <form id="deleteBranchForm" method="post">
                    <input type="hidden" name="action" value="delete_branch">
                    <input type="hidden" name="branch_id" id="delete_branch_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete Branch</button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Rooms Modal -->
<div id="manageRoomsModal" class="modal fade" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><em class="fa fa-bed"></em> Manage Rooms - <span id="manage_rooms_branch_name"></span></h4>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs">
                    <li class="active"><a data-toggle="tab" href="#branch-rooms">Branch Rooms</a></li>
                    <li><a data-toggle="tab" href="#create-room">Create New Room</a></li>
                </ul>
                
                <div class="tab-content" style="margin-top: 20px;">
                    <!-- Branch Rooms Tab -->
                    <div id="branch-rooms" class="tab-pane fade in active">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="branchRoomsTable">
                                <thead>
                                    <tr>
                                        <th>Room No</th>
                                        <th>Room Type</th>
                                        <th>Status</th>
                                        <th>Check In</th>
                                        <th>Check Out</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="branchRoomsBody">
                                    <!-- Rooms will be loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Create New Room Tab -->
                    <div id="create-room" class="tab-pane fade">
                        <form id="createRoomForm" method="post">
                            <input type="hidden" name="action" value="create_room_for_branch">
                            <input type="hidden" name="branch_id" id="create_room_branch_id">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="form-group">
                                <label>Room Type <span class="text-danger">*</span></label>
                                <select name="room_type_id" id="create_room_type_id" class="form-control" required>
                                    <option value="">Select Room Type</option>
                                    <?php
                                    $roomTypesQuery = "SELECT * FROM room_type ORDER BY room_type";
                                    $roomTypesResult = mysqli_query($connection, $roomTypesQuery);
                                    while ($roomType = mysqli_fetch_assoc($roomTypesResult)) {
                                        echo '<option value="' . $roomType['room_type_id'] . '">' . 
                                             htmlspecialchars($roomType['room_type']) . ' - LKR ' . 
                                             number_format($roomType['price']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Room Number <span class="text-danger">*</span></label>
                                <input type="text" name="room_no" id="create_room_no" class="form-control" required maxlength="10">
                                <small class="text-muted">Enter a unique room number (e.g., A-101, B-205)</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Create Room</button>
                            <div class="response" style="margin-top: 10px;"></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
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
    $('#branchesTable').DataTable({
        "pageLength": 25,
        "order": [[0, "desc"]]
    });
    
    // Save new branch
    $('#saveBranch').on('click', function() {
        $('#addBranchForm').submit();
    });
    
    // Edit branch
    $('.edit-branch').on('click', function() {
        $('#edit_branch_id').val($(this).data('branch-id'));
        $('#edit_branch_name').val($(this).data('branch-name'));
        $('#edit_branch_code').val($(this).data('branch-code'));
        $('#edit_address').val($(this).data('address'));
        $('#edit_city').val($(this).data('city'));
        $('#edit_state').val($(this).data('state'));
        $('#edit_country').val($(this).data('country'));
        $('#edit_postal_code').val($(this).data('postal-code'));
        $('#edit_phone').val($(this).data('phone'));
        $('#edit_email').val($(this).data('email'));
        $('#edit_manager_name').val($(this).data('manager-name'));
        $('#edit_manager_contact').val($(this).data('manager-contact'));
        $('#edit_status').val($(this).data('status'));
        $('#editBranchModal').modal('show');
    });
    
    // Update branch
    $('#updateBranch').on('click', function() {
        $('#editBranchForm').submit();
    });
    
    // Delete branch
    $('.delete-branch').on('click', function() {
        var branchId = $(this).data('branch-id');
        var branchName = $(this).data('branch-name');
        $('#delete_branch_id').val(branchId);
        $('#delete_branch_name').text(branchName);
        $('#deleteBranchModal').modal('show');
    });
    
    // Confirm delete
    $('#confirmDelete').on('click', function() {
        $('#deleteBranchForm').submit();
    });
    
    // Auto-uppercase branch code
    $('input[name="branch_code"], input[name="branch_code"]').on('input', function() {
        this.value = this.value.toUpperCase();
    });
    
    // Manage rooms
    $('.manage-rooms').on('click', function() {
        var branchId = $(this).data('branch-id');
        var branchName = $(this).data('branch-name');
        
        $('#manage_rooms_branch_name').text(branchName);
        $('#create_room_branch_id').val(branchId);
        
        // Load branch rooms
        loadBranchRooms(branchId);
        
        $('#manageRoomsModal').modal('show');
    });
    
    // Load branch rooms
    function loadBranchRooms(branchId) {
        $.ajax({
            url: '../ajax.php',
            type: 'POST',
            data: {
                action: 'get_branch_rooms',
                branch_id: branchId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Clear previous results
                    var html = '';
                    
                    // Verify we're showing rooms for the correct branch
                    if (response.branch_id && response.branch_id != branchId) {
                        console.warn('Branch ID mismatch! Expected:', branchId, 'Got:', response.branch_id);
                    }
                    
                    if (response.rooms && response.rooms.length > 0) {
                        response.rooms.forEach(function(room) {
                            html += '<tr>';
                            html += '<td>' + room.room_no + '</td>';
                            html += '<td>' + room.room_type + '</td>';
                            html += '<td>';
                            if (room.status == 1) {
                                html += '<span class="label label-danger">Booked</span>';
                            } else {
                                html += '<span class="label label-success">Available</span>';
                            }
                            html += '</td>';
                            html += '<td>';
                            if (room.check_in_status == 1) {
                                html += '<span class="label label-warning">Checked In</span>';
                            } else {
                                html += '<span class="text-muted">-</span>';
                            }
                            html += '</td>';
                            html += '<td>';
                            if (room.check_out_status == 1) {
                                html += '<span class="label label-info">Checked Out</span>';
                            } else {
                                html += '<span class="text-muted">-</span>';
                            }
                            html += '</td>';
                            html += '<td>';
                            html += '<button class="btn btn-danger btn-xs remove-room-from-branch" data-room-id="' + room.room_id + '" data-room-no="' + room.room_no + '">';
                            html += '<em class="fa fa-times"></em> Remove</button>';
                            html += '</td>';
                            html += '</tr>';
                        });
                    } else {
                        html = '<tr><td colspan="6" class="text-center text-muted">No rooms assigned to this branch</td></tr>';
                    }
                    $('#branchRoomsBody').html(html);
                }
            }
        });
    }
    
    // Remove room from branch
    $(document).on('click', '.remove-room-from-branch', function() {
        if (!confirm('Remove room ' + $(this).data('room-no') + ' from this branch?')) {
            return;
        }
        
        var roomId = $(this).data('room-id');
        var branchId = $('#create_room_branch_id').val();
        
        $.ajax({
            url: '../ajax.php',
            type: 'POST',
            data: {
                action: 'remove_room_from_branch',
                room_id: roomId,
                csrf_token: '<?php echo generateCSRFToken(); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    loadBranchRooms(branchId);
                    alert('Room removed successfully!');
                } else {
                    alert(response.message || 'Error removing room');
                }
            }
        });
    });
    
    // Create room form submission
    $('#createRoomForm').on('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        var $submitBtn = $(this).find('button[type="submit"]');
        var originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true).html('<em class="fa fa-spinner fa-spin"></em> Creating...');
        
        $.ajax({
            url: 'branches.php',
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            data: $(this).serialize() + '&ajax=1',
            success: function(response) {
                $submitBtn.prop('disabled', false).html(originalText);
                
                if (response && response.success) {
                    $('#createRoomForm .response').html('<div class="alert alert-success">' + (response.message || 'Room created successfully!') + '</div>');
                    var branchId = $('#create_room_branch_id').val();
                    loadBranchRooms(branchId);
                    $('#createRoomForm')[0].reset();
                    setTimeout(function() {
                        $('#createRoomForm .response').html('');
                    }, 3000);
                } else {
                    $('#createRoomForm .response').html('<div class="alert alert-danger">' + (response ? (response.message || 'Error creating room') : 'Error creating room') + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $submitBtn.prop('disabled', false).html(originalText);
                
                // Try to parse as JSON first
                var errorMessage = 'Error creating room. Please try again.';
                try {
                    if (xhr.responseText) {
                        var response = JSON.parse(xhr.responseText);
                        if (response && response.message) {
                            errorMessage = response.message;
                        }
                    }
                } catch(e) {
                    // If response is HTML, check for error messages
                    if (xhr.responseText && xhr.responseText.indexOf('Error') !== -1) {
                        errorMessage = 'An error occurred. Please check the form and try again.';
                    }
                }
                
                $('#createRoomForm .response').html('<div class="alert alert-danger">' + errorMessage + '</div>');
                console.error('AJAX Error:', status, error, xhr.responseText);
            }
        });
    });
});
</script>

<?php include_once "../footer.php"; ?>
