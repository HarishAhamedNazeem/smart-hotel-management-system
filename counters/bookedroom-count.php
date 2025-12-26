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
    
    $sql = "SELECT * FROM room WHERE status = '1'";
    
    // Add branch filter for branch admins
    if ($userBranchId && !hasRole('super_admin')) {
        $sql .= " AND branch_id = " . intval($userBranchId);
    }
    
    $query = $connection->query($sql);
    echo "$query->num_rows";
?>