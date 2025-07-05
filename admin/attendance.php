<?php
/**
 * Attendance Verification
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Enable error logging and hide errors from display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Attendance Verification";

// Include header
include_once '../include/admin_header.php';

// Initialize variables
$search = "";
$filter = "all";
$event_id = 0;
$current_page = 1;
$items_per_page = 15;
$total_attendance = 0;
$total_pages = 1;
$attendance_records = [];
$events = [];

// Get search parameter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Get filter parameters
if (isset($_GET['filter']) && in_array($_GET['filter'], ['all', 'pending_attendance', 'pending_verification', 'verified'])) {
    $filter = $_GET['filter'];
}

// Get event filter
if (isset($_GET['event']) && is_numeric($_GET['event'])) {
    $event_id = intval($_GET['event']);
}

// Get pagination parameters
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $current_page = intval($_GET['page']);
    if ($current_page < 1) {
        $current_page = 1;
    }
}

// Process manual attendance registration
if (isset($_POST['register_attendance']) && isset($_POST['user_id']) && isset($_POST['event_id'])) {
    $user_id = intval($_POST['user_id']);
    $event_id_manual = intval($_POST['event_id']);
    $admin_id = $_SESSION["id"];
    
    // First check if attendance already exists
    $check_sql = "SELECT id FROM attendance WHERE event_id = ? AND user_id = ?";
    $attendance_exists = false;
    
    if ($check_stmt = $conn->prepare($check_sql)) {
        $check_stmt->bind_param("ii", $event_id_manual, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $attendance_exists = true;
        }
        $check_stmt->close();
    }
    
    if ($attendance_exists) {
        $_SESSION['error_message'] = "Attendance record already exists for this student and event.";
    } else {
        // Insert attendance record
        $insert_sql = "INSERT INTO attendance (event_id, user_id, method, verified, verification_time, verified_by) 
                       VALUES (?, ?, 'manual', 1, NOW(), ?)";
        
        if ($insert_stmt = $conn->prepare($insert_sql)) {
            $insert_stmt->bind_param("iii", $event_id_manual, $user_id, $admin_id);
            
            try {
                if ($insert_stmt->execute()) {
                    $attendance_id = $conn->insert_id;
                    
                    // Get event details for points calculation
                    $event_sql = "SELECT e.title, e.category_id, c.name as category_name 
                                 FROM events e 
                                 JOIN event_categories c ON e.category_id = c.id 
                                 WHERE e.id = ?";
                    
                    if ($event_stmt = $conn->prepare($event_sql)) {
                        $event_stmt->bind_param("i", $event_id_manual);
                        $event_stmt->execute();
                        $event_result = $event_stmt->get_result();
                        
                        if ($event_row = $event_result->fetch_assoc()) {
                            // Determine points based on category
                            $points = 0;
                            switch ($event_row['category_id']) {
                                case 1: // Faculty
                                    $points = 15;
                                    break;
                                case 2: // Club
                                    $points = 10;
                                    break;
                                case 3: // College
                                    $points = 10;
                                    break;
                                case 4: // International
                                    $points = 30;
                                    break;
                                case 5: // National
                                    $points = 25;
                                    break;
                                case 6: // Local
                                    $points = 15;
                                    break;
                                default:
                                    $points = 10;
                            }
                            
                            // Check if points already awarded to prevent duplicate
                            $check_points_sql = "SELECT id FROM user_points WHERE user_id = ? AND event_id = ?";
                            $points_exist = false;
                            
                            if ($check_points_stmt = $conn->prepare($check_points_sql)) {
                                $check_points_stmt->bind_param("ii", $user_id, $event_id_manual);
                                $check_points_stmt->execute();
                                $check_points_result = $check_points_stmt->get_result();
                                
                                if ($check_points_result->num_rows > 0) {
                                    $points_exist = true;
                                }
                                $check_points_stmt->close();
                            }
                            
                            if (!$points_exist) {
                                // Award points
                                $points_sql = "INSERT INTO user_points (user_id, event_id, points, awarded_by) 
                                              VALUES (?, ?, ?, ?)";
                                
                                if ($points_stmt = $conn->prepare($points_sql)) {
                                    $points_stmt->bind_param("iiii", $user_id, $event_id_manual, $points, $admin_id);
                                    
                                    try {
                                        $points_stmt->execute();
                                        $points_stmt->close();
                                        
                                        // Create notification
                                        $notification_sql = "INSERT INTO notifications 
                                                           (user_id, title, message, type, related_id) 
                                                           VALUES (?, ?, ?, 'points_added', ?)";
                                        
                                        if ($notif_stmt = $conn->prepare($notification_sql)) {
                                            $title = "Attendance Recorded & Points Added";
                                            $message = "Your attendance for \"" . $event_row['title'] . "\" has been recorded manually by admin. You earned " . $points . " points.";
                                            
                                            $notif_stmt->bind_param("issi", $user_id, $title, $message, $event_id_manual);
                                            $notif_stmt->execute();
                                            $notif_stmt->close();
                                        }
                                    } catch (mysqli_sql_exception $e) {
                                        // Handle duplicate points entry silently
                                        error_log("Duplicate points entry attempted: " . $e->getMessage());
                                    }
                                }
                            }
                        }
                        
                        $event_stmt->close();
                    }
                    
                    $_SESSION['success_message'] = "Attendance recorded manually and points awarded successfully.";
                } else {
                    $_SESSION['error_message'] = "Error recording attendance: " . $conn->error;
                }
            } catch (mysqli_sql_exception $e) {
                // Handle any database errors gracefully
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $_SESSION['error_message'] = "Attendance record already exists for this student and event.";
                } else {
                    $_SESSION['error_message'] = "Error recording attendance. Please try again.";
                }
                error_log("Database error in attendance recording: " . $e->getMessage());
            }
            
            $insert_stmt->close();
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
    exit;
}

// Process verification action (for existing attendance records)
if (isset($_POST['verify_attendance']) && isset($_POST['attendance_id'])) {
    $attendance_id = intval($_POST['attendance_id']);
    $admin_id = $_SESSION["id"];
    
    // Verify attendance
    $verify_sql = "UPDATE attendance SET verified = 1, verification_time = NOW(), verified_by = ? WHERE id = ?";
    
    if ($verify_stmt = $conn->prepare($verify_sql)) {
        $verify_stmt->bind_param("ii", $admin_id, $attendance_id);
        
        if ($verify_stmt->execute()) {
            // Get attendance details to award points
            $get_attendance_sql = "SELECT user_id, event_id FROM attendance WHERE id = ?";
            if ($get_stmt = $conn->prepare($get_attendance_sql)) {
                $get_stmt->bind_param("i", $attendance_id);
                $get_stmt->execute();
                $get_result = $get_stmt->get_result();
                
                if ($attendance_row = $get_result->fetch_assoc()) {
                    $user_id = $attendance_row['user_id'];
                    $event_id = $attendance_row['event_id'];
                    
                    // Get event category to determine points
                    $event_sql = "SELECT e.title, e.category_id, c.name as category_name 
                                 FROM events e 
                                 JOIN event_categories c ON e.category_id = c.id 
                                 WHERE e.id = ?";
                    
                    if ($event_stmt = $conn->prepare($event_sql)) {
                        $event_stmt->bind_param("i", $event_id);
                        $event_stmt->execute();
                        $event_result = $event_stmt->get_result();
                        
                        if ($event_row = $event_result->fetch_assoc()) {
                            // Determine points based on category
                            $points = 0;
                            switch ($event_row['category_id']) {
                                case 1: // Faculty
                                    $points = 15;
                                    break;
                                case 2: // Club
                                    $points = 10;
                                    break;
                                case 3: // College
                                    $points = 10;
                                    break;
                                case 4: // International
                                    $points = 30;
                                    break;
                                case 5: // National
                                    $points = 25;
                                    break;
                                case 6: // Local
                                    $points = 15;
                                    break;
                                default:
                                    $points = 10;
                            }
                            
                            // Award points
                            $points_sql = "INSERT INTO user_points (user_id, event_id, points, awarded_by) 
                                          VALUES (?, ?, ?, ?) 
                                          ON DUPLICATE KEY UPDATE 
                                          points = VALUES(points),
                                          awarded_date = NOW(),
                                          awarded_by = VALUES(awarded_by)";
                            
                            if ($points_stmt = $conn->prepare($points_sql)) {
                                $points_stmt->bind_param("iiii", $user_id, $event_id, $points, $admin_id);
                                $points_stmt->execute();
                                $points_stmt->close();
                                
                                // Create notification
                                $notification_sql = "INSERT INTO notifications 
                                                   (user_id, title, message, type, related_id) 
                                                   VALUES (?, ?, ?, 'points_added', ?)";
                                
                                if ($notif_stmt = $conn->prepare($notification_sql)) {
                                    $title = "Points Added";
                                    $message = "You earned " . $points . " points for attending \"" . $event_row['title'] . "\".";
                                    
                                    $notif_stmt->bind_param("issi", $user_id, $title, $message, $event_id);
                                    $notif_stmt->execute();
                                    $notif_stmt->close();
                                }
                            }
                        }
                        
                        $event_stmt->close();
                    }
                }
                
                $get_stmt->close();
            }
            
            $_SESSION['success_message'] = "Attendance verified and points awarded successfully.";
        } else {
            $_SESSION['error_message'] = "Error verifying attendance.";
        }
        
        $verify_stmt->close();
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
    exit;
}

// Get list of events for filtering
$events_sql = "SELECT id, title FROM events ORDER BY event_date DESC";
$events_result = $conn->query($events_sql);
if ($events_result) {
    while ($row = $events_result->fetch_assoc()) {
        $events[] = $row;
    }
}

// Calculate offset for pagination
$offset = ($current_page - 1) * $items_per_page;

// Build base query based on filter
if ($filter === 'pending_attendance') {
    // Show users who registered but haven't marked attendance (only for events that have started)
    $query = "SELECT er.id as registration_id, er.event_id, er.user_id, er.registration_date,
              e.title as event_title, e.event_date, e.event_time, 
              u.name as student_name, u.matric_id, u.email, 
              c.name as category_name,
              'pending_attendance' as status_type
              FROM event_registrations er
              JOIN events e ON er.event_id = e.id
              JOIN users u ON er.user_id = u.id
              JOIN event_categories c ON e.category_id = c.id
              LEFT JOIN attendance a ON er.event_id = a.event_id AND er.user_id = a.user_id
              WHERE er.status = 'registered' 
              AND a.id IS NULL
              AND CONCAT(e.event_date, ' ', e.event_time) <= NOW()";
              
} elseif ($filter === 'pending_verification') {
    // Show attendance records that need verification
    $query = "SELECT a.*, e.title as event_title, e.event_date, e.event_time, 
              u.name as student_name, u.matric_id, u.email, 
              c.name as category_name,
              'pending_verification' as status_type
              FROM attendance a
              JOIN events e ON a.event_id = e.id
              JOIN users u ON a.user_id = u.id
              JOIN event_categories c ON e.category_id = c.id
              WHERE a.verified = 0";
              
} elseif ($filter === 'verified') {
    // Show verified attendance records
    $query = "SELECT a.*, e.title as event_title, e.event_date, e.event_time, 
              u.name as student_name, u.matric_id, u.email, 
              c.name as category_name,
              'verified' as status_type
              FROM attendance a
              JOIN events e ON a.event_id = e.id
              JOIN users u ON a.user_id = u.id
              JOIN event_categories c ON e.category_id = c.id
              WHERE a.verified = 1";
              
} else {
    // Show all records (both registrations and attendance)
    $query = "(SELECT a.id, a.event_id, a.user_id, a.attendance_time as record_time,
              e.title as event_title, e.event_date, e.event_time, 
              u.name as student_name, u.matric_id, u.email, 
              c.name as category_name, a.verified, a.method,
              'attendance' as status_type
              FROM attendance a
              JOIN events e ON a.event_id = e.id
              JOIN users u ON a.user_id = u.id
              JOIN event_categories c ON e.category_id = c.id)
              UNION ALL
              (SELECT NULL as id, er.event_id, er.user_id, er.registration_date as record_time,
              e.title as event_title, e.event_date, e.event_time,
              u.name as student_name, u.matric_id, u.email, 
              c.name as category_name, NULL as verified, 'registration' as method,
              'pending_attendance' as status_type
              FROM event_registrations er
              JOIN events e ON er.event_id = e.id
              JOIN users u ON er.user_id = u.id
              JOIN event_categories c ON e.category_id = c.id
              LEFT JOIN attendance a ON er.event_id = a.event_id AND er.user_id = a.user_id
              WHERE er.status = 'registered' AND a.id IS NULL)";
}

// Add filters
if ($event_id > 0) {
    if ($filter === 'pending_attendance') {
        $query .= " AND er.event_id = " . $event_id;
    } elseif (in_array($filter, ['pending_verification', 'verified'])) {
        $query .= " AND a.event_id = " . $event_id;
    } else {
        // For 'all' filter with UNION, need to add to both parts
        $query = str_replace("JOIN event_categories c ON e.category_id = c.id)", 
                           "JOIN event_categories c ON e.category_id = c.id WHERE e.id = " . $event_id . ")", $query);
        $query = str_replace("WHERE er.status = 'registered'", 
                           "WHERE er.status = 'registered' AND er.event_id = " . $event_id . " AND CONCAT(e.event_date, ' ', e.event_time) <= NOW()", $query);
    }
}

if (!empty($search)) {
    $search_escaped = $conn->real_escape_string($search);
    $search_condition = " AND (u.name LIKE '%" . $search_escaped . "%' OR 
                         u.matric_id LIKE '%" . $search_escaped . "%' OR 
                         e.title LIKE '%" . $search_escaped . "%')";
    
    if ($filter === 'pending_attendance') {
        $query .= $search_condition;
    } elseif (in_array($filter, ['pending_verification', 'verified'])) {
        $query .= $search_condition;
    } else {
        // For 'all' filter with UNION, need to add to both parts
        $query = str_replace("JOIN event_categories c ON e.category_id = c.id)", 
                           "JOIN event_categories c ON e.category_id = c.id" . $search_condition . ")", $query);
        $query = str_replace("WHERE er.status = 'registered'", 
                           "WHERE er.status = 'registered'" . $search_condition . " AND CONCAT(e.event_date, ' ', e.event_time) <= NOW()", $query);
    }
}

// Count total records with applied filters for pagination
$count_query = "SELECT COUNT(*) as count FROM (" . $query . ") as filtered";
$count_result = $conn->query($count_query);
if ($count_result && $row = $count_result->fetch_assoc()) {
    $total_attendance = $row['count'];
    $total_pages = ceil($total_attendance / $items_per_page);
    
    // Adjust current page if needed
    if ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
        $offset = ($current_page - 1) * $items_per_page;
    }
}

// Get attendance records with pagination
if ($filter === 'all') {
    $query = "SELECT * FROM (" . $query . ") as combined_results ORDER BY event_date DESC, record_time DESC";
} else {
    $query .= " ORDER BY e.event_date DESC, e.created_at DESC";
}

$query .= " LIMIT " . $items_per_page . " OFFSET " . $offset;

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $attendance_records[] = $row;
    }
}

// Get summary statistics
$stats = [
    'total_registrations' => 0,
    'pending_attendance' => 0,
    'pending_verification' => 0,
    'verified' => 0,
    'points_awarded' => 0
];

$stats_sql = "SELECT 
    (SELECT COUNT(*) FROM event_registrations WHERE status = 'registered') as total_registrations,
    (SELECT COUNT(*) FROM event_registrations er 
     JOIN events e ON er.event_id = e.id
     LEFT JOIN attendance a ON er.event_id = a.event_id AND er.user_id = a.user_id 
     WHERE er.status = 'registered' 
     AND a.id IS NULL 
     AND CONCAT(e.event_date, ' ', e.event_time) <= NOW()) as pending_attendance,
    (SELECT COUNT(*) FROM attendance WHERE verified = 0) as pending_verification,
    (SELECT COUNT(*) FROM attendance WHERE verified = 1) as verified,
    (SELECT SUM(points) FROM user_points) as points_awarded";

$stats_result = $conn->query($stats_sql);
if ($stats_result && $row = $stats_result->fetch_assoc()) {
    $stats['total_registrations'] = $row['total_registrations'];
    $stats['pending_attendance'] = $row['pending_attendance'];
    $stats['pending_verification'] = $row['pending_verification'];
    $stats['verified'] = $row['verified'];
    $stats['points_awarded'] = $row['points_awarded'] ?: 0;
}
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h1 class="h3 mb-3">Attendance Management</h1>
                    <p class="lead">Manage student registrations, attendance, and point awards.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Overview -->
    <div class="row">
        <!-- Total Registrations Card -->
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-4 text-info mb-2">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3 class="counter display-4 font-weight-bold"><?php echo $stats['total_registrations']; ?></h3>
                    <h5 class="text-muted">Total Registrations</h5>
                </div>
            </div>
        </div>

        <!-- Pending Attendance Card -->
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-4 text-danger mb-2">
                        <i class="fas fa-user-clock"></i>
                    </div>
                    <h3 class="counter display-4 font-weight-bold"><?php echo $stats['pending_attendance']; ?></h3>
                    <h5 class="text-muted">Pending Attendance</h5>
                </div>
            </div>
        </div>

        <!-- Pending Verification Card -->
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-4 text-warning mb-2">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <h3 class="counter display-4 font-weight-bold"><?php echo $stats['pending_verification']; ?></h3>
                    <h5 class="text-muted">Pending Verification</h5>
                </div>
            </div>
        </div>

        <!-- Verified Attendance Card -->
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-4 text-success mb-2">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="counter display-4 font-weight-bold"><?php echo $stats['verified']; ?></h3>
                    <h5 class="text-muted">Verified</h5>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Records List -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Attendance Records</h4>
                    <div>
                        <a href="export_attendance.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-file-export mr-1"></i> Export
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filters and Search -->
                    <form class="mb-4">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="filter">Status Filter</label>
                                    <select name="filter" id="filter" class="form-control" onchange="this.form.submit()">
                                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Records</option>
                                        <option value="pending_attendance" <?php echo $filter === 'pending_attendance' ? 'selected' : ''; ?>>Pending Attendance</option>
                                        <option value="pending_verification" <?php echo $filter === 'pending_verification' ? 'selected' : ''; ?>>Pending Verification</option>
                                        <option value="verified" <?php echo $filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="event">Event Filter</label>
                                    <select name="event" id="event" class="form-control" onchange="this.form.submit()">
                                        <option value="0">All Events</option>
                                        <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event['id']; ?>" <?php echo $event_id == $event['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="search">Search</label>
                                    <div class="input-group">
                                        <input type="text" name="search" id="search" class="form-control" placeholder="Search by name, matric ID or event..." value="<?php echo htmlspecialchars($search); ?>">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i>
                                            </button>
                                            <?php if (!empty($search) || $filter !== 'all' || $event_id > 0): ?>
                                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Clear
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <?php if (count($attendance_records) > 0): ?>
                    <!-- Records Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Matric ID</th>
                                    <th>Event</th>
                                    <th>Category</th>
                                    <th>Event Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance_records as $record): ?>
                                <tr <?php 
                                    if (isset($record['status_type'])) {
                                        if ($record['status_type'] === 'pending_attendance') echo 'class="table-danger"';
                                        elseif ($record['status_type'] === 'pending_verification') echo 'class="table-warning"';
                                        elseif ($record['status_type'] === 'verified') echo 'class="table-success"';
                                    } elseif (isset($record['verified'])) {
                                        if ($record['verified'] == 1) echo 'class="table-success"';
                                        else echo 'class="table-warning"';
                                    } elseif (isset($record['method']) && $record['method'] === 'registration') {
                                        echo 'class="table-danger"';
                                    }
                                ?>>
                                    <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['matric_id']); ?></td>
                                    <td>
                                        <a href="view_event.php?id=<?php echo $record['event_id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($record['event_title']); ?>
                                        </a>
                                    </td>
                                    <td><span class="badge badge-info"><?php echo htmlspecialchars($record['category_name']); ?></span></td>
                                    <td><?php echo date("d M Y", strtotime($record['event_date'])); ?></td>
                                    <td>
                                        <?php 
                                        if (isset($record['status_type'])) {
                                            if ($record['status_type'] === 'pending_attendance') {
                                                echo '<span class="badge badge-danger">Not Attended</span>';
                                            } elseif ($record['status_type'] === 'pending_verification') {
                                                echo '<span class="badge badge-warning">Pending Verification</span>';
                                            } elseif ($record['status_type'] === 'verified') {
                                                echo '<span class="badge badge-success">Verified</span>';
                                            }
                                        } elseif (isset($record['verified'])) {
                                            if ($record['verified'] == 1) {
                                                echo '<span class="badge badge-success">Verified</span>';
                                            } else {
                                                echo '<span class="badge badge-warning">Pending Verification</span>';
                                            }
                                        } elseif (isset($record['method']) && $record['method'] === 'registration') {
                                            echo '<span class="badge badge-danger">Not Attended</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ((isset($record['status_type']) && $record['status_type'] === 'pending_attendance') || 
                                                  (isset($record['method']) && $record['method'] === 'registration')): ?>
                                        <!-- Manual Attendance Registration -->
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                data-toggle="modal" 
                                                data-target="#recordAttendanceModal"
                                                data-user-id="<?php echo $record['user_id']; ?>"
                                                data-event-id="<?php echo $record['event_id']; ?>"
                                                data-student-name="<?php echo htmlspecialchars($record['student_name']); ?>"
                                                data-event-title="<?php echo htmlspecialchars($record['event_title']); ?>">
                                            <i class="fas fa-plus"></i> Record Attendance
                                        </button>
                                        
                                        <?php elseif (isset($record['verified']) && $record['verified'] == 0): ?>
                                        <!-- Verify Existing Attendance -->
                                        <button type="button" class="btn btn-sm btn-success"
                                                data-toggle="modal" 
                                                data-target="#verifyAttendanceModal"
                                                data-attendance-id="<?php echo $record['id']; ?>"
                                                data-student-name="<?php echo htmlspecialchars($record['student_name']); ?>"
                                                data-event-title="<?php echo htmlspecialchars($record['event_title']); ?>">
                                            <i class="fas fa-check"></i> Verify
                                        </button>
                                        
                                        <?php elseif (isset($record['verified']) && $record['verified'] == 1): ?>
                                        <span class="text-success"><i class="fas fa-check-circle"></i> Completed</span>
                                        
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <!-- Previous page link -->
                            <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php 
                                    $prev_params = $_GET;
                                    $prev_params['page'] = $current_page - 1;
                                    echo $_SERVER['PHP_SELF'] . '?' . http_build_query($prev_params);
                                ?>">
                                    <i class="fas fa-angle-left"></i> Previous
                                </a>
                            </li>
                            
                            <!-- Page numbers -->
                            <?php 
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_pages, $current_page + 2);
                            
                            if ($start_page > 1) {
                                $first_params = $_GET;
                                $first_params['page'] = 1;
                                echo '<li class="page-item"><a class="page-link" href="' . $_SERVER['PHP_SELF'] . '?' . http_build_query($first_params) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                $page_params = $_GET;
                                $page_params['page'] = $i;
                                echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">
                                    <a class="page-link" href="' . $_SERVER['PHP_SELF'] . '?' . http_build_query($page_params) . '">' . $i . '</a>
                                </li>';
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                $last_params = $_GET;
                                $last_params['page'] = $total_pages;
                                echo '<li class="page-item"><a class="page-link" href="' . $_SERVER['PHP_SELF'] . '?' . http_build_query($last_params) . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <!-- Next page link -->
                            <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php 
                                    $next_params = $_GET;
                                    $next_params['page'] = $current_page + 1;
                                    echo $_SERVER['PHP_SELF'] . '?' . http_build_query($next_params);
                                ?>">
                                    Next <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <!-- Empty State -->
                    <div class="text-center py-5">
                        <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">No records found</h5>
                        <?php if (!empty($search) || $filter !== 'all' || $event_id > 0): ?>
                        <p>Try adjusting your search criteria or filters</p>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-primary mt-2">
                            <i class="fas fa-sync mr-1"></i> Reset Filters
                        </a>
                        <?php else: ?>
                        <p>No attendance records found</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Points Breakdown -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Points Breakdown by Category</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Points Awarded</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><span class="badge badge-primary">Faculty</span></td>
                                            <td><strong class="text-primary">15 points</strong></td>
                                            <td>Events organized by faculty departments</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-info">Club</span></td>
                                            <td><strong class="text-primary">10 points</strong></td>
                                            <td>Events organized by student clubs and societies</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-secondary">College</span></td>
                                            <td><strong class="text-primary">10 points</strong></td>
                                            <td>Events organized by residential colleges</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-danger">International</span></td>
                                            <td><strong class="text-primary">30 points</strong></td>
                                            <td>Events with international scope or participation</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-warning">National</span></td>
                                            <td><strong class="text-primary">25 points</strong></td>
                                            <td>Events with national scope or participation</td>
                                        </tr>
                                        <tr>
                                            <td><span class="badge badge-success">Local</span></td>
                                            <td><strong class="text-primary">15 points</strong></td>
                                            <td>Events with local or campus-wide scope</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle mr-2"></i> Points System</h5>
                                <p>Points are automatically awarded when attendance is recorded or verified. Manual attendance records are automatically verified and points awarded immediately.</p>
                                <p><strong>Note:</strong> Points can only be awarded once per event per student.</p>
                                <p class="mb-0"><strong>Pending Attendance Rule:</strong> Only shows registered students for events that have already started (past event date/time).</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Quick Actions</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <a href="attendance.php?filter=pending_attendance" class="btn btn-danger btn-lg btn-block">
                                <i class="fas fa-user-clock mr-2"></i> Pending Attendance
                                <span class="badge badge-light ml-2"><?php echo $stats['pending_attendance']; ?></span>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="attendance.php?filter=pending_verification" class="btn btn-warning btn-lg btn-block">
                                <i class="fas fa-hourglass-half mr-2"></i> Pending Verification
                                <span class="badge badge-light ml-2"><?php echo $stats['pending_verification']; ?></span>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="attendance.php?filter=verified" class="btn btn-success btn-lg btn-block">
                                <i class="fas fa-check-circle mr-2"></i> Verified
                                <span class="badge badge-light ml-2"><?php echo $stats['verified']; ?></span>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="events.php" class="btn btn-primary btn-lg btn-block">
                                <i class="fas fa-calendar-alt mr-2"></i> Manage Events
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Record Attendance Modal -->
<div class="modal fade" id="recordAttendanceModal" tabindex="-1" aria-labelledby="recordAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="recordAttendanceModalLabel">Confirm Manual Attendance Recording</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to record attendance manually for: <strong id="recordStudentName"></strong>?</p>
                <p>Event: <strong id="recordEventTitle"></strong></p>
                <p class="text-info"><i class="fas fa-info-circle"></i> This action will:</p>
                <ul class="text-info">
                    <li>Create an attendance record</li>
                    <li>Award points immediately based on event category</li>
                    <li>Send notification to the student</li>
                    <li>Mark attendance as verified</li>
                </ul>
                <p class="text-warning"><strong>Note:</strong> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="recordAttendanceForm" action="" method="post" style="display: inline;">
                    <input type="hidden" name="user_id" id="recordUserId">
                    <input type="hidden" name="event_id" id="recordEventId">
                    <button type="submit" name="register_attendance" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Record Attendance
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Verify Attendance Modal -->
<div class="modal fade" id="verifyAttendanceModal" tabindex="-1" aria-labelledby="verifyAttendanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verifyAttendanceModalLabel">Confirm Attendance Verification</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to verify attendance for: <strong id="verifyStudentName"></strong>?</p>
                <p>Event: <strong id="verifyEventTitle"></strong></p>
                <p class="text-success"><i class="fas fa-check-circle"></i> This action will:</p>
                <ul class="text-success">
                    <li>Mark attendance as verified</li>
                    <li>Award points based on event category</li>
                    <li>Send notification to the student</li>
                </ul>
                <p class="text-warning"><strong>Note:</strong> Points can only be awarded once per event per student.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="verifyAttendanceForm" action="" method="post" style="display: inline;">
                    <input type="hidden" name="attendance_id" id="verifyAttendanceId">
                    <button type="submit" name="verify_attendance" class="btn btn-success">
                        <i class="fas fa-check"></i> Verify Attendance
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<footer class="bg-light mt-5 py-3">
    <div class="container text-center">
        <p class="mb-0">&copy; <?php echo date("Y"); ?> EventSphere@UPSI. All rights reserved.</p>
    </div>
</footer>

<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
$(document).ready(function() {
    // Handle Record Attendance Modal
    $('#recordAttendanceModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var userId = button.data('user-id');
        var eventId = button.data('event-id');
        var studentName = button.data('student-name');
        var eventTitle = button.data('event-title');
        
        var modal = $(this);
        modal.find('#recordStudentName').text(studentName);
        modal.find('#recordEventTitle').text(eventTitle);
        modal.find('#recordUserId').val(userId);
        modal.find('#recordEventId').val(eventId);
    });
    
    // Handle Verify Attendance Modal
    $('#verifyAttendanceModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var attendanceId = button.data('attendance-id');
        var studentName = button.data('student-name');
        var eventTitle = button.data('event-title');
        
        var modal = $(this);
        modal.find('#verifyStudentName').text(studentName);
        modal.find('#verifyEventTitle').text(eventTitle);
        modal.find('#verifyAttendanceId').val(attendanceId);
    });
    
    // Show success/error messages
    <?php if (isset($_SESSION['success_message'])): ?>
    alert('<?php echo addslashes($_SESSION['success_message']); ?>');
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    alert('Error: <?php echo addslashes($_SESSION['error_message']); ?>');
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
});
</script>
</body>
</html>