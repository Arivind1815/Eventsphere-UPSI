<?php
/**
 * Tag Management System
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Tag Management";

// Include header
include_once '../include/admin_header.php';

// Initialize variables
$tag_name = "";
$tag_name_err = "";
$edit_mode = false;
$edit_tag_id = 0;

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'add_tag':
                // Validate tag name
                if (empty(trim($_POST["tag_name"]))) {
                    $tag_name_err = "Please enter a tag name.";
                } else {
                    $tag_name = trim($_POST["tag_name"]);
                    
                    // Check if tag already exists
                    $check_sql = "SELECT COUNT(*) as count FROM event_tags WHERE LOWER(tag) = LOWER(?)";
                    if ($check_stmt = $conn->prepare($check_sql)) {
                        $check_stmt->bind_param("s", $tag_name);
                        $check_stmt->execute();
                        $result = $check_stmt->get_result();
                        $row = $result->fetch_assoc();
                        
                        if ($row['count'] > 0) {
                            $tag_name_err = "This tag already exists.";
                        }
                        $check_stmt->close();
                    }
                }
                
                // Add tag if no errors
                if (empty($tag_name_err)) {
                    $insert_sql = "INSERT INTO event_tags (event_id, tag) VALUES (0, ?)";
                    if ($insert_stmt = $conn->prepare($insert_sql)) {
                        $insert_stmt->bind_param("s", $tag_name);
                        if ($insert_stmt->execute()) {
                            $_SESSION['success_message'] = "Tag added successfully!";
                        } else {
                            $_SESSION['error_message'] = "Error adding tag.";
                        }
                        $insert_stmt->close();
                    }
                    header("location: " . $_SERVER['PHP_SELF']);
                    exit;
                }
                break;
                
            case 'edit_tag':
                $edit_tag_id = intval($_POST['tag_id']);
                $old_tag = trim($_POST['old_tag']);
                $new_tag = trim($_POST['new_tag']);
                
                if (empty($new_tag)) {
                    $_SESSION['error_message'] = "Tag name cannot be empty.";
                } else {
                    // Update all instances of this tag
                    $update_sql = "UPDATE event_tags SET tag = ? WHERE tag = ?";
                    if ($update_stmt = $conn->prepare($update_sql)) {
                        $update_stmt->bind_param("ss", $new_tag, $old_tag);
                        if ($update_stmt->execute()) {
                            $_SESSION['success_message'] = "Tag updated successfully! All events using this tag have been updated.";
                        } else {
                            $_SESSION['error_message'] = "Error updating tag.";
                        }
                        $update_stmt->close();
                    }
                }
                header("location: " . $_SERVER['PHP_SELF']);
                exit;
                break;
                
            case 'delete_tag':
                $delete_tag = trim($_POST['delete_tag']);
                
                // Delete all instances of this tag
                $delete_sql = "DELETE FROM event_tags WHERE tag = ?";
                if ($delete_stmt = $conn->prepare($delete_sql)) {
                    $delete_stmt->bind_param("s", $delete_tag);
                    if ($delete_stmt->execute()) {
                        $_SESSION['success_message'] = "Tag deleted successfully! Removed from all events.";
                    } else {
                        $_SESSION['error_message'] = "Error deleting tag.";
                    }
                    $delete_stmt->close();
                }
                header("location: " . $_SERVER['PHP_SELF']);
                exit;
                break;
                
            case 'bulk_delete':
                if (isset($_POST['selected_tags']) && is_array($_POST['selected_tags'])) {
                    $deleted_count = 0;
                    foreach ($_POST['selected_tags'] as $tag_to_delete) {
                        $delete_sql = "DELETE FROM event_tags WHERE tag = ?";
                        if ($delete_stmt = $conn->prepare($delete_sql)) {
                            $delete_stmt->bind_param("s", $tag_to_delete);
                            if ($delete_stmt->execute()) {
                                $deleted_count++;
                            }
                            $delete_stmt->close();
                        }
                    }
                    $_SESSION['success_message'] = "Deleted $deleted_count tag(s) successfully!";
                } else {
                    $_SESSION['error_message'] = "No tags selected for deletion.";
                }
                header("location: " . $_SERVER['PHP_SELF']);
                exit;
                break;
        }
    }
}

// Get all unique tags with usage count
$tags_sql = "SELECT tag, COUNT(*) as usage_count, 
             GROUP_CONCAT(DISTINCT e.title SEPARATOR ', ') as events
             FROM event_tags et
             LEFT JOIN events e ON et.event_id = e.id 
             WHERE et.event_id > 0
             GROUP BY tag 
             ORDER BY tag ASC";
$tags_result = $conn->query($tags_sql);

$all_tags = [];
if ($tags_result) {
    while ($tag_row = $tags_result->fetch_assoc()) {
        $all_tags[] = $tag_row;
    }
}

// Get orphaned tags (tags with event_id = 0)
$orphaned_sql = "SELECT DISTINCT tag FROM event_tags WHERE event_id = 0 ORDER BY tag ASC";
$orphaned_result = $conn->query($orphaned_sql);
$orphaned_tags = [];
if ($orphaned_result) {
    while ($orphan_row = $orphaned_result->fetch_assoc()) {
        $orphaned_tags[] = $orphan_row['tag'];
    }
}
?>

<div class="container mt-4">
    <!-- Page Header -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="mb-1">Tag Management</h1>
                            <p class="text-muted mb-0">Manage event tags across your system</p>
                        </div>
                        <div>
                            <button class="btn btn-primary" data-toggle="modal" data-target="#addTagModal">
                                <i class="fas fa-plus mr-2"></i>Add New Tag
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h3 class="text-primary"><?php echo count($all_tags); ?></h3>
                    <p class="mb-0">Active Tags</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h3 class="text-info"><?php echo array_sum(array_column($all_tags, 'usage_count')); ?></h3>
                    <p class="mb-0">Total Tag Usage</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <h3 class="text-warning"><?php echo count($orphaned_tags); ?></h3>
                    <p class="mb-0">Unused Tags</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Tags Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Active Tags</h5>
            <?php if (!empty($all_tags)): ?>
            <button class="btn btn-outline-danger btn-sm" onclick="toggleBulkDelete()">
                <i class="fas fa-trash mr-1"></i>Bulk Delete
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($all_tags)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No tags found</h5>
                    <p class="text-muted">Add some tags to get started</p>
                </div>
            <?php else: ?>
                <form id="bulkDeleteForm" method="post" style="display: none;">
                    <input type="hidden" name="action" value="bulk_delete">
                    <div class="mb-3">
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete selected tags?')">
                            Delete Selected
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="toggleBulkDelete()">
                            Cancel
                        </button>
                    </div>
                </form>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="50" id="bulk-select-header" style="display: none;">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>Tag Name</th>
                                <th>Usage Count</th>
                                <th>Used in Events</th>
                                <th width="150">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_tags as $tag): ?>
                            <tr>
                                <td class="bulk-select-cell" style="display: none;">
                                    <input type="checkbox" name="selected_tags[]" value="<?php echo htmlspecialchars($tag['tag']); ?>" form="bulkDeleteForm">
                                </td>
                                <td>
                                    <span class="tag-display-<?php echo md5($tag['tag']); ?>">
                                        <span class="badge badge-primary badge-lg"><?php echo htmlspecialchars($tag['tag']); ?></span>
                                    </span>
                                    <div class="tag-edit-<?php echo md5($tag['tag']); ?>" style="display: none;">
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="edit_tag">
                                            <input type="hidden" name="old_tag" value="<?php echo htmlspecialchars($tag['tag']); ?>">
                                            <div class="input-group input-group-sm" style="max-width: 200px;">
                                                <input type="text" name="new_tag" class="form-control" value="<?php echo htmlspecialchars($tag['tag']); ?>" required>
                                                <div class="input-group-append">
                                                    <button type="submit" class="btn btn-success btn-sm">Save</button>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEdit('<?php echo md5($tag['tag']); ?>')">Cancel</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo $tag['usage_count']; ?> event(s)</span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php 
                                        $events = $tag['events'];
                                        if (strlen($events) > 100) {
                                            echo htmlspecialchars(substr($events, 0, 100)) . '...';
                                        } else {
                                            echo htmlspecialchars($events);
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <button class="btn btn-outline-primary btn-sm" onclick="startEdit('<?php echo md5($tag['tag']); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="delete_tag">
                                        <input type="hidden" name="delete_tag" value="<?php echo htmlspecialchars($tag['tag']); ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                onclick="return confirm('Are you sure? This will remove the tag from all <?php echo $tag['usage_count']; ?> event(s).')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Unused Tags Section -->
    <?php if (!empty($orphaned_tags)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Unused Tags</h5>
            <small class="text-muted">These tags exist but are not currently used by any events</small>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($orphaned_tags as $orphan_tag): ?>
                <div class="col-md-3 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge badge-secondary"><?php echo htmlspecialchars($orphan_tag); ?></span>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="delete_tag">
                            <input type="hidden" name="delete_tag" value="<?php echo htmlspecialchars($orphan_tag); ?>">
                            <button type="submit" class="btn btn-outline-danger btn-xs" 
                                    onclick="return confirm('Delete this unused tag?')">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Tag Modal -->
<div class="modal fade" id="addTagModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Tag</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_tag">
                    <div class="form-group">
                        <label for="tag_name">Tag Name</label>
                        <input type="text" name="tag_name" id="tag_name" 
                               class="form-control <?php echo (!empty($tag_name_err)) ? 'is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($tag_name); ?>" required>
                        <span class="invalid-feedback"><?php echo $tag_name_err; ?></span>
                        <small class="form-text text-muted">Enter a descriptive tag name (e.g., Workshop, Seminar, Competition)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Tag</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
function startEdit(tagId) {
    $('.tag-display-' + tagId).hide();
    $('.tag-edit-' + tagId).show();
}

function cancelEdit(tagId) {
    $('.tag-display-' + tagId).show();
    $('.tag-edit-' + tagId).hide();
}

function toggleBulkDelete() {
    const form = document.getElementById('bulkDeleteForm');
    const header = document.getElementById('bulk-select-header');
    const cells = document.querySelectorAll('.bulk-select-cell');
    
    if (form.style.display === 'none') {
        form.style.display = 'block';
        header.style.display = 'table-cell';
        cells.forEach(cell => cell.style.display = 'table-cell');
    } else {
        form.style.display = 'none';
        header.style.display = 'none';
        cells.forEach(cell => cell.style.display = 'none');
        // Uncheck all checkboxes
        document.querySelectorAll('input[name="selected_tags[]"]').forEach(cb => cb.checked = false);
        document.getElementById('selectAll').checked = false;
    }
}

// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('input[name="selected_tags[]"]');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

// Show modal if there are errors
<?php if (!empty($tag_name_err)): ?>
$(document).ready(function() {
    $('#addTagModal').modal('show');
});
<?php endif; ?>
</script>

</body>
</html>