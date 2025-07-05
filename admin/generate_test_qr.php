<?php
/**
 * Generate Test QR Code (For Demonstration Purposes Only)
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection and phpqrcode library
require_once '../config/db.php';
require_once '../lib/phpqrcode/qrlib.php';

// Start or resume session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if event ID is provided
if (!isset($_POST['event_id']) || empty($_POST['event_id'])) {
    echo json_encode(['success' => false, 'message' => 'No event specified']);
    exit;
}

$event_id = intval($_POST['event_id']);

// Verify event exists
$event_sql = "SELECT id FROM events WHERE id = ?";
if ($stmt = $conn->prepare($event_sql)) {
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows != 1) {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        exit;
    }
    
    $stmt->close();
}

// Generate a unique token for attendance
function generateToken($length = 32) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $token;
}

// Generate test token
$token = generateToken();

// Store token in session for verification
$_SESSION['qr_token_' . $event_id] = $token;
$_SESSION['qr_token_time_' . $event_id] = time();

// QR code content with test flag
$qr_content = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/student/mark_attendance.php?event=' . $event_id . '&token=' . $token . '&test=1';

// Generate QR code file path
$temp_dir = "../temp/qrcodes/";
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0777, true);
}

// Create a unique filename with timestamp to prevent caching
$timestamp = time();
$qr_filename = "test_qrcode_" . $event_id . "_" . $timestamp . ".png";
$qr_filepath = $temp_dir . $qr_filename;

// Generate QR code and save to file
QRcode::png($qr_content, $qr_filepath, QR_ECLEVEL_L, 10);

// URL to the QR code image
$qr_url = "../temp/qrcodes/" . $qr_filename;

// Return success with the test QR code URL
echo json_encode([
    'success' => true, 
    'qr_url' => $qr_url,
    'message' => 'Test QR code generated successfully'
]);