<?php
/**
 * Logout Page
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once 'config/db.php';

// Initialize the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("location: admin_login.php");
exit;
?>