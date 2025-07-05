<?php
/**
 * Export Attendance Records to CSV
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: ../login.php");
    exit;
}

// Get filter parameters (same as in attendance.php)
$search = "";
$filter = "all";
$event_id = 0;

// Get search parameter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Get filter parameters
if (isset($_GET['filter']) && in_array($_GET['filter'], ['all', 'pending', 'verified'])) {
    $filter = $_GET['filter'];
}

// Get event filter
if (isset($_GET['event']) && is_numeric($_GET['event'])) {
    $event_id = intval($_GET['event']);
}

// Build query with filters (similar to attendance.php but without pagination)
$query = "SELECT a.*, e.title as event_title, e.event_date, e.event_time, u.name as student_name, 
          u.matric_id, u.email, c.name as category_name, 
          v.name as verified_by_name
          FROM attendance a
          JOIN events e ON a.event_id = e.id
          JOIN users u ON a.user_id = u.id
          JOIN event_categories c ON e.category_id = c.id
          LEFT JOIN users v ON a.verified_by = v.id
          WHERE 1=1";

// Add filters
if ($filter === 'pending') {
    $query .= " AND a.verified = 0";
} elseif ($filter === 'verified') {
    $query .= " AND a.verified = 1";
}

if ($event_id > 0) {
    $query .= " AND a.event_id = " . $event_id;
}

if (!empty($search)) {
    $query .= " AND (u.name LIKE '%" . $conn->real_escape_string($search) . "%' OR 
                     u.matric_id LIKE '%" . $conn->real_escape_string($search) . "%' OR 
                     e.title LIKE '%" . $conn->real_escape_string($search) . "%')";
}

// Order by attendance time (most recent first)
$query .= " ORDER BY a.attendance_time DESC";

// Execute query
$result = $conn->query($query);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=attendance_export_' . date('Y-m-d') . '.csv');

// Create file pointer connected to PHP output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel CSV encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Set column headers
fputcsv($output, [
    'ID',
    'Student Name',
    'Matric ID',
    'Email',
    'Event',
    'Event Date',
    'Event Time',
    'Category',
    'Attendance Time',
    'Method',
    'Status',
    'Verification Time',
    'Verified By'
]);

// Add data rows
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Format dates and times
        $event_date = date("d/m/Y", strtotime($row['event_date']));
        $event_time = date("h:i A", strtotime($row['event_time']));
        $attendance_time = date("d/m/Y h:i A", strtotime($row['attendance_time']));
        $verification_time = $row['verification_time'] ? date("d/m/Y h:i A", strtotime($row['verification_time'])) : 'N/A';
        
        // Format method
        $method = ($row['method'] == 'qr') ? 'QR Scan' : 'CAPTCHA';
        
        // Format status
        $status = $row['verified'] ? 'Verified' : 'Pending';
        
        // Output CSV row
        fputcsv($output, [
            $row['id'],
            $row['student_name'],
            $row['matric_id'],
            $row['email'],
            $row['event_title'],
            $event_date,
            $event_time,
            $row['category_name'],
            $attendance_time,
            $method,
            $status,
            $verification_time,
            $row['verified_by_name'] ?? 'N/A'
        ]);
    }
}

// Close the file pointer
fclose($output);
exit;
?>