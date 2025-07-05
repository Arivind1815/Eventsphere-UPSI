<?php
/**
 * Event Details
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Event Details";

// Check if event ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No event specified.";
    header("location: events.php");
    exit;
}

$event_id = intval($_GET['id']);
$user_id = $_SESSION["id"];

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

// Check if user is registered for this event
$is_registered = false;
$registration_date = null;
$check_sql = "SELECT registration_date FROM event_registrations WHERE event_id = ? AND user_id = ? AND status = 'registered'";
if ($stmt = $conn->prepare($check_sql)) {
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();
    $check_result = $stmt->get_result();

    if ($check_result->num_rows > 0) {
        $is_registered = true;
        $row = $check_result->fetch_assoc();
        $registration_date = $row['registration_date'];
    }

    $stmt->close();
}

// Check if user has previously registered (even if cancelled)
$has_previous_registration = false;
$previous_reg_sql = "SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ?";
if ($stmt = $conn->prepare($previous_reg_sql)) {
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();
    $prev_result = $stmt->get_result();

    if ($prev_result->num_rows > 0) {
        $has_previous_registration = true;
    }

    $stmt->close();
}

// Get registration count
$registrations_count = 0;
$reg_sql = "SELECT COUNT(*) as count FROM event_registrations WHERE event_id = ? AND status = 'registered'";
if ($stmt = $conn->prepare($reg_sql)) {
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $reg_result = $stmt->get_result();

    if ($row = $reg_result->fetch_assoc()) {
        $registrations_count = $row['count'];
    }

    $stmt->close();
}

// Check if event is past, already started, registration closed, or full
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$is_past = ($event['event_date'] < $current_date);

// Check if event is happening today (for QR attendance)
$is_today = ($event['event_date'] == $current_date);

// Check if event has already started (if it's today and current time is past the start time)
$has_started = false;
if ($is_today && strtotime($current_time) >= strtotime($event['event_time'])) {
    $has_started = true;
}

// Check if event is ending soon (within 15 minutes of the end time)
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

// Check if user has already marked attendance and if it was verified
$has_marked_attendance = false;
$attendance_verified = false;
$check_attendance_sql = "SELECT id, verified FROM attendance WHERE event_id = ? AND user_id = ?";
if ($stmt = $conn->prepare($check_attendance_sql)) {
    $stmt->bind_param("ii", $event_id, $user_id);
    $stmt->execute();
    $check_result = $stmt->get_result();

    if ($check_result->num_rows > 0) {
        $attendance_row = $check_result->fetch_assoc();
        $has_marked_attendance = true;
        $attendance_verified = ($attendance_row['verified'] == 1);
    }

    $stmt->close();
}

$registration_closed = (!empty($event['registration_deadline']) && $event['registration_deadline'] < date('Y-m-d'));
$is_full = (!empty($event['max_participants']) && $registrations_count >= $event['max_participants']);

// Process registration form
// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {

    if ($is_registered) {
        $_SESSION['error_message'] = "You are already registered for this event.";
    } elseif ($is_past) {
        $_SESSION['error_message'] = "This event has already passed.";
    } elseif ($has_started) {
        $_SESSION['error_message'] = "Registration is closed as this event has already started.";
    } elseif ($registration_closed) {
        $_SESSION['error_message'] = "Registration for this event has closed.";
    } elseif ($is_full) {
        $_SESSION['error_message'] = "This event has reached its maximum capacity.";
    } else {
        $registration_successful = false;

        // Check if user has previously registered and then cancelled
        if ($has_previous_registration) {
            // Update the existing registration status
            $update_sql = "UPDATE event_registrations SET status = 'registered', registration_date = NOW() WHERE event_id = ? AND user_id = ?";

            if ($update_stmt = $conn->prepare($update_sql)) {
                $update_stmt->bind_param("ii", $event_id, $user_id);

                if ($update_stmt->execute()) {
                    // Create notification
                    $notif_sql = "INSERT INTO notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, 'registration_confirmation', ?)";

                    if ($notif_stmt = $conn->prepare($notif_sql)) {
                        $title = "Registration Confirmed";
                        $message = "Your registration for \"" . $event['title'] . "\" has been confirmed.";

                        $notif_stmt->bind_param("issi", $user_id, $title, $message, $event_id);
                        $notif_stmt->execute();
                        $notif_stmt->close();
                    }

                    $_SESSION['success_message'] = "You have successfully registered for this event!";
                    $is_registered = true;
                    $registration_date = date('Y-m-d H:i:s');
                    $registrations_count++;
                    $registration_successful = true;
                } else {
                    $_SESSION['error_message'] = "Error registering for event. Please try again.";
                }

                $update_stmt->close();
            }
        } else {
            // New registration - insert a new record
            $sql = "INSERT INTO event_registrations (event_id, user_id, registration_date) VALUES (?, ?, NOW())";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ii", $event_id, $user_id);

                if ($stmt->execute()) {
                    // Create notification
                    $notif_sql = "INSERT INTO notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, 'registration_confirmation', ?)";

                    if ($notif_stmt = $conn->prepare($notif_sql)) {
                        $title = "Registration Confirmed";
                        $message = "Your registration for \"" . $event['title'] . "\" has been confirmed.";

                        $notif_stmt->bind_param("issi", $user_id, $title, $message, $event_id);
                        $notif_stmt->execute();
                        $notif_stmt->close();
                    }

                    $_SESSION['success_message'] = "You have successfully registered for this event!";
                    $is_registered = true;
                    $registration_date = date('Y-m-d H:i:s');
                    $registrations_count++;
                    $registration_successful = true;
                } else {
                    $_SESSION['error_message'] = "Error registering for event. Please try again.";
                }

                $stmt->close();
            }
        }

        // If registration was successful, send confirmation email
        if ($registration_successful) {
            // Get user email
            $user_email = '';
            $user_name = '';
            $user_sql = "SELECT name, email FROM users WHERE id = ?";
            if ($user_stmt = $conn->prepare($user_sql)) {
                $user_stmt->bind_param("i", $user_id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();

                if ($user_row = $user_result->fetch_assoc()) {
                    $user_email = $user_row['email'];
                    $user_name = $user_row['name'];
                }

                $user_stmt->close();
            }

            // Prepare email data
            $email_data = [
                'user_id' => $user_id,
                'user_name' => $user_name,
                'user_email' => $user_email,
                'event_id' => $event_id,
                'event_title' => $event['title'],
                'event_date' => $event['event_date'],
                'event_time' => $event['event_time'],
                'event_venue' => $event['venue'],
                'event_organizer' => $event['organizer'],
                'registration_date' => $registration_date
            ];

            // Add end date and time if available
            if (!empty($event_end_date_formatted)) {
                $email_data['event_end_date'] = $event_end_date_formatted;
            }

            if (!empty($event_end_time_formatted)) {
                $email_data['event_end_time'] = $event_end_time_formatted;
            }

            // Send confirmation email
            include_once 'send_email_register.php';
            send_registration_email($email_data);
        }
    }

    // Redirect to prevent form resubmission
    header("Location: event_details.php?id=" . $event_id);
    exit;
}

// Process cancel registration form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel'])) {

    if (!$is_registered) {
        $_SESSION['error_message'] = "You are not registered for this event.";
    } elseif ($is_past) {
        $_SESSION['error_message'] = "Cannot cancel registration for past events.";
    } else {
        // Cancel registration
        $sql = "UPDATE event_registrations SET status = 'cancelled' WHERE event_id = ? AND user_id = ?";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $event_id, $user_id);

            if ($stmt->execute()) {
                // Create notification
                $notif_sql = "INSERT INTO notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, 'registration_cancelled', ?)";

                if ($notif_stmt = $conn->prepare($notif_sql)) {
                    $title = "Registration Cancelled";
                    $message = "Your registration for \"" . $event['title'] . "\" has been cancelled.";

                    $notif_stmt->bind_param("issi", $user_id, $title, $message, $event_id);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }

                $_SESSION['success_message'] = "Your registration has been cancelled.";
                $is_registered = false;
                $registration_date = null;
                $registrations_count--;
            } else {
                $_SESSION['error_message'] = "Error cancelling registration. Please try again.";
            }

            $stmt->close();
        }
    }

    // Redirect to prevent form resubmission
    header("Location: event_details.php?id=" . $event_id);
    exit;
}

// Format date and time
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

// Include header
include_once '../include/student_header.php';
?>

<div class="container mt-4">
    <!-- Event Header -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h1 class="mb-0">
                                <?php if ($event['is_featured']): ?>
                                    <span class="badge badge-warning"><i class="fas fa-star"></i> Featured</span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($event['title']); ?>
                            </h1>
                            <div class="d-flex flex-wrap mt-2">
                                <span
                                    class="badge badge-primary mr-2 p-2"><?php echo htmlspecialchars($event['category_name']); ?></span>
                                <?php foreach ($tags as $tag): ?>
                                    <span class="badge badge-info mr-2 p-2"><?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <a href="events.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Events
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Event Details -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <!-- Event Poster -->
                    <?php if (!empty($event['poster_url'])): ?>
                        <div class="text-center mb-4">
                            <img src="<?php echo '../' . htmlspecialchars($event['poster_url']); ?>" alt="Event Poster"
                                class="img-fluid rounded" style="max-height: 500px;">
                        </div>
                    <?php endif; ?>

                    <!-- Date, Time & Venue -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="far fa-calendar-alt mr-2 text-primary"></i> Date &
                                        Time</h5>
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
                                    <h5 class="card-title"><i class="fas fa-map-marker-alt mr-2 text-danger"></i> Venue
                                    </h5>
                                    <p class="card-text"><?php echo htmlspecialchars($event['venue']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-4">
                        <h4>Description</h4>
                        <div class="event-description">
                            <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                        </div>
                    </div>

                    <!-- Event Rules -->
                    <?php if (!empty($event['rules'])): ?>
                        <?php
                        // Extract Google Meet link from rules
                        $rules_text = $event['rules'];
                        $meet_link = '';
                        $rules_without_meet = $rules_text;

                        // Simple pattern to match meeting links
                        if (preg_match('/Meeting Link:\s*(https?:\/\/[^\s\n\r]+)/i', $rules_text, $matches)) {
                            $meet_link = trim($matches[1]);
                            // Remove the meeting link line from rules
                            $rules_without_meet = preg_replace('/Meeting Link:.*$/im', '', $rules_text);
                            $rules_without_meet = trim($rules_without_meet);
                        }
                        ?>

                        <!-- Google Meet Link (if found) -->
                        <?php if (!empty($meet_link)): ?>
                            <div class="alert alert-info mb-3">
                                <h5><i class="fas fa-video mr-2"></i>Meeting Link</h5>
                                <a href="<?php echo htmlspecialchars($meet_link); ?>" target="_blank" class="btn btn-primary">
                                    <i class="fas fa-external-link-alt mr-2"></i>Join Meeting
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="mb-4">
                            <h4>Rules & Guidelines</h4>


                            <!-- Rules (without meeting link) -->
                            <?php if (!empty($rules_without_meet)): ?>
                                <div class="event-rules bg-light p-3 rounded">
                                    <?php echo nl2br(htmlspecialchars($rules_without_meet)); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    <?php endif; ?>

                    <!-- Organizer Info -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-users mr-2 text-success"></i> Organizer</h5>
                                    <p class="card-text"><?php echo htmlspecialchars($event['organizer']); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-envelope mr-2 text-info"></i> Contact</h5>
                                    <p class="card-text"><?php echo htmlspecialchars($event['contact_info']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Brochure Download -->
                    <?php if (!empty($event['brochure_url'])): ?>
                        <div class="text-center mb-4">
                            <a href="<?php echo '../' . htmlspecialchars($event['brochure_url']); ?>" class="btn btn-info"
                                target="_blank">
                                <i class="fas fa-file-pdf mr-2"></i> Download Brochure/Rulebook
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Registration Sidebar -->
        <div class="col-lg-4">
            <!-- Registration Status -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Registration Status</h5>
                </div>
                <div class="card-body">
                    <?php if ($is_registered): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i> You are registered for this event!
                            <p class="mb-0 small mt-1">Registered on:
                                <?php echo date("F j, Y, g:i a", strtotime($registration_date)); ?>
                            </p>
                        </div>

                        <?php if (!$is_past): ?>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $event_id); ?>"
                                method="post" onsubmit="return confirm('Are you sure you want to cancel your registration?');">
                                <button type="submit" name="cancel" class="btn btn-outline-danger btn-block">
                                    <i class="fas fa-times-circle mr-2"></i> Cancel Registration
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($is_past || $has_started): ?>
                        <div class="alert alert-secondary">
                            <i class="fas fa-hourglass-end mr-2"></i>
                            <?php echo $is_past ? "This event has already passed." : "This event has already started."; ?>
                        </div>
                    <?php elseif ($registration_closed): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-times-circle mr-2"></i> Registration for this event has closed.
                        </div>
                    <?php elseif ($is_full): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-users-slash mr-2"></i> This event has reached its maximum capacity.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i> Registration is open for this event!
                        </div>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $event_id); ?>"
                            method="post">
                            <button type="submit" name="register" class="btn btn-primary btn-block btn-lg">
                                <i class="fas fa-user-plus mr-2"></i> Register Now
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>


            <!-- Feedback Button -->
            <?php
            // Check if event has ended and user has attended
            $has_attended = false;
            $check_attendance_query = "SELECT id FROM attendance WHERE event_id = ? AND user_id = ? AND verified = 1";
            if ($check_stmt = $conn->prepare($check_attendance_query)) {
                $check_stmt->bind_param("ii", $event_id, $user_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $has_attended = ($check_result->num_rows > 0);
                $check_stmt->close();
            }

            // Check if the event has ended
            $event_has_ended = false;
            if (!empty($event['event_end_date']) && !empty($event['event_end_time'])) {
                // If end date/time is specified
                $end_datetime = strtotime($event['event_end_date'] . ' ' . $event['event_end_time']);
                $event_has_ended = (time() > $end_datetime);
            } else if ($is_past) {
                // If no end date/time, use event date (if it's past)
                $event_has_ended = true;
            }

            // Check if user has already submitted feedback
            $has_submitted_feedback = false;
            $feedback_query = "SELECT id FROM feedback WHERE event_id = ? AND user_id = ?";
            if ($feedback_stmt = $conn->prepare($feedback_query)) {
                $feedback_stmt->bind_param("ii", $event_id, $user_id);
                $feedback_stmt->execute();
                $feedback_result = $feedback_stmt->get_result();
                $has_submitted_feedback = ($feedback_result->num_rows > 0);
                $feedback_stmt->close();
            }

            // Show feedback button if event has ended, user attended, and hasn't already submitted feedback
            if ($event_has_ended && $has_attended && !$has_submitted_feedback):
                ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Event Feedback</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-comment-alt mr-2"></i> We value your opinion! Please share your feedback on
                            this event.
                        </div>
                        <a href="feedback.php?event=<?php echo $event_id; ?>" class="btn btn-success btn-block">
                            <i class="fas fa-star mr-2"></i> Submit Feedback
                        </a>
                    </div>
                </div>
            <?php elseif ($has_submitted_feedback): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Event Feedback</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle mr-2"></i> Thank you for submitting your feedback!
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Attendance QR Code Scanner -->
            <?php if ($is_registered && !$is_past): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Attendance</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($has_marked_attendance): ?>
                            <?php if ($attendance_verified): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle mr-2"></i> Your attendance has been verified for this event!
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-clock mr-2"></i> You have marked your attendance. Waiting for verification.
                                </div>
                            <?php endif; ?>
                        <?php elseif ($is_ending_soon): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle mr-2"></i> This event is ending soon. Please mark your
                                attendance.
                            </div>
                            <button id="scanQrBtn" class="btn btn-primary btn-block btn-lg">
                                <i class="fas fa-qrcode mr-2"></i> Scan QR Code for Attendance
                            </button>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i> Attendance can be marked 15 minutes before the event
                                ends.
                            </div>
                            <button id="scanQrBtn" class="btn btn-outline-primary btn-block">
                                <i class="fas fa-qrcode mr-2"></i> Scan QR Code
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Event Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Event Information</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-users mr-2 text-primary"></i> Registrations</span>
                            <span class="badge badge-primary badge-pill"><?php echo $registrations_count; ?></span>
                        </li>
                        <?php if (!empty($event['max_participants'])): ?>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user-friends mr-2 text-info"></i> Maximum Participants</span>
                                <span class="badge badge-info badge-pill"><?php echo $event['max_participants']; ?></span>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-ticket-alt mr-2 text-success"></i> Available Spots</span>
                                <span
                                    class="badge <?php echo ($event['max_participants'] - $registrations_count) > 0 ? 'badge-success' : 'badge-danger'; ?> badge-pill">
                                    <?php echo max(0, $event['max_participants'] - $registrations_count); ?>
                                </span>
                            </li>
                        <?php endif; ?>
                        <li class="list-group-item px-0">
                            <i class="fas fa-clock mr-2 text-warning"></i> Registration Deadline<br>
                            <strong><?php echo $registration_deadline_formatted; ?></strong>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Add to Calendar -->

        </div>
    </div>
</div>

<!-- QR Code Scanner Modal -->
<div class="modal fade" id="qrScannerModal" tabindex="-1" role="dialog" aria-labelledby="qrScannerModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrScannerModalLabel">Scan QR Code for Attendance</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <div id="qr-reader"></div>
                <div id="qr-result" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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

<!-- HTML5 QR Code Scanner Library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
    $(document).ready(function () {
        // QR Code Scanner
        let html5QrCode;

        $('#scanQrBtn').click(function () {
            // Show modal
            $('#qrScannerModal').modal('show');

            // Initialize scanner
            html5QrCode = new Html5Qrcode("qr-reader");

            // Set the result div to loading
            $('#qr-result').html('<div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>');

            // Options
            const qrConfig = { fps: 10, qrbox: 250 };

            // Start scanner
            html5QrCode.start(
                { facingMode: "environment" }, // Use the back camera
                qrConfig,
                onScanSuccess,
                onScanError
            ).catch(err => {
                // Handle startup errors
                console.error(`QR Code scanning failed to start: ${err}`);
                $('#qr-result').html(`<div class="alert alert-danger">Failed to start camera: ${err}</div>`);
            });
        });

        // Handle QR Code scan success
        function onScanSuccess(decodedText, decodedResult) {
            // Stop scanner
            html5QrCode.stop();

            // Show loading indicator
            $('#qr-result').html('<div class="spinner-border text-primary" role="status"><span class="sr-only">Processing...</span></div>');

            // Process the URL
            if (decodedText.includes('mark_attendance.php')) {
                // Success! We've found a valid attendance URL
                $('#qr-result').html('<div class="alert alert-success">QR Code detected! Redirecting to attendance page...</div>');

                // Navigate to the URL after a short delay
                setTimeout(() => {
                    window.location.href = decodedText;
                }, 1000);
            } else {
                // Invalid QR code
                $('#qr-result').html('<div class="alert alert-danger">Invalid QR code. Please scan the attendance QR code shown by the event organizer.</div>');
            }
        }

        // Handle scan errors
        function onScanError(err) {
            // We don't need to show errors during normal operation
            console.warn(`QR scan error: ${err}`);
        }

        // Clean up when modal is closed
        $('#qrScannerModal').on('hidden.bs.modal', function () {
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop().catch(err => {
                    console.error(`Failed to stop QR Code scanner: ${err}`);
                });
            }
        });
    });
</script>
</body>

</html>