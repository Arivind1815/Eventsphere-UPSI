<?php
/**
 * Student Notifications
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Notifications";

// Include header
include_once '../include/student_header.php';

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $_SESSION['error_message'] = "You must be logged in to view your notifications.";
    header("location: login.php");
    exit;
}

// Get user id
$user_id = $_SESSION["id"];

// Handle mark as read for individual notification
if (isset($_GET['mark_read']) && !empty($_GET['mark_read'])) {
    $notification_id = intval($_GET['mark_read']);

    $sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $stmt->close();

        // Redirect to remove the query parameter
        header("location: notifications.php");
        exit;
    }
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success_message'] = "All notifications marked as read.";
        header("location: notifications.php");
        exit;
    }
}

// Handle delete all notifications
if (isset($_GET['delete_all'])) {
    $sql = "DELETE FROM notifications WHERE user_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success_message'] = "All notifications have been deleted.";
        header("location: notifications.php");
        exit;
    }
}

// Get user notifications with pagination
$notifications_per_page = 10;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $notifications_per_page;

// Get total number of notifications
$total_notifications = 0;
$count_sql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
if ($stmt = $conn->prepare($count_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $total_notifications = $row['total'];
    }
    $stmt->close();
}

// Calculate total pages
$total_pages = ceil($total_notifications / $notifications_per_page);

// Get notifications
$notifications = array();
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("iii", $user_id, $notifications_per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $stmt->close();
}

// Count unread notifications
$unread_count = 0;
$unread_sql = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND is_read = 0";
if ($stmt = $conn->prepare($unread_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $unread_count = $row['total'];
    }
    $stmt->close();
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h1 class="h3 mb-0">Notifications</h1>

                        <div>
                            <?php if ($total_notifications > 0): ?>
                                <?php if ($unread_count > 0): ?>
                                    <a href="notifications.php?mark_all_read=1" class="btn btn-outline-primary mr-2">
                                        <i class="fas fa-check-double mr-2"></i> Mark All as Read
                                    </a>
                                <?php endif; ?>

                                <a href="notifications.php?delete_all=1" class="btn btn-outline-danger"
                                    onclick="return confirm('Are you sure you want to delete all notifications? This action cannot be undone.');">
                                    <i class="fas fa-trash mr-2"></i> Clear All
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">All Notifications</h4>

                        <?php if ($unread_count > 0): ?>
                            <span class="badge badge-primary badge-pill p-2">
                                <?php echo $unread_count; ?> Unread
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body p-0">
                    <?php if (count($notifications) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="list-group-item list-group-item-action 
                                    <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1">
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge badge-primary mr-2">New</span>
                                                <?php endif; ?>

                                                <?php echo htmlspecialchars($notification['title']); ?>
                                            </h5>

                                            <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>

                                            <small class="text-muted">
                                                <i class="far fa-clock mr-1"></i>
                                                <?php echo date("M j, Y g:i A", strtotime($notification['created_at'])); ?>
                                            </small>
                                        </div>

                                        <div class="d-flex align-items-center">
                                            <?php if ($notification['related_id'] && $notification['type'] != 'feedback_reminder'): ?>
                                                <a href="event_details.php?id=<?php echo $notification['related_id']; ?>"
                                                    class="btn btn-sm btn-outline-primary mr-2">
                                                    <i class="fas fa-external-link-alt mr-1"></i> View
                                                </a>
                                            <?php elseif ($notification['related_id'] && $notification['type'] == 'feedback_reminder'): ?>
                                                <a href="feedback.php?event=<?php echo $notification['related_id']; ?>"
                                                    class="btn btn-sm btn-outline-primary mr-2">
                                                    <i class="fas fa-comment mr-1"></i> View
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!$notification['is_read']): ?>
                                                <a href="notifications.php?mark_read=<?php echo $notification['id']; ?>"
                                                    class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-check mr-1"></i> Mark as Read
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-white">
                                <nav aria-label="Notification pagination">
                                    <ul class="pagination justify-content-center mb-0">
                                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>

                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">No notifications</h4>
                            <p>You don't have any notifications at the moment.</p>
                            <a href="index.php" class="btn btn-primary mt-2">
                                <i class="fas fa-home mr-2"></i> Back to Dashboard
                            </a>
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