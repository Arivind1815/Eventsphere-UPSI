<?php
/**
 * Event Management
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Event Management";

// Include header
include_once '../include/admin_header.php';

// Auto-remove past events that ended more than 1 week ago (optional cleanup)
$cleanup_sql = "DELETE FROM events WHERE CONCAT(COALESCE(event_end_date, event_date), ' ', COALESCE(event_end_time, '23:59:59')) < DATE_SUB(NOW(), INTERVAL 7 DAY)";
$conn->query($cleanup_sql);

// Process delete event action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $event_id = intval($_GET['id']);
    
    if ($event_id > 0) {
        // Check if event exists and get its details
        $check_sql = "SELECT id, title, event_date, event_end_date, event_end_time FROM events WHERE id = ?";
        if ($stmt = $conn->prepare($check_sql)) {
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                // Allow deletion of both past and upcoming events
                // Allow deletion of both past and upcoming events
                
                // Start transaction for data integrity
                $conn->begin_transaction();
                
                try {
                    // Delete related records first to maintain referential integrity
                    
                    // Delete event tags
                    $delete_tags_sql = "DELETE FROM event_tags WHERE event_id = ?";
                    if ($delete_tags_stmt = $conn->prepare($delete_tags_sql)) {
                        $delete_tags_stmt->bind_param("i", $event_id);
                        $delete_tags_stmt->execute();
                        $delete_tags_stmt->close();
                    }
                    
                    // Delete attendance records
                    $delete_attendance_sql = "DELETE FROM attendance WHERE event_id = ?";
                    if ($delete_attendance_stmt = $conn->prepare($delete_attendance_sql)) {
                        $delete_attendance_stmt->bind_param("i", $event_id);
                        $delete_attendance_stmt->execute();
                        $delete_attendance_stmt->close();
                    }
                    
                    // Delete user points related to this event
                    $delete_points_sql = "DELETE FROM user_points WHERE event_id = ?";
                    if ($delete_points_stmt = $conn->prepare($delete_points_sql)) {
                        $delete_points_stmt->bind_param("i", $event_id);
                        $delete_points_stmt->execute();
                        $delete_points_stmt->close();
                    }
                    
                    // Delete feedback records
                    $delete_feedback_sql = "DELETE FROM feedback WHERE event_id = ?";
                    if ($delete_feedback_stmt = $conn->prepare($delete_feedback_sql)) {
                        $delete_feedback_stmt->bind_param("i", $event_id);
                        $delete_feedback_stmt->execute();
                        $delete_feedback_stmt->close();
                    }
                    
                    // Get registered users for notifications before deleting registrations
                    $users_sql = "SELECT user_id FROM event_registrations WHERE event_id = ? AND status = 'registered'";
                    $registered_users = [];
                    if ($users_stmt = $conn->prepare($users_sql)) {
                        $users_stmt->bind_param("i", $event_id);
                        $users_stmt->execute();
                        $users_result = $users_stmt->get_result();
                        while ($user_row = $users_result->fetch_assoc()) {
                            $registered_users[] = $user_row['user_id'];
                        }
                        $users_stmt->close();
                    }
                    
                    // Delete event registrations
                    $delete_registrations_sql = "DELETE FROM event_registrations WHERE event_id = ?";
                    if ($delete_registrations_stmt = $conn->prepare($delete_registrations_sql)) {
                        $delete_registrations_stmt->bind_param("i", $event_id);
                        $delete_registrations_stmt->execute();
                        $delete_registrations_stmt->close();
                    }
                    
                    // Delete notifications related to this event
                    $delete_notifications_sql = "DELETE FROM notifications WHERE related_id = ? AND type IN ('event_reminder', 'registration_confirmation', 'attendance_confirmation', 'points_added', 'feedback_reminder')";
                    if ($delete_notifications_stmt = $conn->prepare($delete_notifications_sql)) {
                        $delete_notifications_stmt->bind_param("i", $event_id);
                        $delete_notifications_stmt->execute();
                        $delete_notifications_stmt->close();
                    }
                    
                    // Finally, delete the event itself
                    $delete_event_sql = "DELETE FROM events WHERE id = ?";
                    if ($delete_event_stmt = $conn->prepare($delete_event_sql)) {
                        $delete_event_stmt->bind_param("i", $event_id);
                        
                        if ($delete_event_stmt->execute()) {
                            // Send notifications to registered users about event deletion
                            foreach ($registered_users as $user_id) {
                                $notif_sql = "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'system')";
                                if ($notif_stmt = $conn->prepare($notif_sql)) {
                                    $title = "Event Deleted";
                                    $message = "The event \"" . $row['title'] . "\" has been deleted by the organizer.";
                                    $notif_stmt->bind_param("iss", $user_id, $title, $message);
                                    $notif_stmt->execute();
                                    $notif_stmt->close();
                                }
                            }
                            
                            // Commit transaction
                            $conn->commit();
                            $_SESSION['success_message'] = "Event \"" . htmlspecialchars($row['title']) . "\" deleted successfully.";
                        } else {
                            throw new Exception("Failed to delete event");
                        }
                        
                        $delete_event_stmt->close();
                    } else {
                        throw new Exception("Failed to prepare delete statement");
                    }
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $_SESSION['error_message'] = "Error deleting event: " . $e->getMessage();
                }
                
            } else {
                $_SESSION['error_message'] = "Event not found.";
            }
            
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Database error occurred.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid event ID.";
    }
    
    // Redirect to prevent resubmission
    header("Location: events.php");
    exit;
}

// Toggle featured status
if (isset($_GET['action']) && $_GET['action'] == 'toggle_featured' && isset($_GET['id'])) {
    $event_id = intval($_GET['id']);
    
    // Get current featured status
    $featured_sql = "SELECT is_featured FROM events WHERE id = ?";
    if ($stmt = $conn->prepare($featured_sql)) {
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $new_status = $row['is_featured'] ? 0 : 1;
            
            // Update featured status
            $update_sql = "UPDATE events SET is_featured = ? WHERE id = ?";
            if ($update_stmt = $conn->prepare($update_sql)) {
                $update_stmt->bind_param("ii", $new_status, $event_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['success_message'] = "Event featured status updated.";
                } else {
                    $_SESSION['error_message'] = "Error updating featured status.";
                }
                
                $update_stmt->close();
            }
        }
        
        $stmt->close();
    }
    
    // Redirect to prevent resubmission
    header("Location: events.php");
    exit;
}

// Handle filter and search
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Build query based on filter (time-sensitive)
$sql_conditions = [];
$params = [];
$param_types = "";

if ($filter == 'upcoming') {
    $sql_conditions[] = "CONCAT(COALESCE(e.event_end_date, e.event_date), ' ', COALESCE(e.event_end_time, '23:59:59')) > NOW()";
} elseif ($filter == 'past') {
    $sql_conditions[] = "CONCAT(COALESCE(e.event_end_date, e.event_date), ' ', COALESCE(e.event_end_time, '23:59:59')) <= NOW()";
} elseif ($filter == 'featured') {
    $sql_conditions[] = "e.is_featured = 1";
}

// Add search condition if provided
if (!empty($search)) {
    $sql_conditions[] = "(e.title LIKE ? OR e.description LIKE ? OR e.venue LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param);
    $param_types .= "sss";
}

// Add category filter if provided
if ($category > 0) {
    $sql_conditions[] = "e.category_id = ?";
    array_push($params, $category);
    $param_types .= "i";
}

// Combine conditions
$where_clause = !empty($sql_conditions) ? "WHERE " . implode(" AND ", $sql_conditions) : "";

// Order by date
$order_by = $filter == 'past' ? "ORDER BY e.event_date DESC, e.event_time DESC" : "ORDER BY e.event_date ASC, e.event_time ASC";

// Get events with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

$events_sql = "SELECT e.*, c.name as category_name, 
               (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND status = 'registered') as registrations,
               CASE 
                   WHEN CONCAT(COALESCE(e.event_end_date, e.event_date), ' ', COALESCE(e.event_end_time, '23:59:59')) <= NOW() 
                   THEN 'past' 
                   ELSE 'upcoming' 
               END as event_status
               FROM events e 
               JOIN event_categories c ON e.category_id = c.id 
               $where_clause 
               $order_by 
               LIMIT ?, ?";

$events = [];
if ($stmt = $conn->prepare($events_sql)) {
    // Add pagination params
    array_push($params, $offset, $records_per_page);
    $param_types .= "ii";
    
    // Bind parameters if any
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    
    $stmt->close();
}

// Count total records for pagination
$count_sql = "SELECT COUNT(*) as total FROM events e JOIN event_categories c ON e.category_id = c.id $where_clause";
$total_records = 0;

if ($count_stmt = $conn->prepare($count_sql)) {
    // Bind parameters if any (except pagination)
    if (!empty($params)) {
        // Remove the last two pagination parameters
        array_pop($params);
        array_pop($params);
        
        if (!empty($params)) {
            $count_stmt->bind_param(substr($param_types, 0, -2), ...$params);
        }
    }
    
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    
    if ($count_row = $count_result->fetch_assoc()) {
        $total_records = $count_row['total'];
    }
    
    $count_stmt->close();
}

$total_pages = ceil($total_records / $records_per_page);

// Get event categories for filter
$categories = [];
$categories_sql = "SELECT id, name FROM event_categories ORDER BY name";
$categories_result = $conn->query($categories_sql);

if ($categories_result) {
    while ($category_row = $categories_result->fetch_assoc()) {
        $categories[] = $category_row;
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-0">Event Management</h1>
                        <p class="text-muted">Create, update, and manage events</p>
                    </div>
                    <a href="create_event.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle mr-2"></i> Create New Event
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i><?php echo $_SESSION['success_message']; ?>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $_SESSION['error_message']; ?>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- Filters and Search -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="events.php" method="get" class="row">
                        <!-- Status Filter -->
                        <div class="col-md-3 mb-3">
                            <label for="filter">Status</label>
                            <select name="filter" id="filter" class="form-control">
                                <option value="upcoming" <?php echo $filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming Events</option>
                                <option value="past" <?php echo $filter == 'past' ? 'selected' : ''; ?>>Past Events</option>
                                <option value="featured" <?php echo $filter == 'featured' ? 'selected' : ''; ?>>Featured Events</option>
                                <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Events</option>
                            </select>
                        </div>

                        <!-- Category Filter -->
                        <div class="col-md-3 mb-3">
                            <label for="category">Category</label>
                            <select name="category" id="category" class="form-control">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Search Box -->
                        <div class="col-md-4 mb-3">
                            <label for="search">Search</label>
                            <input type="text" name="search" id="search" class="form-control" placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <!-- Submit Button -->
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-search mr-2"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Events Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">
                        <?php
                        if ($filter == 'upcoming') echo 'Upcoming Events';
                        elseif ($filter == 'past') echo 'Past Events';
                        elseif ($filter == 'featured') echo 'Featured Events';
                        else echo 'All Events';
                        
                        if (!empty($search)) {
                            echo ' - Search: "' . htmlspecialchars($search) . '"';
                        }
                        
                        if ($category > 0) {
                            foreach ($categories as $cat) {
                                if ($cat['id'] == $category) {
                                    echo ' - Category: ' . htmlspecialchars($cat['name']);
                                    break;
                                }
                            }
                        }
                        ?>
                        <span class="badge badge-primary ml-2"><?php echo $total_records; ?></span>
                    </h4>
                </div>
                <div class="card-body p-0">
                    <?php if (count($events) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Date & Time</th>
                                        <th>Venue</th>
                                        <th>Category</th>
                                        <th>Registrations</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <tr <?php echo $event['event_status'] == 'past' ? 'class="table-secondary"' : ''; ?>>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($event['is_featured']): ?>
                                                        <span class="badge badge-warning mr-2"><i class="fas fa-star"></i></span>
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                                        <?php if ($event['event_status'] == 'past'): ?>
                                                            <small class="d-block text-muted">Completed</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php echo date("d M Y", strtotime($event['event_date'])); ?>
                                                    <?php if ($event['event_end_date'] && $event['event_end_date'] != $event['event_date']): ?>
                                                        - <?php echo date("d M Y", strtotime($event['event_end_date'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date("h:i A", strtotime($event['event_time'])); ?>
                                                    <?php if ($event['event_end_time']): ?>
                                                        - <?php echo date("h:i A", strtotime($event['event_end_time'])); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo htmlspecialchars($event['category_name']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-success">
                                                    <?php echo $event['registrations']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($event['event_status'] == 'past'): ?>
                                                    <span class="badge badge-secondary">Past</span>
                                                <?php else: ?>
                                                    <span class="badge badge-primary">Upcoming</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="view_event.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if ($event['event_status'] == 'upcoming'): ?>
                                                        <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Event">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        
                                                        <a href="events.php?action=toggle_featured&id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-warning" title="<?php echo $event['is_featured'] ? 'Remove from Featured' : 'Mark as Featured'; ?>">
                                                            <i class="fas <?php echo $event['is_featured'] ? 'fa-star-half-alt' : 'fa-star'; ?>"></i>
                                                        </a>
                                                        
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-event" 
                                                                data-id="<?php echo $event['id']; ?>" 
                                                                data-title="<?php echo htmlspecialchars($event['title']); ?>" 
                                                                data-registrations="<?php echo $event['registrations']; ?>"
                                                                title="Delete Event">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-event" 
                                                                data-id="<?php echo $event['id']; ?>" 
                                                                data-title="<?php echo htmlspecialchars($event['title']); ?>" 
                                                                data-registrations="<?php echo $event['registrations']; ?>"
                                                                title="Delete Event">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-white">
                                <nav aria-label="Event pagination">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1<?php echo !empty($filter) ? '&filter=' . $filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category > 0 ? '&category=' . $category : ''; ?>">
                                                    <i class="fas fa-angle-double-left"></i>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($filter) ? '&filter=' . $filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category > 0 ? '&category=' . $category : ''; ?>">
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
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($filter) ? '&filter=' . $filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category > 0 ? '&category=' . $category : ''; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($filter) ? '&filter=' . $filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category > 0 ? '&category=' . $category : ''; ?>">
                                                    <i class="fas fa-angle-right"></i>
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($filter) ? '&filter=' . $filter : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $category > 0 ? '&category=' . $category : ''; ?>">
                                                    <i class="fas fa-angle-double-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No events found</h5>
                            <p>Try changing your search criteria or create a new event.</p>
                            <a href="create_event.php" class="btn btn-primary mt-2">
                                <i class="fas fa-plus-circle mr-2"></i> Create New Event
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Confirm Delete Event
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Warning:</strong> This action cannot be undone and will have the following effects:
                </div>
                
                <h5 class="text-danger mb-3">Event to be deleted:</h5>
                <h4 id="eventTitle" class="text-primary mb-4"></h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>What will be deleted:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-danger mr-2"></i>Event details and information</li>
                            <li><i class="fas fa-check text-danger mr-2"></i>All event registrations (<span id="registrationCount">0</span> users affected)</li>
                            <li><i class="fas fa-check text-danger mr-2"></i>Event tags and categories</li>
                            <li><i class="fas fa-check text-danger mr-2"></i>Attendance records</li>
                            <li><i class="fas fa-check text-danger mr-2"></i>User points earned from this event</li>
                            <li><i class="fas fa-check text-danger mr-2"></i>Feedback and notifications</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>What will happen:</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-bell text-info mr-2"></i>Registered users will be notified</li>
                            <li><i class="fas fa-undo text-warning mr-2"></i>Registration fees may need manual refund</li>
                            <li><i class="fas fa-calendar-times text-secondary mr-2"></i>Event will be removed from all calendars</li>
                            <li><i class="fas fa-chart-line text-primary mr-2"></i>Statistics will be updated</li>
                        </ul>
                    </div>
                </div>
                
                <div class="form-group mt-3">
                    <label for="deleteReason">Reason for deletion (optional):</label>
                    <textarea class="form-control" id="deleteReason" rows="3" placeholder="Enter reason for deleting this event (will be included in user notifications)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-2"></i>Cancel
                </button>
                <a href="#" id="confirmDelete" class="btn btn-danger">
                    <i class="fas fa-trash-alt mr-2"></i>Delete Event
                </a>
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
    // Handle delete event confirmation with enhanced functionality
    $('.delete-event').click(function() {
        const eventId = $(this).data('id');
        const eventTitle = $(this).data('title');
        const registrationCount = $(this).data('registrations');
        
        // Populate modal with event details
        $('#eventTitle').text(eventTitle);
        $('#registrationCount').text(registrationCount);
        
        // Update delete link
        $('#confirmDelete').attr('href', 'events.php?action=delete&id=' + eventId);
        
        // Show the modal
        $('#deleteModal').modal('show');
    });
    
    // Enhanced confirmation with reason (no extra JavaScript alert)
    $('#confirmDelete').click(function(e) {
        // Show loading state
        $(this).html('<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...').prop('disabled', true);
        
        // Get reason if provided
        const reason = $('#deleteReason').val().trim();
        
        // If reason provided, append it to the URL
        if (reason) {
            const href = $(this).attr('href');
            $(this).attr('href', href + '&reason=' + encodeURIComponent(reason));
        }
        
        return true;
    });
    
    // Reset modal when closed
    $('#deleteModal').on('hidden.bs.modal', function() {
        $('#deleteReason').val('');
        $('#confirmDelete').html('<i class="fas fa-trash-alt mr-2"></i>Delete Event').prop('disabled', false);
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Add confirmation for featured toggle (removed extra alert)
    $('a[href*="toggle_featured"]').click(function(e) {
        // Just show loading state, no confirmation needed for simple toggle
        $(this).html('<i class="fas fa-spinner fa-spin"></i>').addClass('disabled');
    });
    
    // Show loading state for form submissions
    $('form').submit(function() {
        $(this).find('button[type="submit"]').html('<i class="fas fa-spinner fa-spin mr-2"></i>Filtering...').prop('disabled', true);
    });
});

// Function to refresh page data (useful for real-time updates)
function refreshEventData() {
    location.reload();
}

// Auto-refresh every 5 minutes to keep data current (optional)
// setInterval(refreshEventData, 300000);
</script>
</body>
</html>