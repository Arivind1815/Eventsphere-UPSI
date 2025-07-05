<?php
/**
 * AJAX Tag Update Handler - Simple Version
 * Save this as: admin/ajax/update_tag.php
 */

// Include database connection
require_once '../../config/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if request is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input data
$old_tag = isset($_POST['old_tag']) ? trim($_POST['old_tag']) : '';
$new_tag = isset($_POST['new_tag']) ? trim($_POST['new_tag']) : '';

// Validate input
if (empty($old_tag) || empty($new_tag)) {
    echo json_encode(['success' => false, 'message' => 'Both old and new tag names are required']);
    exit;
}

if ($old_tag === $new_tag) {
    echo json_encode(['success' => false, 'message' => 'New tag name must be different from the old one']);
    exit;
}

try {
    // Check if new tag already exists (case-insensitive)
    $check_sql = "SELECT COUNT(*) as count FROM event_tags WHERE LOWER(tag) = LOWER(?) AND tag != ?";
    $check_stmt = $conn->prepare($check_sql);
    
    if (!$check_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $check_stmt->bind_param("ss", $new_tag, $old_tag);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'A tag with this name already exists']);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();
    
    // Update all instances of the old tag to the new tag
    $update_sql = "UPDATE event_tags SET tag = ? WHERE tag = ?";
    $update_stmt = $conn->prepare($update_sql);
    
    if (!$update_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $update_stmt->bind_param("ss", $new_tag, $old_tag);
    
    if ($update_stmt->execute()) {
        $affected_rows = $update_stmt->affected_rows;
        $update_stmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => "Tag updated successfully! Updated $affected_rows instances.",
            'affected_rows' => $affected_rows,
            'old_tag' => $old_tag,
            'new_tag' => $new_tag
        ]);
    } else {
        throw new Exception("Execute failed: " . $update_stmt->error);
    }
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Tag update error: " . $e->getMessage());
    
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    // Close connection
    if (isset($conn)) {
        $conn->close();
    }
}
?>