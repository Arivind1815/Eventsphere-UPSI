<?php
/**
 * View Event Details
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Add this at the beginning of view_event.php, right after the database connection inclusion

// Set timezone to match your location (adjust to your timezone)
date_default_timezone_set('Asia/Kuala_Lumpur'); // Change this to your appropriate timezone
// Include database connection
require_once '../config/db.php';
require_once '../lib/phpqrcode/qrlib.php'; // Include the phpqrcode library

// Set page title
$page_title = "Event Details";

// Include header
include_once '../include/admin_header.php';

// Check if event ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No event specified.";
    header("location: events.php");
    exit;
}

$event_id = intval($_GET['id']);

// Get event details
$event = null;
$sql = "SELECT e.*, c.name as category_name 
        FROM events e 
        JOIN event_categories c ON e.category_id = c.id 
        WHERE e.id = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $event = $result->fetch_assoc();
    } else {
        $_SESSION['error_message'] = "Event not found.";
        header("location: events.php");
        exit;
    }
    
    $stmt->close();
}

// Get event tags
$tags = [];
$tags_sql = "SELECT tag FROM event_tags WHERE event_id = ?";
if ($stmt = $conn->prepare($tags_sql)) {
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $tags_result = $stmt->get_result();
    
    while ($tag_row = $tags_result->fetch_assoc()) {
        $tags[] = $tag_row['tag'];
    }
    
    $stmt->close();
}

// Get registrations count and list
$registrations_count = 0;
$registrations = [];
$registrations_sql = "SELECT r.*, u.name, u.matric_id, u.email, u.phone 
                      FROM event_registrations r 
                      JOIN users u ON r.user_id = u.id 
                      WHERE r.event_id = ? 
                      ORDER BY r.registration_date DESC";

if ($stmt = $conn->prepare($registrations_sql)) {
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $registrations_result = $stmt->get_result();
    
    $registrations_count = $registrations_result->num_rows;
    
    while ($registration_row = $registrations_result->fetch_assoc()) {
        $registrations[] = $registration_row;
    }
    
    $stmt->close();
}

// Get attendance count and list
$attendance_count = 0;
$attendance_verified_count = 0;
$attendance = [];
$attendance_sql = "SELECT a.*, u.name, u.matric_id, u.email, u.phone 
                   FROM attendance a 
                   JOIN users u ON a.user_id = u.id 
                   WHERE a.event_id = ? 
                   ORDER BY a.verified ASC, a.attendance_time DESC";

if ($stmt = $conn->prepare($attendance_sql)) {
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
    
    $attendance_count = $attendance_result->num_rows;
    
    while ($attendance_row = $attendance_result->fetch_assoc()) {
        $attendance[] = $attendance_row;
        if ($attendance_row['verified']) {
            $attendance_verified_count++;
        }
    }
    
    $stmt->close();
}

// Format date and time for display
$event_date_formatted = date("l, F j, Y", strtotime($event['event_date']));
$event_time_formatted = date("h:i A", strtotime($event['event_time']));

// Format end date and time if available
$event_end_date_formatted = "";
$event_end_time_formatted = "";
if (isset($event['event_end_date']) && !empty($event['event_end_date'])) {
    $event_end_date_formatted = date("l, F j, Y", strtotime($event['event_end_date']));
}
if (isset($event['event_end_time']) && !empty($event['event_end_time'])) {
    $event_end_time_formatted = date("h:i A", strtotime($event['event_end_time']));
}

$registration_deadline_formatted = !empty($event['registration_deadline']) ? date("F j, Y", strtotime($event['registration_deadline'])) : "Same as event date";
$created_at_formatted = date("F j, Y, h:i A", strtotime($event['created_at']));
$updated_at_formatted = date("F j, Y, h:i A", strtotime($event['updated_at']));

// Process attendance verification if submitted
if (isset($_POST['verify_attendance']) && isset($_POST['attendance_id'])) {
    $attendance_id = intval($_POST['attendance_id']);
    $admin_id = $_SESSION["id"];
    
    // Verify attendance
    $verify_sql = "UPDATE attendance SET verified = 1, verification_time = NOW(), verified_by = ? WHERE id = ? AND event_id = ?";
    
    if ($verify_stmt = $conn->prepare($verify_sql)) {
        $verify_stmt->bind_param("iii", $admin_id, $attendance_id, $event_id);
        
        if ($verify_stmt->execute()) {
            // Get user ID to award points
            $user_sql = "SELECT user_id FROM attendance WHERE id = ?";
            if ($user_stmt = $conn->prepare($user_sql)) {
                $user_stmt->bind_param("i", $attendance_id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                
                if ($user_row = $user_result->fetch_assoc()) {
                    $user_id = $user_row['user_id'];
                    
                    // Determine points based on category
                    $points = 0;
                    switch ($event['category_id']) {
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
                            $message = "You earned " . $points . " points for attending " . $event['title'];
                            
                            $notif_stmt->bind_param("issi", $user_id, $title, $message, $event_id);
                            $notif_stmt->execute();
                            $notif_stmt->close();
                        }
                    }
                }
                
                $user_stmt->close();
            }
            
            $_SESSION['success_message'] = "Attendance verified and points awarded.";
            header("location: view_event.php?id=" . $event_id);
            exit;
        }
        
        $verify_stmt->close();
    }
}

// Process attendance rejection if submitted
if (isset($_POST['reject_attendance']) && isset($_POST['attendance_id'])) {
    $attendance_id = intval($_POST['attendance_id']);
    
    // Delete attendance record
    $reject_sql = "DELETE FROM attendance WHERE id = ? AND event_id = ?";
    
    if ($reject_stmt = $conn->prepare($reject_sql)) {
        $reject_stmt->bind_param("ii", $attendance_id, $event_id);
        
        if ($reject_stmt->execute()) {
            $_SESSION['success_message'] = "Attendance rejected successfully.";
            header("location: view_event.php?id=" . $event_id);
            exit;
        }
        
        $reject_stmt->close();
    }
}

// Check if the event is past
$is_past_event = $event['event_date'] < date('Y-m-d');

// Check if it's time to show the QR code (15 minutes before event end time)
$show_qr_code = false;
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$event_end_date = isset($event['event_end_date']) ? $event['event_end_date'] : $event['event_date'];
$event_end_time = isset($event['event_end_time']) ? $event['event_end_time'] : '23:59:59';

// If event date/time is today and we're within 15 minutes of the end
if ($current_date == $event_end_date) {
    $end_time_obj = new DateTime($event_end_time);
    $end_time_obj->modify('-15 minutes');
    $qr_start_time = $end_time_obj->format('H:i:s');
    
    if ($current_time >= $qr_start_time && $current_time <= $event_end_time) {
        $show_qr_code = true;
    }
}

// For testing purposes - uncomment this in development
// $show_qr_code = true;

// Generate a unique token for attendance
function generateToken($length = 32) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $token;
}

// Generate token and store in session
$token = generateToken();
$_SESSION['qr_token_' . $event_id] = $token;
$_SESSION['qr_token_time_' . $event_id] = time();

// QR code content (URL for student attendance)
$qr_content = 'https://treefrog-moving-uniformly.ngrok-free.app/eventspehere/student/mark_attendance.php?event=' . $event_id . '&token=' . $token; // change to your actual domain
// Generate QR code file path
$temp_dir = "../temp/qrcodes/";
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0777, true);
}

// Create a unique filename with timestamp to prevent caching
$timestamp = time();
$qr_filename = "qrcode_" . $event_id . "_" . $timestamp . ".png";
$qr_filepath = $temp_dir . $qr_filename;

// Generate QR code and save to file
QRcode::png($qr_content, $qr_filepath, QR_ECLEVEL_L, 10);

// URL to the QR code image
$qr_url = "../temp/qrcodes/" . $qr_filename;
?>

<div class="container mt-4">
    <!-- Event Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="mb-0">
                            <?php if ($event['is_featured']): ?>
                                <span class="badge badge-warning"><i class="fas fa-star"></i> Featured</span>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($event['title']); ?>
                        </h1>
                        <div>
                            <?php if (!$is_past_event): ?>
                                <a href="edit_event.php?id=<?php echo $event_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit mr-2"></i> Edit Event
                                </a>
                            <?php endif; ?>
                            <a href="events.php" class="btn btn-outline-secondary ml-2">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Events
                            </a>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <p class="lead"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                            
                            <div class="mb-3">
                                <?php foreach ($tags as $tag): ?>
                                    <span class="badge badge-info mr-1 p-2"><?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title"><i class="far fa-calendar-alt mr-2"></i> Date & Time</h5>
                                            <p class="card-text">
                                                <strong>Start:</strong> <?php echo $event_date_formatted; ?><br>
                                                <?php echo $event_time_formatted; ?>
                                                
                                                <?php if (!empty($event_end_date_formatted)): ?>
                                                <br><br><strong>End:</strong> <?php echo $event_end_date_formatted; ?><br>
                                                <?php echo $event_end_time_formatted; ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title"><i class="fas fa-map-marker-alt mr-2"></i> Venue</h5>
                                            <p class="card-text"><?php echo htmlspecialchars($event['venue']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title"><i class="fas fa-users mr-2"></i> Organizer</h5>
                                            <p class="card-text"><?php echo htmlspecialchars($event['organizer']); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title"><i class="fas fa-envelope mr-2"></i> Contact</h5>
                                            <p class="card-text"><?php echo htmlspecialchars($event['contact_info']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <?php if (!empty($event['poster_url'])): ?>
                                <div class="text-center mb-3">
                                    <img src="<?php echo '../' . htmlspecialchars($event['poster_url']); ?>" alt="Event Poster" class="img-fluid rounded shadow-sm">
                                </div>
                            <?php endif; ?>
                            
                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-info-circle mr-2"></i> Event Details</h5>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item bg-light d-flex justify-content-between px-0">
                                            <span><strong>Category:</strong></span>
                                            <span><?php echo htmlspecialchars($event['category_name']); ?></span>
                                        </li>
                                        <li class="list-group-item bg-light d-flex justify-content-between px-0">
                                            <span><strong>Registration Deadline:</strong></span>
                                            <span><?php echo $registration_deadline_formatted; ?></span>
                                        </li>
                                        <li class="list-group-item bg-light d-flex justify-content-between px-0">
                                            <span><strong>Max Participants:</strong></span>
                                            <span><?php echo !empty($event['max_participants']) ? $event['max_participants'] : 'Unlimited'; ?></span>
                                        </li>
                                        <li class="list-group-item bg-light d-flex justify-content-between px-0">
                                            <span><strong>Current Registrations:</strong></span>
                                            <span><?php echo $registrations_count; ?></span>
                                        </li>
                                        <li class="list-group-item bg-light d-flex justify-content-between px-0">
                                            <span><strong>Attended:</strong></span>
                                            <span><?php echo $attendance_verified_count . ' / ' . $attendance_count; ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <?php if (!empty($event['brochure_url'])): ?>
                                <div class="text-center mb-3">
                                    <a href="<?php echo '../' . htmlspecialchars($event['brochure_url']); ?>" class="btn btn-info btn-block" target="_blank">
                                        <i class="fas fa-file-pdf mr-2"></i> View Brochure/Rulebook
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Event Rules -->
    <?php if (!empty($event['rules'])): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0"><i class="fas fa-clipboard-list mr-2"></i> Event Rules & Guidelines</h4>
                    </div>
                    <div class="card-body">
                        <div class="rules-content">
                            <?php echo nl2br(htmlspecialchars($event['rules'])); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Tabs for Registrations and Attendance -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white p-0">
                    <ul class="nav nav-tabs" id="eventTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="registrations-tab" data-toggle="tab" href="#registrations" role="tab" aria-controls="registrations" aria-selected="true">
                                <i class="fas fa-user-plus mr-2"></i> Registrations <span class="badge badge-primary"><?php echo $registrations_count; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="attendance-tab" data-toggle="tab" href="#attendance" role="tab" aria-controls="attendance" aria-selected="false">
                                <i class="fas fa-clipboard-check mr-2"></i> Attendance <span class="badge badge-primary"><?php echo $attendance_count; ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="qr-tab" data-toggle="tab" href="#qr" role="tab" aria-controls="qr" aria-selected="false">
                                <i class="fas fa-qrcode mr-2"></i> QR Code
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="eventTabsContent">
                        <!-- Registrations Tab -->
                        <div class="tab-pane fade show active" id="registrations" role="tabpanel" aria-labelledby="registrations-tab">
                            <?php if ($registrations_count > 0): ?>
                                <div class="mb-3">
                                    <div class="input-group">
                                        <input type="text" id="registrationSearch" class="form-control" placeholder="Search by name or matric ID...">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover" id="registrationsTable">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Matric ID</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Registration Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($registrations as $registration): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($registration['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($registration['matric_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($registration['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($registration['phone']); ?></td>
                                                    <td><?php echo date("M j, Y, g:i a", strtotime($registration['registration_date'])); ?></td>
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
                                
                                <div class="mt-3">
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-user-plus fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">No registrations yet</h5>
                                    <p>No students have registered for this event yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Attendance Tab -->
                        <div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
                            <?php if ($attendance_count > 0): ?>
                                <div class="mb-3">
                                    <div class="input-group">
                                        <input type="text" id="attendanceSearch" class="form-control" placeholder="Search by name or matric ID...">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover" id="attendanceTable">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Matric ID</th>
                                                <th>Email</th>
                                                <th>Time</th>
                                                <th>Method</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance as $record): ?>
                                                <tr <?php echo $record['verified'] ? '' : 'class="table-warning"'; ?>>
                                                    <td><?php echo htmlspecialchars($record['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($record['matric_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($record['email']); ?></td>
                                                    <td><?php echo date("M j, Y, g:i a", strtotime($record['attendance_time'])); ?></td>
                                                    <td>
                                                        <?php if ($record['method'] == 'qr'): ?>
                                                            <span class="badge badge-success">QR Scan</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-info">CAPTCHA</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($record['verified']): ?>
                                                            <span class="badge badge-success">Verified</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-warning">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!$record['verified']): ?>
                                                            <form method="post" style="display: inline;">
                                                                <input type="hidden" name="attendance_id" value="<?php echo $record['id']; ?>">
                                                                <button type="submit" name="verify_attendance" class="btn btn-sm btn-success">
                                                                    <i class="fas fa-check"></i> Verify
                                                                </button>
                                                                <button type="submit" name="reject_attendance" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to reject this attendance?');">
                                                                    <i class="fas fa-times"></i> Reject
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">No attendance records yet</h5>
                                    <p>No students have marked their attendance for this event yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- QR Code Tab -->
                        <div class="tab-pane fade" id="qr" role="tabpanel" aria-labelledby="qr-tab">
                            <div class="row justify-content-center">
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body text-center p-5">
                                            <h4 class="mb-4"><i class="fas fa-qrcode mr-2"></i> Attendance QR Code</h4>
                                            
                                            <?php if ($is_past_event): ?>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-exclamation-triangle mr-2"></i> This event has already ended. The QR code is no longer available.
                                                </div>
                                            <?php elseif ($show_qr_code): ?>
                                                <div class="mb-3">
                                                    <img src="<?php echo $qr_url; ?>" alt="Attendance QR Code" class="img-fluid border p-2" id="qrCodeImage">
                                                </div>
                                                <p class="text-muted">This QR code will only work for the next 15 minutes.</p>
                                                <div class="mt-3">
                                                    <button id="refreshQrBtn" class="btn btn-primary">
                                                        <i class="fas fa-sync-alt mr-2"></i> Generate New QR Code
                                                    </button>
                                                </div>
                                                <div class="mt-4">
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle mr-2"></i> Display this QR code to students. They can scan it using their mobile devices to mark their attendance.
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle mr-2"></i> The QR code for attendance will be available 15 minutes before the event ends.
                                                </div>
                                                
                                                <!-- For testing/demonstration purposes only - Remove in production -->
                                                <div class="mt-4">
                                                    <button id="showQrForTestingBtn" class="btn btn-outline-secondary">
                                                        <i class="fas fa-flask mr-2"></i> Show QR for Testing
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="card border-0 shadow-sm mt-4">
                                        <div class="card-body">
                                            <h5>How Attendance QR Works</h5>
                                            <ol>
                                                <li>Display this QR code 15 minutes before the event ends</li>
                                                <li>Students scan the QR code with their smartphones</li>
                                                <li>Students are required to be logged into their EventSphere accounts</li>
                                                <li>System automatically records attendance with timestamp</li>
                                                <li>You can verify attendance records in the Attendance tab</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Event Metadata -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0"><i class="fas fa-info-circle mr-2"></i> Event Metadata</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Created:</strong> <?php echo $created_at_formatted; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Last Updated:</strong> <?php echo $updated_at_formatted; ?></p>
                        </div>
                    </div>
                </div>
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
$(document).ready(function() {
    // Search functionality for registrations table
    $("#registrationSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#registrationsTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    // Search functionality for attendance table
    $("#attendanceSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#attendanceTable tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
    
    // Refresh QR code
    $("#refreshQrBtn").click(function() {
        // Show loading indicator
        $("#qrCodeImage").attr("src", "../assets/img/loading.gif");
        
        // Make AJAX request to regenerate QR code
        $.ajax({
            url: 'regenerate_qr.php',
            type: 'POST',
            data: {
                event_id: <?php echo $event_id; ?>
            },
            success: function(response) {
                if (response.success) {
                    // Update the QR code image with the new one (add timestamp to bypass cache)
                    $("#qrCodeImage").attr("src", response.qr_url + "?t=" + new Date().getTime());
                } else {
                    alert("Failed to regenerate QR code: " + response.message);
                }
            },
            error: function() {
                alert("Error connecting to server. Please try again.");
                // Restore the original QR code image
                $("#qrCodeImage").attr("src", "<?php echo $qr_url; ?>");
            }
        });
    });
    
    // Show QR for testing (for demonstration purposes only)
    $("#showQrForTestingBtn").click(function() {
        var btnContainer = $(this).parent();
        
        // Make AJAX request to generate test QR code
        $.ajax({
            url: 'generate_test_qr.php',
            type: 'POST',
            data: {
                event_id: <?php echo $event_id; ?>
            },
            success: function(response) {
                if (response.success) {
                    btnContainer.html(
                        '<div class="mb-3"><img src="' + response.qr_url + '" alt="Testing QR Code" class="img-fluid border p-2"></div>' +
                        '<p class="text-muted">This is a test QR code for demonstration purposes only.</p>'
                    );
                } else {
                    alert("Failed to generate test QR code: " + response.message);
                }
            },
            error: function() {
                alert("Error connecting to server. Please try again.");
            }
        });
    });
});
</script>
</body>
</html>