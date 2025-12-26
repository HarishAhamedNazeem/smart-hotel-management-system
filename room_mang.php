<div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
    <div class="row">
        <ol class="breadcrumb">
            <li><a href="#">
                    <em class="fa fa-home"></em>
                </a></li>
            <li class="active">Manage Rooms</li>
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
                <div class="panel-heading">Manage Rooms
                    <div class="pull-right">
                        <button class="btn btn-info" style="border-radius:0%; margin-right: 5px;" data-toggle="modal" data-target="#addRoomType">
                            <em class="fa fa-plus"></em> Add Room Type
                        </button>
                        <button class="btn btn-secondary" style="border-radius:0%" data-toggle="modal" data-target="#addRoom">Add Rooms</button>
                    </div>
                </div>
                <div class="panel-body">
                    <?php
                    if (isset($_GET['error'])) {
                        echo "<div class='alert alert-danger'>
                                <span class='glyphicon glyphicon-info-sign'></span> &nbsp; Error on Delete !
                            </div>";
                    }
                    if (isset($_GET['success'])) {
                        echo "<div class='alert alert-success'>
                                <span class='glyphicon glyphicon-info-sign'></span> &nbsp; Successfully Delete !
                            </div>";
                    }
                    ?>
                    <table class="table table-striped table-bordered table-responsive" cellspacing="0" width="100%"
                           id="rooms">
                        <thead>
                        <tr>
                            <th>Room No</th>
                            <th>Branch</th>
                            <th>Room Type</th>
                            <th>Booking Status</th>
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
                        
                        // Build query with branch filter for branch admins
                        // Check if deleteStatus or is_deleted column exists
                        $checkColumn = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'deleteStatus'");
                        $hasDeleteStatus = mysqli_num_rows($checkColumn) > 0;
                        
                        $checkColumn2 = mysqli_query($connection, "SHOW COLUMNS FROM room LIKE 'is_deleted'");
                        $hasIsDeleted = mysqli_num_rows($checkColumn2) > 0;
                        
                        $room_query = "SELECT r.*, b.branch_name, b.branch_code, rt.room_type 
                                      FROM room r 
                                      LEFT JOIN branches b ON r.branch_id = b.branch_id 
                                      LEFT JOIN room_type rt ON r.room_type_id = rt.room_type_id 
                                      WHERE 1=1";
                        
                        // Add soft delete filter if column exists
                        if ($hasDeleteStatus) {
                            $room_query .= " AND r.deleteStatus = 0";
                        } elseif ($hasIsDeleted) {
                            $room_query .= " AND r.is_deleted = 0";
                        }
                        
                        if ($userBranchId && !hasRole('super_admin')) {
                            // Branch admin - only show rooms from their branch
                            $room_query .= " AND r.branch_id = " . intval($userBranchId);
                        }
                        
                        $rooms_result = mysqli_query($connection, $room_query);
                        if (mysqli_num_rows($rooms_result) > 0) {
                            while ($rooms = mysqli_fetch_assoc($rooms_result)) { ?>
                                <tr>
                                    <td><?php echo $rooms['room_no'] ?></td>
                                    <td><?php echo $rooms['branch_name'] ? htmlspecialchars($rooms['branch_name'] . ' (' . $rooms['branch_code'] . ')') : '<em class="text-muted">Not Assigned</em>'; ?></td>
                                    <td><?php echo $rooms['room_type'] ? htmlspecialchars($rooms['room_type']) : '<em class="text-muted">Not Set</em>' ?></td>
                                    <td>
                                        <?php
                                        if ($rooms['status'] == 0) {
                                            echo '<span class="label label-success">Available</span>';
                                        } else {
                                            echo '<span class="label label-danger">Occupied</span>';
                                        }
                                        ?>
                                    <td>
                                        <button title="Edit Room Information" style="border-radius:60px;" data-toggle="modal"
                                                data-target="#editRoom" data-id="<?php echo $rooms['room_id']; ?>"
                                                id="roomEdit" class="btn btn-info"><i class="fa fa-pencil"></i></button>

                                        <a href="ajax.php?delete_room=<?php echo $rooms['room_id']; ?>"
                                           class="btn btn-danger" style="border-radius:60px;" onclick="return confirm('Are you Sure?')"><i
                                                    class="fa fa-trash" alt="delete"></i></a>
                                    </td>
                                </tr>
                            <?php }
                        } else {
                            echo "No Rooms";
                        }
                        ?>

                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Add Room Modal -->
    <div id="addRoom" class="modal fade" role="dialog">
        <div class="modal-dialog">

            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Add New Room</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <form id="addRoomForm" data-toggle="validator" role="form">
                                <div class="response"></div>
                                <div class="form-group">
                                    <label>Branch <span class="text-danger">*</span></label>
                                    <select class="form-control" id="branch_id" required
                                            data-error="Select Branch">
                                        <option value="">Select Branch</option>
                                        <?php
                                        // Get user's branch if they're a branch admin, otherwise show all branches
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
                                        
                                        // Build query based on user role
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
                                    <div class="help-block with-errors"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Room Type <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <select class="form-control" id="room_type_id" required
                                                data-error="Select Room Type">
                                            <option value="">Select Room Type</option>
                                            <?php
                                            $query = "SELECT * FROM room_type ORDER BY room_type";
                                            $result = mysqli_query($connection, $query);
                                            if (mysqli_num_rows($result) > 0) {
                                                while ($room_type = mysqli_fetch_assoc($result)) {
                                                    echo '<option value="' . $room_type['room_type_id'] . '">' . 
                                                         htmlspecialchars($room_type['room_type']) . 
                                                         ' (LKR ' . number_format($room_type['price']) . '/night, Max ' . $room_type['max_person'] . ' persons)' . 
                                                         '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <span class="input-group-btn">
                                            <button class="btn btn-info" type="button" data-toggle="modal" data-target="#addRoomType" title="Add New Room Type">
                                                <em class="fa fa-plus"></em>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group">
                                    <label>Room No</label>
                                    <input class="form-control" placeholder="Room No" id="room_no"
                                           data-error="Enter Room No" required>
                                    <div class="help-block with-errors"></div>
                                </div>
                                <button class="btn btn-success pull-right" style="border-radius:0%">Add Room</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!--Edit Room Modal -->
    <div id="editRoom" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Edit Room</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <form id="roomEditFrom" data-toggle="validator" role="form">
                                <div class="edit_response"></div>
                                <div class="form-group">
                                    <label>Branch <span class="text-danger">*</span></label>
                                    <select class="form-control" id="edit_branch_id" required
                                            data-error="Select Branch">
                                        <option value="">Select Branch</option>
                                        <?php
                                        // Get user's branch if they're a branch admin, otherwise show all branches
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
                                        
                                        // Build query based on user role
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
                                    <div class="help-block with-errors"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Room Type <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <select class="form-control" id="edit_room_type" required
                                                data-error="Select Room Type">
                                            <option value="">Select Room Type</option>
                                            <?php
                                            $query = "SELECT * FROM room_type ORDER BY room_type";
                                            $result = mysqli_query($connection, $query);
                                            if (mysqli_num_rows($result) > 0) {
                                                while ($room_type = mysqli_fetch_assoc($result)) {
                                                    echo '<option value="' . $room_type['room_type_id'] . '">' . 
                                                         htmlspecialchars($room_type['room_type']) . 
                                                         ' (LKR ' . number_format($room_type['price']) . '/night, Max ' . $room_type['max_person'] . ' persons)' . 
                                                         '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                        <span class="input-group-btn">
                                            <button class="btn btn-info" type="button" data-toggle="modal" data-target="#addRoomType" title="Add New Room Type">
                                                <em class="fa fa-plus"></em>
                                            </button>
                                        </span>
                                    </div>
                                    <div class="help-block with-errors"></div>
                                </div>

                                <div class="form-group">
                                    <label>Room No</label>
                                    <input class="form-control" placeholder="Room No" id="edit_room_no" required
                                           data-error="Enter Room No">
                                    <div class="help-block with-errors"></div>
                                </div>
                                <input type="hidden" id="edit_room_id">
                                <button class="btn btn-success pull-right">Edit Room</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Room Type Modal -->
    <div id="addRoomType" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Add New Room Type</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-12">
                            <form id="addRoomTypeForm" data-toggle="validator" role="form">
                                <div class="response"></div>
                                <div class="form-group">
                                    <label>Room Type Name <span class="text-danger">*</span></label>
                                    <input class="form-control" placeholder="e.g., Deluxe, Suite, Standard" id="room_type_name"
                                           data-error="Enter Room Type Name" required maxlength="100">
                                    <div class="help-block with-errors"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Price per Night <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" placeholder="Enter price" id="room_type_price"
                                           data-error="Enter Price" required min="0" step="0.01">
                                    <div class="help-block with-errors"></div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Maximum Persons <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" placeholder="Enter maximum persons" id="room_type_max_person"
                                           data-error="Enter Maximum Persons" required min="1" max="20">
                                    <div class="help-block with-errors"></div>
                                </div>
                                
                                <button class="btn btn-success pull-right" style="border-radius:0%">Add Room Type</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>    <!--/.main-->

<script>
// Auto-refresh room status every 30 seconds to reflect check-ins/check-outs
(function() {
    function refreshRoomStatus() {
        if (typeof jQuery === 'undefined') {
            setTimeout(refreshRoomStatus, 100);
            return;
        }
        
        var $ = jQuery;
        
        // Function to update room statuses
        function updateRoomStatuses() {
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: { get_room_statuses: true },
                dataType: 'json',
                success: function(response) {
                    if (response.done && response.rooms) {
                        // Update each room's status in the table
                        $.each(response.rooms, function(index, room) {
                            // Find the row for this room
                            var $rows = $('#rooms tbody tr');
                            $rows.each(function() {
                                var roomNo = $(this).find('td:first').text().trim();
                                if (roomNo === room.room_no) {
                                    var $statusCell = $(this).find('td:eq(3)'); // Status column
                                    
                                    // Update status badge
                                    if (room.status == 0) {
                                        $statusCell.html('<span class="label label-success">Available</span>');
                                    } else {
                                        $statusCell.html('<span class="label label-danger">Occupied</span>');
                                    }
                                }
                            });
                        });
                    }
                },
                error: function() {
                    console.log('Failed to refresh room statuses');
                }
            });
        }
        
        // Update immediately on page load
        $(document).ready(function() {
            // Refresh every 30 seconds
            setInterval(updateRoomStatuses, 30000);
            
            // Also refresh when returning to the page (if user was on another tab)
            $(document).on('visibilitychange', function() {
                if (!document.hidden) {
                    updateRoomStatuses();
                }
            });
        });
    }
    
    refreshRoomStatus();
})();
</script>



