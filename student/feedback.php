<?php
/**
 * Event Feedback
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Event Feedback";

// Include header
include_once '../include/student_header.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $_SESSION['error_message'] = "You must be logged in to provide feedback.";
    header("location: login.php");
    exit;
}

$user_id = $_SESSION["id"];
$event = null;
$can_submit_feedback = false;
$already_submitted = false;

// Check if event ID is provided
if (!isset($_GET['event']) || empty($_GET['event'])) {
    $_SESSION['error_message'] = "No event specified.";
    header("location: events.php");
    exit;
}

$event_id = intval($_GET['event']);

// Check if the event exists and user has attended it
$event_sql = "SELECT e.*, c.name as category_name, 
              (SELECT COUNT(*) FROM feedback WHERE event_id = e.id AND user_id = ?) as has_feedback
              FROM events e
              JOIN event_categories c ON e.category_id = c.id
              JOIN attendance a ON e.id = a.event_id AND a.user_id = ?
              WHERE e.id = ? AND a.verified = 1";

if ($stmt = $conn->prepare($event_sql)) {
    $stmt->bind_param("iii", $user_id, $user_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $event = $result->fetch_assoc();
        
        // Check if feedback can be submitted (within 1 week of the event)
        $current_date = date('Y-m-d');
        $event_date = $event['event_date'];
        $one_week_after = date('Y-m-d', strtotime($event_date . ' + 7 days'));
        
        // Check if we're still within the 1 week feedback window
        $in_feedback_window = ($current_date <= $one_week_after);
        
        // Check if user has already provided feedback
        $already_submitted = ($event['has_feedback'] > 0);
        
        // User can submit feedback if within window and hasn't submitted yet
        $can_submit_feedback = $in_feedback_window && !$already_submitted;
    } else {
        $_SESSION['error_message'] = "You cannot provide feedback for this event. Either the event does not exist or you did not attend it.";
        header("location: events.php");
        exit;
    }
    
    $stmt->close();
}

// Process feedback submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $can_submit_feedback) {
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : "";
    
    // Validate input
    $error = false;
    
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error_message'] = "Please provide a valid rating (1-5).";
        $error = true;
    }
    
    if (empty($comments)) {
        $_SESSION['error_message'] = "Please provide some comments about the event.";
        $error = true;
    }
    
    if (!$error) {
        // Insert feedback
        $sql = "INSERT INTO feedback (event_id, user_id, rating, comments, submission_date) 
                VALUES (?, ?, ?, ?, NOW())";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("iiis", $event_id, $user_id, $rating, $comments);
            
            if ($stmt->execute()) {
                // Create notification for the user that feedback has been submitted
                $notification_title = "Feedback Submitted";
                $notification_message = "Thank you for submitting your feedback for \"" . $event['title'] . "\".";
                $notification_type = "feedback_reminder"; // Using the existing type in your enum
                
                $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at) 
                                    VALUES (?, ?, ?, ?, ?, 0, NOW())";
                
                if ($notification_stmt = $conn->prepare($notification_sql)) {
                    $notification_stmt->bind_param("isssi", $user_id, $notification_title, $notification_message, $notification_type, $event_id);
                    $notification_stmt->execute();
                    $notification_stmt->close();
                }
                
                $_SESSION['success_message'] = "Thank you for your feedback!";
                header("location: event_details.php?id=" . $event_id);
                exit;
            } else {
                $_SESSION['error_message'] = "Something went wrong. Please try again.";
            }
            
            $stmt->close();
        }
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h3 mb-0">Event Feedback</h1>
                        <a href="event_details.php?id=<?php echo $event_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Event
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($already_submitted): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-2"></i> You have already submitted feedback for this event.
                </div>
                <div class="text-center mt-4">
                    <a href="events.php" class="btn btn-primary">
                        <i class="fas fa-calendar mr-2"></i> Back to Events
                    </a>
                </div>
            </div>
        </div>
    <?php elseif (!$can_submit_feedback): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Feedback can only be submitted within 1 week of attending the event.
                </div>
                <div class="text-center mt-4">
                    <a href="events.php" class="btn btn-primary">
                        <i class="fas fa-calendar mr-2"></i> Back to Events
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Your Feedback for "<?php echo htmlspecialchars($event['title']); ?>"</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?event=" . $event_id); ?>">
                            <!-- Rating -->
                            <div class="form-group">
                                <label for="rating" class="font-weight-bold">Rating <span class="text-danger">*</span></label>
                                <div class="rating-container">
                                    <div class="rating">
                                        <input type="radio" id="star5" name="rating" value="5" required />
                                        <label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
                                        <input type="radio" id="star4" name="rating" value="4" />
                                        <label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
                                        <input type="radio" id="star3" name="rating" value="3" />
                                        <label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
                                        <input type="radio" id="star2" name="rating" value="2" />
                                        <label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
                                        <input type="radio" id="star1" name="rating" value="1" />
                                        <label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">Please rate your overall experience (1 star = Poor, 5 stars = Excellent)</small>
                                </div>
                            </div>

                            <!-- Comments -->
                            <div class="form-group mt-4">
                                <label for="comments" class="font-weight-bold">Comments <span class="text-danger">*</span></label>
                                <textarea name="comments" id="comments" rows="5" class="form-control" required></textarea>
                                <small class="form-text text-muted">Please share your thoughts, suggestions, or any feedback about the event.</small>
                            </div>

                            <!-- Submit Button -->
                            <div class="form-group mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane mr-2"></i> Submit Feedback
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Event Details</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Event:</strong> <?php echo htmlspecialchars($event['title']); ?></p>
                        <p class="mb-2"><strong>Date:</strong> <?php echo date("F j, Y", strtotime($event['event_date'])); ?></p>
                        <p class="mb-2"><strong>Time:</strong> <?php echo date("h:i A", strtotime($event['event_time'])); ?></p>
                        <p class="mb-2"><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
                        <p class="mb-0"><strong>Category:</strong> <?php echo htmlspecialchars($event['category_name']); ?></p>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle mr-2"></i> Your feedback helps us improve future events. Thank you for taking the time to share your thoughts!
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
/* Rating Star Styles */
.rating-container {
    display: flex;
    justify-content: flex-start;
    margin-top: 10px;
}

.rating {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}

.rating input {
    display: none;
}

.rating label {
    cursor: pointer;
    width: 40px;
    height: 40px;
    margin: 0;
    padding: 0;
    font-size: 30px;
    color: #ddd;
}

.rating label i {
    transition: all 0.2s ease;
}

.rating input:checked ~ label i,
.rating label:hover ~ label i,
.rating label:hover i {
    color: #f8ce0b;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize stars
    const stars = document.querySelectorAll('.rating input');
    const ratingValue = document.querySelector('.rating-value');
    
    stars.forEach(star => {
        star.addEventListener('change', function() {
            if (ratingValue) {
                ratingValue.textContent = this.value;
            }
        });
    });
});
</script>

<?php include_once '../include/student_footer.php'; ?>