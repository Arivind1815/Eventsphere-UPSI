<?php
/**
 * QR Code Generator for Event Attendance
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Start or resume session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if event ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("No event specified");
}

$event_id = intval($_GET['id']);

// Verify event exists
$event_sql = "SELECT id, title FROM events WHERE id = ?";
if ($stmt = $conn->prepare($event_sql)) {
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows != 1) {
        die("Event not found");
    }
    
    $event = $result->fetch_assoc();
    $stmt->close();
}

// Generate a unique token for this event attendance
// Use built-in PHP functions instead of random_bytes for wider compatibility
function generateToken($length = 32) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $token;
}

$token = generateToken(32);

// Store token in session for verification
$_SESSION['qr_token_' . $event_id] = $token;
$_SESSION['qr_token_time_' . $event_id] = time();

// Create the QR code content
// This will point to the student-facing attendance marking page
$qr_content = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/student/mark_attendance.php?event=' . $event_id . '&token=' . $token;

// Use Google Chart API to generate QR code
$size = isset($_GET['size']) ? intval($_GET['size']) : 300;
$google_chart_api_url = 'https://chart.googleapis.com/chart?cht=qr&chs=' . $size . 'x' . $size . '&chl=' . urlencode($qr_content);

// If test parameter is set, just show the content that would be in the QR
if (isset($_GET['debug'])) {
    echo "QR Content: " . htmlspecialchars($qr_content);
    echo "<br><br>";
    echo "Google API URL: " . htmlspecialchars($google_chart_api_url);
    exit;
}

// Option 1: Redirect to Google Chart API
// header("Location: " . $google_chart_api_url);
// exit;

// Option 2: Fetch the QR code image and output it
$qr_image = file_get_contents($google_chart_api_url);
header('Content-Type: image/png');
echo $qr_image;