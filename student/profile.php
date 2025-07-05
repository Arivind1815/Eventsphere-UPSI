<?php
/**
 * Student Profile Page
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "My Profile";

// Include header
include_once '../include/student_header.php';

// Initialize variables
$phone = "";
$password = $confirm_password = "";
$phone_err = $password_err = $confirm_password_err = "";
$success_message = $error_message = "";

// Get user details
$user_id = $_SESSION["id"];
$sql = "SELECT * FROM users WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $phone = $user['phone'];
    }
    
    $stmt->close();
}

// Get user interests
$user_interests = array();
$sql = "SELECT interest FROM user_interests WHERE user_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $user_interests[] = $row['interest'];
    }
    
    $stmt->close();
}

// Get all available interest tags from the database
$all_interests = array();
$sql = "SELECT DISTINCT tag FROM event_tags ORDER BY tag";
if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $all_interests[] = $row['tag'];
    }
}

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check which form was submitted
    if (isset($_POST["update_profile"])) {
        // Phone update form
        
        // Validate phone
        if (empty(trim($_POST["phone"]))) {
            $phone_err = "Please enter your phone number.";
        } else {
            $phone = trim($_POST["phone"]);
        }
        
        // Check if no errors
        if (empty($phone_err)) {
            // Update phone
            $sql = "UPDATE users SET phone = ? WHERE id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("si", $param_phone, $param_id);
                
                // Set parameters
                $param_phone = $phone;
                $param_id = $user_id;
                
                // Execute the statement
                if ($stmt->execute()) {
                    $success_message = "Your phone number has been updated successfully.";
                } else {
                    $error_message = "Oops! Something went wrong. Please try again later.";
                }
                
                $stmt->close();
            }
        }
    } elseif (isset($_POST["update_password"])) {
        // Password update form
        
        // Validate password
        if (empty(trim($_POST["password"]))) {
            $password_err = "Please enter a password.";
        } else {
            $password = trim($_POST["password"]);
        }
        
        // Validate confirm password
        if (empty(trim($_POST["confirm_password"]))) {
            $confirm_password_err = "Please confirm the password.";
        } else {
            $confirm_password = trim($_POST["confirm_password"]);
            if ($password != $confirm_password) {
                $confirm_password_err = "Password did not match.";
            }
        }
        
        // Check if no errors
        if (empty($password_err) && empty($confirm_password_err)) {
            // Update password
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("si", $param_password, $param_id);
                
                // Set parameters
                $param_password = $password; // Not hashing as requested
                $param_id = $user_id;
                
                // Execute the statement
                if ($stmt->execute()) {
                    $success_message = "Your password has been updated successfully.";
                    $password = $confirm_password = "";
                } else {
                    $error_message = "Oops! Something went wrong. Please try again later.";
                }
                
                $stmt->close();
            }
        }
    } elseif (isset($_POST["update_interests"])) {
        // Interests update form
        
        // First delete all existing interests
        $sql = "DELETE FROM user_interests WHERE user_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
        
        // Then insert new interests
        if (isset($_POST["interests"]) && is_array($_POST["interests"])) {
            $user_interests = $_POST["interests"]; // Update the array for display
            
            foreach ($_POST["interests"] as $interest) {
                $sql = "INSERT INTO user_interests (user_id, interest) VALUES (?, ?)";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("is", $user_id, $interest);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            $success_message = "Your interests have been updated successfully.";
        } else {
            $user_interests = array(); // Empty array if no interests selected
            $success_message = "Your interests have been cleared.";
        }
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h1 class="display-4 mb-3">My Profile</h1>
                    <p class="lead">View and update your personal information.</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($success_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $success_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error_message; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Profile Information -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Personal Information</h4>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <!-- Name (Read-only) -->
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($_SESSION["name"]); ?>" readonly>
                            <small class="form-text text-muted">Your name cannot be changed. Contact admin for assistance.</small>
                        </div>
                        
                        <!-- Matric ID (Read-only) -->
                        <div class="form-group">
                            <label>Matric ID</label>
                            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($_SESSION["matric_id"]); ?>" readonly>
                            <small class="form-text text-muted">Your Matric ID cannot be changed.</small>
                        </div>
                        
                        <!-- Email (Read-only) -->
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($_SESSION["email"]); ?>" readonly>
                            <small class="form-text text-muted">Your email cannot be changed. Contact admin for assistance.</small>
                        </div>
                        
                        <!-- Phone (Editable) -->
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($phone); ?>">
                            <span class="invalid-feedback"><?php echo $phone_err; ?></span>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="update_profile" class="btn btn-primary">Update Information</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Password Update -->
        <div class="col-md-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Update Password</h4>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <!-- New Password -->
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>">
                            <span class="invalid-feedback"><?php echo $password_err; ?></span>
                        </div>
                        
                        <!-- Confirm New Password -->
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $confirm_password; ?>">
                            <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Interests Section -->
    <div class="row" id="interests">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">My Interests</h4>
                </div>
                <div class="card-body">
                    <p>Select interests to help us recommend events that match your preferences.</p>
                    
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                        <div class="row">
                            <?php foreach ($all_interests as $interest): ?>
                            <div class="col-lg-3 col-md-4 col-sm-6">
                                <div class="custom-control custom-checkbox mb-3">
                                    <input type="checkbox" class="custom-control-input" id="interest-<?php echo htmlspecialchars($interest); ?>" name="interests[]" value="<?php echo htmlspecialchars($interest); ?>" <?php echo in_array($interest, $user_interests) ? 'checked' : ''; ?>>
                                    <label class="custom-control-label" for="interest-<?php echo htmlspecialchars($interest); ?>"><?php echo htmlspecialchars($interest); ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="form-group mt-3">
                            <button type="submit" name="update_interests" class="btn btn-primary">Update Interests</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Activity History -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Recent Activity</h4>
                    <a href="myevents.php" class="btn btn-sm btn-outline-primary">View All Events</a>
                </div>
                <div class="card-body p-0">
                    <?php
                    // Get recent activities (registrations and attendances)
                    $activity_sql = "
                        (SELECT 'registration' as type, r.registration_date as date, e.title as event_name, e.id as event_id
                         FROM event_registrations r
                         JOIN events e ON r.event_id = e.id
                         WHERE r.user_id = ?)
                        UNION
                        (SELECT 'attendance' as type, a.attendance_time as date, e.title as event_name, e.id as event_id
                         FROM attendance a
                         JOIN events e ON a.event_id = e.id
                         WHERE a.user_id = ?)
                        ORDER BY date DESC
                        LIMIT 5";
                    
                    if ($stmt = $conn->prepare($activity_sql)) {
                        $stmt->bind_param("ii", $user_id, $user_id);
                        $stmt->execute();
                        $activity_result = $stmt->get_result();
                        
                        if ($activity_result->num_rows > 0) {
                            echo '<div class="list-group list-group-flush">';
                            
                            while ($activity = $activity_result->fetch_assoc()) {
                                $date_formatted = date("d M Y, h:i A", strtotime($activity['date']));
                                $icon_class = ($activity['type'] == 'registration') ? 'fas fa-user-plus text-primary' : 'fas fa-check-circle text-success';
                                $activity_text = ($activity['type'] == 'registration') ? 'Registered for' : 'Attended';
                                ?>
                                <a href="event_details.php?id=<?php echo $activity['event_id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex">
                                        <div class="mr-3">
                                            <i class="<?php echo $icon_class; ?> fa-2x"></i>
                                        </div>
                                        <div>
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo $activity_text . ' ' . htmlspecialchars($activity['event_name']); ?></h6>
                                                <small><?php echo $date_formatted; ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                                <?php
                            }
                            
                            echo '</div>';
                        } else {
                            ?>
                            <div class="text-center py-4">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No recent activity found.</p>
                                <a href="events.php" class="btn btn-sm btn-primary mt-2">Discover Events</a>
                            </div>
                            <?php
                        }
                        
                        $stmt->close();
                    }
                    ?>
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

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>