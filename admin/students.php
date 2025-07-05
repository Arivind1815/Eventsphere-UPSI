<?php
/**
 * Student Management
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Student Management";

// Include header
include_once '../include/admin_header.php';

// Initialize variables
$search = "";
$current_page = 1;
$items_per_page = 10;
$total_students = 0;
$total_pages = 1;
$students = [];
$student_details = null;

// Get search parameter
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Get pagination parameters
if (isset($_GET['page']) && is_numeric($_GET['page'])) {
    $current_page = intval($_GET['page']);
    if ($current_page < 1) {
        $current_page = 1;
    }
}

// Check if student ID is provided for details view
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $student_id = intval($_GET['id']);

    // Get student details
    $student_sql = "SELECT * FROM users WHERE id = ? AND role = 'student'";
    if ($stmt = $conn->prepare($student_sql)) {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $student_details = $result->fetch_assoc();

            // Get student's total points
            $points_sql = "SELECT SUM(points) AS total_points FROM user_points WHERE user_id = ?";
            if ($points_stmt = $conn->prepare($points_sql)) {
                $points_stmt->bind_param("i", $student_id);
                $points_stmt->execute();
                $points_result = $points_stmt->get_result();

                if ($row = $points_result->fetch_assoc()) {
                    $student_details['total_points'] = $row['total_points'] ? $row['total_points'] : 0;
                }

                $points_stmt->close();
            }

            // Get student's event attendance with points
            $attendance_sql = "SELECT a.*, e.title as event_title, e.event_date, e.event_time, 
                                  c.name as category_name, up.points
                               FROM attendance a
                               JOIN events e ON a.event_id = e.id
                               JOIN event_categories c ON e.category_id = c.id
                               LEFT JOIN user_points up ON a.event_id = up.event_id AND a.user_id = up.user_id
                               WHERE a.user_id = ?
                               ORDER BY e.event_date DESC, e.event_time DESC";

            if ($att_stmt = $conn->prepare($attendance_sql)) {
                $att_stmt->bind_param("i", $student_id);
                $att_stmt->execute();
                $attendance_result = $att_stmt->get_result();

                $student_details['attendance'] = [];
                while ($attendance_row = $attendance_result->fetch_assoc()) {
                    $student_details['attendance'][] = $attendance_row;
                }

                $att_stmt->close();
            }

            // Get student's registered events
            $registration_sql = "SELECT r.*, e.title as event_title, e.event_date, e.event_time,
                                    c.name as category_name, e.venue
                                 FROM event_registrations r
                                 JOIN events e ON r.event_id = e.id
                                 JOIN event_categories c ON e.category_id = c.id
                                 WHERE r.user_id = ?
                                 ORDER BY e.event_date DESC, e.event_time DESC";

            if ($reg_stmt = $conn->prepare($registration_sql)) {
                $reg_stmt->bind_param("i", $student_id);
                $reg_stmt->execute();
                $registration_result = $reg_stmt->get_result();

                $student_details['registrations'] = [];
                while ($registration_row = $registration_result->fetch_assoc()) {
                    $student_details['registrations'][] = $registration_row;
                }

                $reg_stmt->close();
            }

            // Get goals
            $goals_sql = "SELECT * FROM user_goals WHERE user_id = ? ORDER BY semester DESC";
            if ($goals_stmt = $conn->prepare($goals_sql)) {
                $goals_stmt->bind_param("i", $student_id);
                $goals_stmt->execute();
                $goals_result = $goals_stmt->get_result();

                $student_details['goals'] = [];
                while ($goal_row = $goals_result->fetch_assoc()) {
                    $student_details['goals'][] = $goal_row;
                }

                $goals_stmt->close();
            }
        }

        $stmt->close();
    }
} else {
    // Calculate offset for pagination
    $offset = ($current_page - 1) * $items_per_page;

    // Get total count of students (for pagination)
    $count_sql = "SELECT COUNT(*) as count FROM users WHERE role = 'student'";
    if (!empty($search)) {
        $count_sql .= " AND (name LIKE ? OR matric_id LIKE ? OR email LIKE ?)";
    }

    if ($count_stmt = $conn->prepare($count_sql)) {
        if (!empty($search)) {
            $search_param = "%$search%";
            $count_stmt->bind_param("sss", $search_param, $search_param, $search_param);
        }

        $count_stmt->execute();
        $count_result = $count_stmt->get_result();

        if ($row = $count_result->fetch_assoc()) {
            $total_students = $row['count'];
            $total_pages = ceil($total_students / $items_per_page);

            // Adjust current page if needed
            if ($current_page > $total_pages && $total_pages > 0) {
                $current_page = $total_pages;
                $offset = ($current_page - 1) * $items_per_page;
            }
        }

        $count_stmt->close();
    }

    // Get students list
    $sql = "SELECT u.*, 
               (SELECT COUNT(*) FROM event_registrations WHERE user_id = u.id) as total_registrations,
               (SELECT COUNT(*) FROM attendance WHERE user_id = u.id) as total_attendance,
               (SELECT SUM(points) FROM user_points WHERE user_id = u.id) as total_points
            FROM users u
            WHERE u.role = 'student'";

    if (!empty($search)) {
        $sql .= " AND (u.name LIKE ? OR u.matric_id LIKE ? OR u.email LIKE ?)";
    }

    $sql .= " ORDER BY u.name ASC LIMIT ? OFFSET ?";

    if ($stmt = $conn->prepare($sql)) {
        if (!empty($search)) {
            $search_param = "%$search%";
            $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $items_per_page, $offset);
        } else {
            $stmt->bind_param("ii", $items_per_page, $offset);
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
}

// Process student deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_student']) && isset($_POST['delete_student_id'])) {
    $delete_student_id = intval($_POST['delete_student_id']);
    
    // Start transaction for data integrity
    $conn->begin_transaction();
    
    try {
        // Delete student's semester goals
        $goals_sql = "DELETE FROM user_goals WHERE user_id = ?";
        $goals_stmt = $conn->prepare($goals_sql);
        $goals_stmt->bind_param("i", $delete_student_id);
        $goals_stmt->execute();
        $goals_stmt->close();
        
        // Delete student's points
        $points_sql = "DELETE FROM user_points WHERE user_id = ?";
        $points_stmt = $conn->prepare($points_sql);
        $points_stmt->bind_param("i", $delete_student_id);
        $points_stmt->execute();
        $points_stmt->close();
        
        // Delete student's attendances
        $attendance_sql = "DELETE FROM attendance WHERE user_id = ?";
        $attendance_stmt = $conn->prepare($attendance_sql);
        $attendance_stmt->bind_param("i", $delete_student_id);
        $attendance_stmt->execute();
        $attendance_stmt->close();
        
        // Delete student's registrations
        $reg_sql = "DELETE FROM event_registrations WHERE user_id = ?";
        $reg_stmt = $conn->prepare($reg_sql);
        $reg_stmt->bind_param("i", $delete_student_id);
        $reg_stmt->execute();
        $reg_stmt->close();
        
        // Delete student's notifications
        $notif_sql = "DELETE FROM notifications WHERE user_id = ?";
        $notif_stmt = $conn->prepare($notif_sql);
        $notif_stmt->bind_param("i", $delete_student_id);
        $notif_stmt->execute();
        $notif_stmt->close();
        
        // Delete student's interests
        $interests_sql = "DELETE FROM user_interests WHERE user_id = ?";
        $interests_stmt = $conn->prepare($interests_sql);
        $interests_stmt->bind_param("i", $delete_student_id);
        $interests_stmt->execute();
        $interests_stmt->close();
        
        // Finally, delete the student
        $student_sql = "DELETE FROM users WHERE id = ? AND role = 'student'";
        $student_stmt = $conn->prepare($student_sql);
        $student_stmt->bind_param("i", $delete_student_id);
        $student_stmt->execute();
        
        // Check if student was deleted
        if ($student_stmt->affected_rows > 0) {
            $conn->commit();
            $_SESSION['success_message'] = "Student and all associated data have been successfully deleted.";
        } else {
            // Student not found or not a student
            $conn->rollback();
            $_SESSION['error_message'] = "Error: Student not found or you don't have permission to delete this user.";
        }
        
        $student_stmt->close();
    } catch (Exception $e) {
        // Something went wrong, rollback the transaction
        $conn->rollback();
        $_SESSION['error_message'] = "Error deleting student: " . $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header("Location: students.php");
    exit;
}
?>

<div class="container mt-4">
    <?php if ($student_details): ?>
        <!-- Student Details View -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h1 class="h3 mb-0">Student Details: <?php echo htmlspecialchars($student_details['name']); ?>
                            </h1>
                            <a href="students.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Students
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="row">
            <!-- Student Info Card -->
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-graduate mr-2 text-primary"></i> Student Information
                        </h5>
                        <table class="table table-borderless">
                            <tr>
                                <th width="150">Matric ID:</th>
                                <td><?php echo htmlspecialchars($student_details['matric_id']); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td><?php echo htmlspecialchars($student_details['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Phone:</th>
                                <td><?php echo htmlspecialchars($student_details['phone']); ?></td>
                            </tr>
                            <tr>
                                <th>Joined On:</th>
                                <td><?php echo date("F j, Y", strtotime($student_details['created_at'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Attendance Stats -->
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="display-4 text-success mb-2">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <h3 class="counter display-4 font-weight-bold"><?php echo count($student_details['attendance']); ?>
                        </h3>
                        <h5 class="text-muted">Events Attended</h5>
                    </div>
                </div>
            </div>

            <!-- Points Stats -->
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="display-4 text-warning mb-2">
                            <i class="fas fa-award"></i>
                        </div>
                        <h3 class="counter display-4 font-weight-bold"><?php echo $student_details['total_points']; ?></h3>
                        <h5 class="text-muted">Total Points</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Semester Goals -->
            <?php if (!empty($student_details['goals'])): ?>
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h4 class="mb-0">Semester Goals</h4>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($student_details['goals'] as $goal): ?>
                                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-bullseye mr-2 text-primary"></i>
                                            <?php echo htmlspecialchars($goal['semester']); ?></span>
                                        <span class="badge badge-primary badge-pill"><?php echo $goal['points_goal']; ?> pts</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Registration Stats -->
            <div class="col-lg-<?php echo !empty($student_details['goals']) ? '8' : '12'; ?> mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Registration Statistics</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center mb-3">
                                <div class="h2 mb-0 text-primary"><?php echo count($student_details['registrations']); ?>
                                </div>
                                <div class="text-muted">Total Registrations</div>
                            </div>

                            <div class="col-md-4 text-center mb-3">
                                <?php
                                $active_registrations = 0;
                                foreach ($student_details['registrations'] as $reg) {
                                    if ($reg['status'] == 'registered')
                                        $active_registrations++;
                                }
                                ?>
                                <div class="h2 mb-0 text-success"><?php echo $active_registrations; ?></div>
                                <div class="text-muted">Active Registrations</div>
                            </div>

                            <div class="col-md-4 text-center mb-3">
                                <?php
                                $upcoming_events = 0;
                                foreach ($student_details['registrations'] as $reg) {
                                    if ($reg['status'] == 'registered' && $reg['event_date'] >= date('Y-m-d')) {
                                        $upcoming_events++;
                                    }
                                }
                                ?>
                                <div class="h2 mb-0 text-info"><?php echo $upcoming_events; ?></div>
                                <div class="text-muted">Upcoming Events</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance History -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Attendance History</h4>
                        <a href="export_student_attendance.php?id=<?php echo $student_id; ?>"
                            class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-file-export mr-1"></i> Export
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($student_details['attendance'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Category</th>
                                            <th>Date & Time</th>
                                            <th>Attendance Time</th>
                                            <th>Method</th>
                                            <th>Status</th>
                                            <th>Points</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($student_details['attendance'] as $attendance): ?>
                                            <tr>
                                                <td>
                                                    <a href="view_event.php?id=<?php echo $attendance['event_id']; ?>"
                                                        class="text-decoration-none">
                                                        <?php echo htmlspecialchars($attendance['event_title']); ?>
                                                    </a>
                                                </td>
                                                <td><span
                                                        class="badge badge-info"><?php echo htmlspecialchars($attendance['category_name']); ?></span>
                                                </td>
                                                <td>
                                                    <?php echo date("d M Y", strtotime($attendance['event_date'])); ?><br>
                                                    <small><?php echo date("h:i A", strtotime($attendance['event_time'])); ?></small>
                                                </td>
                                                <td><?php echo date("d M Y, h:i A", strtotime($attendance['attendance_time'])); ?>
                                                </td>
                                                <td>
                                                    <?php if ($attendance['method'] == 'qr'): ?>
                                                        <span class="badge badge-success">QR Scan</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-info">CAPTCHA</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($attendance['verified']): ?>
                                                        <span class="badge badge-success">Verified</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-warning">Pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($attendance['points'])): ?>
                                                        <span class="badge badge-primary"><?php echo $attendance['points']; ?>
                                                            pts</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No attendance records</h5>
                                <p>This student has not attended any events yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event Registrations -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Event Registrations</h4>
                        <a href="export_student_registrations.php?id=<?php echo $student_id; ?>"
                            class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-file-export mr-1"></i> Export
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($student_details['registrations'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Category</th>
                                            <th>Venue</th>
                                            <th>Date & Time</th>
                                            <th>Registration Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($student_details['registrations'] as $registration): ?>
                                            <tr>
                                                <td>
                                                    <a href="view_event.php?id=<?php echo $registration['event_id']; ?>"
                                                        class="text-decoration-none">
                                                        <?php echo htmlspecialchars($registration['event_title']); ?>
                                                    </a>
                                                </td>
                                                <td><span
                                                        class="badge badge-info"><?php echo htmlspecialchars($registration['category_name']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($registration['venue']); ?></td>
                                                <td>
                                                    <?php echo date("d M Y", strtotime($registration['event_date'])); ?><br>
                                                    <small><?php echo date("h:i A", strtotime($registration['event_time'])); ?></small>
                                                </td>
                                                <td><?php echo date("d M Y, h:i A", strtotime($registration['registration_date'])); ?>
                                                </td>
                                                <td>
                                                    <?php if ($registration['status'] == 'registered'): ?>
                                                        <span class="badge badge-success">Registered</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">Cancelled</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-alt fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No registrations</h5>
                                <p>This student has not registered for any events yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Students List View -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h1 class="h3 mb-3">Student Management</h1>
                        <p class="lead">Manage student records, view attendance and points earned.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Overview -->
        <div class="row">
            <!-- Total Students Card -->
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="display-4 text-primary mb-2">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h3 class="counter display-4 font-weight-bold"><?php echo $total_students; ?></h3>
                        <h5 class="text-muted">Total Students</h5>
                    </div>
                </div>
            </div>

            <!-- Total Attendance Card -->
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="display-4 text-success mb-2">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <?php
                        // Get total attendance count
                        $attendance_count = 0;
                        $sql = "SELECT COUNT(*) as count FROM attendance";
                        $result = $conn->query($sql);
                        if ($result && $result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $attendance_count = $row['count'];
                        }
                        ?>
                        <h3 class="counter display-4 font-weight-bold"><?php echo $attendance_count; ?></h3>
                        <h5 class="text-muted">Total Attendances</h5>
                    </div>
                </div>
            </div>

            <!-- Total Points Card -->
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="display-4 text-warning mb-2">
                            <i class="fas fa-award"></i>
                        </div>
                        <?php
                        // Get total points awarded
                        $total_points = 0;
                        $sql = "SELECT SUM(points) as total FROM user_points";
                        $result = $conn->query($sql);
                        if ($result && $result->num_rows > 0) {
                            $row = $result->fetch_assoc();
                            $total_points = $row['total'] ? $row['total'] : 0;
                        }
                        ?>
                        <h3 class="counter display-4 font-weight-bold"><?php echo $total_points; ?></h3>
                        <h5 class="text-muted">Total Points Awarded</h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Student List</h4>
                        <a href="export_students.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>"
                            class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-file-export mr-1"></i> Export
                        </a>
                    </div>
                    <div class="card-body">
                        <!-- Search and Filters -->
                        <form action="students.php" method="get" class="mb-4">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control"
                                    placeholder="Search by name, matric ID or email..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search mr-1"></i> Search
                                    </button>
                                    <?php if (!empty($search)): ?>
                                        <a href="students.php" class="btn btn-secondary">
                                            <i class="fas fa-times mr-1"></i> Clear
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <!-- Students Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Matric ID</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Registrations</th>
                                        <th>Attendance</th>
                                        <th>Total Points</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4">
                                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                <p class="mb-0 text-muted">No students found. Try a different search.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                <td><?php echo htmlspecialchars($student['matric_id']); ?></td>
                                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                                <td class="text-center"><?php echo $student['total_registrations']; ?></td>
                                                <td class="text-center"><?php echo $student['total_attendance']; ?></td>
                                                <td class="text-center font-weight-bold"><?php echo $student['total_points']; ?>
                                                </td>
                                                <td>
                                                    <a href="students.php?id=<?php echo $student['id']; ?>"
                                                        class="btn btn-sm btn-info mr-1">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger delete-student-btn"
                                                        data-id="<?php echo $student['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($student['name']); ?>">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <!-- Previous page link -->
                                    <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link"
                                            href="<?php echo ($current_page > 1) ? "?page=" . ($current_page - 1) . (!empty($search) ? "&search=" . urlencode($search) : "") : "#"; ?>">
                                            <i class="fas fa-angle-left"></i> Previous
                                        </a>
                                    </li>

                                    <!-- Page numbers -->
                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);

                                    if ($start_page > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($search) ? "&search=" . urlencode($search) : "") . '">1</a></li>';
                                        if ($start_page > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                    }

                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        echo '<li class="page-item ' . ($i == $current_page ? 'active' : '') . '">
                                        <a class="page-link" href="?page=' . $i . (!empty($search) ? "&search=" . urlencode($search) : "") . '">' . $i . '</a>
                                    </li>';
                                    }

                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . (!empty($search) ? "&search=" . urlencode($search) : "") . '">' . $total_pages . '</a></li>';
                                    }
                                    ?>

                                    <!-- Next page link -->
                                    <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link"
                                            href="<?php echo ($current_page < $total_pages) ? "?page=" . ($current_page + 1) . (!empty($search) ? "&search=" . urlencode($search) : "") : "#"; ?>">
                                            Next <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
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
                                <a href="events.php" class="btn btn-success btn-lg btn-block">
                                    <i class="fas fa-calendar-alt mr-2"></i> Manage Events
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="attendance.php" class="btn btn-info btn-lg btn-block">
                                    <i class="fas fa-clipboard-check mr-2"></i> Verify Attendance
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="reports.php" class="btn btn-secondary btn-lg btn-block">
                                    <i class="fas fa-chart-bar mr-2"></i> Generate Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Student Confirmation Modal -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1" role="dialog" aria-labelledby="deleteStudentModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteStudentModalLabel">Confirm Student Deletion</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the student: <strong id="deleteStudentName"></strong>?</p>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action will permanently remove
                    the student and all associated data including:</p>
                <ul class="text-danger">
                    <li>Event registrations</li>
                    <li>Attendance records</li>
                    <li>Points earned</li>
                    <li>Semester goals</li>
                </ul>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteStudentForm" action="students.php" method="post">
                    <input type="hidden" name="delete_student_id" id="deleteStudentId">
                    <button type="submit" name="delete_student" class="btn btn-danger">Delete Student</button>
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

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
    $(document).ready(function () {
        // Export students table to CSV
        $('#exportStudentsBtn').click(function () {
            // Prepare CSV content
            let csvContent = "data:text/csv;charset=utf-8,";

            // Add headers
            csvContent += "Name,Matric ID,Email,Phone,Registrations,Attendance,Total Points\n";

            // Add each row
            $('.table tbody tr').each(function () {
                let row = [];
                $(this).find('td').each(function (index) {
                    // Skip the Actions column (index 7)
                    if (index < 7) {
                        // Clean up the text and wrap in quotes
                        let cellText = $(this).text().trim().replace(/"/g, '""');
                        row.push('"' + cellText + '"');
                    }
                });

                // Add row to CSV
                if (row.length > 0) {
                    csvContent += row.join(',') + "\n";
                }
            });

            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "students_report_<?php echo date('Y-m-d'); ?>.csv");
            document.body.appendChild(link);

            // Download the file
            link.click();
        });
    });

    // Handle delete student button clicks
    $('.delete-student-btn').click(function () {
        var studentId = $(this).data('id');
        var studentName = $(this).data('name');

        // Set values in the modal
        $('#deleteStudentName').text(studentName);
        $('#deleteStudentId').val(studentId);

        // Show the confirmation modal
        $('#deleteStudentModal').modal('show');
    });
</script>