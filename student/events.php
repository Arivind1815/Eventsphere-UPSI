<?php
/**
 * Event Discovery & Browse
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Discover Events";

// Include header
include_once '../include/student_header.php';
//

// Get user ID for checking registrations
$user_id = $_SESSION["id"];

// Initialize filter variables
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$tag_filter = isset($_GET['tag']) ? $_GET['tag'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'card';
$time_filter = isset($_GET['time']) ? $_GET['time'] : 'upcoming';

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

// Get all event categories for filter
$categories = [];
$categories_sql = "SELECT id, name FROM event_categories ORDER BY name";
$categories_result = $conn->query($categories_sql);

if ($categories_result) {
    while ($category_row = $categories_result->fetch_assoc()) {
        $categories[] = $category_row;
    }
}

// Get all tags for filter
$tags = [];
$tags_sql = "SELECT DISTINCT tag FROM event_tags ORDER BY tag";
$tags_result = $conn->query($tags_sql);

if ($tags_result) {
    while ($tag_row = $tags_result->fetch_assoc()) {
        $tags[] = $tag_row['tag'];
    }
}

// Build query conditions based on filters
$where_conditions = [];
$params = [];
$param_types = "";

// Base time filter (default: upcoming events)
// Current timestamp for comparing both date and time
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$current_datetime = date('Y-m-d H:i:s');

// Base time filter (default: upcoming events)
if ($time_filter == 'upcoming') {
    // Event is upcoming if:
    // 1. It starts in the future (event_date > today)
    // 2. OR it starts today but the event time is still in the future
    // 3. OR it's ongoing (started but not yet ended)
    $where_conditions[] = "(
        e.event_date > ? 
        OR (e.event_date = ? AND e.event_time > ?) 
        OR (
            e.event_date <= ? 
            AND (
                (e.event_end_date IS NULL AND e.event_date = ? AND e.event_time > ?) 
                OR (e.event_end_date IS NULL AND e.event_date > ?)
                OR (e.event_end_date > ? OR (e.event_end_date = ? AND e.event_end_time >= ?))
            )
        )
    )";
    $params[] = $current_date;
    $params[] = $current_date;
    $params[] = $current_time;
    $params[] = $current_date;
    $params[] = $current_date;
    $params[] = $current_time;
    $params[] = $current_date;
    $params[] = $current_date;
    $params[] = $current_date;
    $params[] = $current_time;
    $param_types .= "ssssssssss";
    
} elseif ($time_filter == 'past') {
    // Event is past if:
    // 1. It has a defined end date that's in the past
    // 2. OR it has a defined end date of today but the end time has passed
    // 3. OR it has no end date but the start date/time is in the past
    $where_conditions[] = "(
        (e.event_end_date IS NOT NULL AND e.event_end_date < ?) 
        OR (e.event_end_date = ? AND e.event_end_time < ?) 
        OR (e.event_end_date IS NULL AND (e.event_date < ? OR (e.event_date = ? AND e.event_time < ?)))
    )";
    $params[] = $current_date;
    $params[] = $current_date;
    $params[] = $current_time;
    $params[] = $current_date;
    $params[] = $current_date;
    $params[] = $current_time;
    $param_types .= "ssssss";
    
} elseif ($time_filter == 'today') {
    // Event is today if:
    // 1. It starts today (regardless of time)
    // 2. OR it ends today (regardless of time)
    // 3. OR it spans today (started before and ends after)
    $where_conditions[] = "(
        e.event_date = ? 
        OR e.event_end_date = ? 
        OR (e.event_date < ? AND (e.event_end_date > ? OR e.event_end_date IS NULL))
    )";
    $params[] = $current_date;
    $params[] = $current_date;
    $params[] = $current_date;
    $params[] = $current_date;
    $param_types .= "ssss";
    
} elseif ($time_filter == 'week') {
    // Get date 7 days from now
    $one_week_later = date('Y-m-d', strtotime('+7 days'));
    
    // Event is within this week if:
    // 1. It starts between now and 7 days from now
    // 2. OR it ends between now and 7 days from now
    // 3. OR it spans this period (started before and ends after)
    $where_conditions[] = "(
        (e.event_date BETWEEN ? AND ?) 
        OR (e.event_end_date BETWEEN ? AND ?) 
        OR (e.event_date < ? AND (e.event_end_date > ? OR e.event_end_date IS NULL))
    )";
    $params[] = $current_date;
    $params[] = $one_week_later;
    $params[] = $current_date;
    $params[] = $one_week_later;
    $params[] = $current_date;
    $params[] = $one_week_later;
    $param_types .= "ssssss";
    
} elseif ($time_filter == 'month') {
    // Get date 30 days from now
    $one_month_later = date('Y-m-d', strtotime('+30 days'));
    
    // Event is within this month if:
    // 1. It starts between now and 30 days from now
    // 2. OR it ends between now and 30 days from now
    // 3. OR it spans this period (started before and ends after)
    $where_conditions[] = "(
        (e.event_date BETWEEN ? AND ?) 
        OR (e.event_end_date BETWEEN ? AND ?) 
        OR (e.event_date < ? AND (e.event_end_date > ? OR e.event_end_date IS NULL))
    )";
    $params[] = $current_date;
    $params[] = $one_month_later;
    $params[] = $current_date;
    $params[] = $one_month_later;
    $params[] = $current_date;
    $params[] = $one_month_later;
    $param_types .= "ssssss";
}

// Category filter
if (!empty($category_filter)) {
    $where_conditions[] = "c.name = ?";
    $params[] = $category_filter;
    $param_types .= "s";
}

// Tag filter
if (!empty($tag_filter)) {
    $where_conditions[] = "e.id IN (SELECT event_id FROM event_tags WHERE tag = ?)";
    $params[] = $tag_filter;
    $param_types .= "s";
}

// Search term
if (!empty($search_term)) {
    $where_conditions[] = "(e.title LIKE ? OR e.description LIKE ? OR e.venue LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "sss";
}

// Combine all conditions
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Order by date and featured status
$order_by = "ORDER BY e.is_featured DESC, e.event_date ASC";

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 9; // Number of events per page
$offset = ($page - 1) * $items_per_page;

// Get total events count for pagination
$count_sql = "SELECT COUNT(*) as total FROM events e 
              JOIN event_categories c ON e.category_id = c.id 
              $where_clause";

$total_events = 0;
if ($count_stmt = $conn->prepare($count_sql)) {
    // Bind parameters if any
    if (!empty($params)) {
        $count_stmt->bind_param($param_types, ...$params);
    }
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    
    if ($row = $count_result->fetch_assoc()) {
        $total_events = $row['total'];
    }
    
    $count_stmt->close();
}

$total_pages = ceil($total_events / $items_per_page);

// Get events
$events = [];
$events_sql = "SELECT e.*, c.name as category_name 
               FROM events e 
               JOIN event_categories c ON e.category_id = c.id 
               $where_clause 
               $order_by 
               LIMIT ?, ?";

if ($stmt = $conn->prepare($events_sql)) {
    // Add pagination parameters
    $params[] = $offset;
    $params[] = $items_per_page;
    $param_types .= "ii";
    
    // Bind parameters
    $stmt->bind_param($param_types, ...$params);
    
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
        
        // Check if user is registered
        $is_registered = false;
        $check_sql = "SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ? AND status = 'registered'";
        if ($check_stmt = $conn->prepare($check_sql)) {
            $check_stmt->bind_param("ii", $row['id'], $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $is_registered = ($check_result->num_rows > 0);
            $check_stmt->close();
        }
        
        // Add tags and registration status to event data
        $row['tags'] = $event_tags;
        $row['is_registered'] = $is_registered;
        $row['registration_closed'] = (!empty($row['registration_deadline']) && $row['registration_deadline'] < date('Y-m-d'));
        
        // Get registration count
        $reg_count = 0;
        $reg_sql = "SELECT COUNT(*) as count FROM event_registrations WHERE event_id = ? AND status = 'registered'";
        if ($reg_stmt = $conn->prepare($reg_sql)) {
            $reg_stmt->bind_param("i", $row['id']);
            $reg_stmt->execute();
            $reg_result = $reg_stmt->get_result();
            if ($reg_row = $reg_result->fetch_assoc()) {
                $reg_count = $reg_row['count'];
            }
            $reg_stmt->close();
        }
        
        $row['registration_count'] = $reg_count;
        $row['is_full'] = (!empty($row['max_participants']) && $reg_count >= $row['max_participants']);
        
        $events[] = $row;
    }
    
    $stmt->close();
}

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    
    if ($event_id > 0) {
        // Check if already registered
        $check_sql = "SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ?";
        $already_registered = false;
        
        if ($check_stmt = $conn->prepare($check_sql)) {
            $check_stmt->bind_param("ii", $event_id, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $already_registered = ($check_result->num_rows > 0);
            $check_stmt->close();
        }
        
        if (!$already_registered) {
            // Check event details (if registration is still open)
            $event_sql = "SELECT e.*, 
                          (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND status = 'registered') as registration_count 
                          FROM events e WHERE e.id = ?";
            
            if ($event_stmt = $conn->prepare($event_sql)) {
                $event_stmt->bind_param("i", $event_id);
                $event_stmt->execute();
                $event_result = $event_stmt->get_result();
                
                if ($event_row = $event_result->fetch_assoc()) {
                    $registration_closed = (!empty($event_row['registration_deadline']) && $event_row['registration_deadline'] < date('Y-m-d'));
                    $is_full = (!empty($event_row['max_participants']) && $event_row['registration_count'] >= $event_row['max_participants']);
                    $is_past = ($event_row['event_date'] < date('Y-m-d'));
                    
                    if (!$registration_closed && !$is_full && !$is_past) {
                        // Insert registration
                        $reg_sql = "INSERT INTO event_registrations (event_id, user_id, registration_date) VALUES (?, ?, NOW())";
                        
                        if ($reg_stmt = $conn->prepare($reg_sql)) {
                            $reg_stmt->bind_param("ii", $event_id, $user_id);
                            
                            if ($reg_stmt->execute()) {
                                // Create notification
                                $notif_sql = "INSERT INTO notifications (user_id, title, message, type, related_id) VALUES (?, ?, ?, 'registration_confirmation', ?)";
                                
                                if ($notif_stmt = $conn->prepare($notif_sql)) {
                                    $title = "Registration Confirmed";
                                    $message = "Your registration for \"" . $event_row['title'] . "\" has been confirmed.";
                                    
                                    $notif_stmt->bind_param("issi", $user_id, $title, $message, $event_id);
                                    $notif_stmt->execute();
                                    $notif_stmt->close();
                                }
                                
                                $_SESSION['success_message'] = "You have successfully registered for this event!";
                            } else {
                                $_SESSION['error_message'] = "Error registering for event. Please try again.";
                            }
                            
                            $reg_stmt->close();
                        }
                    } else {
                        if ($registration_closed) {
                            $_SESSION['error_message'] = "Registration for this event has closed.";
                        } elseif ($is_full) {
                            $_SESSION['error_message'] = "This event has reached its maximum capacity.";
                        } elseif ($is_past) {
                            $_SESSION['error_message'] = "This event has already passed.";
                        }
                    }
                }
                
                $event_stmt->close();
            }
        } else {
            $_SESSION['error_message'] = "You are already registered for this event.";
        }
    }
    
    // Redirect to prevent form resubmission
    $redirect_url = "events.php";
    
    // Preserve filters in redirect
    $query_params = [];
    if (!empty($category_filter)) $query_params[] = "category=" . urlencode($category_filter);
    if (!empty($tag_filter)) $query_params[] = "tag=" . urlencode($tag_filter);
    if (!empty($search_term)) $query_params[] = "search=" . urlencode($search_term);
    if ($view_mode !== 'card') $query_params[] = "view=" . urlencode($view_mode);
    if ($time_filter !== 'upcoming') $query_params[] = "time=" . urlencode($time_filter);
    
    if (!empty($query_params)) {
        $redirect_url .= "?" . implode("&", $query_params);
    }
    
    header("Location: " . $redirect_url);
    exit;
}

// Process calendar view data
$calendar_events = [];
if ($view_mode === 'calendar') {
    // Get all events for the current month
    $start_date = date('Y-m-01'); // First day of current month
    $end_date = date('Y-m-t');    // Last day of current month
    
    $cal_sql = "SELECT id, title, event_date FROM events 
                WHERE event_date BETWEEN ? AND ? 
                ORDER BY event_date";
    
    if ($cal_stmt = $conn->prepare($cal_sql)) {
        $cal_stmt->bind_param("ss", $start_date, $end_date);
        $cal_stmt->execute();
        $cal_result = $cal_stmt->get_result();
        
        while ($cal_row = $cal_result->fetch_assoc()) {
            $event_date = $cal_row['event_date'];
            if (!isset($calendar_events[$event_date])) {
                $calendar_events[$event_date] = [];
            }
            $calendar_events[$event_date][] = $cal_row;
        }
        
        $cal_stmt->close();
    }
}

// Helper function to generate pagination URL
function getPaginationUrl($page) {
    global $category_filter, $tag_filter, $search_term, $view_mode, $time_filter;
    
    $url = "events.php?page=" . $page;
    
    if (!empty($category_filter)) $url .= "&category=" . urlencode($category_filter);
    if (!empty($tag_filter)) $url .= "&tag=" . urlencode($tag_filter);
    if (!empty($search_term)) $url .= "&search=" . urlencode($search_term);
    if ($view_mode !== 'card') $url .= "&view=" . urlencode($view_mode);
    if ($time_filter !== 'upcoming') $url .= "&time=" . urlencode($time_filter);
    
    return $url;
}

// Helper function to generate filter URL
function getFilterUrl($param_name, $param_value) {
    global $category_filter, $tag_filter, $search_term, $view_mode, $time_filter;
    
    $params = [
        'category' => $category_filter,
        'tag' => $tag_filter,
        'search' => $search_term,
        'view' => $view_mode,
        'time' => $time_filter
    ];
    
    // Update the specified parameter
    $params[$param_name] = $param_value;
    
    // Remove empty parameters
    $params = array_filter($params);
    
    // If $param_value is empty, remove that parameter
    if (empty($param_value) && isset($params[$param_name])) {
        unset($params[$param_name]);
    }
    
    // Build the URL
    $url = "events.php";
    if (!empty($params)) {
        $query_parts = [];
        foreach ($params as $key => $value) {
            $query_parts[] = $key . "=" . urlencode($value);
        }
        $url .= "?" . implode("&", $query_parts);
    }
    
    return $url;
}
?>

<div class="container mt-4">
    <!-- Page Header -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h1 class="mb-3">Discover Events</h1>
                    <p class="lead">Browse upcoming events and find activities that match your interests.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search and Filters -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="events.php" method="get" class="mb-3">
                        <!-- Preserve other filters -->
                        <?php if (!empty($category_filter)): ?>
                            <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                        <?php endif; ?>
                        
                        <?php if (!empty($tag_filter)): ?>
                            <input type="hidden" name="tag" value="<?php echo htmlspecialchars($tag_filter); ?>">
                        <?php endif; ?>
                        
                        <?php if ($view_mode !== 'card'): ?>
                            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view_mode); ?>">
                        <?php endif; ?>
                        
                        <?php if ($time_filter !== 'upcoming'): ?>
                            <input type="hidden" name="time" value="<?php echo htmlspecialchars($time_filter); ?>">
                        <?php endif; ?>
                        
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search for events..." value="<?php echo htmlspecialchars($search_term); ?>">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <div class="row">
                        <!-- Category Filter -->
                        <div class="col-md-4 mb-3">
                            <label for="categoryFilter" class="small text-muted mb-1">Filter by Category</label>
                            <select id="categoryFilter" class="form-control" onchange="window.location.href=this.value">
                                <option value="<?php echo getFilterUrl('category', ''); ?>">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo getFilterUrl('category', $category['name']); ?>" <?php echo ($category_filter === $category['name'] ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Tag Filter -->
                        <div class="col-md-4 mb-3">
                            <label for="tagFilter" class="small text-muted mb-1">Filter by Tag</label>
                            <select id="tagFilter" class="form-control" onchange="window.location.href=this.value">
                                <option value="<?php echo getFilterUrl('tag', ''); ?>">All Tags</option>
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?php echo getFilterUrl('tag', $tag); ?>" <?php echo ($tag_filter === $tag ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($tag); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Time Filter -->
                        <div class="col-md-4 mb-3">
                            <label for="timeFilter" class="small text-muted mb-1">Time Frame</label>
                            <select id="timeFilter" class="form-control" onchange="window.location.href=this.value">
                                <option value="<?php echo getFilterUrl('time', 'upcoming'); ?>" <?php echo ($time_filter === 'upcoming' ? 'selected' : ''); ?>>Upcoming Events</option>
                                <option value="<?php echo getFilterUrl('time', 'today'); ?>" <?php echo ($time_filter === 'today' ? 'selected' : ''); ?>>Today</option>
                                <option value="<?php echo getFilterUrl('time', 'week'); ?>" <?php echo ($time_filter === 'week' ? 'selected' : ''); ?>>This Week</option>
                                <option value="<?php echo getFilterUrl('time', 'month'); ?>" <?php echo ($time_filter === 'month' ? 'selected' : ''); ?>>This Month</option>
                                <option value="<?php echo getFilterUrl('time', 'past'); ?>" <?php echo ($time_filter === 'past' ? 'selected' : ''); ?>>Past Events</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- View Mode Switcher -->
                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <div>
                            <?php if (!empty($category_filter) || !empty($tag_filter) || !empty($search_term) || $time_filter !== 'upcoming'): ?>
                                <a href="events.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="btn-group" role="group">
                            <a href="<?php echo getFilterUrl('view', 'card'); ?>" class="btn btn-sm btn-outline-secondary <?php echo ($view_mode === 'card' ? 'active' : ''); ?>">
                                <i class="fas fa-th"></i> Card View
                            </a>
                            <a href="<?php echo getFilterUrl('view', 'list'); ?>" class="btn btn-sm btn-outline-secondary <?php echo ($view_mode === 'list' ? 'active' : ''); ?>">
                                <i class="fas fa-list"></i> List View
                            </a>
                            <a href="<?php echo getFilterUrl('view', 'calendar'); ?>" class="btn btn-sm btn-outline-secondary <?php echo ($view_mode === 'calendar' ? 'active' : ''); ?>">
                                <i class="fas fa-calendar-alt"></i> Calendar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Active Filters -->
    <?php if (!empty($category_filter) || !empty($tag_filter) || !empty($search_term) || $time_filter !== 'upcoming'): ?>
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-3">Active Filters:</h5>
                        <div class="d-flex flex-wrap">
                            <?php if (!empty($category_filter)): ?>
                                <div class="badge badge-primary p-2 m-1">
                                    Category: <?php echo htmlspecialchars($category_filter); ?>
                                    <a href="<?php echo getFilterUrl('category', ''); ?>" class="text-white ml-2"><i class="fas fa-times"></i></a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($tag_filter)): ?>
                                <div class="badge badge-info p-2 m-1">
                                    Tag: <?php echo htmlspecialchars($tag_filter); ?>
                                    <a href="<?php echo getFilterUrl('tag', ''); ?>" class="text-white ml-2"><i class="fas fa-times"></i></a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($search_term)): ?>
                                <div class="badge badge-secondary p-2 m-1">
                                    Search: <?php echo htmlspecialchars($search_term); ?>
                                    <a href="<?php echo getFilterUrl('search', ''); ?>" class="text-white ml-2"><i class="fas fa-times"></i></a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($time_filter !== 'upcoming'): ?>
                                <div class="badge badge-success p-2 m-1">
                                    Time: 
                                    <?php 
                                    switch ($time_filter) {
                                        case 'today': echo 'Today'; break;
                                        case 'week': echo 'This Week'; break;
                                        case 'month': echo 'This Month'; break;
                                        case 'past': echo 'Past Events'; break;
                                        default: echo ucfirst($time_filter);
                                    }
                                    ?>
                                    <a href="<?php echo getFilterUrl('time', 'upcoming'); ?>" class="text-white ml-2"><i class="fas fa-times"></i></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Events Display -->
    <div class="row mb-4">
        <div class="col-md-12">
            <?php if (count($events) > 0): ?>
                <!-- Card View -->
                <?php if ($view_mode === 'card'): ?>
                    <div class="row">
                        <?php foreach ($events as $event): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm event-card">
                                    <?php if (!empty($event['poster_url'])): ?>
                                        <div class="card-img-wrapper">
                                            <img src="<?php echo '../' . htmlspecialchars($event['poster_url']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($event['title']); ?>" style="height: 200px; object-fit: cover;">
                                            <?php if ($event['is_featured']): ?>
                                                <div class="featured-badge">
                                                    <span class="badge badge-warning"><i class="fas fa-star"></i> Featured</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php if ($event['is_featured']): ?>
                                            <div class="card-header bg-warning text-white">
                                                <i class="fas fa-star mr-1"></i> Featured Event
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                        
                                        <p class="card-text small text-muted mb-2">
                                            <i class="far fa-calendar-alt mr-1"></i> <?php echo date("d M Y", strtotime($event['event_date'])); ?>
                                            <i class="far fa-clock ml-2 mr-1"></i> <?php echo date("h:i A", strtotime($event['event_time'])); ?>
                                        </p>
                                        
                                        <p class="card-text small text-muted mb-2">
                                            <i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($event['venue']); ?>
                                        </p>
                                        
                                        <p class="card-text small mb-2">
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($event['category_name']); ?></span>
                                            <?php foreach ($event['tags'] as $tag): ?>
                                                <?php if (in_array($tag, $user_interests)): ?>
                                                    <span class="badge badge-success"><?php echo htmlspecialchars($tag); ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-info"><?php echo htmlspecialchars($tag); ?></span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </p>
                                        
                                        <p class="card-text">
                                            <?php 
                                            $desc = htmlspecialchars($event['description']);
                                            echo (strlen($desc) > 100) ? substr($desc, 0, 100) . '...' : $desc;
                                            ?>
                                        </p>
                                    </div>
                                    
                                    <div class="card-footer bg-white border-0">
                                        <?php if ($event['is_registered']): ?>
                                            <span class="badge badge-success p-2 mb-2"><i class="fas fa-check-circle mr-1"></i> You are registered</span>
                                        <?php elseif ($event['registration_closed'] || $event['is_full'] || $event['event_date'] < date('Y-m-d')): ?>
                                            <?php if ($event['registration_closed']): ?>
                                                <span class="badge badge-secondary p-2 mb-2"><i class="fas fa-times-circle mr-1"></i> Registration closed</span>
                                            <?php elseif ($event['is_full']): ?>
                                                <span class="badge badge-secondary p-2 mb-2"><i class="fas fa-users-slash mr-1"></i> Event full</span>
                                            <?php elseif ($event['event_date'] < date('Y-m-d')): ?>
                                                <span class="badge badge-secondary p-2 mb-2"><i class="fas fa-hourglass-end mr-1"></i> Event ended</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" name="register" class="btn btn-sm btn-primary mb-2">Register Now</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                
                <!-- List View -->
                <?php elseif ($view_mode === 'list'): ?>
                    <div class="list-group">
                        <?php foreach ($events as $event): ?>
                            <div class="list-group-item list-group-item-action flex-column align-items-start p-4 mb-3 border-0 shadow-sm">
                                <div class="d-flex w-100 justify-content-between align-items-center mb-2">
                                    <h5 class="mb-1">
                                        <?php if ($event['is_featured']): ?>
                                            <span class="badge badge-warning mr-2"><i class="fas fa-star"></i> Featured</span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </h5>
                                    <small>
                                        <span class="badge badge-primary"><?php echo htmlspecialchars($event['category_name']); ?></span>
                                    </small>
                                </div>
                                
                                <div class="mb-2 text-muted">
                                    <i class="far fa-calendar-alt mr-1"></i> <?php echo date("d M Y", strtotime($event['event_date'])); ?> 
                                    <i class="far fa-clock ml-3 mr-1"></i> <?php echo date("h:i A", strtotime($event['event_time'])); ?>
                                    <i class="fas fa-map-marker-alt ml-3 mr-1"></i> <?php echo htmlspecialchars($event['venue']); ?>
                                </div>
                                
                                <p class="mb-2">
                                    <?php 
                                    $desc = htmlspecialchars($event['description']);
                                    echo (strlen($desc) > 200) ? substr($desc, 0, 200) . '...' : $desc;
                                    ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php foreach ($event['tags'] as $tag): ?>
                                            <?php if (in_array($tag, $user_interests)): ?>
                                                <span class="badge badge-success mr-1"><?php echo htmlspecialchars($tag); ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-info mr-1"><?php echo htmlspecialchars($tag); ?></span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="d-flex align-items-center">
                                        <?php if ($event['is_registered']): ?>
                                            <span class="badge badge-success p-2 mr-2"><i class="fas fa-check-circle mr-1"></i> Registered</span>
                                        <?php elseif ($event['registration_closed'] || $event['is_full'] || $event['event_date'] < date('Y-m-d')): ?>
                                            <?php if ($event['registration_closed']): ?>
                                                <span class="badge badge-secondary p-2 mr-2"><i class="fas fa-times-circle mr-1"></i> Registration closed</span>
                                            <?php elseif ($event['is_full']): ?>
                                                <span class="badge badge-secondary p-2 mr-2"><i class="fas fa-users-slash mr-1"></i> Event full</span>
                                            <?php elseif ($event['event_date'] < date('Y-m-d')): ?>
                                                <span class="badge badge-secondary p-2 mr-2"><i class="fas fa-hourglass-end mr-1"></i> Event ended</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="mr-2">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" name="register" class="btn btn-sm btn-primary">Register Now</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                
                <!-- Calendar View -->
                <?php elseif ($view_mode === 'calendar'): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <?php
                            // Calendar header
                            $month = date('n');
                            $year = date('Y');
                            $month_name = date('F Y');
                            $num_days = date('t');
                            $first_day_of_month = date('N', strtotime("$year-$month-01"));
                            ?>
                            
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4><?php echo $month_name; ?></h4>
                                <div>
                                    <a href="#" class="btn btn-sm btn-outline-primary mr-2" id="prevMonth">
                                        <i class="fas fa-chevron-left"></i> Previous Month
                                    </a>
                                    <a href="#" class="btn btn-sm btn-outline-primary" id="nextMonth">
                                        Next Month <i class="fas fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <table class="table table-bordered calendar-table">
                                <thead>
                                    <tr>
                                        <th>Mon</th>
                                        <th>Tue</th>
                                        <th>Wed</th>
                                        <th>Thu</th>
                                        <th>Fri</th>
                                        <th>Sat</th>
                                        <th>Sun</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Calendar body
                                    $day_count = 1;
                                    $calendar_rows = ceil(($num_days + $first_day_of_month - 1) / 7);
                                    
                                    for ($row = 1; $row <= $calendar_rows; $row++) {
                                        echo "<tr>";
                                        
                                        for ($col = 1; $col <= 7; $col++) {
                                            // Adjust for the fact that our calendar starts on Monday (ISO-8601)
                                            $day_num = $day_count - $first_day_of_month + 1;
                                            
                                            if ($day_count < $first_day_of_month || $day_num > $num_days) {
                                                // Empty cell
                                                echo "<td class='bg-light'></td>";
                                            } else {
                                                // Format the date for lookup
                                                $date_str = sprintf("%04d-%02d-%02d", $year, $month, $day_num);
                                                $is_today = ($date_str === date('Y-m-d'));
                                                $has_events = isset($calendar_events[$date_str]);
                                                
                                                // Determine cell classes
                                                $cell_classes = [];
                                                if ($is_today) $cell_classes[] = 'bg-primary text-white';
                                                else if ($has_events) $cell_classes[] = 'bg-light-blue';
                                                
                                                echo "<td class='" . implode(' ', $cell_classes) . "'>";
                                                
                                                // Day number
                                                echo "<div class='day-number'>" . $day_num . "</div>";
                                                
                                                // Events for this day
                                                if ($has_events) {
                                                    echo "<div class='day-events'>";
                                                    foreach ($calendar_events[$date_str] as $cal_event) {
                                                        echo "<a href='event_details.php?id=" . $cal_event['id'] . "' class='calendar-event'>";
                                                        echo htmlspecialchars($cal_event['title']);
                                                        echo "</a>";
                                                    }
                                                    echo "</div>";
                                                }
                                                
                                                echo "</td>";
                                            }
                                            
                                            $day_count++;
                                        }
                                        
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                            
                            <div class="mt-3 text-center">
                                <span class="calendar-legend mr-3">
                                    <span class="legend-color bg-primary"></span> Today
                                </span>
                                <span class="calendar-legend">
                                    <span class="legend-color bg-light-blue"></span> Events
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1 && $view_mode !== 'calendar'): ?>
                    <div class="mt-4">
                        <nav aria-label="Event pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo getPaginationUrl(1); ?>">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo getPaginationUrl($page - 1); ?>">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo getPaginationUrl($i); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo getPaginationUrl($page + 1); ?>">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo getPaginationUrl($total_pages); ?>">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                        <h3 class="text-muted">No events found</h3>
                        <?php if (!empty($category_filter) || !empty($tag_filter) || !empty($search_term) || $time_filter !== 'upcoming'): ?>
                            <p>No events match your current filters. Try changing or clearing your filters.</p>
                            <a href="events.php" class="btn btn-primary mt-2">
                                <i class="fas fa-times mr-2"></i> Clear All Filters
                            </a>
                        <?php else: ?>
                            <p>There are no upcoming events at this time. Check back later!</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer class="bg-light mt-5 py-3">
    <div class="container text-center">
        <p class="mb-0">&copy; <?php echo date("Y"); ?> EventSphere@UPSI. All rights reserved.</p>
    </div>
</footer>

<!-- Custom Calendar Styles -->
<style>
.calendar-table {
    table-layout: fixed;
}

.calendar-table th, 
.calendar-table td {
    height: 100px;
    vertical-align: top;
    width: 14.28%;
}

.day-number {
    font-weight: bold;
    margin-bottom: 5px;
}

.day-events {
    font-size: 0.8rem;
    overflow: auto;
    max-height: 75px;
}

.calendar-event {
    display: block;
    background-color: #e8f4fd;
    border-left: 3px solid #007bff;
    padding: 2px 5px;
    margin-bottom: 3px;
    border-radius: 3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.calendar-event:hover {
    background-color: #d0e8fb;
    text-decoration: none;
}

.bg-light-blue {
    background-color: #f0f7ff;
}

.calendar-legend {
    display: inline-flex;
    align-items: center;
    font-size: 0.875rem;
}

.legend-color {
    display: inline-block;
    width: 15px;
    height: 15px;
    margin-right: 5px;
    border-radius: 3px;
}

.event-card {
    transition: all 0.3s ease;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12) !important;
}

.card-img-wrapper {
    position: relative;
}

.featured-badge {
    position: absolute;
    top: 10px;
    right: 10px;
}
</style>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
$(document).ready(function() {
    // Calendar navigation functionality would go here
    // This would require additional server-side code to handle month navigation
    $('#prevMonth, #nextMonth').click(function(e) {
        e.preventDefault();
        alert("Calendar navigation would be implemented in a production environment");
    });
});
</script>
</body>
</html>