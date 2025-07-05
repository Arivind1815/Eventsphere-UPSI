<?php
/**
 * Reports Generation
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Start output buffering to prevent any accidental output
ob_start();

// Include database connection
require_once '../config/db.php';

// Include FPDF library
require_once '../vendor/autoload.php';

// Handle report generation BEFORE including any headers or HTML
if (isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'];
    $filter = $_POST['filter'];
    $category = isset($_POST['category']) ? intval($_POST['category']) : 0;
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    
    // Clear any buffered output
    ob_clean();
    
    // Create a new PDF document
    class PDF extends FPDF {
        // Page header
        function Header() {
            global $report_type, $filter, $date_from, $date_to;
            
            // Logo
            $this->Image('../uploads/img/navlogo.png', 10, 10, 30);
            
            // Title
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, 'EventSphere@UPSI - Report', 0, 1, 'C');
            
            // Report info
            $this->SetFont('Arial', '', 12);
            
            // Convert report type to title
            $report_title = ucwords(str_replace('_', ' ', $report_type));
            
            // Report type
            $this->Cell(0, 10, 'Report Type: ' . $report_title, 0, 1, 'C');
            
            // Date range if applicable
            if ($filter == 'date_range') {
                $this->Cell(0, 10, 'Period: ' . date('d/m/Y', strtotime($date_from)) . ' - ' . date('d/m/Y', strtotime($date_to)), 0, 1, 'C');
            }
            
            // Line break
            $this->Ln(10);
        }
        
        // Page footer
        function Footer() {
            // Position at 1.5 cm from bottom
            $this->SetY(-15);
            
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            
            // Page number and date
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' | Generated on: ' . date('d/m/Y H:i:s'), 0, 0, 'C');
        }
    }
    
    // Initialize PDF
    $pdf = new PDF();
    $pdf->AddPage('L'); // Landscape orientation for tables
    $pdf->SetFont('Arial', '', 10);
    
    // Generate appropriate report based on type
    switch ($report_type) {
        case 'students':
            // Student List Report
            generateStudentsReport($pdf, $conn);
            break;
            
        case 'student_points':
            // Student Points Report
            generateStudentPointsReport($pdf, $conn);
            break;
            
        case 'event_attendance':
            // Event Attendance Report
            generateEventAttendanceReport($pdf, $conn, $filter, $category, $date_from, $date_to);
            break;
            
        case 'event_registrations':
            // Event Registrations Report
            generateEventRegistrationsReport($pdf, $conn, $filter, $category, $date_from, $date_to);
            break;
    }
    
    // Output the PDF and exit
    $pdf->Output('D', $report_type . '_report_' . date('Y-m-d') . '.pdf');
    exit;
}

// Function to generate students report
function generateStudentsReport($pdf, $conn) {
    // Set table header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 10, 'Student Name', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Matric ID', 1, 0, 'C');
    $pdf->Cell(60, 10, 'Email', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Phone', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Registrations', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Attendance', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Total Points', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Join Date', 1, 1, 'C');
    
    // Get data from database
    $sql = "SELECT u.*, 
           (SELECT COUNT(*) FROM event_registrations WHERE user_id = u.id) as total_registrations,
           (SELECT COUNT(*) FROM attendance WHERE user_id = u.id) as total_attendance,
           (SELECT SUM(points) FROM user_points WHERE user_id = u.id) as total_points
           FROM users u
           WHERE u.role = 'student'
           ORDER BY u.name ASC";
    
    $result = $conn->query($sql);
    
    // Set font for data
    $pdf->SetFont('Arial', '', 9);
    
    // Add data rows
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pdf->Cell(40, 10, utf8_decode($row['name']), 1, 0, 'L');
            $pdf->Cell(30, 10, $row['matric_id'], 1, 0, 'C');
            $pdf->Cell(60, 10, $row['email'], 1, 0, 'L');
            $pdf->Cell(30, 10, $row['phone'], 1, 0, 'C');
            $pdf->Cell(30, 10, $row['total_registrations'], 1, 0, 'C');
            $pdf->Cell(30, 10, $row['total_attendance'], 1, 0, 'C');
            $pdf->Cell(30, 10, $row['total_points'] ? $row['total_points'] : '0', 1, 0, 'C');
            $pdf->Cell(30, 10, date('d/m/Y', strtotime($row['created_at'])), 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, 'No students found', 1, 1, 'C');
    }
}

// Function to generate student points report
function generateStudentPointsReport($pdf, $conn) {
    // Set table header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 10, 'Student Name', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Matric ID', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Total Points', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Faculty Events', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Club Events', 1, 0, 'C');
    $pdf->Cell(40, 10, 'International Events', 1, 0, 'C');
    $pdf->Cell(40, 10, 'National Events', 1, 1, 'C');
    
    // Get data from database
    $sql = "SELECT u.id, u.name, u.matric_id, 
           COALESCE(SUM(p.points), 0) as total_points
           FROM users u
           LEFT JOIN user_points p ON u.id = p.user_id
           WHERE u.role = 'student'
           GROUP BY u.id
           ORDER BY total_points DESC, u.name ASC";
    
    $result = $conn->query($sql);
    
    // Set font for data
    $pdf->SetFont('Arial', '', 9);
    
    // Add data rows
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Get points by category
            $categories = [
                'Faculty' => 0,
                'Club' => 0,
                'International' => 0,
                'National' => 0
            ];
            
            $cat_sql = "SELECT c.name, COALESCE(SUM(p.points), 0) as cat_points
                       FROM user_points p
                       JOIN events e ON p.event_id = e.id
                       JOIN event_categories c ON e.category_id = c.id
                       WHERE p.user_id = ?
                       GROUP BY c.name";
            
            if ($cat_stmt = $conn->prepare($cat_sql)) {
                $cat_stmt->bind_param("i", $row['id']);
                $cat_stmt->execute();
                $cat_result = $cat_stmt->get_result();
                
                while ($cat_row = $cat_result->fetch_assoc()) {
                    if (array_key_exists($cat_row['name'], $categories)) {
                        $categories[$cat_row['name']] = $cat_row['cat_points'];
                    }
                }
                
                $cat_stmt->close();
            }
            
            $pdf->Cell(40, 10, utf8_decode($row['name']), 1, 0, 'L');
            $pdf->Cell(30, 10, $row['matric_id'], 1, 0, 'C');
            $pdf->Cell(40, 10, $row['total_points'], 1, 0, 'C');
            $pdf->Cell(40, 10, $categories['Faculty'], 1, 0, 'C');
            $pdf->Cell(40, 10, $categories['Club'], 1, 0, 'C');
            $pdf->Cell(40, 10, $categories['International'], 1, 0, 'C');
            $pdf->Cell(40, 10, $categories['National'], 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, 'No student points found', 1, 1, 'C');
    }
}

// Function to generate event attendance report
function generateEventAttendanceReport($pdf, $conn, $filter, $category, $date_from, $date_to) {
    // Build query based on filters
    $where_clauses = [];
    $params = [];
    $types = "";
    
    // Apply date filter
    if ($filter == 'date_range') {
        $where_clauses[] = "e.event_date BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
        $types .= "ss";
    }
    
    // Apply category filter
    if ($category > 0) {
        $where_clauses[] = "e.category_id = ?";
        $params[] = $category;
        $types .= "i";
    }
    
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Set table header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(60, 10, 'Event Name', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Date', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Category', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Registrations', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Attendance', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Verified Att.', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Points Awarded', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Organizer', 1, 1, 'C');
    
    // Get data from database
    $sql = "SELECT e.*, c.name as category_name,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registrations,
           (SELECT COUNT(*) FROM attendance WHERE event_id = e.id) as attendance,
           (SELECT COUNT(*) FROM attendance WHERE event_id = e.id AND verified = 1) as verified,
           (SELECT SUM(points) FROM user_points WHERE event_id = e.id) as points
           FROM events e
           JOIN event_categories c ON e.category_id = c.id
           $where_sql
           ORDER BY e.event_date DESC, e.event_time DESC";
    
    // Prepare and execute query
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Set font for data
    $pdf->SetFont('Arial', '', 9);
    
    // Add data rows
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pdf->Cell(60, 10, utf8_decode($row['title']), 1, 0, 'L');
            $pdf->Cell(30, 10, date('d/m/Y', strtotime($row['event_date'])), 1, 0, 'C');
            $pdf->Cell(30, 10, $row['category_name'], 1, 0, 'C');
            $pdf->Cell(30, 10, $row['registrations'], 1, 0, 'C');
            $pdf->Cell(30, 10, $row['attendance'], 1, 0, 'C');
            $pdf->Cell(30, 10, $row['verified'], 1, 0, 'C');
            $pdf->Cell(30, 10, $row['points'] ? $row['points'] : '0', 1, 0, 'C');
            $pdf->Cell(30, 10, utf8_decode($row['organizer']), 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, 'No events found matching the criteria', 1, 1, 'C');
    }
    
    $stmt->close();
}

// Function to generate event registrations report
function generateEventRegistrationsReport($pdf, $conn, $filter, $category, $date_from, $date_to) {
    // Build query based on filters
    $where_clauses = [];
    $params = [];
    $types = "";
    
    // Apply date filter
    if ($filter == 'date_range') {
        $where_clauses[] = "e.event_date BETWEEN ? AND ?";
        $params[] = $date_from;
        $params[] = $date_to;
        $types .= "ss";
    }
    
    // Apply category filter
    if ($category > 0) {
        $where_clauses[] = "e.category_id = ?";
        $params[] = $category;
        $types .= "i";
    }
    
    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    // Set table header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(60, 10, 'Event Name', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Date', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Category', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Total Reg.', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Active Reg.', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Cancelled', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Max. Capacity', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Venue', 1, 1, 'C');
    
    // Get data from database
    $sql = "SELECT e.*, c.name as category_name,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as total_reg,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND status = 'registered') as active_reg,
           (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND status = 'cancelled') as cancelled
           FROM events e
           JOIN event_categories c ON e.category_id = c.id
           $where_sql
           ORDER BY e.event_date DESC, e.event_time DESC";
    
    // Prepare and execute query
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Set font for data
    $pdf->SetFont('Arial', '', 9);
    
    // Add data rows
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pdf->Cell(60, 10, utf8_decode($row['title']), 1, 0, 'L');
            $pdf->Cell(30, 10, date('d/m/Y', strtotime($row['event_date'])), 1, 0, 'C');
            $pdf->Cell(30, 10, $row['category_name'], 1, 0, 'C');
            $pdf->Cell(30, 10, $row['total_reg'], 1, 0, 'C');
            $pdf->Cell(30, 10, $row['active_reg'], 1, 0, 'C');
            $pdf->Cell(30, 10, $row['cancelled'], 1, 0, 'C');
            $pdf->Cell(30, 10, $row['max_participants'] ? $row['max_participants'] : 'Unlimited', 1, 0, 'C');
            $pdf->Cell(30, 10, utf8_decode($row['venue']), 1, 1, 'C');
        }
    } else {
        $pdf->Cell(0, 10, 'No events found matching the criteria', 1, 1, 'C');
    }
    
    $stmt->close();
}

// Now that PDF generation is handled, proceed with the regular page display
// Set page title
$page_title = "Generate Reports";

// Include header
include_once '../include/admin_header.php';

// Initialize variables
$report_type = isset($_GET['type']) ? $_GET['type'] : 'students';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // Default to first day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'); // Default to current date

// Get event categories for dropdown
$categories = [];
$categories_sql = "SELECT id, name FROM event_categories ORDER BY name";
$categories_result = $conn->query($categories_sql);
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h1 class="h3 mb-3">Generate Reports</h1>
                    <p class="lead">Create and download reports in PDF format for students, events, and more.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Report Configuration -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Report Configuration</h4>
                </div>
                <div class="card-body">
                    <form method="post" id="reportForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="report_type">Report Type</label>
                                    <select name="report_type" id="report_type" class="form-control" onchange="toggleFilterOptions()">
                                        <option value="students" <?php echo $report_type == 'students' ? 'selected' : ''; ?>>Student List</option>
                                        <option value="student_points" <?php echo $report_type == 'student_points' ? 'selected' : ''; ?>>Student Points</option>
                                        <option value="event_attendance" <?php echo $report_type == 'event_attendance' ? 'selected' : ''; ?>>Event Attendance</option>
                                        <option value="event_registrations" <?php echo $report_type == 'event_registrations' ? 'selected' : ''; ?>>Event Registrations</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group" id="filterTypeGroup">
                                    <label for="filter">Filter Type</label>
                                    <select name="filter" id="filter" class="form-control" onchange="toggleDateFilter()">
                                        <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Records</option>
                                        <option value="date_range" <?php echo $filter == 'date_range' ? 'selected' : ''; ?>>Date Range</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row" id="dateFilterRow" <?php echo $filter != 'date_range' ? 'style="display:none;"' : ''; ?>>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date_from">From Date</label>
                                    <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date_to">To Date</label>
                                    <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row" id="categoryFilterRow" <?php echo !in_array($report_type, ['event_attendance', 'event_registrations']) ? 'style="display:none;"' : ''; ?>>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="category">Event Category</label>
                                    <select name="category" id="category" class="form-control">
                                        <option value="0">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" name="generate_report" class="btn btn-primary">
                                <i class="fas fa-file-pdf mr-2"></i> Generate PDF Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Report Types Info -->
    <div class="row">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Available Report Types</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="report-type-card mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="mr-3">
                                        <i class="fas fa-users fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Student List</h5>
                                        <p class="text-muted mb-0">Basic student information and statistics</p>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="small">Includes name, matric ID, email, phone, registration count, attendance count, and total points earned.</p>
                                </div>
                            </div>
                            
                            <div class="report-type-card mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="mr-3">
                                        <i class="fas fa-award fa-2x text-warning"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Student Points</h5>
                                        <p class="text-muted mb-0">Detailed breakdown of points by category</p>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="small">Shows total points earned by each student with a breakdown by event category.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="report-type-card mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="mr-3">
                                        <i class="fas fa-clipboard-check fa-2x text-success"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Event Attendance</h5>
                                        <p class="text-muted mb-0">Attendance statistics for events</p>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="small">Includes event details, registration and attendance counts, verification status, and points awarded.</p>
                                </div>
                            </div>
                            
                            <div class="report-type-card mb-4">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="mr-3">
                                        <i class="fas fa-calendar-check fa-2x text-info"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0">Event Registrations</h5>
                                        <p class="text-muted mb-0">Registration statistics for events</p>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <p class="small">Shows event details, total registrations, active vs. cancelled registrations, capacity, and venue information.</p>
                                </div>
                            </div>
                        </div>
                    </div>
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

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
function toggleFilterOptions() {
    var reportType = document.getElementById('report_type').value;
    var filterTypeGroup = document.getElementById('filterTypeGroup');
    var categoryFilterRow = document.getElementById('categoryFilterRow');
    
    // Show/hide category filter based on report type
    if (reportType === 'event_attendance' || reportType === 'event_registrations') {
        filterTypeGroup.style.display = 'block';
        categoryFilterRow.style.display = 'block';
    } else {
        categoryFilterRow.style.display = 'none';
        
        // Only student list and points reports don't need any filters
        if (reportType === 'students' || reportType === 'student_points') {
            filterTypeGroup.style.display = 'none';
            document.getElementById('dateFilterRow').style.display = 'none';
        } else {
            filterTypeGroup.style.display = 'block';
            toggleDateFilter(); // Check if date filter should be shown
        }
    }
}

function toggleDateFilter() {
    var filterType = document.getElementById('filter').value;
    var dateFilterRow = document.getElementById('dateFilterRow');
    
    if (filterType === 'date_range') {
        dateFilterRow.style.display = 'flex';
    } else {
        dateFilterRow.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleFilterOptions();
    toggleDateFilter();
});
</script>