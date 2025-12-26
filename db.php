<?php
/**
 * Database Connection
 * Smart Hotel Management System
 */

// Include configuration
require_once __DIR__ . '/config.php';

// Create database connection
$connection = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$connection) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4 for proper character encoding
mysqli_set_charset($connection, "utf8mb4");

