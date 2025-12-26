<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once "db.php";
require_once "includes/auth.php";
require_once "includes/rbac.php";
require_once "includes/security.php";

requireLogin();

include_once "header.php";
include_once "sidebars/sidebar.php";

// Get staff statistics by type
$staffStatsQuery = "SELECT st.staff_type, COUNT(s.emp_id) as count
                    FROM staff_type st
                    LEFT JOIN staff s ON st.staff_type_id = s.staff_type_id
                    GROUP BY st.staff_type_id, st.staff_type
                    ORDER BY count DESC";
$staffStatsResult = mysqli_query($connection, $staffStatsQuery);
$staffStatsData = [];
while ($row = mysqli_fetch_assoc($staffStatsResult)) {
    $staffStatsData[] = [$row['staff_type'], (int)$row['count']];
}

// Get booking statistics by month
$bookingStatsQuery = "SELECT 
    DATE_FORMAT(STR_TO_DATE(check_in, '%d-%m-%Y'), '%Y-%m') as month,
    COUNT(*) as bookings,
    SUM(total_price) as revenue
    FROM booking
    WHERE payment_status = 1
    GROUP BY DATE_FORMAT(STR_TO_DATE(check_in, '%d-%m-%Y'), '%Y-%m')
    ORDER BY month DESC
    LIMIT 12";
$bookingStatsResult = mysqli_query($connection, $bookingStatsQuery);
$bookingCalendarData = [];
while ($row = mysqli_fetch_assoc($bookingStatsResult)) {
    // Store month string and booking count
    $bookingCalendarData[] = [$row['month'], (int)$row['bookings']];
}

// Get room type popularity
$roomTypeStatsQuery = "SELECT rt.room_type, COUNT(b.booking_id) as bookings, SUM(b.total_price) as revenue
                       FROM room_type rt
                       LEFT JOIN room r ON rt.room_type_id = r.room_type_id
                       LEFT JOIN booking b ON r.room_id = b.room_id AND b.payment_status = 1
                       GROUP BY rt.room_type_id, rt.room_type
                       ORDER BY bookings DESC";
$roomTypeStatsResult = mysqli_query($connection, $roomTypeStatsQuery);
$roomTypeData = [];
while ($row = mysqli_fetch_assoc($roomTypeStatsResult)) {
    $roomTypeData[] = [$row['room_type'], (int)$row['bookings']];
}
?>

<html>
  <head>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
      google.charts.load("current", {packages:["corechart"]});
      google.charts.setOnLoadCallback(drawStaffChart);
      
      function drawStaffChart() {
        var data = google.visualization.arrayToDataTable([
          ['Employee Type', 'Count'],
          <?php
          $first = true;
          foreach ($staffStatsData as $stat) {
              if (!$first) echo ',';
              echo "['" . addslashes($stat[0]) . "', " . $stat[1] . "]";
              $first = false;
          }
          ?>
        ]);

        var options = {
          title: 'Employees According To Positions',
          is3D: true,
          width: 400,
          height: 400
        };

        var chart = new google.visualization.PieChart(document.getElementById('piechart_3d'));
        chart.draw(data, options);
      }
    </script>
<style>
#piechart_3d{
		width: 400px; 
		height: 400px;
	        margin: 20px auto;
            }
#barchart_values{
		width: 400px; 
		height: 400px;
	        margin: 20px auto;
            }
#calendar_basic{
		width: 1000px; 
		height: 250px;
	        margin: 20px auto;
            }
.charts-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 20px;
}
</style>
 <script type="text/javascript">
    google.charts.load("current", {packages:["corechart"]});
    google.charts.setOnLoadCallback(drawRoomTypeChart);
    
    function drawRoomTypeChart() {
      var data = google.visualization.arrayToDataTable([
        ["Room Type", "Bookings", { role: "style" } ],
        <?php
        $colors = ["#3D2C8D", "#BFC0C0", "#5A4BCF", "#2A1F5F", "#3D2C8D", "#5A4BCF"];
        $first = true;
        $colorIndex = 0;
        foreach ($roomTypeData as $stat) {
            if (!$first) echo ',';
            $color = $colors[$colorIndex % count($colors)];
            echo "['" . addslashes($stat[0]) . "', " . $stat[1] . ", '" . $color . "']";
            $first = false;
            $colorIndex++;
        }
        ?>
      ]);

      var view = new google.visualization.DataView(data);
      view.setColumns([0, 1,
                       { calc: "stringify",
                         sourceColumn: 1,
                         type: "string",
                         role: "annotation" },
                       2]);

      var options = {
        title: "Room Type Popularity (Bookings)",
        width: 410,
        height: 400,
        bar: {groupWidth: "95%"},
        legend: { position: "none" },
      };
      var chart = new google.visualization.BarChart(document.getElementById("barchart_values"));
      chart.draw(view, options);
  }
  </script>
  <script type="text/javascript">
      google.charts.load("current", {packages:["calendar"]});
      google.charts.setOnLoadCallback(drawChart);

   function drawChart() {
       var dataTable = new google.visualization.DataTable();
       dataTable.addColumn({ type: 'date', id: 'Date' });
       dataTable.addColumn({ type: 'number', id: 'Room Booked' });
       dataTable.addRows([
          <?php
          $first = true;
          foreach ($bookingCalendarData as $booking) {
              if (!$first) echo ',';
              // $booking[0] is a string, need to parse it
              $dateStr = $booking[0];
              if (is_string($dateStr)) {
                  $date = DateTime::createFromFormat('Y-m-d', $dateStr . '-15');
                  if ($date) {
                      echo "[new Date(" . $date->format('Y') . ", " . 
                           ($date->format('n') - 1) . ", 15), " . 
                           $booking[1] . "]";
                  }
              }
              $first = false;
          }
          // If no data, add some placeholder
          if (empty($bookingCalendarData)) {
              $today = new DateTime();
              echo "[new Date(" . $today->format('Y') . ", " . 
                   ($today->format('n') - 1) . ", 15), 0]";
          }
          ?>
        ]);

       var chart = new google.visualization.Calendar(document.getElementById('calendar_basic'));

       var options = {
         title: "Reserved Room on Different Day",
         height: 350,
       };

       chart.draw(dataTable, options);
   }
</script>
  </head>
  <body>
    <div class="col-sm-9 col-sm-offset-3 col-lg-10 col-lg-offset-2 main">
        <div class="row">
            <ol class="breadcrumb">
                <li><a href="#"><em class="fa fa-home"></em></a></li>
                <li class="active">Statistics</li>
            </ol>
        </div>
        
        <div class="row">
            <div class="col-lg-12">
                <h1 class="page-header">
                    <em class="fa fa-pie-chart"></em> Statistics & Analytics
                </h1>
            </div>
        </div>
        
        <div class="charts-container">
            <div id="piechart_3d"></div>
            <div id="barchart_values"></div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <div id="calendar_basic"></div>
        </div>
    </div>
  </body>
</html>
<?php
include_once "footer.php";
?>
