<?php
/**
 * Mark Attendance
 * EventSphere@UPSI: Navigate, Engage & Excel
 */
date_default_timezone_set('Asia/Kuala_Lumpur'); // Change this to your appropriate timezone

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Mark Attendance";

// Include header
include_once '../include/student_header.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $_SESSION['error_message'] = "You must be logged in to mark attendance.";
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["id"];
$success = false;
$error = "";
$event = null;
$points_awarded = 0;

// Check if event ID and token are provided in URL
if (isset($_GET['event']) && isset($_GET['token'])) {
    $event_id = intval($_GET['event']);
    $provided_token = $_GET['token'];
    
    // Verify event exists
    $event_sql = "SELECT e.*, c.id as category_id, c.name as category_name 
                  FROM events e 
                  JOIN event_categories c ON e.category_id = c.id 
                  WHERE e.id = ?";
    if ($stmt = $conn->prepare($event_sql)) {
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $event = $result->fetch_assoc();
            
            // Verify this is an active event
            $is_past = ($event['event_date'] < date('Y-m-d'));
            
            if ($is_past) {
                $error = "This event has already ended. Attendance cannot be marked.";
            } else {
                // Check if the event is happening today and within 15 minutes of the end time
                $is_today = ($event['event_date'] == date('Y-m-d'));
                $is_ending_soon = false;
                
                if ($is_today) {
                    // Get the current time
                    $current_time = time();
                    
                    // Get event end time (use event_end_time if available, otherwise use event_time)
                    $event_end_time = isset($event['event_end_time']) && !empty($event['event_end_time']) 
                        ? $event['event_end_time'] 
                        : $event['event_time'];
                        
                    // Convert event end time to timestamp
                    $event_end_datetime = $event['event_date'] . ' ' . $event_end_time;
                    $event_end_timestamp = strtotime($event_end_datetime);
                    
                    // Calculate 15 minutes before end time
                    $fifteen_mins_before = $event_end_timestamp - (15 * 60);
                    
                    // Check if current time is within 15 minutes of end time
                    if ($current_time >= $fifteen_mins_before && $current_time <= $event_end_timestamp) {
                        $is_ending_soon = true;
                    }
                }
                
                // For testing purposes - uncomment this line if you want to bypass time restrictions
                // $is_ending_soon = true;
                
                if (!$is_ending_soon && !isset($_GET['test'])) {
                    $error = "Attendance can only be marked 15 minutes before the event ends.";
                } else {
                    // Check if user is registered for the event
                    $check_registration_sql = "SELECT id FROM event_registrations 
                                             WHERE event_id = ? AND user_id = ? AND status = 'registered'";
                    
                    if ($check_reg_stmt = $conn->prepare($check_registration_sql)) {
                        $check_reg_stmt->bind_param("ii", $event_id, $user_id);
                        $check_reg_stmt->execute();
                        $check_reg_result = $check_reg_stmt->get_result();
                        
                        if ($check_reg_result->num_rows == 1) {
                            // Check if already marked attendance
                            $check_attendance_sql = "SELECT id FROM attendance 
                                                   WHERE event_id = ? AND user_id = ?";
                            
                            if ($check_att_stmt = $conn->prepare($check_attendance_sql)) {
                                $check_att_stmt->bind_param("ii", $event_id, $user_id);
                                $check_att_stmt->execute();
                                $check_att_result = $check_att_stmt->get_result();
                                
                                if ($check_att_result->num_rows == 0) {
                                    // Start a transaction
                                    $conn->begin_transaction();
                                    
                                    try {
                                        // Mark attendance
                                        $mark_sql = "INSERT INTO attendance 
                                                  (event_id, user_id, attendance_time, method, verified) 
                                                  VALUES (?, ?, NOW(), 'qr', 1)";
                                        
                                        if ($mark_stmt = $conn->prepare($mark_sql)) {
                                            $mark_stmt->bind_param("ii", $event_id, $user_id);
                                            $mark_stmt->execute();
                                            $mark_stmt->close();
                                            
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
                                            
                                            // Award points immediately
                                            $points_sql = "INSERT INTO user_points (user_id, event_id, points, awarded_date) 
                                                         VALUES (?, ?, ?, NOW())";
                                            
                                            if ($points_stmt = $conn->prepare($points_sql)) {
                                                $points_stmt->bind_param("iii", $user_id, $event_id, $points);
                                                $points_stmt->execute();
                                                $points_stmt->close();
                                                $points_awarded = $points;
                                                
                                                // Create notification
                                                $notification_sql = "INSERT INTO notifications 
                                                                  (user_id, title, message, type, related_id) 
                                                                  VALUES (?, ?, ?, 'points_added', ?)";
                                                
                                                if ($notif_stmt = $conn->prepare($notification_sql)) {
                                                    $title = "Attendance Recorded and Points Awarded";
                                                    $message = "You have earned $points points for attending \"" . $event['title'] . "\".";
                                                    
                                                    $notif_stmt->bind_param("issi", $user_id, $title, $message, $event_id);
                                                    $notif_stmt->execute();
                                                    $notif_stmt->close();
                                                }
                                                
                                                // Commit the transaction
                                                $conn->commit();
                                                $success = true;
                                            } else {
                                                throw new Exception("Error awarding points");
                                            }
                                        } else {
                                            throw new Exception("Error marking attendance");
                                        }
                                    } catch (Exception $e) {
                                        // An error occurred, rollback the transaction
                                        $conn->rollback();
                                        $error = "Something went wrong: " . $e->getMessage();
                                    }
                                } else {
                                    $error = "You have already marked your attendance for this event.";
                                }
                                
                                $check_att_stmt->close();
                            }
                        } else {
                            $error = "You are not registered for this event.";
                        }
                        
                        $check_reg_stmt->close();
                    }
                }
            }
        } else {
            $error = "Event not found.";
        }
        
        $stmt->close();
    }
} else {
    $error = "Missing required information. Please scan the QR code again.";
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center p-5">
                    <?php if ($success): ?>
                        <div class="mb-4">
                            <i class="fas fa-check-circle text-success fa-5x"></i>
                        </div>
                        <h2 class="mb-3">Attendance Recorded!</h2>
                        <p class="lead">Your attendance for "<?php echo htmlspecialchars($event['title']); ?>" has been successfully recorded.</p>
                        
                        <!-- Points Awarded Section -->
                        <div class="alert alert-success mt-3">
                            <h4><i class="fas fa-award mr-2"></i> Points Awarded</h4>
                            <p class="lead mb-0"><strong><?php echo $points_awarded; ?> points</strong> added to your account for attending a <strong><?php echo htmlspecialchars($event['category_name']); ?></strong> event.</p>
                        </div>
                        
                        <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-primary btn-lg mt-3">
                            <i class="fas fa-calendar-alt mr-2"></i> Back to Event
                        </a>
                    <?php else: ?>
                        <div class="mb-4">
                            <i class="fas fa-times-circle text-danger fa-5x"></i>
                        </div>
                        <h2 class="mb-3">Unable to Mark Attendance</h2>
                        <p class="lead text-danger"><?php echo $error; ?></p>
                        <?php if ($event): ?>
                            <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-primary btn-lg mt-3">
                                <i class="fas fa-calendar-alt mr-2"></i> Back to Event
                            </a>
                        <?php else: ?>
                            <a href="events.php" class="btn btn-primary btn-lg mt-3">
                                <i class="fas fa-calendar-alt mr-2"></i> All Events
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../include/student_footer.php'; ?>