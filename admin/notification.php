<?php
/**
 * Admin Notifications Management
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Hide only warnings and notices, but still show fatal errors
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 1);

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Notifications Management";

// Include header
include_once '../include/admin_header.php';

// Check if admin is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: ../login.php");
    exit;
}

// Function to send email
// Function to send email using SMTP
function sendEmail($to, $subject, $message) {
    // Include PHPMailer
    require '../vendor/autoload.php';
    
    // Create a new PHPMailer instance
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'sysmail999@gmail.com';
        $mail->Password = 'lfqd yfxx wxtf zexw'; // The password or app password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('sysmail999@gmail.com', 'EventSphere@UPSI');
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Process notification sending form
$notification_sent = false;
$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_notification'])) {
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $notification_type = isset($_POST['notification_type']) ? trim($_POST['notification_type']) : '';
    $notification_title = isset($_POST['notification_title']) ? trim($_POST['notification_title']) : '';
    $notification_message = isset($_POST['notification_message']) ? trim($_POST['notification_message']) : '';
    $send_email = isset($_POST['send_email']) ? true : false;
    
    // Validate input
    if (empty($event_id) || empty($notification_type) || empty($notification_title) || empty($notification_message)) {
        $error_message = "All fields are required.";
    } else {
        // Get event details
        $event = null;
        $event_sql = "SELECT e.*, c.name as category_name FROM events e 
                     JOIN event_categories c ON e.category_id = c.id 
                     WHERE e.id = ?";
        
        if ($stmt = $conn->prepare($event_sql)) {
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $event_result = $stmt->get_result();
            
            if ($event_result->num_rows > 0) {
                $event = $event_result->fetch_assoc();
            } else {
                $error_message = "Event not found.";
            }
            
            $stmt->close();
        }
        
        if ($event) {
            // Get registered users for this event
            $users = array();
            $users_sql = "SELECT u.id, u.name, u.email FROM users u 
                         JOIN event_registrations r ON u.id = r.user_id 
                         WHERE r.event_id = ? AND r.status = 'registered'";
            
            if ($stmt = $conn->prepare($users_sql)) {
                $stmt->bind_param("i", $event_id);
                $stmt->execute();
                $users_result = $stmt->get_result();
                
                while ($user_row = $users_result->fetch_assoc()) {
                    $users[] = $user_row;
                }
                
                $stmt->close();
            }
            
            if (count($users) > 0) {
                // Insert notification for each user and send email if selected
                $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at) 
                                   VALUES (?, ?, ?, ?, ?, 0, NOW())";
                
                // Prepare email template
                $email_subject = "EventSphere@UPSI: " . $notification_title;
                $email_template = '
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #007bff; color: white; padding: 10px 20px; text-align: center; }
                        .content { padding: 20px; background-color: #f8f9fa; }
                        .event-details { background-color: white; padding: 15px; margin-top: 20px; border-left: 4px solid #007bff; }
                        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6c757d; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h2>EventSphere@UPSI</h2>
                        </div>
                        <div class="content">
                            <h3>{{TITLE}}</h3>
                            <p>Hello {{NAME}},</p>
                            <p>{{MESSAGE}}</p>
                            
                            <div class="event-details">
                                <h4>{{EVENT_TITLE}}</h4>
                                <p><strong>Date:</strong> {{EVENT_DATE}}</p>
                                <p><strong>Time:</strong> {{EVENT_TIME}}</p>
                                <p><strong>Venue:</strong> {{EVENT_VENUE}}</p>
                                <p><strong>Category:</strong> {{EVENT_CATEGORY}}</p>
                            </div>
                            
                            <p>We look forward to seeing you there!</p>
                        </div>
                        <div class="footer">
                            <p>This is an automated message. Please do not reply to this email.</p>
                            <p>&copy; 2025 EventSphere@UPSI. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>';
                
                $successful_notifications = 0;
                $successful_emails = 0;
                
                foreach ($users as $user) {
                    // Insert notification in database
                    if ($stmt = $conn->prepare($notification_sql)) {
                        $stmt->bind_param("isssi", $user['id'], $notification_title, $notification_message, $notification_type, $event_id);
                        
                        if ($stmt->execute()) {
                            $successful_notifications++;
                        }
                        
                        $stmt->close();
                    }
                    
                    // Send email if selected
                    if ($send_email) {
                        $personalized_email = str_replace(
                            [
                                '{{TITLE}}', 
                                '{{NAME}}', 
                                '{{MESSAGE}}', 
                                '{{EVENT_TITLE}}', 
                                '{{EVENT_DATE}}', 
                                '{{EVENT_TIME}}', 
                                '{{EVENT_VENUE}}', 
                                '{{EVENT_CATEGORY}}'
                            ],
                            [
                                htmlspecialchars($notification_title),
                                htmlspecialchars($user['name']),
                                nl2br(htmlspecialchars($notification_message)),
                                htmlspecialchars($event['title']),
                                date("F j, Y", strtotime($event['event_date'])),
                                date("h:i A", strtotime($event['event_time'])),
                                htmlspecialchars($event['venue']),
                                htmlspecialchars($event['category_name'])
                            ],
                            $email_template
                        );
                        
                        if (sendEmail($user['email'], $email_subject, $personalized_email)) {
                            $successful_emails++;
                        }
                    }
                }
                
                // Set success message
                $notification_sent = true;
                $success_message = "Notifications sent to $successful_notifications users.";
                
                if ($send_email) {
                    $success_message .= " Emails sent to $successful_emails users.";
                }
            } else {
                $error_message = "No registered users found for this event.";
            }
        }
    }
}

// Get upcoming events for the dropdown
$upcoming_events = array();
$events_sql = "SELECT id, title, event_date, event_time 
               FROM events 
               WHERE event_date >= CURDATE() 
               ORDER BY event_date ASC, event_time ASC";
$events_result = $conn->query($events_sql);

if ($events_result && $events_result->num_rows > 0) {
    while ($row = $events_result->fetch_assoc()) {
        $upcoming_events[] = $row;
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h3 mb-0">Notifications Management</h1>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error_message; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if ($notification_sent && !empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i> <?php echo $success_message; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Notification Form -->
        <div class="col-md-8 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Send Event Notifications</h4>
                </div>
                <div class="card-body">
                    <?php if (count($upcoming_events) > 0): ?>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <!-- Event Selection -->
                            <div class="form-group">
                                <label for="event_id">Select Event <span class="text-danger">*</span></label>
                                <select name="event_id" id="event_id" class="form-control" required>
                                    <option value="">-- Select an Event --</option>
                                    <?php foreach ($upcoming_events as $event): ?>
                                        <option value="<?php echo $event['id']; ?>">
                                            <?php echo htmlspecialchars($event['title']); ?> 
                                            (<?php echo date("d M Y, h:i A", strtotime($event['event_date'] . ' ' . $event['event_time'])); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">
                                    Select the event for which you want to send notifications.
                                </small>
                            </div>

                            <!-- Notification Type -->
                            <div class="form-group">
                                <label for="notification_type">Notification Type <span class="text-danger">*</span></label>
                                <select name="notification_type" id="notification_type" class="form-control" required>
                                    <option value="event_reminder">Event Reminder</option>
                                    <option value="location_change">Location Change</option>
                                    <option value="time_change">Time Change</option>
                                    <option value="event_cancelled">Event Cancelled</option>
                                    <option value="event_update">Other Update</option>
                                </select>
                            </div>

                            <!-- Notification Title -->
                            <div class="form-group">
                                <label for="notification_title">Notification Title <span class="text-danger">*</span></label>
                                <input type="text" name="notification_title" id="notification_title" class="form-control" required>
                                <small class="form-text text-muted">
                                    Enter a clear, concise title for the notification.
                                </small>
                            </div>

                            <!-- Notification Message -->
                            <div class="form-group">
                                <label for="notification_message">Notification Message <span class="text-danger">*</span></label>
                                <textarea name="notification_message" id="notification_message" rows="5" class="form-control" required></textarea>
                                <small class="form-text text-muted">
                                    Enter the detailed message to be sent to registered users.
                                </small>
                            </div>

                            <!-- Send Email Option -->
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="send_email" name="send_email" checked>
                                    <label class="custom-control-label" for="send_email">Also send via email</label>
                                </div>
                                <small class="form-text text-muted">
                                    If checked, notifications will also be sent to users' email addresses.
                                </small>
                            </div>

                            <!-- Submit Button -->
                            <div class="form-group mb-0">
                                <button type="submit" name="send_notification" class="btn btn-primary">
                                    <i class="fas fa-paper-plane mr-2"></i> Send Notification
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">No upcoming events found</h4>
                            <p>There are no upcoming events for which notifications can be sent.</p>
                            <a href="create_event.php" class="btn btn-primary mt-2">
                                <i class="fas fa-plus-circle mr-2"></i> Create New Event
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notification Type Guide -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Notification Guide</h4>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h5 class="mb-3">Notification Types</h5>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item px-0">
                                <div class="d-flex">
                                    <div class="mr-3">
                                        <span class="badge badge-primary p-2">
                                            <i class="fas fa-bell"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Event Reminder</h6>
                                        <small class="text-muted">Send a reminder about an upcoming event.</small>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item px-0">
                                <div class="d-flex">
                                    <div class="mr-3">
                                        <span class="badge badge-warning p-2">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Location Change</h6>
                                        <small class="text-muted">Notify users about a venue change.</small>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item px-0">
                                <div class="d-flex">
                                    <div class="mr-3">
                                        <span class="badge badge-info p-2">
                                            <i class="fas fa-clock"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Time Change</h6>
                                        <small class="text-muted">Notify users about a time change.</small>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item px-0">
                                <div class="d-flex">
                                    <div class="mr-3">
                                        <span class="badge badge-danger p-2">
                                            <i class="fas fa-ban"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Event Cancelled</h6>
                                        <small class="text-muted">Inform users that an event is cancelled.</small>
                                    </div>
                                </div>
                            </li>
                            <li class="list-group-item px-0">
                                <div class="d-flex">
                                    <div class="mr-3">
                                        <span class="badge badge-secondary p-2">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Other Update</h6>
                                        <small class="text-muted">Send any other important updates about the event.</small>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h5 class="mb-3">Tips for Effective Notifications</h5>
                        <ul class="mb-0">
                            <li class="mb-2">Keep titles short and clear</li>
                            <li class="mb-2">Provide all necessary details in the message</li>
                            <li class="mb-2">Send reminders at least 24 hours before the event</li>
                            <li class="mb-2">For urgent updates (like cancellations), always send via email</li>
                            <li>Personalize messages to increase engagement</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scheduled Notifications System -->
    
</div>

<!-- Automation Info Modal -->
<div class="modal fade" id="automationInfoModal" tabindex="-1" aria-labelledby="automationInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="automationInfoModalLabel">How Automatic Reminders Work</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <h5 class="mb-3">Automatic Reminder System</h5>
                    <p>The system automatically sends notifications to users at strategic times before events:</p>
                    <ul>
                        <li><strong>24-hour reminder:</strong> Helps users plan their schedule</li>
                        <li><strong>1-hour reminder:</strong> Just-in-time reminder to increase attendance</li>
                    </ul>
                </div>
                
                <div class="mb-4">
                    <h5 class="mb-3">Technical Implementation</h5>
                    <p>The reminder system works through a cron job that runs the script <code>send_event_reminders.php</code> at regular intervals. This script:</p>
                    <ol>
                        <li>Identifies upcoming events within the next 24 hours and 1 hour</li>
                        <li>Gets lists of registered users for each event</li>
                        <li>Generates and sends appropriate notifications</li>
                        <li>Updates the database to prevent duplicate reminders</li>
                    </ol>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Important:</strong> The cron job must be set up by your system administrator for automatic reminders to work.
                </div>
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

<script>
$(document).ready(function() {
    // Dynamic title based on notification type
    $('#notification_type').change(function() {
        const type = $(this).val();
        let title = '';
        
        switch(type) {
            case 'event_reminder':
                title = 'Reminder: Your Upcoming Event';
                break;
            case 'location_change':
                title = 'Important: Event Location Changed';
                break;
            case 'time_change':
                title = 'Important: Event Time Changed';
                break;
            case 'event_cancelled':
                title = 'Important: Event Cancelled';
                break;
            case 'event_update':
                title = 'Event Update: Important Information';
                break;
        }
        
        $('#notification_title').val(title);
    });
    
    // Trigger change on page load to set initial value
    $('#notification_type').trigger('change');
});
</script>
</body>
</html>