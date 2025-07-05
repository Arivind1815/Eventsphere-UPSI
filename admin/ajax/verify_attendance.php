<?php
/**
 * AJAX Handler for Attendance Verification
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../../config/db.php';

// Check if user is logged in as admin
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request is POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get attendance ID and action
    $attendance_id = isset($_POST['attendance_id']) ? intval($_POST['attendance_id']) : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($attendance_id > 0 && ($action === 'verify' || $action === 'reject')) {
        // Get admin ID
        $admin_id = $_SESSION["id"];
        
        if ($action === 'verify') {
            // Verify attendance
            $sql = "UPDATE attendance SET verified = 1, verification_time = NOW(), verified_by = ? WHERE id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ii", $admin_id, $attendance_id);
                
                if ($stmt->execute()) {
                    // Get event and user info to award points
                    $points_sql = "SELECT a.user_id, a.event_id, e.category_id 
                                  FROM attendance a 
                                  JOIN events e ON a.event_id = e.id 
                                  WHERE a.id = ?";
                    
                    if ($points_stmt = $conn->prepare($points_sql)) {
                        $points_stmt->bind_param("i", $attendance_id);
                        $points_stmt->execute();
                        $points_result = $points_stmt->get_result();
                        
                        if ($points_result->num_rows === 1) {
                            $points_row = $points_result->fetch_assoc();
                            $user_id = $points_row['user_id'];
                            $event_id = $points_row['event_id'];
                            $category_id = $points_row['category_id'];
                            
                            // Determine points based on category
                            $points = 0;
                            switch ($category_id) {
                                case 1: // Faculty
                                    $points = 15;
                                    break;
                                case 2: // Club
                                    $points = 10;
                                    break;
                                case 3: // College
                                    $points = 10;
                                    break;
                                case 4: // International
                                    $points = 30;
                                    break;
                                case 5: // National
                                    $points = 25;
                                    break;
                                case 6: // Local
                                    $points = 15;
                                    break;
                                default:
                                    $points = 10;
                            }
                            
                            // Award points to user
                            $award_sql = "INSERT INTO user_points (user_id, event_id, points, awarded_by) 
                                         VALUES (?, ?, ?, ?)
                                         ON DUPLICATE KEY UPDATE 
                                         points = VALUES(points),
                                         awarded_date = NOW(),
                                         awarded_by = VALUES(awarded_by)";
                            
                            if ($award_stmt = $conn->prepare($award_sql)) {
                                $award_stmt->bind_param("iiii", $user_id, $event_id, $points, $admin_id);
                                $award_stmt->execute();
                                $award_stmt->close();
                                
                                // Create notification for points
                                $event_name_sql = "SELECT title FROM events WHERE id = ?";
                                if ($event_stmt = $conn->prepare($event_name_sql)) {
                                    $event_stmt->bind_param("i", $event_id);
                                    $event_stmt->execute();
                                    $event_result = $event_stmt->get_result();
                                    
                                    if ($event_row = $event_result->fetch_assoc()) {
                                        $event_title = $event_row['title'];
                                        
                                        $notification_sql = "INSERT INTO notifications 
                                                           (user_id, title, message, type, related_id) 
                                                           VALUES (?, ?, ?, 'points_added', ?)";
                                        
                                        if ($notif_stmt = $conn->prepare($notification_sql)) {
                                            $title = "Points Added";
                                            $message = "You earned " . $points . " points for attending " . $event_title;
                                            
                                            $notif_stmt->bind_param("issi", $user_id, $title, $message, $event_id);
                                            $notif_stmt->execute();
                                            $notif_stmt->close();
                                        }
                                    }
                                    
                                    $event_stmt->close();
                                }
                            }
                        }
                        
                        $points_stmt->close();
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Attendance verified successfully']);
                } else {
                    header('HTTP/1.1 500 Internal Server Error');
                    echo json_encode(['success' => false, 'message' => 'Database error']);
                }
                
                $stmt->close();
            } else {
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } else if ($action === 'reject') {
            // Reject attendance (delete record)
            $sql = "DELETE FROM attendance WHERE id = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("i", $attendance_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Attendance rejected successfully']);
                } else {
                    header('HTTP/1.1 500 Internal Server Error');
                    echo json_encode(['success' => false, 'message' => 'Database error']);
                }
                
                $stmt->close();
            } else {
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        }
    } else {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}