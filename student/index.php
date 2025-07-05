<?php
/**
 * Student Dashboard
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Dashboard";

// Include header
include_once '../include/student_header.php';

// Get user id
$user_id = $_SESSION["id"];

// Get registered events count
$registered_count = 0;
$sql = "SELECT COUNT(*) as total FROM event_registrations WHERE user_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $registered_count = $row['total'];
    }
    $stmt->close();
}

// Get attended events count
$attended_count = 0;
$sql = "SELECT COUNT(*) as total FROM attendance WHERE user_id = ? AND verified = 1";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $attended_count = $row['total'];
    }
    $stmt->close();
}

// Get total points
$total_points = 0;
$sql = "SELECT SUM(points) as total FROM user_points WHERE user_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $total_points = $row['total'] ? $row['total'] : 0;
    }
    $stmt->close();
}

// Get user interests (for event recommendations)
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

// If no interests defined yet, show all events
$has_interests = !empty($user_interests);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h1 class="display-4 mb-3">Welcome, <?php echo htmlspecialchars($_SESSION["name"]); ?>!</h1>
                    <p class="lead">Navigate, engage, and excel with EventSphere@UPSI - your gateway to campus
                        activities.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <!-- Champ Points Card -->
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-award fa-3x mb-3 text-primary"></i>
                    <h2 class="display-4 font-weight-bold"><?php echo $total_points; ?></h2>
                    <h5 class="card-title text-muted">Champ Points</h5>
                    <a href="goals.php" class="btn btn-sm btn-outline-primary mt-2">Set Point Goals</a>
                </div>
            </div>
        </div>

        <!-- Registered Events Card -->
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-check fa-3x mb-3 text-success"></i>
                    <h2 class="display-4 font-weight-bold"><?php echo $registered_count; ?></h2>
                    <h5 class="card-title text-muted">Registered Events</h5>
                    <a href="myevents.php" class="btn btn-sm btn-outline-success mt-2">View My Events</a>
                </div>
            </div>
        </div>

        <!-- Attended Events Card -->
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fa-3x mb-3 text-info"></i>
                    <h2 class="display-4 font-weight-bold"><?php echo $attended_count; ?></h2>
                    <h5 class="card-title text-muted">Attended Events</h5>
                    <a href="myevents.php?tab=attended" class="btn btn-sm btn-outline-info mt-2">View History</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Categories Filter -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Explore Events by Category</h4>
                </div>
                <div class="card-body">
                    <div class="category-filters d-flex flex-wrap justify-content-center">
                        <a href="?category=all"
                            class="btn btn-outline-primary m-1 <?php echo (!isset($_GET['category']) || $_GET['category'] == 'all') ? 'active' : ''; ?>">All
                            Events</a>
                        <a href="?category=faculty"
                            class="btn btn-outline-primary m-1 <?php echo (isset($_GET['category']) && $_GET['category'] == 'faculty') ? 'active' : ''; ?>">Faculty</a>
                        <a href="?category=club"
                            class="btn btn-outline-primary m-1 <?php echo (isset($_GET['category']) && $_GET['category'] == 'club') ? 'active' : ''; ?>">Clubs</a>
                        <a href="?category=college"
                            class="btn btn-outline-primary m-1 <?php echo (isset($_GET['category']) && $_GET['category'] == 'college') ? 'active' : ''; ?>">College</a>
                        <a href="?category=international"
                            class="btn btn-outline-primary m-1 <?php echo (isset($_GET['category']) && $_GET['category'] == 'international') ? 'active' : ''; ?>">International</a>
                        <a href="?category=national"
                            class="btn btn-outline-primary m-1 <?php echo (isset($_GET['category']) && $_GET['category'] == 'national') ? 'active' : ''; ?>">National</a>
                        <a href="?category=local"
                            class="btn btn-outline-primary m-1 <?php echo (isset($_GET['category']) && $_GET['category'] == 'local') ? 'active' : ''; ?>">Local</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Events Based on Interests -->
    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <?php if (!isset($_GET['category']) || $_GET['category'] == 'all'): ?>
                            <?php if ($has_interests): ?>
                                Recommended for You
                            <?php else: ?>
                                Upcoming Events
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo ucfirst(htmlspecialchars($_GET['category'])); ?> Events
                        <?php endif; ?>
                    </h4>
                    <a href="events.php" class="btn btn-sm btn-outline-primary">See All</a>
                </div>
                <div class="card-body p-0">
                    <?php
                    // Build the SQL query based on interests/filters
                    $event_sql = "
                        SELECT e.*, c.name as category_name 
                        FROM events e 
                        JOIN event_categories c ON e.category_id = c.id 
                        WHERE e.event_date >= CURDATE()";

                    // Filter by category if selected
                    if (isset($_GET['category']) && $_GET['category'] != 'all') {
                        $category = $_GET['category'];
                        $event_sql .= " AND c.name = ?";
                        $param_category = true;
                    } else {
                        $param_category = false;
                    }

                    // If user has interests and no category filter, prioritize recommended events
                    if ($has_interests && (!isset($_GET['category']) || $_GET['category'] == 'all')) {
                        // Join with event_tags to find events matching user interests
                        $event_sql = "
                            SELECT e.*, c.name as category_name, 
                                   COUNT(t.tag) as matching_tags
                            FROM events e 
                            JOIN event_categories c ON e.category_id = c.id
                            LEFT JOIN event_tags t ON e.id = t.event_id
                            WHERE e.event_date >= CURDATE() 
                            AND (t.tag IN (" . implode(',', array_fill(0, count($user_interests), '?')) . ") 
                                OR e.is_featured = 1)
                            GROUP BY e.id
                            ORDER BY matching_tags DESC, e.is_featured DESC, e.event_date ASC
                            LIMIT 5";
                        $has_interest_params = true;
                    } else {
                        // Standard order if no interests or specific category selected
                        $event_sql .= " ORDER BY e.is_featured DESC, e.event_date ASC LIMIT 5";
                        $has_interest_params = false;
                    }

                    if ($stmt = $conn->prepare($event_sql)) {
                        // Bind parameters if needed
                        if ($param_category) {
                            $stmt->bind_param("s", $category);
                        } elseif ($has_interest_params) {
                            $types = str_repeat("s", count($user_interests));
                            $stmt->bind_param($types, ...$user_interests);
                        }

                        $stmt->execute();
                        $events_result = $stmt->get_result();

                        if ($events_result->num_rows > 0) {
                            echo '<div class="list-group list-group-flush">';

                            while ($event = $events_result->fetch_assoc()) {
                                // Check if user is already registered and get the registration status
                                $is_registered = false;
                                $is_cancelled = false;
                                $reg_check_sql = "SELECT id, status FROM event_registrations WHERE event_id = ? AND user_id = ?";
                                if ($reg_stmt = $conn->prepare($reg_check_sql)) {
                                    $reg_stmt->bind_param("ii", $event['id'], $user_id);
                                    $reg_stmt->execute();
                                    $reg_result = $reg_stmt->get_result();
                                    if ($reg_result->num_rows > 0) {
                                        $reg_row = $reg_result->fetch_assoc();
                                        $is_registered = true;
                                        $is_cancelled = ($reg_row['status'] === 'cancelled');
                                    }
                                    $reg_stmt->close();
                                }

                                // Format date and time
                                $event_date = date("d M Y", strtotime($event['event_date']));
                                $event_time = date("h:i A", strtotime($event['event_time']));

                                // Get event tags
                                $tags = array();
                                $tag_sql = "SELECT tag FROM event_tags WHERE event_id = ?";
                                if ($tag_stmt = $conn->prepare($tag_sql)) {
                                    $tag_stmt->bind_param("i", $event['id']);
                                    $tag_stmt->execute();
                                    $tag_result = $tag_stmt->get_result();
                                    while ($tag_row = $tag_result->fetch_assoc()) {
                                        $tags[] = $tag_row['tag'];
                                    }
                                    $tag_stmt->close();
                                }

                                // Display the event
                                ?>
                                <div class="list-group-item list-group-item-action flex-column align-items-start p-4">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <h5 class="mb-2">
                                            <?php if ($event['is_featured']): ?>
                                                <span class="badge badge-warning mr-2"><i class="fas fa-star"></i> Featured</span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </h5>
                                        <small class="text-muted badge badge-light">
                                            <?php echo htmlspecialchars($event['category_name']); ?>
                                        </small>
                                    </div>

                                    <div class="mb-2 text-muted">
                                        <i class="far fa-calendar-alt mr-1"></i> <?php echo $event_date; ?>
                                        <i class="far fa-clock ml-3 mr-1"></i> <?php echo $event_time; ?>
                                        <i class="fas fa-map-marker-alt ml-3 mr-1"></i>
                                        <?php echo htmlspecialchars($event['venue']); ?>
                                    </div>

                                    <p class="mb-2">
                                        <?php
                                        // Truncate description if too long
                                        $desc = htmlspecialchars($event['description']);
                                        echo (strlen($desc) > 150) ? substr($desc, 0, 150) . '...' : $desc;
                                        ?>
                                    </p>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <?php foreach ($tags as $tag): ?>
                                                <span class="badge badge-info mr-1">
                                                    <?php echo htmlspecialchars($tag); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>

                                        <div>
                                            <?php if ($is_registered): ?>
                                                <?php if ($is_cancelled): ?>
                                                    <span class="badge badge-danger mr-2">
                                                        <i class="fas fa-times-circle"></i> Cancelled
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-success mr-2">
                                                        <i class="fas fa-check-circle"></i> Registered
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <a href="event_details.php?id=<?php echo $event['id']; ?>"
                                                class="btn btn-sm btn-primary">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            }

                            echo '</div>';
                        } else {
                            // No events found
                            ?>
                            <div class="text-center py-5">
                                <i class="far fa-calendar-times fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No events found</h5>
                                <p>There are no upcoming events at this time.</p>
                                <a href="events.php" class="btn btn-primary mt-2">Explore All Events</a>
                            </div>
                            <?php
                        }

                        $stmt->close();
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Sidebar - My Registered Events & Quick Links -->
        <div class="col-md-4">
            <!-- My Registered Events -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">My Registered Events</h5>
                    <a href="myevents.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php
                    // Get registered events
                    $reg_events_sql = "
                        SELECT e.id, e.title, e.event_date, e.event_time, e.venue, r.status 
                        FROM events e 
                        JOIN event_registrations r ON e.id = r.event_id 
                        WHERE r.user_id = ? AND e.event_date >= CURDATE() 
                        ORDER BY e.event_date ASC 
                        LIMIT 3";

                    if ($reg_stmt = $conn->prepare($reg_events_sql)) {
                        $reg_stmt->bind_param("i", $user_id);
                        $reg_stmt->execute();
                        $reg_events_result = $reg_stmt->get_result();

                        if ($reg_events_result->num_rows > 0) {
                            echo '<div class="list-group list-group-flush">';

                            while ($reg_event = $reg_events_result->fetch_assoc()) {
                                // Format date and time
                                $event_date = date("d M Y", strtotime($reg_event['event_date']));
                                $event_time = date("h:i A", strtotime($reg_event['event_time']));

                                // Calculate days remaining
                                $today = new DateTime();
                                $event_date_obj = new DateTime($reg_event['event_date']);
                                $interval = $today->diff($event_date_obj);
                                $days_remaining = $interval->days;
                                
                                // Check if registration is cancelled
                                $is_cancelled = ($reg_event['status'] === 'cancelled');
                                ?>
                                <a href="event_details.php?id=<?php echo $reg_event['id']; ?>"
                                    class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?php echo htmlspecialchars($reg_event['title']); ?>
                                            <?php if ($is_cancelled): ?>
                                                <span class="badge badge-danger ml-1">Cancelled</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-primary"><?php echo $days_remaining; ?> days left</small>
                                    </div>
                                    <small class="text-muted">
                                        <i class="far fa-calendar-alt mr-1"></i> <?php echo $event_date; ?>
                                        <i class="far fa-clock ml-2 mr-1"></i> <?php echo $event_time; ?>
                                    </small>
                                </a>
                                <?php
                            }

                            echo '</div>';
                        } else {
                            // No registered events
                            ?>
                            <div class="text-center py-4">
                                <i class="far fa-calendar fa-3x text-muted mb-3"></i>
                                <p class="text-muted">You haven't registered for any upcoming events yet.</p>
                                <a href="events.php" class="btn btn-sm btn-primary mt-2">Find Events</a>
                            </div>
                            <?php
                        }

                        $reg_stmt->close();
                    }
                    ?>
                </div>
            </div>



            <!-- Interest Tags -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">My Interests</h5>
                    <a href="profile.php#interests" class="btn btn-sm btn-outline-primary">Edit</a>
                </div>
                <div class="card-body">
                    <?php if ($has_interests): ?>
                        <div class="d-flex flex-wrap">
                            <?php foreach ($user_interests as $interest): ?>
                                <span
                                    class="badge badge-pill badge-primary m-1 p-2"><?php echo htmlspecialchars($interest); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <p class="text-muted">You haven't set any interests yet.</p>
                            <a href="profile.php#interests" class="btn btn-sm btn-primary">Set Interests</a>
                        </div>
                    <?php endif; ?>
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