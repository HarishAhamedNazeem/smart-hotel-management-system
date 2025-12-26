<?php 
    include './db.php';
    require_once 'includes/auth.php';
    require_once 'includes/rbac.php';
    
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
    
    // Check if deleteStatus or is_deleted column exists
    $checkColumn = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'deleteStatus'");
    $hasDeleteStatus = mysqli_num_rows($checkColumn) > 0;
    
    $checkColumn2 = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'is_deleted'");
    $hasIsDeleted = mysqli_num_rows($checkColumn2) > 0;
    
    $sql = "SELECT * FROM room WHERE status = 0";
    
    // Add soft delete filter if column exists
    if ($hasDeleteStatus) {
        $sql .= " AND deleteStatus = 0";
    } elseif ($hasIsDeleted) {
        $sql .= " AND is_deleted = 0";
    }
    
    // Add branch filter for branch admins
    if ($userBranchId && !hasRole('super_admin')) {
        $sql .= " AND branch_id = " . intval($userBranchId);
    }
    
    $query = $connection->query($sql);
    echo "$query->num_rows";
?>