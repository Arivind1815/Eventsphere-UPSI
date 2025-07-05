<?php
/**
 * Edit Event
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Edit Event";

// Include header
include_once '../include/admin_header.php';

// Check if event ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "No event specified.";
    header("location: events.php");
    exit;
}

$event_id = intval($_GET['id']);

// Check if event exists and is not past
$check_sql = "SELECT * FROM events WHERE id = ?";
if ($stmt = $conn->prepare($check_sql)) {
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows != 1) {
        $_SESSION['error_message'] = "Event not found.";
        header("location: events.php");
        exit;
    }
    
    // Get event data
    $event = $result->fetch_assoc();
    
    // Check if event is past
    if ($event['event_date'] < date('Y-m-d')) {
        $_SESSION['error_message'] = "Cannot edit past events.";
        header("location: events.php");
        exit;
    }
    
    $stmt->close();
}

// Initialize variables with event data
$title = $event['title'];
$description = $event['description'];
$event_date = $event['event_date'];
// Initialize the new event_end_date field
$event_end_date = isset($event['event_end_date']) ? $event['event_end_date'] : $event['event_date']; // Default to event_date if null
$event_time = $event['event_time'];
// Initialize the new event_end_time field
$event_end_time = isset($event['event_end_time']) ? $event['event_end_time'] : ''; // Default to empty if null
$venue = $event['venue'];
$organizer = $event['organizer'];
$contact_info = $event['contact_info'];
$max_participants = $event['max_participants'];
$registration_deadline = $event['registration_deadline'];
$rules = $event['rules'];
$category_id = $event['category_id'];
$is_featured = $event['is_featured'];
$poster_url = $event['poster_url'];
$brochure_url = $event['brochure_url'];

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

// Error variables
$title_err = $description_err = $event_date_err = $event_time_err = $venue_err = "";
$organizer_err = $contact_info_err = $category_id_err = "";
// Add new error variables for end date and time
$event_end_date_err = $event_end_time_err = "";

// Get all categories
$categories = [];
$categories_sql = "SELECT id, name FROM event_categories ORDER BY name";
$categories_result = $conn->query($categories_sql);

if ($categories_result) {
    while ($category_row = $categories_result->fetch_assoc()) {
        $categories[] = $category_row;
    }
}

// Get all tags
$all_tags = [];
$all_tags_sql = "SELECT DISTINCT tag FROM event_tags ORDER BY tag";
$all_tags_result = $conn->query($all_tags_sql);

if ($all_tags_result) {
    while ($tag_row = $all_tags_result->fetch_assoc()) {
        $all_tags[] = $tag_row['tag'];
    }
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate title
    if (empty(trim($_POST["title"]))) {
        $title_err = "Please enter event title.";
    } else {
        $title = trim($_POST["title"]);
    }
    
    // Validate description
    if (empty(trim($_POST["description"]))) {
        $description_err = "Please enter event description.";
    } else {
        $description = trim($_POST["description"]);
    }
    
    // Validate event date
    if (empty(trim($_POST["event_date"]))) {
        $event_date_err = "Please enter event date.";
    } else {
        $event_date = trim($_POST["event_date"]);
        
        // Check if date is valid and in the future
        $event_date_obj = new DateTime($event_date);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        if ($event_date_obj < $today) {
            $event_date_err = "Event date must be in the future.";
        }
    }
    
    // Validate event end date
    if (empty(trim($_POST["event_end_date"]))) {
        $event_end_date_err = "Please enter event end date.";
    } else {
        $event_end_date = trim($_POST["event_end_date"]);

        // Check if end date is valid and not before start date
        if (!empty($event_date)) {
            $event_date_obj = new DateTime($event_date);
            $event_end_date_obj = new DateTime($event_end_date);

            if ($event_end_date_obj < $event_date_obj) {
                $event_end_date_err = "End date cannot be before start date.";
            }
        }
    }
    
    // Validate event time
    if (empty(trim($_POST["event_time"]))) {
        $event_time_err = "Please enter event time.";
    } else {
        $event_time = trim($_POST["event_time"]);
    }
    
    // Validate event end time
    if (empty(trim($_POST["event_end_time"]))) {
        $event_end_time_err = "Please enter event end time.";
    } else {
        $event_end_time = trim($_POST["event_end_time"]);
        
        // Check if end time is valid when start/end dates are the same
        if (!empty($event_date) && !empty($event_end_date) && !empty($event_time)) {
            if ($event_date == $event_end_date) {
                $start_time = strtotime($event_time);
                $end_time = strtotime($event_end_time);
                
                if ($end_time <= $start_time) {
                    $event_end_time_err = "End time must be after start time on the same day.";
                }
            }
        }
    }
    
    // Validate venue
    if (empty(trim($_POST["venue"]))) {
        $venue_err = "Please enter event venue.";
    } else {
        $venue = trim($_POST["venue"]);
    }
    
    // Validate organizer
    if (empty(trim($_POST["organizer"]))) {
        $organizer_err = "Please enter event organizer.";
    } else {
        $organizer = trim($_POST["organizer"]);
    }
    
    // Validate contact info
    if (empty(trim($_POST["contact_info"]))) {
        $contact_info_err = "Please enter contact information.";
    } else {
        $contact_info = trim($_POST["contact_info"]);
    }
    
    // Validate category
    if (empty($_POST["category_id"]) || intval($_POST["category_id"]) <= 0) {
        $category_id_err = "Please select a category.";
    } else {
        $category_id = intval($_POST["category_id"]);
    }
    
    // Get other form data
    $max_participants = !empty($_POST["max_participants"]) ? intval($_POST["max_participants"]) : null;
    $is_featured = isset($_POST["is_featured"]) ? 1 : 0;
    $registration_deadline = !empty($_POST["registration_deadline"]) ? trim($_POST["registration_deadline"]) : null;
    $rules = !empty($_POST["rules"]) ? trim($_POST["rules"]) : null;
    
    // Get tags
    $tags = isset($_POST["tags"]) && is_array($_POST["tags"]) ? $_POST["tags"] : [];
    
    // Handle new tag input
    if (!empty($_POST["new_tags"])) {
        $new_tags = explode(",", $_POST["new_tags"]);
        foreach ($new_tags as $new_tag) {
            $new_tag = trim($new_tag);
            if (!empty($new_tag) && !in_array($new_tag, $tags)) {
                $tags[] = $new_tag;
            }
        }
    }
    
    // Check if no errors
    if (empty($title_err) && empty($description_err) && empty($event_date_err) && 
        empty($event_time_err) && empty($venue_err) && empty($organizer_err) && 
        empty($contact_info_err) && empty($category_id_err) &&
        empty($event_end_date_err) && empty($event_end_time_err)) {
        
        // Upload poster if provided
        if (isset($_FILES['poster']) && $_FILES['poster']['error'] == 0) {
            $upload_dir = "../uploads/posters/";
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['poster']['name']);
            $file_path = $upload_dir . $file_name;
            
            // Check file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['poster']['type'], $allowed_types)) {
                if (move_uploaded_file($_FILES['poster']['tmp_name'], $file_path)) {
                    // Delete old poster if exists and different
                    if (!empty($poster_url) && file_exists("../" . $poster_url)) {
                        unlink("../" . $poster_url);
                    }
                    
                    $poster_url = "uploads/posters/" . $file_name;
                }
            }
        }
        
        // Upload brochure if provided
        if (isset($_FILES['brochure']) && $_FILES['brochure']['error'] == 0) {
            $upload_dir = "../uploads/brochures/";
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['brochure']['name']);
            $file_path = $upload_dir . $file_name;
            
            // Check file type
            $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (in_array($_FILES['brochure']['type'], $allowed_types)) {
                if (move_uploaded_file($_FILES['brochure']['tmp_name'], $file_path)) {
                    // Delete old brochure if exists and different
                    if (!empty($brochure_url) && file_exists("../" . $brochure_url)) {
                        unlink("../" . $brochure_url);
                    }
                    
                    $brochure_url = "uploads/brochures/" . $file_name;
                }
            }
        }
        
        // Update event in database - updated SQL query to include event_end_date and event_end_time
        $sql = "UPDATE events SET 
                title = ?, 
                description = ?, 
                event_date = ?, 
                event_end_date = ?,
                event_time = ?, 
                event_end_time = ?,
                venue = ?, 
                category_id = ?, 
                organizer = ?, 
                contact_info = ?, 
                max_participants = ?, 
                is_featured = ?, 
                registration_deadline = ?, 
                poster_url = ?, 
                brochure_url = ?, 
                rules = ?,
                updated_at = NOW()  
                WHERE id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Prepare variables for binding
            $max_participants_param = $max_participants;
            $registration_deadline_param = $registration_deadline;
            $poster_url_param = $poster_url;
            $brochure_url_param = $brochure_url;
            $rules_param = $rules;
            
            // Now bind parameters (added 2 new parameters for end date and time)
            $stmt->bind_param("sssssssississsssi", 
                $title, 
                $description, 
                $event_date,
                $event_end_date,  // New parameter
                $event_time,
                $event_end_time,  // New parameter
                $venue, 
                $category_id, 
                $organizer, 
                $contact_info, 
                $max_participants_param, 
                $is_featured, 
                $registration_deadline_param, 
                $poster_url_param, 
                $brochure_url_param, 
                $rules_param,
                $event_id
            );
            
            if ($stmt->execute()) {
                // Delete all existing tags
                $delete_tags_sql = "DELETE FROM event_tags WHERE event_id = ?";
                if ($delete_stmt = $conn->prepare($delete_tags_sql)) {
                    $delete_stmt->bind_param("i", $event_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                }
                
                // Insert tags
                if (!empty($tags)) {
                    foreach ($tags as $tag) {
                        $tag = trim($tag);
                        if (!empty($tag)) {
                            $tag_sql = "INSERT INTO event_tags (event_id, tag) VALUES (?, ?)";
                            if ($tag_stmt = $conn->prepare($tag_sql)) {
                                $tag_stmt->bind_param("is", $event_id, $tag);
                                $tag_stmt->execute();
                                $tag_stmt->close();
                            }
                        }
                    }
                }
                
                // Create notifications for registered users
                $notification_sql = "INSERT INTO notifications 
                                   (user_id, title, message, type, related_id) 
                                   SELECT user_id, 'Event Updated', ?, 'event_update', ? 
                                   FROM event_registrations 
                                   WHERE event_id = ? AND status = 'registered'";
                
                if ($notif_stmt = $conn->prepare($notification_sql)) {
                    $message = "The event \"" . $title . "\" has been updated. Please check the details.";
                    
                    $notif_stmt->bind_param("sii", $message, $event_id, $event_id);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                }
                
                $_SESSION['success_message'] = "Event updated successfully!";
                header("location: view_event.php?id=" . $event_id);
                exit;
            } else {
                $_SESSION['error_message'] = "Something went wrong. Please try again later.";
            }
            
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Something went wrong. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - EventSphere@UPSI</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h1 class="mb-3">Edit Event</h1>
                        <p class="text-muted">Update details for "<?php echo htmlspecialchars($title); ?>"</p>
                    </div>
                </div>
            </div>
        </div>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?id=<?php echo $event_id; ?>" method="post" enctype="multipart/form-data">
            <div class="row">
                <!-- Basic Information -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <!-- Event Title -->
                            <div class="form-group">
                                <label for="title">Event Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($title); ?>">
                                <span class="invalid-feedback"><?php echo $title_err; ?></span>
                            </div>
                            
                            <!-- Event Description -->
                            <div class="form-group">
                                <label for="description">Description <span class="text-danger">*</span></label>
                                <textarea name="description" id="description" rows="5" class="form-control <?php echo (!empty($description_err)) ? 'is-invalid' : ''; ?>"><?php echo htmlspecialchars($description); ?></textarea>
                                <span class="invalid-feedback"><?php echo $description_err; ?></span>
                                <small class="form-text text-muted">Provide a detailed description of the event.</small>
                            </div>
                            
                            <div class="row">
                                <!-- Event Start Date -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="event_date">Start Date <span class="text-danger">*</span></label>
                                        <input type="date" name="event_date" id="event_date" class="form-control <?php echo (!empty($event_date_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($event_date); ?>">
                                        <span class="invalid-feedback"><?php echo $event_date_err; ?></span>
                                    </div>
                                </div>
                                
                                <!-- Event End Date -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="event_end_date">End Date <span class="text-danger">*</span></label>
                                        <input type="date" name="event_end_date" id="event_end_date" class="form-control <?php echo (!empty($event_end_date_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($event_end_date); ?>">
                                        <span class="invalid-feedback"><?php echo $event_end_date_err; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <!-- Event Start Time -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="event_time">Start Time <span class="text-danger">*</span></label>
                                        <input type="time" name="event_time" id="event_time" class="form-control <?php echo (!empty($event_time_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($event_time); ?>">
                                        <span class="invalid-feedback"><?php echo $event_time_err; ?></span>
                                    </div>
                                </div>
                                
                                <!-- Event End Time -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="event_end_time">End Time <span class="text-danger">*</span></label>
                                        <input type="time" name="event_end_time" id="event_end_time" class="form-control <?php echo (!empty($event_end_time_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($event_end_time); ?>">
                                        <span class="invalid-feedback"><?php echo $event_end_time_err; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Event Venue -->
                            <div class="form-group">
                                <label for="venue">Venue <span class="text-danger">*</span></label>
                                <input type="text" name="venue" id="venue" class="form-control <?php echo (!empty($venue_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($venue); ?>">
                                <span class="invalid-feedback"><?php echo $venue_err; ?></span>
                            </div>
                            
                            <div class="row">
                                <!-- Event Organizer -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="organizer">Organizer <span class="text-danger">*</span></label>
                                        <input type="text" name="organizer" id="organizer" class="form-control <?php echo (!empty($organizer_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($organizer); ?>">
                                        <span class="invalid-feedback"><?php echo $organizer_err; ?></span>
                                    </div>
                                </div>
                                
                                <!-- Contact Information -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="contact_info">Contact Information <span class="text-danger">*</span></label>
                                        <input type="text" name="contact_info" id="contact_info" class="form-control <?php echo (!empty($contact_info_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($contact_info); ?>">
                                        <span class="invalid-feedback"><?php echo $contact_info_err; ?></span>
                                        <small class="form-text text-muted">Email or phone number for inquiries.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Event Rules -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Rules & Guidelines</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-0">
                                <label for="rules">Event Rules</label>
                                <textarea name="rules" id="rules" rows="5" class="form-control"><?php echo htmlspecialchars($rules); ?></textarea>
                                <small class="form-text text-muted">Enter rules, guidelines, or special instructions for participants.</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Event Tags -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Event Tags</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Select Existing Tags</label>
                                <div class="row">
                                    <?php 
                                    // Create a new array with all unique tags (existing tags + current event tags)
                                    $unique_tags = array_unique(array_merge($all_tags, $tags));
                                    sort($unique_tags);
                                    
                                    foreach ($unique_tags as $tag): 
                                    ?>
                                    <div class="col-md-3 mb-2">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="tag-<?php echo htmlspecialchars($tag); ?>" name="tags[]" value="<?php echo htmlspecialchars($tag); ?>" <?php echo in_array($tag, $tags) ? 'checked' : ''; ?>>
                                            <label class="custom-control-label" for="tag-<?php echo htmlspecialchars($tag); ?>"><?php echo htmlspecialchars($tag); ?></label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="form-group mb-0">
                                <label for="new_tags">Add New Tags</label>
                                <input type="text" name="new_tags" id="new_tags" class="form-control">
                                <small class="form-text text-muted">Enter new tags separated by commas (e.g., Workshop, Science, Competition).</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Category and Settings -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Category & Settings</h5>
                        </div>
                        <div class="card-body">
                            <!-- Category -->
                            <div class="form-group">
                                <label for="category_id">Category <span class="text-danger">*</span></label>
                                <select name="category_id" id="category_id" class="form-control <?php echo (!empty($category_id_err)) ? 'is-invalid' : ''; ?>">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="invalid-feedback"><?php echo $category_id_err; ?></span>
                            </div>
                            
                            <!-- Featured Event -->
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="is_featured" name="is_featured" <?php echo $is_featured ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="is_featured">Mark as Featured Event</label>
                                </div>
                                <small class="form-text text-muted">Featured events are highlighted on the homepage.</small>
                            </div>
                            
                            <!-- Maximum Participants -->
                            <div class="form-group">
                                <label for="max_participants">Maximum Participants</label>
                                <input type="number" name="max_participants" id="max_participants" class="form-control" value="<?php echo htmlspecialchars($max_participants); ?>">
                                <small class="form-text text-muted">Leave empty for unlimited participants.</small>
                            </div>
                            
                            <!-- Registration Deadline -->
                            <div class="form-group">
                                <label for="registration_deadline">Registration Deadline</label>
                                <input type="date" name="registration_deadline" id="registration_deadline" class="form-control" value="<?php echo htmlspecialchars($registration_deadline); ?>">
                                <small class="form-text text-muted">Leave empty to use the event date.</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Media Files -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Media Files</h5>
                        </div>
                        <div class="card-body">
                            <!-- Current Poster -->
                            <?php if (!empty($poster_url)): ?>
                            <div class="form-group">
                                <label>Current Poster</label>
                                <div class="mb-2">
                                    <img src="<?php echo '../' . htmlspecialchars($poster_url); ?>" alt="Event Poster" class="img-thumbnail" style="max-height: 150px;">
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Poster Upload -->
                            <div class="form-group">
                                <label for="poster">Update Poster</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="poster" name="poster">
                                    <label class="custom-file-label" for="poster">Choose new poster</label>
                                </div>
                                <small class="form-text text-muted">Leave empty to keep current poster.</small>
                            </div>
                            
                            <!-- Current Brochure -->
                            <?php if (!empty($brochure_url)): ?>
                            <div class="form-group">
                                <label>Current Brochure</label>
                                <div class="mb-2">
                                    <a href="<?php echo '../' . htmlspecialchars($brochure_url); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-file-pdf mr-1"></i> View Current Brochure
                                    </a>
                                    </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Brochure Upload -->
                            <div class="form-group mb-0">
                                <label for="brochure">Update Brochure/Rulebook</label>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" id="brochure" name="brochure">
                                    <label class="custom-file-label" for="brochure">Choose new PDF file</label>
                                </div>
                                <small class="form-text text-muted">Leave empty to keep current brochure.</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary btn-block btn-lg">
                                <i class="fas fa-save mr-2"></i> Update Event
                            </button>
                            <a href="view_event.php?id=<?php echo $event_id; ?>" class="btn btn-outline-secondary btn-block">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
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
        // Display selected filename for file inputs
        $('.custom-file-input').on('change', function() {
            var fileName = $(this).val().split('\\').pop();
            $(this).next('.custom-file-label').html(fileName);
        });
        
        // Set minimum date for event date
        var today = new Date();
        var dd = String(today.getDate()).padStart(2, '0');
        var mm = String(today.getMonth() + 1).padStart(2, '0');
        var yyyy = today.getFullYear();
        
        today = yyyy + '-' + mm + '-' + dd;
        $('#event_date').attr('min', today);
        $('#event_end_date').attr('min', today);
        
        // Ensure end date is not before start date
        $('#event_date, #event_end_date').change(function() {
            var startDate = $('#event_date').val();
            var endDate = $('#event_end_date').val();
            
            if (startDate && endDate && startDate > endDate) {
                $('#event_end_date').val(startDate);
            }
        });
        
        // Copy start time to end time if end time is empty (with 1 hour added)
        $('#event_time').change(function() {
            if ($('#event_end_time').val() === '') {
                var startTime = $(this).val();
                if (startTime) {
                    // Try to add 1 hour to the start time
                    var startTimeParts = startTime.split(':');
                    var hours = parseInt(startTimeParts[0]);
                    var minutes = startTimeParts[1];
                    
                    hours = (hours + 1) % 24;
                    var endTime = String(hours).padStart(2, '0') + ':' + minutes;
                    
                    $('#event_end_time').val(endTime);
                }
            }
        });
    });
    </script>
</body>
</html>