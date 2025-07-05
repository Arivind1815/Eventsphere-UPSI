<?php
/**
 * My Events
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "My Events";

// Include header
include_once '../include/student_header.php';

// Get user ID
$user_id = $_SESSION["id"];

// Default active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'upcoming';

// Process registration cancellation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_registration'])) {
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    
    if ($event_id > 0) {
        // Check if registration exists and event is not past (using time-sensitive check)
        $check_sql = "SELECT r.id, e.title, e.event_date, e.event_end_date, e.event_end_time 
                      FROM event_registrations r 
                      JOIN events e ON r.event_id = e.id 
                      WHERE r.event_id = ? AND r.user_id = ? AND r.status = 'registered'";
        
        if ($check_stmt = $conn->prepare($check_sql)) {
            $check_stmt->bind_param("ii", $event_id, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $registration = $check_result->fetch_assoc();
                
                // Create end datetime for comparison
                $end_date = $registration['event_end_date'] ?: $registration['event_date'];
                $end_time = $registration['event_end_time'] ?: '23:59:59';
                $event_end_datetime = $end_date . ' ' . $end_time;
                $current_datetime = date('Y-m-d H:i:s');
                
                // Check if event has not ended yet
                if ($event_end_datetime > $current_datetime) {
                    // Cancel registration
                    $cancel_sql = "UPDATE event_registrations SET status = 'cancelled' WHERE id = ?";
                    
                    if ($cancel_stmt = $conn->prepare($cancel_sql)) {
                        $cancel_stmt->bind_param("i", $registration['id']);
                        
                        if ($cancel_stmt->execute()) {
                            // Add notification
                            $notif_sql = "INSERT INTO notifications (user_id, title, message, type, related_id) 
                                         VALUES (?, ?, ?, 'registration_cancelled', ?)";
                            
                            if ($notif_stmt = $conn->prepare($notif_sql)) {
                                $title = "Registration Cancelled";
                                $message = "Your registration for \"" . $registration['title'] . "\" has been cancelled.";
                                
                                $notif_stmt->bind_param("issi", $user_id, $title, $message, $event_id);
                                $notif_stmt->execute();
                                $notif_stmt->close();
                            }
                            
                            $_SESSION['success_message'] = "Registration cancelled successfully.";
                        } else {
                            $_SESSION['error_message'] = "Error cancelling registration.";
                        }
                        
                        $cancel_stmt->close();
                    }
                } else {
                    $_SESSION['error_message'] = "Cannot cancel registration for past events.";
                }
            } else {
                $_SESSION['error_message'] = "Registration not found or already cancelled.";
            }
            
            $check_stmt->close();
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: myevents.php?tab=" . $active_tab);
    exit;
}

// Get student's upcoming registered events (time-sensitive)
$upcoming_events = [];
$upcoming_sql = "SELECT e.*, c.name as category_name, r.registration_date, r.status as registration_status,
                (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND status = 'registered') as registration_count
                FROM events e 
                JOIN event_categories c ON e.category_id = c.id 
                JOIN event_registrations r ON e.id = r.event_id 
                WHERE r.user_id = ? AND r.status = 'registered' 
                AND CONCAT(COALESCE(e.event_end_date, e.event_date), ' ', COALESCE(e.event_end_time, '23:59:59')) > NOW()
                ORDER BY e.event_date ASC, e.event_time ASC";

if ($stmt = $conn->prepare($upcoming_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Get tags for the event
        $event_tags = [];
        $tags_sql = "SELECT tag FROM event_tags WHERE event_id = ?";
        if ($tags_stmt = $conn->prepare($tags_sql)) {
            $tags_stmt->bind_param("i", $row['id']);
            $tags_stmt->execute();
            $tags_result = $tags_stmt->get_result();
            
            while ($tag_row = $tags_result->fetch_assoc()) {
                $event_tags[] = $tag_row['tag'];
            }
            
            $tags_stmt->close();
        }
        
        // Add tags to event data
        $row['tags'] = $event_tags;
        $row['is_full'] = (!empty($row['max_participants']) && $row['registration_count'] >= $row['max_participants']);
        
        $upcoming_events[] = $row;
    }
    
    $stmt->close();
}

// Get student's past registered events (time-sensitive - events that have ended)
$past_events = [];
$past_sql = "SELECT e.*, c.name as category_name, r.registration_date, r.status as registration_status,
            a.verified as attendance_verified, a.attendance_time,
            p.points as points_earned
            FROM events e 
            JOIN event_categories c ON e.category_id = c.id 
            JOIN event_registrations r ON e.id = r.event_id 
            LEFT JOIN attendance a ON e.id = a.event_id AND a.user_id = ?
            LEFT JOIN user_points p ON e.id = p.event_id AND p.user_id = ?
            WHERE r.user_id = ? 
            AND CONCAT(COALESCE(e.event_end_date, e.event_date), ' ', COALESCE(e.event_end_time, '23:59:59')) <= NOW()
            ORDER BY e.event_date DESC, e.event_time DESC";

if ($stmt = $conn->prepare($past_sql)) {
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Get tags for the event
        $event_tags = [];
        $tags_sql = "SELECT tag FROM event_tags WHERE event_id = ?";
        if ($tags_stmt = $conn->prepare($tags_sql)) {
            $tags_stmt->bind_param("i", $row['id']);
            $tags_stmt->execute();
            $tags_result = $tags_stmt->get_result();
            
            while ($tag_row = $tags_result->fetch_assoc()) {
                $event_tags[] = $tag_row['tag'];
            }
            
            $tags_stmt->close();
        }
        
        // Add tags to event data and additional time info
        $row['tags'] = $event_tags;
        
        // Calculate how long ago the event ended
        $end_date = $row['event_end_date'] ?: $row['event_date'];
        $end_time = $row['event_end_time'] ?: '23:59:59';
        $event_end_datetime = $end_date . ' ' . $end_time;
        $row['event_end_datetime'] = $event_end_datetime;
        $row['time_since_ended'] = time() - strtotime($event_end_datetime);
        
        $past_events[] = $row;
    }
    
    $stmt->close();
}

// Get student's cancelled registrations (also time-sensitive for better organization)
$cancelled_events = [];
$cancelled_sql = "SELECT e.*, c.name as category_name, r.registration_date, r.status as registration_status,
                 CASE 
                    WHEN CONCAT(COALESCE(e.event_end_date, e.event_date), ' ', COALESCE(e.event_end_time, '23:59:59')) <= NOW() 
                    THEN 'past' 
                    ELSE 'upcoming' 
                 END as event_status
                 FROM events e 
                 JOIN event_categories c ON e.category_id = c.id 
                 JOIN event_registrations r ON e.id = r.event_id 
                 WHERE r.user_id = ? AND r.status = 'cancelled' 
                 ORDER BY e.event_date DESC, e.event_time DESC";

if ($stmt = $conn->prepare($cancelled_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Get tags for the event
        $event_tags = [];
        $tags_sql = "SELECT tag FROM event_tags WHERE event_id = ?";
        if ($tags_stmt = $conn->prepare($tags_sql)) {
            $tags_stmt->bind_param("i", $row['id']);
            $tags_stmt->execute();
            $tags_result = $tags_stmt->get_result();
            
            while ($tag_row = $tags_result->fetch_assoc()) {
                $event_tags[] = $tag_row['tag'];
            }
            
            $tags_stmt->close();
        }
        
        // Add tags to event data
        $row['tags'] = $event_tags;
        
        $cancelled_events[] = $row;
    }
    
    $stmt->close();
}

// Get total points earned
$total_points = 0;
$points_sql = "SELECT SUM(points) as total FROM user_points WHERE user_id = ?";
if ($stmt = $conn->prepare($points_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $total_points = $row['total'] ? $row['total'] : 0;
    }
    
    $stmt->close();
}

// Get points goal
$points_goal = 0;
$goal_sql = "SELECT points_goal FROM user_goals WHERE user_id = ? AND semester = ? ORDER BY id DESC LIMIT 1";
$current_semester = date('Y') . ' ' . (date('n') <= 6 ? 'Spring' : 'Fall');

if ($stmt = $conn->prepare($goal_sql)) {
    $stmt->bind_param("is", $user_id, $current_semester);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $points_goal = $row['points_goal'];
    }
    
    $stmt->close();
}

// Process goal setting form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['set_goal'])) {
    $new_goal = isset($_POST['points_goal']) ? intval($_POST['points_goal']) : 0;
    
    if ($new_goal > 0) {
        // Check if goal already exists for current semester
        if ($points_goal > 0) {
            // Update existing goal
            $update_sql = "UPDATE user_goals SET points_goal = ?, updated_at = NOW() WHERE user_id = ? AND semester = ?";
            
            if ($update_stmt = $conn->prepare($update_sql)) {
                $update_stmt->bind_param("iis", $new_goal, $user_id, $current_semester);
                
                if ($update_stmt->execute()) {
                    $_SESSION['success_message'] = "Your points goal has been updated.";
                    $points_goal = $new_goal;
                } else {
                    $_SESSION['error_message'] = "Error updating points goal.";
                }
                
                $update_stmt->close();
            }
        } else {
            // Insert new goal
            $insert_sql = "INSERT INTO user_goals (user_id, semester, points_goal) VALUES (?, ?, ?)";
            
            if ($insert_stmt = $conn->prepare($insert_sql)) {
                $insert_stmt->bind_param("isi", $user_id, $current_semester, $new_goal);
                
                if ($insert_stmt->execute()) {
                    $_SESSION['success_message'] = "Your points goal has been set.";
                    $points_goal = $new_goal;
                } else {
                    $_SESSION['error_message'] = "Error setting points goal.";
                }
                
                $insert_stmt->close();
            }
        }
    } else {
        $_SESSION['error_message'] = "Please enter a valid points goal.";
    }
    
    // Redirect to prevent form resubmission
    header("Location: myevents.php?tab=points");
    exit;
}
?>

<div class="container mt-4">
    <!-- Page Header -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h1 class="mb-0">My Events</h1>
                    <p class="text-muted">Manage your event registrations and track your participation.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <!-- Upcoming Events Card -->
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-primary mb-2">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="counter"><?php echo count($upcoming_events); ?></h3>
                    <h5 class="text-muted">Upcoming Events</h5>
                </div>
            </div>
        </div>
        
        <!-- Past Events Card -->
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-success mb-2">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="counter"><?php echo count($past_events); ?></h3>
                    <h5 class="text-muted">Past Events</h5>
                </div>
            </div>
        </div>
        
        <!-- Attendance Rate Card -->
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <?php
                    $attendance_count = 0;
                    foreach ($past_events as $event) {
                        if ($event['attendance_verified']) {
                            $attendance_count++;
                        }
                    }
                    
                    $attendance_rate = (count($past_events) > 0) ? round(($attendance_count / count($past_events)) * 100) : 0;
                    ?>
                    <div class="display-4 text-info mb-2">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3 class="counter"><?php echo $attendance_rate; ?>%</h3>
                    <h5 class="text-muted">Attendance Rate</h5>
                </div>
            </div>
        </div>
        
        <!-- Points Card -->
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="display-4 text-warning mb-2">
                        <i class="fas fa-award"></i>
                    </div>
                    <h3 class="counter"><?php echo $total_points; ?></h3>
                    <h5 class="text-muted">Champ Points</h5>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Events Tabs -->
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white p-0">
                    <ul class="nav nav-tabs" id="eventsTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($active_tab == 'upcoming') ? 'active' : ''; ?>" id="upcoming-tab" data-toggle="tab" href="#upcoming" role="tab" aria-controls="upcoming" aria-selected="<?php echo ($active_tab == 'upcoming') ? 'true' : 'false'; ?>">
                                <i class="fas fa-calendar-alt mr-2"></i> Upcoming
                                <span class="badge badge-primary"><?php echo count($upcoming_events); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($active_tab == 'past') ? 'active' : ''; ?>" id="past-tab" data-toggle="tab" href="#past" role="tab" aria-controls="past" aria-selected="<?php echo ($active_tab == 'past') ? 'true' : 'false'; ?>">
                                <i class="fas fa-history mr-2"></i> Past Events
                                <span class="badge badge-success"><?php echo count($past_events); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($active_tab == 'cancelled') ? 'active' : ''; ?>" id="cancelled-tab" data-toggle="tab" href="#cancelled" role="tab" aria-controls="cancelled" aria-selected="<?php echo ($active_tab == 'cancelled') ? 'true' : 'false'; ?>">
                                <i class="fas fa-times-circle mr-2"></i> Cancelled
                                <span class="badge badge-secondary"><?php echo count($cancelled_events); ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($active_tab == 'points') ? 'active' : ''; ?>" id="points-tab" data-toggle="tab" href="#points" role="tab" aria-controls="points" aria-selected="<?php echo ($active_tab == 'points') ? 'true' : 'false'; ?>">
                                <i class="fas fa-award mr-2"></i> Champ Points
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="eventsTabsContent">
                        <!-- Upcoming Events Tab -->
                        <div class="tab-pane fade <?php echo ($active_tab == 'upcoming') ? 'show active' : ''; ?>" id="upcoming" role="tabpanel" aria-labelledby="upcoming-tab">
                            <?php if (count($upcoming_events) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($upcoming_events as $event): ?>
                                        <div class="list-group-item p-0 border-0">
                                            <div class="card border-0 mb-3">
                                                <div class="row no-gutters">
                                                    <!-- Event Image -->
                                                    <div class="col-md-3">
                                                        <?php if (!empty($event['poster_url'])): ?>
                                                            <img src="<?php echo '../' . htmlspecialchars($event['poster_url']); ?>" class="card-img" alt="<?php echo htmlspecialchars($event['title']); ?>" style="height: 100%; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="h-100 d-flex align-items-center justify-content-center bg-light">
                                                                <i class="fas fa-calendar-alt fa-4x text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Event Details -->
                                                    <div class="col-md-9">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h5 class="card-title">
                                                                        <?php if ($event['is_featured']): ?>
                                                                            <span class="badge badge-warning mr-2"><i class="fas fa-star"></i> Featured</span>
                                                                        <?php endif; ?>
                                                                        <?php echo htmlspecialchars($event['title']); ?>
                                                                    </h5>
                                                                    <div class="mb-2 text-muted">
                                                                        <i class="far fa-calendar-alt mr-1"></i> <?php echo date("D, M j, Y", strtotime($event['event_date'])); ?>
                                                                        <i class="far fa-clock ml-3 mr-1"></i> <?php echo date("h:i A", strtotime($event['event_time'])); ?>
                                                                        <i class="fas fa-map-marker-alt ml-3 mr-1"></i> <?php echo htmlspecialchars($event['venue']); ?>
                                                                    </div>
                                                                </div>
                                                                <div>
                                                                    <?php
                                                                    // Calculate days until event
                                                                    $event_date = new DateTime($event['event_date']);
                                                                    $today = new DateTime();
                                                                    $interval = $today->diff($event_date);
                                                                    $days_left = $interval->days;
                                                                    ?>
                                                                    <span class="badge badge-primary p-2">
                                                                        <?php if ($days_left === 0): ?>
                                                                            Today!
                                                                        <?php else: ?>
                                                                            <?php echo $days_left; ?> day<?php echo ($days_left !== 1) ? 's' : ''; ?> left
                                                                        <?php endif; ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="mb-2">
                                                                <span class="badge badge-info mr-1"><?php echo htmlspecialchars($event['category_name']); ?></span>
                                                                <?php foreach ($event['tags'] as $tag): ?>
                                                                    <span class="badge badge-secondary mr-1"><?php echo htmlspecialchars($tag); ?></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            
                                                            <p class="card-text">
                                                                <?php
                                                                $desc = htmlspecialchars($event['description']);
                                                                echo (strlen($desc) > 150) ? substr($desc, 0, 150) . '...' : $desc;
                                                                ?>
                                                            </p>
                                                            
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <small class="text-muted">
                                                                    Registered on <?php echo date("M j, Y", strtotime($event['registration_date'])); ?>
                                                                </small>
                                                                
                                                                <div>
                                                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" onsubmit="return confirm('Are you sure you want to cancel your registration?');" style="display: inline;">
                                                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                                        <button type="submit" name="cancel_registration" class="btn btn-sm btn-outline-danger mr-2">
                                                                            <i class="fas fa-times-circle mr-1"></i> Cancel Registration
                                                                        </button>
                                                                    </form>
                                                                    
                                                                    <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">
                                                                        <i class="fas fa-info-circle mr-1"></i> View Details
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-calendar-alt fa-4x text-muted mb-3"></i>
                                    <h4 class="text-muted">No upcoming events</h4>
                                    <p>You haven't registered for any upcoming events yet.</p>
                                    <a href="events.php" class="btn btn-primary mt-2">
                                        <i class="fas fa-search mr-2"></i> Browse Events
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Past Events Tab -->
                        <div class="tab-pane fade <?php echo ($active_tab == 'past') ? 'show active' : ''; ?>" id="past" role="tabpanel" aria-labelledby="past-tab">
                            <?php if (count($past_events) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($past_events as $event): ?>
                                        <div class="list-group-item p-0 border-0">
                                            <div class="card border-0 mb-3">
                                                <div class="row no-gutters">
                                                    <!-- Event Image -->
                                                    <div class="col-md-3">
                                                        <?php if (!empty($event['poster_url'])): ?>
                                                            <img src="<?php echo '../' . htmlspecialchars($event['poster_url']); ?>" class="card-img" alt="<?php echo htmlspecialchars($event['title']); ?>" style="height: 100%; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="h-100 d-flex align-items-center justify-content-center bg-light">
                                                                <i class="fas fa-calendar-alt fa-4x text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Event Details -->
                                                    <div class="col-md-9">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h5 class="card-title">
                                                                        <?php echo htmlspecialchars($event['title']); ?>
                                                                    </h5>
                                                                    <div class="mb-2 text-muted">
                                                                        <i class="far fa-calendar-alt mr-1"></i> <?php echo date("D, M j, Y", strtotime($event['event_date'])); ?>
                                                                        <i class="far fa-clock ml-3 mr-1"></i> <?php echo date("h:i A", strtotime($event['event_time'])); ?>
                                                                        <i class="fas fa-map-marker-alt ml-3 mr-1"></i> <?php echo htmlspecialchars($event['venue']); ?>
                                                                    </div>
                                                                </div>
                                                                <div>
                                                                    <?php if ($event['attendance_verified']): ?>
                                                                        <span class="badge badge-success p-2">
                                                                            <i class="fas fa-check-circle mr-1"></i> Attended
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <span class="badge badge-secondary p-2">
                                                                            <i class="fas fa-times-circle mr-1"></i> Not Attended
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="mb-2">
                                                                <span class="badge badge-info mr-1"><?php echo htmlspecialchars($event['category_name']); ?></span>
                                                                <?php foreach ($event['tags'] as $tag): ?>
                                                                    <span class="badge badge-secondary mr-1"><?php echo htmlspecialchars($tag); ?></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <?php if ($event['attendance_verified'] && $event['points_earned']): ?>
                                                                        <span class="badge badge-warning p-2 mr-2">
                                                                            <i class="fas fa-award mr-1"></i> <?php echo $event['points_earned']; ?> Points Earned
                                                                        </span>
                                                                    <?php endif; ?>
                                                                    
                                                                    <small class="text-muted">
                                                                        Registered on <?php echo date("M j, Y", strtotime($event['registration_date'])); ?>
                                                                    </small>
                                                                </div>
                                                                
                                                                <div>
                                                                    <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">
                                                                        <i class="fas fa-info-circle mr-1"></i> View Details
                                                                    </a>
                                                                    
                                                                    <?php if ($event['attendance_verified']): ?>
                                                                        <a href="feedback.php?event=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-secondary ml-2">
                                                                            <i class="fas fa-comment mr-1"></i> Give Feedback
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-history fa-4x text-muted mb-3"></i>
                                    <h4 class="text-muted">No past events</h4>
                                    <p>You haven't attended any events yet.</p>
                                    <a href="events.php" class="btn btn-primary mt-2">
                                        <i class="fas fa-search mr-2"></i> Browse Events
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Cancelled Events Tab -->
                        <div class="tab-pane fade <?php echo ($active_tab == 'cancelled') ? 'show active' : ''; ?>" id="cancelled" role="tabpanel" aria-labelledby="cancelled-tab">
                            <?php if (count($cancelled_events) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($cancelled_events as $event): ?>
                                        <div class="list-group-item p-0 border-0">
                                            <div class="card border-0 mb-3">
                                                <div class="row no-gutters">
                                                    <!-- Event Image -->
                                                    <div class="col-md-3">
                                                        <?php if (!empty($event['poster_url'])): ?>
                                                            <img src="<?php echo '../' . htmlspecialchars($event['poster_url']); ?>" class="card-img" alt="<?php echo htmlspecialchars($event['title']); ?>" style="height: 100%; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="h-100 d-flex align-items-center justify-content-center bg-light">
                                                                <i class="fas fa-calendar-alt fa-4x text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Event Details -->
                                                    <div class="col-md-9">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h5 class="card-title">
                                                                        <?php echo htmlspecialchars($event['title']); ?>
                                                                    </h5>
                                                                    <div class="mb-2 text-muted">
                                                                        <i class="far fa-calendar-alt mr-1"></i> <?php echo date("D, M j, Y", strtotime($event['event_date'])); ?>
                                                                        <i class="far fa-clock ml-3 mr-1"></i> <?php echo date("h:i A", strtotime($event['event_time'])); ?>
                                                                        <i class="fas fa-map-marker-alt ml-3 mr-1"></i> <?php echo htmlspecialchars($event['venue']); ?>
                                                                    </div>
                                                                </div>
                                                                <div>
                                                                    <span class="badge badge-secondary p-2">
                                                                        <i class="fas fa-times-circle mr-1"></i> Registration Cancelled
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="mb-2">
                                                                <span class="badge badge-info mr-1"><?php echo htmlspecialchars($event['category_name']); ?></span>
                                                                <?php foreach ($event['tags'] as $tag): ?>
                                                                    <span class="badge badge-secondary mr-1"><?php echo htmlspecialchars($tag); ?></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <small class="text-muted">
                                                                    Registered on <?php echo date("M j, Y", strtotime($event['registration_date'])); ?>
                                                                </small>
                                                                
                                                                <div>
                                                                    <?php if ($event['event_date'] >= date('Y-m-d')): ?>
                                                                        <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-primary">
                                                                            <i class="fas fa-redo mr-1"></i> Register Again
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                            <i class="fas fa-info-circle mr-1"></i> View Details
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-times-circle fa-4x text-muted mb-3"></i>
                                    <h4 class="text-muted">No cancelled registrations</h4>
                                    <p>You haven't cancelled any event registrations.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Champ Points Tab -->
                        <div class="tab-pane fade <?php echo ($active_tab == 'points') ? 'show active' : ''; ?>" id="points" role="tabpanel" aria-labelledby="points-tab">
                            <div class="p-4">
                                <!-- Points Overview -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-white">
                                                <h5 class="mb-0">Points Overview</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6 text-center mb-3">
                                                        <h2 class="display-4 text-primary"><?php echo $total_points; ?></h2>
                                                        <p class="lead">Total Points</p>
                                                    </div>
                                                    <div class="col-md-6 text-center mb-3">
                                                        <h2 class="display-4 text-success"><?php echo $points_goal; ?></h2>
                                                        <p class="lead">Semester Goal</p>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($points_goal > 0): ?>
                                                    <div class="progress mb-3" style="height: 25px;">
                                                        <?php $progress = min(100, ($total_points / $points_goal) * 100); ?>
                                                        <div class="progress-bar progress-bar-striped <?php echo ($progress >= 100) ? 'bg-success' : 'bg-primary'; ?>" role="progressbar" style="width: <?php echo $progress; ?>%" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                                            <?php echo round($progress); ?>%
                                                        </div>
                                                    </div>
                                                    <p class="text-center">
                                                        <?php if ($total_points >= $points_goal): ?>
                                                            <span class="badge badge-success p-2"><i class="fas fa-trophy mr-1"></i> Goal Achieved!</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-primary p-2"><i class="fas fa-flag-checkered mr-1"></i> <?php echo $points_goal - $total_points; ?> points to go</span>
                                                        <?php endif; ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card border-0 shadow-sm">
                                            <div class="card-header bg-white">
                                                <h5 class="mb-0">Set Points Goal</h5>
                                            </div>
                                            <div class="card-body">
                                                <p>Set a goal for the number of Champ Points you want to earn this semester.</p>
                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                    <div class="form-group">
                                                        <label for="points_goal">Points Goal for <?php echo $current_semester; ?></label>
                                                        <input type="number" name="points_goal" id="points_goal" class="form-control" min="1" value="<?php echo $points_goal > 0 ? $points_goal : ''; ?>" placeholder="Enter your points goal">
                                                    </div>
                                                    <button type="submit" name="set_goal" class="btn btn-primary">
                                                        <i class="fas fa-bullseye mr-2"></i> Set Goal
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Points History -->
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-white">
                                        <h5 class="mb-0">Points History</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <?php
                                        // Get points history
                                        $points_history = [];
                                        $history_sql = "SELECT p.*, e.title as event_title, e.event_date, c.name as category_name 
                                                      FROM user_points p 
                                                      JOIN events e ON p.event_id = e.id 
                                                      JOIN event_categories c ON e.category_id = c.id
                                                      WHERE p.user_id = ? 
                                                      ORDER BY p.awarded_date DESC";
                                        
                                        if ($stmt = $conn->prepare($history_sql)) {
                                            $stmt->bind_param("i", $user_id);
                                            $stmt->execute();
                                            $result = $stmt->get_result();
                                            
                                            while ($row = $result->fetch_assoc()) {
                                                $points_history[] = $row;
                                            }
                                            
                                            $stmt->close();
                                        }
                                        ?>
                                        
                                        <?php if (count($points_history) > 0): ?>
                                            <div class="table-responsive">
                                                <table class="table table-hover mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Event</th>
                                                            <th>Category</th>
                                                            <th>Date</th>
                                                            <th>Points</th>
                                                            <th>Awarded On</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($points_history as $history): ?>
                                                            <tr>
                                                                <td>
                                                                    <a href="event_details.php?id=<?php echo $history['event_id']; ?>">
                                                                        <?php echo htmlspecialchars($history['event_title']); ?>
                                                                    </a>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($history['category_name']); ?></td>
                                                                <td><?php echo date("M j, Y", strtotime($history['event_date'])); ?></td>
                                                                <td><span class="badge badge-warning"><?php echo $history['points']; ?></span></td>
                                                                <td><?php echo date("M j, Y", strtotime($history['awarded_date'])); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-5">
                                                <i class="fas fa-award fa-4x text-muted mb-3"></i>
                                                <h4 class="text-muted">No points earned yet</h4>
                                                <p>Attend events to earn Champ Points.</p>
                                                <a href="events.php" class="btn btn-primary mt-2">
                                                    <i class="fas fa-search mr-2"></i> Browse Events
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Points Categories Info -->
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-white">
                                        <h5 class="mb-0">Points Categories</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Different event categories award different amounts of points:</p>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <ul class="list-group mb-3">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        International Events
                                                        <span class="badge badge-warning badge-pill">30 points</span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        National Events
                                                        <span class="badge badge-warning badge-pill">25 points</span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Faculty Events
                                                        <span class="badge badge-warning badge-pill">15 points</span>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <ul class="list-group mb-3">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Local Events
                                                        <span class="badge badge-warning badge-pill">15 points</span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Club Events
                                                        <span class="badge badge-warning badge-pill">10 points</span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        College Events
                                                        <span class="badge badge-warning badge-pill">10 points</span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle mr-2"></i> Points are awarded when administrators verify your attendance at an event.
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
    // Activate tabs based on URL hash
    var hash = window.location.hash;
    if (hash) {
        $('.nav-tabs a[href="' + hash + '"]').tab('show');
    }
    
    // Change hash for page-reload
    $('.nav-tabs a').on('shown.bs.tab', function(e) {
        var id = $(e.target).attr('href').substr(1);
        window.location.hash = id;
    });
});
</script>
</body>
</html>