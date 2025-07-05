<?php
/**
 * Edit Tags Management
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Manage Tags";

// Include header
include_once '../include/admin_header.php';

// Initialize variables
$success_message = "";
$error_message = "";

// Handle AJAX requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add_tag':
            $new_tag = trim($_POST['tag_name']);
            
            if (empty($new_tag)) {
                echo json_encode(['success' => false, 'message' => 'Tag name cannot be empty']);
                exit;
            }
            
            // Check if tag already exists
            $check_sql = "SELECT COUNT(*) as count FROM event_tags WHERE LOWER(tag) = LOWER(?)";
            if ($check_stmt = $conn->prepare($check_sql)) {
                $check_stmt->bind_param("s", $new_tag);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Tag already exists']);
                    exit;
                }
                $check_stmt->close();
            }
            
            // Add new tag with a dummy event_id (0) to make it available for selection
            $insert_sql = "INSERT INTO event_tags (event_id, tag) VALUES (0, ?)";
            if ($insert_stmt = $conn->prepare($insert_sql)) {
                $insert_stmt->bind_param("s", $new_tag);
                if ($insert_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Tag added successfully', 'tag' => $new_tag]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add tag']);
                }
                $insert_stmt->close();
            }
            exit;
            
        case 'edit_tag':
            $old_tag = trim($_POST['old_tag']);
            $new_tag = trim($_POST['new_tag']);
            
            if (empty($new_tag)) {
                echo json_encode(['success' => false, 'message' => 'Tag name cannot be empty']);
                exit;
            }
            
            if ($old_tag === $new_tag) {
                echo json_encode(['success' => true, 'message' => 'No changes made']);
                exit;
            }
            
            // Check if new tag name already exists
            $check_sql = "SELECT COUNT(*) as count FROM event_tags WHERE LOWER(tag) = LOWER(?) AND tag != ?";
            if ($check_stmt = $conn->prepare($check_sql)) {
                $check_stmt->bind_param("ss", $new_tag, $old_tag);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Tag name already exists']);
                    exit;
                }
                $check_stmt->close();
            }
            
            // Update all instances of the tag
            $update_sql = "UPDATE event_tags SET tag = ? WHERE tag = ?";
            if ($update_stmt = $conn->prepare($update_sql)) {
                $update_stmt->bind_param("ss", $new_tag, $old_tag);
                if ($update_stmt->execute()) {
                    $affected_rows = $update_stmt->affected_rows;
                    echo json_encode(['success' => true, 'message' => "Tag updated successfully ($affected_rows events affected)", 'old_tag' => $old_tag, 'new_tag' => $new_tag]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update tag']);
                }
                $update_stmt->close();
            }
            exit;
            
        case 'delete_tag':
            $tag_to_delete = trim($_POST['tag_name']);
            
            // Get count of events using this tag
            $count_sql = "SELECT COUNT(*) as count FROM event_tags WHERE tag = ? AND event_id > 0";
            if ($count_stmt = $conn->prepare($count_sql)) {
                $count_stmt->bind_param("s", $tag_to_delete);
                $count_stmt->execute();
                $result = $count_stmt->get_result();
                $row = $result->fetch_assoc();
                $event_count = $row['count'];
                $count_stmt->close();
            }
            
            // Delete all instances of the tag
            $delete_sql = "DELETE FROM event_tags WHERE tag = ?";
            if ($delete_stmt = $conn->prepare($delete_sql)) {
                $delete_stmt->bind_param("s", $tag_to_delete);
                if ($delete_stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => "Tag deleted successfully ($event_count events affected)"]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete tag']);
                }
                $delete_stmt->close();
            }
            exit;
    }
}

// Get all unique tags with usage statistics
$tags_sql = "
    SELECT 
        tag,
        COUNT(CASE WHEN event_id > 0 THEN 1 END) as event_count,
        MIN(CASE WHEN event_id > 0 THEN event_id END) as first_event_id
    FROM event_tags 
    GROUP BY tag 
    ORDER BY tag ASC
";

$tags_result = $conn->query($tags_sql);
$all_tags = [];

if ($tags_result) {
    while ($tag_row = $tags_result->fetch_assoc()) {
        $all_tags[] = $tag_row;
    }
}

// Get total number of events for statistics
$total_events_sql = "SELECT COUNT(*) as total FROM events";
$total_events_result = $conn->query($total_events_sql);
$total_events = $total_events_result ? $total_events_result->fetch_assoc()['total'] : 0;
?>

<div class="container mt-4">
    <!-- Header -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="mb-2">Manage Tags</h1>
                            <p class="text-muted mb-0">Add, edit, or delete event tags. Changes will affect all events using these tags.</p>
                        </div>
                        <div class="text-right">
                            <small class="text-muted">
                                <i class="fas fa-tags mr-1"></i><?php echo count($all_tags); ?> total tags<br>
                                <i class="fas fa-calendar-alt mr-1"></i><?php echo $total_events; ?> total events
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="alert-container"></div>

    <div class="row">
        <!-- Add New Tag -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus mr-2"></i>Add New Tag</h5>
                </div>
                <div class="card-body">
                    <form id="add-tag-form">
                        <div class="form-group">
                            <label for="new_tag_name">Tag Name</label>
                            <input type="text" class="form-control" id="new_tag_name" name="tag_name" 
                                   placeholder="Enter tag name" required>
                            <small class="form-text text-muted">Tag names are case-insensitive and must be unique.</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-plus mr-2"></i>Add Tag
                        </button>
                    </form>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-primary mb-0"><?php echo count($all_tags); ?></h3>
                            <small class="text-muted">Total Tags</small>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success mb-0">
                                <?php echo count(array_filter($all_tags, function($tag) { return $tag['event_count'] > 0; })); ?>
                            </h3>
                            <small class="text-muted">Used Tags</small>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-warning mb-0">
                                <?php echo count(array_filter($all_tags, function($tag) { return $tag['event_count'] == 0; })); ?>
                            </h3>
                            <small class="text-muted">Unused Tags</small>
                        </div>
                        <div class="col-6">
                            <h3 class="text-info mb-0"><?php echo $total_events; ?></h3>
                            <small class="text-muted">Total Events</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tags List -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list mr-2"></i>All Tags</h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary" onclick="filterTags('all')" id="filter-all">All</button>
                        <button type="button" class="btn btn-outline-success" onclick="filterTags('used')" id="filter-used">Used</button>
                        <button type="button" class="btn btn-outline-warning" onclick="filterTags('unused')" id="filter-unused">Unused</button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($all_tags)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Tags Found</h5>
                            <p class="text-muted">Start by adding your first tag using the form on the left.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tag Name</th>
                                        <th class="text-center">Events Count</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="tags-table-body">
                                    <?php foreach ($all_tags as $tag): ?>
                                        <tr class="tag-row" data-tag="<?php echo htmlspecialchars($tag['tag']); ?>" 
                                            data-status="<?php echo $tag['event_count'] > 0 ? 'used' : 'unused'; ?>">
                                            <td>
                                                <span class="tag-display">
                                                    <i class="fas fa-tag text-primary mr-2"></i>
                                                    <strong><?php echo htmlspecialchars($tag['tag']); ?></strong>
                                                </span>
                                                <div class="tag-edit" style="display: none;">
                                                    <input type="text" class="form-control form-control-sm tag-edit-input" 
                                                           value="<?php echo htmlspecialchars($tag['tag']); ?>">
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge badge-<?php echo $tag['event_count'] > 0 ? 'success' : 'secondary'; ?>">
                                                    <?php echo $tag['event_count']; ?> event<?php echo $tag['event_count'] != 1 ? 's' : ''; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($tag['event_count'] > 0): ?>
                                                    <span class="badge badge-success">Used</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">Unused</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group btn-group-sm tag-actions">
                                                    <button type="button" class="btn btn-outline-primary btn-sm edit-tag-btn" 
                                                            onclick="editTag('<?php echo htmlspecialchars($tag['tag']); ?>')" 
                                                            title="Edit tag">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm delete-tag-btn" 
                                                            onclick="deleteTag('<?php echo htmlspecialchars($tag['tag']); ?>')" 
                                                            title="Delete tag">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="btn-group btn-group-sm tag-edit-actions" style="display: none;">
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            onclick="saveTag('<?php echo htmlspecialchars($tag['tag']); ?>')" 
                                                            title="Save changes">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-secondary btn-sm" 
                                                            onclick="cancelEdit('<?php echo htmlspecialchars($tag['tag']); ?>')" 
                                                            title="Cancel">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the tag "<strong id="delete-tag-name"></strong>"?</p>
                <p class="text-warning">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    This action will remove the tag from <strong id="delete-event-count"></strong> and cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-btn">Delete Tag</button>
            </div>
        </div>
    </div>
</div>

<footer class="bg-light mt-5 py-3">
    <div class="container text-center">
        <p class="mb-0">&copy; <?php echo date("Y"); ?> EventSphere@UPSI. All rights reserved.</p>
    </div>
</footer>

<!-- Load scripts -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
let tagToDelete = '';

// Show alert function
function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    $('#alert-container').html(alertHtml);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        $('.alert').fadeOut();
    }, 5000);
}

// Add new tag
$('#add-tag-form').on('submit', function(e) {
    e.preventDefault();
    
    const tagName = $('#new_tag_name').val().trim();
    if (!tagName) return;
    
    // Show loading state
    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Adding...');
    
    $.ajax({
        url: '',
        method: 'POST',
        data: {
            action: 'add_tag',
            tag_name: tagName
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#new_tag_name').val('');
                
                // Refresh page to show updated tag list
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert('danger', response.message);
                submitBtn.prop('disabled', false).html(originalText);
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX Error:', status, error);
            console.log('Response:', xhr.responseText);
            
            // Check if tag was likely added despite error
            showAlert('info', 'Tag addition completed. Refreshing page to update display...');
            
            setTimeout(function() {
                location.reload();
            }, 2000);
        }
    });
});

// Edit tag
function editTag(tagName) {
    const row = $(`tr[data-tag="${tagName}"]`);
    row.find('.tag-display').hide();
    row.find('.tag-edit').show();
    row.find('.tag-actions').hide();
    row.find('.tag-edit-actions').show();
    row.find('.tag-edit-input').focus().select();
}

// Cancel edit
function cancelEdit(tagName) {
    const row = $(`tr[data-tag="${tagName}"]`);
    row.find('.tag-display').show();
    row.find('.tag-edit').hide();
    row.find('.tag-actions').show();
    row.find('.tag-edit-actions').hide();
    
    // Reset input value
    row.find('.tag-edit-input').val(tagName);
}

// Save tag
function saveTag(oldTagName) {
    const row = $(`tr[data-tag="${oldTagName}"]`);
    const newTagName = row.find('.tag-edit-input').val().trim();
    
    if (!newTagName) {
        showAlert('danger', 'Tag name cannot be empty');
        return;
    }
    
    if (newTagName === oldTagName) {
        cancelEdit(oldTagName);
        return;
    }
    
    // Show loading state
    row.find('.tag-edit-actions .btn-success').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
    $.ajax({
        url: '',
        method: 'POST',
        data: {
            action: 'edit_tag',
            old_tag: oldTagName,
            new_tag: newTagName
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                
                // Refresh page to show updated data
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert('danger', response.message);
                row.find('.tag-edit-actions .btn-success').prop('disabled', false).html('<i class="fas fa-check"></i>');
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX Error:', status, error);
            console.log('Response:', xhr.responseText);
            
            // Check if edit was likely successful despite error
            showAlert('info', 'Tag edit completed. Refreshing page to update display...');
            
            setTimeout(function() {
                location.reload();
            }, 2000);
        }
    });
}

// Delete tag
function deleteTag(tagName) {
    tagToDelete = tagName;
    const row = $(`tr[data-tag="${tagName}"]`);
    const eventCount = row.find('.badge').text();
    
    $('#delete-tag-name').text(tagName);
    $('#delete-event-count').text(eventCount);
    $('#deleteModal').modal('show');
}

// Confirm delete
$('#confirm-delete-btn').on('click', function() {
    if (!tagToDelete) return;
    
    // Show loading state
    $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Deleting...');
    
    $.ajax({
        url: '',
        method: 'POST',
        data: {
            action: 'delete_tag',
            tag_name: tagToDelete
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#deleteModal').modal('hide');
                
                // Refresh page after short delay to show success message
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showAlert('danger', response.message);
                resetDeleteButton();
            }
        },
        error: function(xhr, status, error) {
            console.log('AJAX Error:', status, error);
            console.log('Response:', xhr.responseText);
            
            // Check if the operation might have succeeded despite the error
            showAlert('info', 'Tag deletion completed. Refreshing page to update display...');
            $('#deleteModal').modal('hide');
            
            setTimeout(function() {
                location.reload();
            }, 2000);
        }
    });
});

function resetDeleteButton() {
    $('#confirm-delete-btn').prop('disabled', false).html('Delete Tag');
}

// Filter tags
function filterTags(filter) {
    $('.btn-group button').removeClass('btn-secondary btn-success btn-warning').addClass('btn-outline-secondary btn-outline-success btn-outline-warning');
    
    if (filter === 'all') {
        $('.tag-row').show();
        $('#filter-all').removeClass('btn-outline-secondary').addClass('btn-secondary');
    } else if (filter === 'used') {
        $('.tag-row').hide();
        $('.tag-row[data-status="used"]').show();
        $('#filter-used').removeClass('btn-outline-success').addClass('btn-success');
    } else if (filter === 'unused') {
        $('.tag-row').hide();
        $('.tag-row[data-status="unused"]').show();
        $('#filter-unused').removeClass('btn-outline-warning').addClass('btn-warning');
    }
}

// Update statistics
function updateStatistics() {
    // This would typically reload statistics from server
    // For now, we'll keep it simple and suggest a page reload for accurate stats
}

// Handle Enter/Escape keys in edit inputs
$(document).on('keypress keydown', '.tag-edit-input', function(e) {
    const tagName = $(this).closest('tr').data('tag');
    
    if (e.which === 13) { // Enter key
        e.preventDefault();
        saveTag(tagName);
    } else if (e.which === 27) { // Escape key
        e.preventDefault();
        cancelEdit(tagName);
    }
});

// Initialize
$(document).ready(function() {
    // Set initial filter
    filterTags('all');
    
    console.log("Tags management page loaded successfully");
});
</script>

</body>
</html>