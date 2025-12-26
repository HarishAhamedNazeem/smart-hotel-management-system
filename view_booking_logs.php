<?php
/**
 * View Recent Booking Email Debug Logs
 * Shows the last 100 lines of PHP error log filtered for booking email messages
 */

header('Content-Type: text/html; charset=utf-8');

$errorLogPath = ini_get('error_log');
if (empty($errorLogPath) || $errorLogPath == 'syslog') {
    // Try XAMPP default location
    $errorLogPath = 'C:\xampp\apache\logs\error.log';
    if (!file_exists($errorLogPath)) {
        $errorLogPath = 'C:\xampp\php\logs\php_error_log';
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Booking Email Debug Logs</title>
    <style>
        body { 
            font-family: 'Courier New', monospace; 
            padding: 20px; 
            background: #1e1e1e; 
            color: #d4d4d4;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #252526;
            padding: 20px;
            border-radius: 5px;
        }
        h1 { color: #4ec9b0; }
        .log-line { 
            padding: 5px; 
            border-left: 3px solid #333; 
            margin: 5px 0;
            font-size: 13px;
            line-height: 1.4;
        }
        .log-line.debug { border-left-color: #007acc; background: rgba(0, 122, 204, 0.1); }
        .log-line.success { border-left-color: #4ec9b0; background: rgba(78, 201, 176, 0.1); }
        .log-line.error { border-left-color: #f48771; background: rgba(244, 135, 113, 0.1); }
        .log-line.warning { border-left-color: #dcdcaa; background: rgba(220, 220, 170, 0.1); }
        .timestamp { color: #858585; }
        .message { color: #d4d4d4; }
        .controls {
            background: #2d2d30;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 3px;
        }
        .btn {
            background: #0e639c;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 3px;
            margin-right: 10px;
        }
        .btn:hover { background: #1177bb; }
        .info { 
            background: #1e1e1e; 
            padding: 10px; 
            border-left: 3px solid #4ec9b0; 
            margin-bottom: 20px;
        }
        .no-logs {
            text-align: center;
            padding: 40px;
            color: #858585;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Booking Email Debug Logs</h1>
        
        <div class="info">
            <strong>Log file:</strong> <?php echo htmlspecialchars($errorLogPath); ?><br>
            <strong>Last updated:</strong> <?php echo file_exists($errorLogPath) ? date('Y-m-d H:i:s', filemtime($errorLogPath)) : 'File not found'; ?>
        </div>

        <div class="controls">
            <button class="btn" onclick="location.reload()">🔄 Refresh Logs</button>
            <button class="btn" onclick="filterLogs('all')">Show All</button>
            <button class="btn" onclick="filterLogs('debug')">Debug Only</button>
            <button class="btn" onclick="filterLogs('error')">Errors Only</button>
        </div>

        <div id="logs">
<?php
if (!file_exists($errorLogPath)) {
    echo "<div class='no-logs'>";
    echo "<h2>❌ Log file not found</h2>";
    echo "<p>Tried: $errorLogPath</p>";
    echo "<p>Create a booking to generate logs</p>";
    echo "</div>";
} else {
    // Read last 200 lines of error log
    $lines = [];
    $file = new SplFileObject($errorLogPath, 'r');
    $file->seek(PHP_INT_MAX);
    $totalLines = $file->key();
    
    $startLine = max(0, $totalLines - 200);
    $file->seek($startLine);
    
    while (!$file->eof()) {
        $line = $file->current();
        $file->next();
        
        // Filter for booking email related logs
        if (stripos($line, 'BOOKING EMAIL') !== false || 
            stripos($line, 'confirmation email') !== false ||
            stripos($line, 'sendBookingConfirmationEmail') !== false ||
            stripos($line, 'notification_settings') !== false ||
            stripos($line, 'email_notifications.php') !== false) {
            $lines[] = $line;
        }
    }
    
    if (empty($lines)) {
        echo "<div class='no-logs'>";
        echo "<h2>📭 No email logs found</h2>";
        echo "<p>No booking email logs in the last 200 log entries.</p>";
        echo "<p><strong>Next steps:</strong></p>";
        echo "<ol style='text-align: left; display: inline-block;'>";
        echo "<li>Create a test booking</li>";
        echo "<li>Refresh this page</li>";
        echo "<li>You should see debug messages here</li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<p><strong>Found " . count($lines) . " email-related log entries:</strong></p>";
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Determine log type
            $class = 'log-line';
            if (stripos($line, 'BOOKING EMAIL DEBUG') !== false) {
                $class .= ' debug';
            } elseif (stripos($line, '✓') !== false || stripos($line, 'successfully') !== false) {
                $class .= ' success';
            } elseif (stripos($line, '✗') !== false || stripos($line, 'failed') !== false || stripos($line, 'error') !== false) {
                $class .= ' error';
            } elseif (stripos($line, 'NOT FOUND') !== false || stripos($line, 'DOES NOT EXIST') !== false) {
                $class .= ' warning';
            }
            
            // Extract timestamp if present
            if (preg_match('/^\[(.*?)\]/', $line, $matches)) {
                $timestamp = $matches[1];
                $message = substr($line, strlen($matches[0]));
                
                echo "<div class='$class'>";
                echo "<span class='timestamp'>[$timestamp]</span> ";
                echo "<span class='message'>" . htmlspecialchars($message) . "</span>";
                echo "</div>";
            } else {
                echo "<div class='$class'>";
                echo "<span class='message'>" . htmlspecialchars($line) . "</span>";
                echo "</div>";
            }
        }
    }
}
?>
        </div>
    </div>

    <script>
    function filterLogs(type) {
        const logs = document.querySelectorAll('.log-line');
        logs.forEach(log => {
            if (type === 'all') {
                log.style.display = 'block';
            } else if (type === 'debug' && log.classList.contains('debug')) {
                log.style.display = 'block';
            } else if (type === 'error' && log.classList.contains('error')) {
                log.style.display = 'block';
            } else if (type !== 'all') {
                log.style.display = 'none';
            }
        });
    }
    </script>
</body>
</html>
