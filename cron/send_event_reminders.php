<?php
/**
 * Automated Event Reminders Cron Script
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Hide only warnings and notices, but still show fatal errors
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 1);

// Set script execution time to unlimited
set_time_limit(0);

// Include database connection
require_once __DIR__ . '/../config/db.php';

// Function to send email
// Function to send email using SMTP
function sendEmail($to, $subject, $message) {
    // Include PHPMailer
    require __DIR__ . '/../vendor/autoload.php';
    
    // Create a new PHPMailer instance
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'sysmail999@gmail.com';
        $mail->Password = 'lfqd yfxx wxtf zexw'; // The password or app password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('sysmail999@gmail.com', 'EventSphere@UPSI');
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error
        logMessage("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Log function
function logMessage($message) {
    $log_file = __DIR__ . '/reminder_logs.txt';
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($log_file, $timestamp . ' ' . $message . PHP_EOL, FILE_APPEND);
}

// Start logging
logMessage("Automatic reminder script started");

// Email template
$email_template = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #007bff; color: white; padding: 10px 20px; text-align: center; }
        .content { padding: 20px; background-color: #f8f9fa; }
        .event-details { background-color: white; padding: 15px; margin-top: 20px; border-left: 4px solid #007bff; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>EventSphere@UPSI</h2>
        </div>
        <div class="content">
            <h3>{{TITLE}}</h3>
            <p>Hello {{NAME}},</p>
            <p>{{MESSAGE}}</p>
            
            <div class="event-details">
                <h4>{{EVENT_TITLE}}</h4>
                <p><strong>Date:</strong> {{EVENT_DATE}}</p>
                <p><strong>Time:</strong> {{EVENT_TIME}}</p>
                <p><strong>Venue:</strong> {{EVENT_VENUE}}</p>
                <p><strong>Category:</strong> {{EVENT_CATEGORY}}</p>
            </div>
            
            <p>We look forward to seeing you there!</p>
        </div>
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; 2025 EventSphere@UPSI. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';

// Process 24-hour reminders
logMessage("Processing 24-hour reminders");

// Get events happening in ~24 hours
$events_24h_sql = "SELECT e.*, c.name as category_name FROM events e 
                  JOIN event_categories c ON e.category_id = c.id 
                  WHERE e.event_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) 
                  OR (e.event_date = CURDATE() 
                      AND e.event_time > TIME(NOW()) 
                      AND e.event_time <= TIME(DATE_ADD(NOW(), INTERVAL 24 HOUR)))";
$events_24h_result = $conn->query($events_24h_sql);

if ($events_24h_result && $events_24h_result->num_rows > 0) {
    while ($event = $events_24h_result->fetch_assoc()) {
        logMessage("Processing 24-hour reminder for event: " . $event['title'] . " (ID: " . $event['id'] . ")");
        
        // Get registered users who haven't received a 24-hour reminder yet
        $users_sql = "SELECT u.id, u.name, u.email 
                     FROM users u 
                     JOIN event_registrations r ON u.id = r.user_id 
                     WHERE r.event_id = ? AND r.status = 'registered' 
                     AND NOT EXISTS (
                         SELECT 1 FROM notifications 
                         WHERE user_id = u.id 
                         AND related_id = r.event_id 
                         AND type = 'event_reminder_24h'
                     )";
        
        if ($stmt = $conn->prepare($users_sql)) {
            $stmt->bind_param("i", $event['id']);
            $stmt->execute();
            $users_result = $stmt->get_result();
            
            $notification_count = 0;
            $email_count = 0;
            
            while ($user = $users_result->fetch_assoc()) {
                // Create notification title and message
                $title = "Reminder: Your Event is Tomorrow";
                $message = "Don't forget! Your registered event \"" . $event['title'] . "\" is happening in about 24 hours.";
                
                // Insert notification in database
                $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at) 
                                   VALUES (?, ?, ?, 'event_reminder_24h', ?, 0, NOW())";
                
                if ($notification_stmt = $conn->prepare($notification_sql)) {
                    $notification_stmt->bind_param("issi", $user['id'], $title, $message, $event['id']);
                    
                    if ($notification_stmt->execute()) {
                        $notification_count++;
                    }
                    
                    $notification_stmt->close();
                }
                
                // Send email
                $personalized_email = str_replace(
                    [
                        '{{TITLE}}', 
                        '{{NAME}}', 
                        '{{MESSAGE}}', 
                        '{{EVENT_TITLE}}', 
                        '{{EVENT_DATE}}', 
                        '{{EVENT_TIME}}', 
                        '{{EVENT_VENUE}}', 
                        '{{EVENT_CATEGORY}}'
                    ],
                    [
                        htmlspecialchars($title),
                        htmlspecialchars($user['name']),
                        nl2br(htmlspecialchars($message)),
                        htmlspecialchars($event['title']),
                        date("F j, Y", strtotime($event['event_date'])),
                        date("h:i A", strtotime($event['event_time'])),
                        htmlspecialchars($event['venue']),
                        htmlspecialchars($event['category_name'])
                    ],
                    $email_template
                );
                
                $email_subject = "EventSphere@UPSI: " . $title;
                
                if (sendEmail($user['email'], $email_subject, $personalized_email)) {
                    $email_count++;
                }
            }
            
            logMessage("Sent $notification_count notifications and $email_count emails for 24-hour reminders for event ID " . $event['id']);
            $stmt->close();
        }
    }
} else {
    logMessage("No events found for 24-hour reminders");
}

// Process 1-hour reminders
logMessage("Processing 1-hour reminders");

// Get events happening in ~1 hour
$events_1h_sql = "SELECT e.*, c.name as category_name FROM events e 
                 JOIN event_categories c ON e.category_id = c.id 
                 WHERE e.event_date = CURDATE() 
                 AND e.event_time BETWEEN TIME(NOW()) 
                 AND TIME(DATE_ADD(NOW(), INTERVAL 1 HOUR))";
$events_1h_result = $conn->query($events_1h_sql);

if ($events_1h_result && $events_1h_result->num_rows > 0) {
    while ($event = $events_1h_result->fetch_assoc()) {
        logMessage("Processing 1-hour reminder for event: " . $event['title'] . " (ID: " . $event['id'] . ")");
        
        // Get registered users who haven't received a 1-hour reminder yet
        $users_sql = "SELECT u.id, u.name, u.email 
                     FROM users u 
                     JOIN event_registrations r ON u.id = r.user_id 
                     WHERE r.event_id = ? AND r.status = 'registered' 
                     AND NOT EXISTS (
                         SELECT 1 FROM notifications 
                         WHERE user_id = u.id 
                         AND related_id = r.event_id 
                         AND type = 'event_reminder_1h'
                     )";
        
        if ($stmt = $conn->prepare($users_sql)) {
            $stmt->bind_param("i", $event['id']);
            $stmt->execute();
            $users_result = $stmt->get_result();
            
            $notification_count = 0;
            $email_count = 0;
            
            while ($user = $users_result->fetch_assoc()) {
                // Create notification title and message
                $title = "Reminder: Your Event Starts Soon";
                $message = "Your registered event \"" . $event['title'] . "\" is starting in about 1 hour. See you there!";
                
                // Insert notification in database
                $notification_sql = "INSERT INTO notifications (user_id, title, message, type, related_id, is_read, created_at) 
                                   VALUES (?, ?, ?, 'event_reminder_1h', ?, 0, NOW())";
                
                if ($notification_stmt = $conn->prepare($notification_sql)) {
                    $notification_stmt->bind_param("issi", $user['id'], $title, $message, $event['id']);
                    
                    if ($notification_stmt->execute()) {
                        $notification_count++;
                    }
                    
                    $notification_stmt->close();
                }
                
                // Send email
                $personalized_email = str_replace(
                    [
                        '{{TITLE}}', 
                        '{{NAME}}', 
                        '{{MESSAGE}}', 
                        '{{EVENT_TITLE}}', 
                        '{{EVENT_DATE}}', 
                        '{{EVENT_TIME}}', 
                        '{{EVENT_VENUE}}', 
                        '{{EVENT_CATEGORY}}'
                    ],
                    [
                        htmlspecialchars($title),
                        htmlspecialchars($user['name']),
                        nl2br(htmlspecialchars($message)),
                        htmlspecialchars($event['title']),
                        date("F j, Y", strtotime($event['event_date'])),
                        date("h:i A", strtotime($event['event_time'])),
                        htmlspecialchars($event['venue']),
                        htmlspecialchars($event['category_name'])
                    ],
                    $email_template
                );
                
                $email_subject = "EventSphere@UPSI: " . $title;
                
                if (sendEmail($user['email'], $email_subject, $personalized_email)) {
                    $email_count++;
                }
            }
            
            logMessage("Sent $notification_count notifications and $email_count emails for 1-hour reminders for event ID " . $event['id']);
            $stmt->close();
        }
    }
} else {
    logMessage("No events found for 1-hour reminders");
}

// Finish
logMessage("Automatic reminder script completed");
$conn->close();
?>