<?php
/**
 * Admin Dashboard
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Dashboard";

// Include header
include_once '../include/admin_header.php';

// Get statistics
// Total events
$total_events = 0;
$sql = "SELECT COUNT(*) as count FROM events";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_events = $row['count'];
}

// Total students
$total_students = 0;
$sql = "SELECT COUNT(*) as count FROM users WHERE role = 'student'";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_students = $row['count'];
}

// Pending attendance (students who registered but haven't attended events that have started)
$pending_attendance = 0;
$sql = "SELECT COUNT(*) as count FROM event_registrations er 
        JOIN events e ON er.event_id = e.id
        LEFT JOIN attendance a ON er.event_id = a.event_id AND er.user_id = a.user_id 
        WHERE er.status = 'registered' 
        AND a.id IS NULL 
        AND CONCAT(e.event_date, ' ', e.event_time) <= NOW()";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $pending_attendance = $row['count'];
}

// Recent registrations (last 7 days)
$recent_registrations = 0;
$sql = "SELECT COUNT(*) as count FROM event_registrations 
        WHERE registration_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $recent_registrations = $row['count'];
}

// Get upcoming events
$upcoming_events = array();
$sql = "SELECT e.*, c.name as category_name, 
        (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registrations 
        FROM events e 
        JOIN event_categories c ON e.category_id = c.id 
        WHERE e.event_date >= CURDATE() 
        ORDER BY e.event_date ASC 
        LIMIT 5";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $upcoming_events[] = $row;
    }
}

// Get recent pending attendance records (students who registered but haven't attended events that have started)
$recent_pending_attendance = array();
$sql = "SELECT er.id as registration_id, er.event_id, er.user_id, er.registration_date,
        e.title as event_title, e.event_date, e.event_time, 
        u.name as student_name, u.matric_id, u.email
        FROM event_registrations er
        JOIN events e ON er.event_id = e.id
        JOIN users u ON er.user_id = u.id
        LEFT JOIN attendance a ON er.event_id = a.event_id AND er.user_id = a.user_id
        WHERE er.status = 'registered' 
        AND a.id IS NULL
        AND CONCAT(e.event_date, ' ', e.event_time) <= NOW()
        ORDER BY e.event_date DESC, e.event_time DESC
        LIMIT 5";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_pending_attendance[] = $row;
    }
}

// Get student distribution by registration
$event_distribution = array();
$sql = "SELECT e.id, e.title, COUNT(r.id) as registration_count 
        FROM events e 
        LEFT JOIN event_registrations r ON e.id = r.event_id 
        WHERE e.event_date >= CURDATE() 
        GROUP BY e.id 
        ORDER BY registration_count DESC 
        LIMIT 5";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $event_distribution[] = $row;
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h1 class="display-4 mb-3">Admin Dashboard</h1>
                    <p class="lead">Welcome back, <strong><?php echo htmlspecialchars($_SESSION["name"]); ?></strong>! Here's an overview of the EventSphere system.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="row">
        <!-- Total Events Card -->
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-4 text-primary mb-2">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="counter display-4 font-weight-bold"><?php echo $total_events; ?></h3>
                    <h5 class="text-muted">Total Events</h5>
                    <a href="events.php" class="btn btn-sm btn-outline-primary mt-2">Manage Events</a>
                </div>
            </div>
        </div>

        <!-- Total Students Card -->
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-4 text-success mb-2">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3 class="counter display-4 font-weight-bold"><?php echo $total_students; ?></h3>
                    <h5 class="text-muted">Total Students</h5>
                    <a href="students.php" class="btn btn-sm btn-outline-success mt-2">View Students</a>
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
                    <h3 class="counter display-4 font-weight-bold"><?php echo $pending_attendance; ?></h3>
                    <h5 class="text-muted">Pending Attendance</h5>
                    <a href="attendance.php?filter=pending_attendance" class="btn btn-sm btn-outline-danger mt-2">Record Attendance</a>
                </div>
            </div>
        </div>

        <!-- Recent Registrations Card -->
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <div class="display-4 text-info mb-2">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3 class="counter display-4 font-weight-bold"><?php echo $recent_registrations; ?></h3>
                    <h5 class="text-muted">Recent Registrations</h5>
                    <small class="text-muted">Last 7 days</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Upcoming Events Table -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Upcoming Events</h4>
                    <a href="events.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Event Name</th>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Registrations</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($upcoming_events) > 0): ?>
                                    <?php foreach ($upcoming_events as $event): ?>
                                        <tr>
                                            <td>
                                                <?php if ($event['is_featured']): ?>
                                                    <span class="badge badge-warning mr-1"><i class="fas fa-star"></i></span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($event['title']); ?>
                                            </td>
                                            <td><?php echo date("d M Y", strtotime($event['event_date'])); ?></td>
                                            <td><span class="badge badge-info"><?php echo htmlspecialchars($event['category_name']); ?></span></td>
                                            <td><span class="badge badge-success"><?php echo $event['registrations']; ?></span></td>
                                            <td>
                                                <a href="view_event.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                                <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3">No upcoming events found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Event Registration Distribution -->
        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Registration Distribution</h4>
                </div>
                <div class="card-body">
                    <?php if (count($event_distribution) > 0): ?>
                        <?php foreach ($event_distribution as $event): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo htmlspecialchars($event['title']); ?></span>
                                    <span class="badge badge-primary"><?php echo $event['registration_count']; ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <?php 
                                    // Calculate percentage (max 100%)
                                    $percent = min(100, ($event['registration_count'] / max(1, $total_students)) * 100);
                                    ?>
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $percent; ?>%" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No registration data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Pending Attendance Records -->
        <div class="col-lg-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Students Pending Attendance</h4>
                    <a href="attendance.php?filter=pending_attendance" class="btn btn-sm btn-outline-danger">Record All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (count($recent_pending_attendance) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Matric ID</th>
                                        <th>Event</th>
                                        <th>Event Date/Time</th>
                                        <th>Registration Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_pending_attendance as $record): ?>
                                        <tr class="table-danger">
                                            <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                            <td><?php echo htmlspecialchars($record['matric_id']); ?></td>
                                            <td><?php echo htmlspecialchars($record['event_title']); ?></td>
                                            <td><?php echo date("d M Y, h:i A", strtotime($record['event_date'] . ' ' . $record['event_time'])); ?></td>
                                            <td><?php echo date("d M Y, h:i A", strtotime($record['registration_date'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary record-attendance-btn" 
                                                        data-user-id="<?php echo $record['user_id']; ?>"
                                                        data-event-id="<?php echo $record['event_id']; ?>"
                                                        data-student-name="<?php echo htmlspecialchars($record['student_name']); ?>"
                                                        data-event-title="<?php echo htmlspecialchars($record['event_title']); ?>">
                                                    <i class="fas fa-plus"></i> Record Attendance
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No students with pending attendance</p>
                            <small class="text-muted">All registered students have either attended their events or events haven't started yet</small>
                        </div>
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
                            <a href="create_event.php" class="btn btn-primary btn-lg btn-block">
                                <i class="fas fa-plus-circle mr-2"></i> Create Event
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="students.php" class="btn btn-success btn-lg btn-block">
                                <i class="fas fa-users mr-2"></i> Manage Students
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="attendance.php" class="btn btn-danger btn-lg btn-block">
                                <i class="fas fa-user-clock mr-2"></i> Manage Attendance
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="faq.php" class="btn btn-secondary btn-lg btn-block">
                                <i class="fas fa-question-circle mr-2"></i> Manage FAQ
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
                <form id="recordAttendanceForm" action="attendance.php" method="post" style="display: inline;">
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
    $('.record-attendance-btn').click(function() {
        var userId = $(this).data('user-id');
        var eventId = $(this).data('event-id');
        var studentName = $(this).data('student-name');
        var eventTitle = $(this).data('event-title');
        
        $('#recordStudentName').text(studentName);
        $('#recordEventTitle').text(eventTitle);
        $('#recordUserId').val(userId);
        $('#recordEventId').val(eventId);
        
        $('#recordAttendanceModal').modal('show');
    });
});
</script>
</body>
</html>