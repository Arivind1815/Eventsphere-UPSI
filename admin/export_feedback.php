<?php
/**
 * Export Feedback Data
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Hide only warnings and notices, but still show fatal errors
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 1);

// Include database connection
require_once '../config/db.php';

// Check if admin is logged in (include this before any output)
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("location: ../login.php");
    exit;
}

// Get export format
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv';

// Get filter parameters
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$rating_filter = isset($_GET['rating']) ? intval($_GET['rating']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the base SQL query
$sql_base = "SELECT f.id, f.rating, f.comments, f.submission_date, 
                    e.title as event_title, e.event_date, e.venue,
                    c.name as category_name, 
                    u.name as student_name, u.matric_id 
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

// Complete the SQL for the actual results
$sql = $sql_base . " ORDER BY f.submission_date DESC";

// Get feedback data
$feedback_data = array();
if ($stmt = $conn->prepare($sql)) {
    if (!empty($param_types)) {
        $stmt->bind_param($param_types, ...$sql_params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $feedback_data[] = $row;
    }
    
    $stmt->close();
}

// Export as CSV
if ($format === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="feedback_export_' . date('Y-m-d') . '.csv"');
    
    // Create CSV file
    $output = fopen('php://output', 'w');
    
    // Add BOM (Byte Order Mark) for proper UTF-8 recognition in Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write header row
    fputcsv($output, array(
        'ID', 'Event', 'Category', 'Date', 'Venue', 'Student Name', 'Matric ID', 
        'Rating', 'Comments', 'Submission Date'
    ));
    
    // Write data rows
    foreach ($feedback_data as $row) {
        fputcsv($output, array(
            $row['id'],
            $row['event_title'],
            $row['category_name'],
            date("Y-m-d", strtotime($row['event_date'])),
            $row['venue'],
            $row['student_name'],
            $row['matric_id'],
            $row['rating'],
            $row['comments'],
            date("Y-m-d H:i:s", strtotime($row['submission_date']))
        ));
    }
    
    fclose($output);
    exit;
}

// Export as PDF
if ($format === 'pdf') {
    // Include TCPDF library
    require_once('../vendor/tecnickcom/tcpdf/tcpdf.php');

    // Create new PDF document
    class MYPDF extends TCPDF {
        // Page header
        public function Header() {
            // Logo
            $image_file = '../assets/images/logo.png';
            if (file_exists($image_file)) {
                $this->Image($image_file, 10, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
            
            // Set font
            $this->SetFont('helvetica', 'B', 20);
            // Title
            $this->Cell(0, 15, 'EventSphere@UPSI - Feedback Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
            
            // Subtitle with date
            $this->Ln(10);
            $this->SetFont('helvetica', '', 10);
            $this->Cell(0, 10, 'Generated on ' . date('F j, Y, g:i a'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
            
            // Line separator
            $this->Line(10, 30, $this->getPageWidth() - 10, 30);
            
            // Set Y position for the content to start below the header
            $this->SetY(35);
        }

        // Page footer
        public function Footer() {
            // Position at 15 mm from bottom
            $this->SetY(-15);
            // Set font
            $this->SetFont('helvetica', 'I', 8);
            // Page number
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }

    // Create new PDF document
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('EventSphere@UPSI');
    $pdf->SetAuthor('Admin');
    $pdf->SetTitle('Feedback Report');
    $pdf->SetSubject('Event Feedback Data');
    $pdf->SetKeywords('Feedback, Events, Report');

    // Set default header and footer data
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 15, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add a page
    $pdf->AddPage();

    // Add filter information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Filter Criteria:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    
    // Display applied filters
    $filter_text = "All feedback";
    if ($category_id > 0) {
        $cat_sql = "SELECT name FROM event_categories WHERE id = ?";
        if ($cat_stmt = $conn->prepare($cat_sql)) {
            $cat_stmt->bind_param("i", $category_id);
            $cat_stmt->execute();
            $cat_result = $cat_stmt->get_result();
            if ($cat_row = $cat_result->fetch_assoc()) {
                $filter_text .= " in category '" . $cat_row['name'] . "'";
            }
            $cat_stmt->close();
        }
    }
    
    if ($rating_filter > 0) {
        $filter_text .= " with " . $rating_filter . " star rating";
    }
    
    if (!empty($search)) {
        $filter_text .= " matching search term '" . $search . "'";
    }
    
    $pdf->Cell(0, 10, $filter_text, 0, 1);
    $pdf->Ln(5);

    // Statistics
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Feedback Statistics:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    
    // Total feedback count
    $pdf->Cell(0, 10, 'Total Feedback: ' . count($feedback_data), 0, 1);
    
    // Calculate average rating
    $total_rating = 0;
    foreach ($feedback_data as $row) {
        $total_rating += $row['rating'];
    }
    $avg_rating = count($feedback_data) > 0 ? round($total_rating / count($feedback_data), 1) : 0;
    $pdf->Cell(0, 10, 'Average Rating: ' . $avg_rating . ' / 5', 0, 1);
    
    // Rating distribution
    $rating_dist = array(1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0);
    foreach ($feedback_data as $row) {
        $rating_dist[$row['rating']]++;
    }
    
    $pdf->Cell(0, 10, 'Rating Distribution:', 0, 1);
    
    for ($i = 5; $i >= 1; $i--) {
        $percent = count($feedback_data) > 0 ? round(($rating_dist[$i] / count($feedback_data)) * 100) : 0;
        $pdf->Cell(0, 10, $i . ' Stars: ' . $rating_dist[$i] . ' (' . $percent . '%)', 0, 1);
    }
    
    $pdf->Ln(5);

    // Feedback Listing
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Feedback Details:', 0, 1);
    
    // Create table header
    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(60, 7, 'Event', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Student', 1, 0, 'C', 1);
    $pdf->Cell(15, 7, 'Rating', 1, 0, 'C', 1);
    $pdf->Cell(65, 7, 'Comments', 1, 0, 'C', 1);
    $pdf->Cell(30, 7, 'Date', 1, 1, 'C', 1);
    
    // Create table rows
    $pdf->SetFont('helvetica', '', 8);
    
    foreach ($feedback_data as $row) {
        // Check if we need a new page
        if ($pdf->GetY() > 250) {
            $pdf->AddPage();
            
            // Recreate table header on new page
            $pdf->SetFillColor(230, 230, 230);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(60, 7, 'Event', 1, 0, 'C', 1);
            $pdf->Cell(30, 7, 'Student', 1, 0, 'C', 1);
            $pdf->Cell(15, 7, 'Rating', 1, 0, 'C', 1);
            $pdf->Cell(65, 7, 'Comments', 1, 0, 'C', 1);
            $pdf->Cell(30, 7, 'Date', 1, 1, 'C', 1);
            
            $pdf->SetFont('helvetica', '', 8);
        }
        
        // Prepare event details cell
        $event_details = $row['event_title'] . "\n" . 
                         date("Y-m-d", strtotime($row['event_date'])) . "\n" . 
                         $row['venue'] . "\n" . 
                         "Category: " . $row['category_name'];
        
        // Prepare student details cell
        $student_details = $row['student_name'] . "\n" . $row['matric_id'];
        
        // Prepare rating cell with stars
        $rating_text = str_repeat('A', $row['rating']) . str_repeat('I', 5 - $row['rating']);
        
        // Limit comments length
        $comments = $row['comments'];
        if (strlen($comments) > 200) {
            $comments = substr($comments, 0, 197) . '...';
        }
        
        // Row data
        $pdf->MultiCell(60, 0, $event_details, 1, 'L', 0, 0);
        $pdf->MultiCell(30, 0, $student_details, 1, 'L', 0, 0);
        $pdf->MultiCell(15, 0, $rating_text, 1, 'C', 0, 0);
        $pdf->MultiCell(65, 0, $comments, 1, 'L', 0, 0);
        $pdf->MultiCell(30, 0, date("Y-m-d", strtotime($row['submission_date'])), 1, 'C', 0, 1);
    }

    // Output the PDF
    $pdf->Output('feedback_report_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

// If format is not supported, redirect back to the feedback page
header("location: feedback.php");
exit;