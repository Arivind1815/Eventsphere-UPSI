<?php
/**
 * Export Student Registration Records to CSV
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

// Get student's registration records
$query = "SELECT r.*, e.title as event_title, e.event_date, e.event_time,
          e.venue, e.organizer, e.max_participants, e.registration_deadline,
          c.name as category_name
          FROM event_registrations r
          JOIN events e ON r.event_id = e.id
          JOIN event_categories c ON e.category_id = c.id
          WHERE r.user_id = ?
          ORDER BY e.event_date DESC, e.event_time DESC";

// Prepare and execute query
$registration_records = [];
if ($stmt = $conn->prepare($query)) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $registration_records[] = $row;
    }
    
    $stmt->close();
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=registrations_' . $student_matric . '_' . date('Y-m-d') . '.csv');

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
    'Venue',
    'Organizer',
    'Event Date',
    'Event Time',
    'Registration Date',
    'Registration Status',
    'Registration Deadline',
    'Max Participants'
]);

// Add data rows
if (!empty($registration_records)) {
    foreach ($registration_records as $record) {
        // Format dates and times
        $event_date = date("d/m/Y", strtotime($record['event_date']));
        $event_time = date("h:i A", strtotime($record['event_time']));
        $registration_date = date("d/m/Y h:i A", strtotime($record['registration_date']));
        $registration_deadline = $record['registration_deadline'] ? date("d/m/Y", strtotime($record['registration_deadline'])) : 'N/A';
        
        // Format status
        $status = ($record['status'] == 'registered') ? 'Registered' : 'Cancelled';
        
        // Output CSV row
        fputcsv($output, [
            $student_name,
            $student_matric,
            $record['event_title'],
            $record['category_name'],
            $record['venue'],
            $record['organizer'],
            $event_date,
            $event_time,
            $registration_date,
            $status,
            $registration_deadline,
            $record['max_participants'] ?? 'Unlimited'
        ]);
    }
}

// Close the file pointer
fclose($output);
exit;
?>