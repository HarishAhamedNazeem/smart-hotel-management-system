<?php
/**
 * Room Status Sync Utility
 * Run this page to synchronize all room statuses with actual booking data
 */

session_start();
require_once 'db.php';
require_once 'includes/auth.php';
require_once 'includes/rbac.php';

// Require admin access
requireLogin();
if (!hasRole('super_admin') && !hasRole('administrator')) {
    die('Access denied. This utility requires administrator privileges.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Sync Room Statuses - Kaizen Hotel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .container {
            border: 1px solid #ddd;
            padding: 30px;
            border-radius: 8px;
            background: #f9f9f9;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .info {
            background: #e3f2fd;
            padding: 15px;
            border-left: 4px solid #2196f3;
            margin: 20px 0;
        }
        .warning {
            background: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            padding: 15px;
            border-left: 4px solid #28a745;
            margin: 20px 0;
            display: none;
        }
        .error {
            background: #f8d7da;
            padding: 15px;
            border-left: 4px solid #dc3545;
            margin: 20px 0;
            display: none;
        }
        button {
            background: #007bff;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #0056b3;
        }
        button:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .results {
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f2f2f2;
            font-weight: bold;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-available {
            background: #28a745;
            color: white;
        }
        .status-occupied {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Room Status Synchronization Utility</h1>
        
        <div class="info">
            <strong>ℹ️ What does this do?</strong>
            <p>This utility synchronizes all room statuses with actual booking data. It checks for active bookings (confirmed or checked-in) and updates room availability accordingly.</p>
        </div>
        
        <div class="warning">
            <strong>⚠️ When to use this:</strong>
            <ul>
                <li>Room statuses don't match actual bookings</li>
                <li>After checking out guests, rooms still show as occupied</li>
                <li>After system updates or data migrations</li>
                <li>To fix any inconsistencies in room availability</li>
            </ul>
        </div>
        
        <button id="syncButton" onclick="syncRoomStatuses()">
            Sync All Room Statuses
        </button>
        
        <div class="success" id="successMessage"></div>
        <div class="error" id="errorMessage"></div>
        
        <div class="results" id="results" style="display: none;">
            <h3>Synchronization Results:</h3>
            <table id="resultsTable">
                <thead>
                    <tr>
                        <th>Room No</th>
                        <th>Old Status</th>
                        <th>New Status</th>
                        <th>Active Bookings</th>
                    </tr>
                </thead>
                <tbody id="resultsBody"></tbody>
            </table>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <a href="index.php?room_mang" style="text-decoration: none; color: #007bff;">
                ← Back to Room Management
            </a>
        </div>
    </div>
    
    <script>
    function syncRoomStatuses() {
        const button = document.getElementById('syncButton');
        const successMessage = document.getElementById('successMessage');
        const errorMessage = document.getElementById('errorMessage');
        const results = document.getElementById('results');
        
        // Disable button and show loading
        button.disabled = true;
        button.textContent = 'Syncing...';
        successMessage.style.display = 'none';
        errorMessage.style.display = 'none';
        results.style.display = 'none';
        
        // First, get current room statuses
        fetch('ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'get_room_statuses=true'
        })
        .then(response => response.json())
        .then(oldData => {
            // Now sync all statuses
            return fetch('ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'sync_all_room_statuses=true'
            })
            .then(response => response.json())
            .then(syncResult => {
                return { oldData, syncResult };
            });
        })
        .then(({ oldData, syncResult }) => {
            if (syncResult.done) {
                // Get new statuses
                return fetch('ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'get_room_statuses=true'
                })
                .then(response => response.json())
                .then(newData => {
                    return { oldData, syncResult, newData };
                });
            } else {
                throw new Error(syncResult.data || 'Sync failed');
            }
        })
        .then(({ oldData, syncResult, newData }) => {
            // Show success message
            successMessage.textContent = '✓ ' + syncResult.data;
            successMessage.style.display = 'block';
            
            // Build results table
            const tbody = document.getElementById('resultsBody');
            tbody.innerHTML = '';
            
            if (oldData.rooms && newData.rooms) {
                oldData.rooms.forEach(oldRoom => {
                    const newRoom = newData.rooms.find(r => r.room_id === oldRoom.room_id);
                    if (newRoom) {
                        const changed = oldRoom.status !== newRoom.status;
                        const row = document.createElement('tr');
                        row.style.background = changed ? '#fff3cd' : 'white';
                        
                        row.innerHTML = `
                            <td><strong>${oldRoom.room_no}</strong></td>
                            <td><span class="status-badge status-${oldRoom.status == 0 ? 'available' : 'occupied'}">${oldRoom.status == 0 ? 'Available' : 'Occupied'}</span></td>
                            <td><span class="status-badge status-${newRoom.status == 0 ? 'available' : 'occupied'}">${newRoom.status == 0 ? 'Available' : 'Occupied'}</span></td>
                            <td>${changed ? 'Updated' : 'No change'}</td>
                        `;
                        tbody.appendChild(row);
                    }
                });
            }
            
            results.style.display = 'block';
            
            // Re-enable button
            button.disabled = false;
            button.textContent = 'Sync Again';
        })
        .catch(error => {
            errorMessage.textContent = '✗ Error: ' + error.message;
            errorMessage.style.display = 'block';
            
            button.disabled = false;
            button.textContent = 'Try Again';
        });
    }
    </script>
</body>
</html>
