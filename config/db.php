<?php
/**
 * Database Connection Configuration
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Database Configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "eventsphere_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, 3307);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// time zone
date_default_timezone_set('Asia/Kuala_Lumpur');
?>