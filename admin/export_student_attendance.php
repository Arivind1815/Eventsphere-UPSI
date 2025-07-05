<?php
/**
 * Export Student Attendance Records to CSV
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: ../login.php");
    exit;
}

// Check if student ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("location: students.php");
    exit;
}

$student_id = intval($_GET['id']);

// Get student info for the filename
$student_name = "student";
$student_matric = "";
$student_sql = "SELECT name, matric_id FROM users WHERE id = ? AND role = 'student'";
if ($stmt = $conn->prepare($student_sql)) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $student_row = $result->fetch_assoc();
        $student_name = $student_row['name'];
        $student_matric = $student_row['matric_id'];
    } else {
        // Student not found
        header("location: students.php");
        exit;
    }
    
    $stmt->close();
}

// Get student's attendance records
$query = "SELECT a.*, e.title as event_title, e.event_date, e.event_time, 
          c.name as category_name, up.points,
          CONCAT(v.name, ' (', v.matric_id, ')') as verified_by
          FROM attendance a
          JOIN events e ON a.event_id = e.id
          JOIN event_categories c ON e.category_id = c.id
          LEFT JOIN user_points up ON a.event_id = up.event_id AND a.user_id = up.user_id
          LEFT JOIN users v ON a.verified_by = v.id
          WHERE a.user_id = ?
          ORDER BY e.event_date DESC, e.event_time DESC";

// Prepare and execute query
$attendance_records = [];
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $attendance_records[] = $row;
    }
    
    $stmt->close();
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=attendance_' . $student_matric . '_' . date('Y-m-d') . '.csv');

// Create file pointer connected to PHP output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel CSV encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Set column headers
fputcsv($output, [
    'Student Name',
    'Matric ID',
    'Event',
    'Category',
    'Event Date',
    'Event Time',
    'Attendance Time',
    'Method',
    'Status',
    'Verification Time',
    'Verified By',
    'Points'
]);

// Add data rows
if (!empty($attendance_records)) {
    foreach ($attendance_records as $record) {
        // Format dates and times
        $event_date = date("d/m/Y", strtotime($record['event_date']));
        $event_time = date("h:i A", strtotime($record['event_time']));
        $attendance_time = date("d/m/Y h:i A", strtotime($record['attendance_time']));
        $verification_time = $record['verification_time'] ? date("d/m/Y h:i A", strtotime($record['verification_time'])) : 'N/A';
        
        // Format method
        $method = ($record['method'] == 'qr') ? 'QR Scan' : 'CAPTCHA';
        
        // Format status
        $status = $record['verified'] ? 'Verified' : 'Pending';
        
        // Output CSV row
        fputcsv($output, [
            $student_name,
            $student_matric,
            $record['event_title'],
            $record['category_name'],
            $event_date,
            $event_time,
            $attendance_time,
            $method,
            $status,
            $verification_time,
            $record['verified_by'] ?? 'N/A',
            $record['points'] ?? '0'
        ]);
    }
}

// Close the file pointer
fclose($output);
exit;
?>