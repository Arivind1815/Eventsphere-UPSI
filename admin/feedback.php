<?php
/**
 * Admin Feedback Management
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Feedback Management";

// Include header
include_once '../include/admin_header.php';

// Check if admin is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: ../login.php");
    exit;
}

// Initialize variables for filtering
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$rating_filter = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination
$items_per_page = 10;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Build the base SQL query
$sql_base = "SELECT f.*, e.title as event_title, e.event_date, 
                    c.name as category_name, u.name as student_name, u.matric_id 
             FROM feedback f
             JOIN events e ON f.event_id = e.id
             JOIN event_categories c ON e.category_id = c.id
             JOIN users u ON f.user_id = u.id
             WHERE 1=1";

// Add filters to SQL
$sql_params = array();
$param_types = "";

if ($category_id > 0) {
    $sql_base .= " AND e.category_id = ?";
    $sql_params[] = $category_id;
    $param_types .= "i";
}

if ($rating_filter > 0) {
    $sql_base .= " AND f.rating = ?";
    $sql_params[] = $rating_filter;
    $param_types .= "i";
}

if (!empty($search)) {
    $search_param = "%$search%";
    $sql_base .= " AND (e.title LIKE ? OR u.name LIKE ? OR u.matric_id LIKE ?)";
    $sql_params[] = $search_param;
    $sql_params[] = $search_param;
    $sql_params[] = $search_param;
    $param_types .= "sss";
}
error_reporting(0);
ini_set('display_errors', 0);
// Count total results for pagination
$count_sql = str_replace("f.*, e.title as event_title, e.event_date, c.name as category_name, u.name as student_name, u.matric_id", "COUNT(*) as total", $sql_base);
$total_items = 0;

if ($count_stmt = $conn->prepare($count_sql)) {
    if (!empty($param_types)) {
        $count_stmt->bind_param($param_types, ...$sql_params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    if ($count_row = $count_result->fetch_assoc()) {
        $total_items = $count_row['total'];
    }
    $count_stmt->close();
}

$total_pages = ceil($total_items / $items_per_page);

// Complete the SQL for the actual results
$sql = $sql_base . " ORDER BY f.submission_date DESC LIMIT ? OFFSET ?";
$sql_params[] = $items_per_page;
$sql_params[] = $offset;
$param_types .= "ii";

// Get feedback data
$feedback_data = array();
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param($param_types, ...$sql_params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $feedback_data[] = $row;
    }
    
    $stmt->close();
}

// Get categories for filter dropdown
$categories = array();
$cat_sql = "SELECT id, name FROM event_categories ORDER BY name ASC";
$cat_result = $conn->query($cat_sql);
if ($cat_result && $cat_result->num_rows > 0) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get average ratings
$avg_rating = 0;
$avg_sql = "SELECT AVG(rating) as avg_rating FROM feedback";
$avg_result = $conn->query($avg_sql);
if ($avg_result && $avg_result->num_rows > 0) {
    $row = $avg_result->fetch_assoc();
    $avg_rating = round($row['avg_rating'], 1);
}

// Get rating distribution
$rating_dist = array();
for ($i = 1; $i <= 5; $i++) {
    $dist_sql = "SELECT COUNT(*) as count FROM feedback WHERE rating = $i";
    $dist_result = $conn->query($dist_sql);
    if ($dist_result && $dist_result->num_rows > 0) {
        $row = $dist_result->fetch_assoc();
        $rating_dist[$i] = $row['count'];
    } else {
        $rating_dist[$i] = 0;
    }
}
$total_ratings = array_sum($rating_dist);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h3 mb-0">Feedback Management</h1>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Feedback Overview -->
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Feedback Overview</h4>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h2 class="display-4 font-weight-bold text-warning"><?php echo $avg_rating; ?></h2>
                        <p class="text-muted">Average Rating</p>
                        <div class="star-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo ($i <= $avg_rating) ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="mt-2">Based on <?php echo $total_ratings; ?> reviews</p>
                    </div>

                    <h5 class="mb-3">Rating Distribution</h5>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <?php 
                        $percent = ($total_ratings > 0) ? ($rating_dist[$i] / $total_ratings) * 100 : 0;
                        ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo $i; ?> Star</span>
                                <span><?php echo $rating_dist[$i]; ?> (<?php echo round($percent); ?>%)</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $percent; ?>%" aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <div class="col-md-8 mb-4">
            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Filter Feedback</h4>
                </div>
                <div class="card-body">
                    <form method="get" action="feedback.php" class="form-row">
                        <div class="form-group col-md-4">
                            <label for="category">Event Category</label>
                            <select name="category" id="category" class="form-control">
                                <option value="0">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="rating">Rating</label>
                            <select name="rating" id="rating" class="form-control">
                                <option value="0">All Ratings</option>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($rating_filter == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> Stars
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-5">
                            <label for="search">Search</label>
                            <div class="input-group">
                                <input type="text" name="search" id="search" class="form-control" placeholder="Event title, student name or ID" value="<?php echo htmlspecialchars($search); ?>">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Export Options -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Export Options</h5>
                        <div>
                            <a href="export_feedback.php?format=csv<?php echo (!empty($_SERVER['QUERY_STRING'])) ? '&' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-sm btn-outline-success mr-2">
                                <i class="fas fa-file-csv mr-1"></i> Export as CSV
                            </a>
                            <a href="export_feedback.php?format=pdf<?php echo (!empty($_SERVER['QUERY_STRING'])) ? '&' . $_SERVER['QUERY_STRING'] : ''; ?>" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-file-pdf mr-1"></i> Export as PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Listing -->
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Feedback Submissions</h4>
                    <span class="badge badge-primary badge-pill p-2">
                        <?php echo $total_items; ?> Results
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if (count($feedback_data) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Student</th>
                                        <th>Rating</th>
                                        <th>Comments</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($feedback_data as $feedback): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($feedback['event_title']); ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo date("d M Y", strtotime($feedback['event_date'])); ?> · 
                                                    <span class="badge badge-info"><?php echo htmlspecialchars($feedback['category_name']); ?></span>
                                                </small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($feedback['student_name']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($feedback['matric_id']); ?></small>
                                            </td>
                                            <td>
                                                <div class="star-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo ($i <= $feedback['rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-link view-comments" data-toggle="modal" data-target="#commentsModal" data-comments="<?php echo htmlspecialchars($feedback['comments']); ?>" data-event="<?php echo htmlspecialchars($feedback['event_title']); ?>" data-student="<?php echo htmlspecialchars($feedback['student_name']); ?>">
                                                    View Comments
                                                </button>
                                            </td>
                                            <td><?php echo date("d M Y, h:i A", strtotime($feedback['submission_date'])); ?></td>
                                            <td>
                                                <a href="view_event.php?id=<?php echo $feedback['event_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> Event
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-white">
                                <nav aria-label="Feedback pagination">
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo (!empty($_SERVER['QUERY_STRING'])) ? '&' . preg_replace('/page=\d+&?/', '', $_SERVER['QUERY_STRING']) : ''; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo (!empty($_SERVER['QUERY_STRING'])) ? '&' . preg_replace('/page=\d+&?/', '', $_SERVER['QUERY_STRING']) : ''; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo (!empty($_SERVER['QUERY_STRING'])) ? '&' . preg_replace('/page=\d+&?/', '', $_SERVER['QUERY_STRING']) : ''; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comment-slash fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">No feedback found</h4>
                            <p>There are no feedback submissions matching your criteria.</p>
                            <a href="feedback.php" class="btn btn-primary mt-2">
                                <i class="fas fa-sync-alt mr-2"></i> Reset Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Comments Modal -->
<div class="modal fade" id="commentsModal" tabindex="-1" aria-labelledby="commentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="commentsModalLabel">Feedback Comments</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><strong>Event:</strong> <span id="modalEventTitle"></span></p>
                <p><strong>Student:</strong> <span id="modalStudentName"></span></p>
                <div class="card bg-light">
                    <div class="card-body">
                        <p id="modalComments" class="mb-0"></p>
                    </div>
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
    // View comments modal
    $('.view-comments').click(function() {
        const comments = $(this).data('comments');
        const event = $(this).data('event');
        const student = $(this).data('student');
        
        $('#modalComments').text(comments);
        $('#modalEventTitle').text(event);
        $('#modalStudentName').text(student);
    });
});
</script>
</body>
</html>