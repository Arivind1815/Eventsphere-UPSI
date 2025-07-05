<?php
/**
 * Send Registration Email
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Load Composer's autoloader
require '../vendor/autoload.php';

// Function to send registration confirmation email
function send_registration_email($data) {
    // die(var_dump($data));
    // Check if all required data is present
    if (!isset($data['user_email']) || empty($data['user_email']) || 
        !isset($data['event_title']) || empty($data['event_title'])) {
        // Log error
        error_log("Error sending registration email: Missing required data");
        return false;
    }
    
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'sysmail999@gmail.com';
        $mail->Password = 'lfqd yfxx wxtf zexw'; // The password or app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Set sender and recipient
        $mail->setFrom('sysmail999@gmail.com', 'EventSphere@UPSI');
        $mail->addAddress($data['user_email'], $data['user_name']);
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = "Registration Confirmation: " . $data['event_title'];
        
        // Build the email body
        $message = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Registration Confirmation</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                }
                .header {
                    background: linear-gradient(135deg, #3a86ff 0%, #8338ec 100%);
                    color: white;
                    padding: 20px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                    margin-bottom: 20px;
                }
                .content {
                    padding: 20px;
                }
                .event-details {
                    background-color: #f9f9f9;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }
                .event-details table {
                    width: 100%;
                }
                .event-details table td {
                    padding: 8px;
                }
                .event-details table td:first-child {
                    font-weight: bold;
                    width: 120px;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    font-size: 12px;
                    color: #777;
                }
                .btn {
                    display: inline-block;
                    background-color: #3a86ff;
                    color: white;
                    padding: 10px 20px;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Registration Confirmation</h1>
                </div>
                
                <div class="content">
                    <p>Dear ' . htmlspecialchars($data['user_name']) . ',</p>
                    
                    <p>Thank you for registering for the following event:</p>
                    
                    <div class="event-details">
                        <table>
                            <tr>
                                <td>Event:</td>
                                <td>' . htmlspecialchars($data['event_title']) . '</td>
                            </tr>
                            <tr>
                                <td>Date:</td>
                                <td>' . htmlspecialchars($data['event_date']) . '</td>
                            </tr>';
        
        // Add end date if available
        if (isset($data['event_end_date']) && !empty($data['event_end_date'])) {
            $message .= '
                            <tr>
                                <td>End Date:</td>
                                <td>' . htmlspecialchars($data['event_end_date']) . '</td>
                            </tr>';
        }
        
        $message .= '
                            <tr>
                                <td>Time:</td>
                                <td>' . htmlspecialchars($data['event_time']);
        
        // Add end time if available
        if (isset($data['event_end_time']) && !empty($data['event_end_time'])) {
            $message .= ' - ' . htmlspecialchars($data['event_end_time']);
        }
        
        $message .= '</td>
                            </tr>
                            <tr>
                                <td>Venue:</td>
                                <td>' . htmlspecialchars($data['event_venue']) . '</td>
                            </tr>
                            <tr>
                                <td>Organizer:</td>
                                <td>' . htmlspecialchars($data['event_organizer']) . '</td>
                            </tr>
                        </table>
                    </div>
                    
                    <p>Your registration has been confirmed. Please keep this email for your reference.</p>
                    
                    <p>Remember to mark your attendance during the event to earn Champ Points!</p>
                    
                    <div style="text-align: center;">
                        <a href="' . get_event_url($data['event_id']) . '" class="btn">View Event Details</a>
                    </div>
                </div>
                
                <div class="footer">
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; ' . date('Y') . ' EventSphere@UPSI. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->Body = $message;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $message)); // Plain text version of the email
        
        // Send the email
        return $mail->send();
        
    } catch (Exception $e) {
        // Log the error
        error_log("Error sending registration email: " . $mail->ErrorInfo);
        return false;
    }
}

// Helper function to get the event URL

// Helper function to get the event URL
function get_event_url($event_id) {
    // Using the fixed ngrok URL
    return 'https://treefrog-moving-uniformly.ngrok-free.app/eventspehere/student/event_details.php?id=' . $event_id;
}
// function get_event_url($event_id) {
//     // Construct the full URL to the event details page
//     $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
//     $host = $_SERVER['HTTP_HOST'];
//     $path = dirname($_SERVER['PHP_SELF']);
    
//     // Remove "student" from the path and add the event details page
//     $path = str_replace('/student', '', $path) . '/student/event_details.php';
    
//     return $protocol . '://' . $host . $path . '?id=' . $event_id;
// }
?>