<?php
/**
 * Export All Students to CSV
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: ../login.php");
    exit;
}

// Get search parameter (if any)
$search = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Build query with filters
$query = "SELECT u.*, 
           (SELECT COUNT(*) FROM event_registrations WHERE user_id = u.id) as total_registrations,
           (SELECT COUNT(*) FROM attendance WHERE user_id = u.id) as total_attendance,
           (SELECT SUM(points) FROM user_points WHERE user_id = u.id) as total_points
        FROM users u
        WHERE u.role = 'student'";

// Add search filter if provided
if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.matric_id LIKE ? OR u.email LIKE ?)";
}

$query .= " ORDER BY u.name ASC";

// Prepare statement
$students = [];
if ($stmt = $conn->prepare($query)) {
    // Bind search parameters if needed
    if (!empty($search)) {
        $search_param = "%$search%";
        $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Ensure total_points is not null
        $row['total_points'] = $row['total_points'] ? $row['total_points'] : 0;
        $students[] = $row;
    }
    
    $stmt->close();
}

// Get semester goals for each student
foreach ($students as &$student) {
    $student['goals'] = [];
    
    $goals_sql = "SELECT semester, points_goal FROM user_goals WHERE user_id = ? ORDER BY semester DESC";
    if ($goals_stmt = $conn->prepare($goals_sql)) {
        $goals_stmt->bind_param("i", $student['id']);
        $goals_stmt->execute();
        $goals_result = $goals_stmt->get_result();
        
        while ($goal_row = $goals_result->fetch_assoc()) {
            $student['goals'][] = $goal_row;
        }
        
        $goals_stmt->close();
    }
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=students_report_' . date('Y-m-d') . '.csv');

// Create file pointer connected to PHP output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel CSV encoding issues
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Set column headers
fputcsv($output, [
    'Name',
    'Matric ID',
    'Email',
    'Phone',
    'Total Registrations',
    'Total Attendance',
    'Total Points',
    'Current Semester Goal',
    'Joined Date'
]);

// Add data rows
if (!empty($students)) {
    foreach ($students as $student) {
        // Get current semester goal if any
        $current_semester = date('Y') . ' ' . (date('n') <= 6 ? 'Spring' : 'Fall');
        $current_goal = 'Not Set';
        
        foreach ($student['goals'] as $goal) {
            if ($goal['semester'] == $current_semester) {
                $current_goal = $goal['points_goal'];
                break;
            }
        }
        
        // Format joined date
        $joined_date = date("d/m/Y", strtotime($student['created_at']));
        
        // Output CSV row
        fputcsv($output, [
            $student['name'],
            $student['matric_id'],
            $student['email'],
            $student['phone'],
            $student['total_registrations'],
            $student['total_attendance'],
            $student['total_points'],
            $current_goal,
            $joined_date
        ]);
    }
}

// Close the file pointer
fclose($output);
exit;
?>