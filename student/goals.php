<?php
/**
 * Champ Points Goals
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection and header
require_once '../config/db.php';
include_once '../include/student_header.php';
$page_title = "Champ Points Goals";

// Get user ID and initialize variables
$user_id = $_SESSION["id"];
$current_semester = date('Y') . ' ' . (date('n') <= 6 ? 'Spring' : 'Fall');
$current_goal = null;
$all_goals = [];
$total_points = 0;
$points_by_category = [];
$points_by_month = [];
$points_history = [];

// Get total points earned
$total_sql = "SELECT SUM(points) as total FROM user_points WHERE user_id = ?";
if ($stmt = $conn->prepare($total_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $total_points = $row['total'] ? $row['total'] : 0;
    }
    $stmt->close();
}

// Get points breakdown by category
$category_sql = "SELECT c.name as category, SUM(p.points) as points 
                FROM user_points p 
                JOIN events e ON p.event_id = e.id 
                JOIN event_categories c ON e.category_id = c.id 
                WHERE p.user_id = ? 
                GROUP BY c.name 
                ORDER BY points DESC";
if ($stmt = $conn->prepare($category_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $points_by_category[] = $row;
    }
    $stmt->close();
}

// Get points by month (for the current year)
$month_sql = "SELECT DATE_FORMAT(p.awarded_date, '%m') as month, SUM(p.points) as points 
             FROM user_points p 
             WHERE p.user_id = ? AND YEAR(p.awarded_date) = YEAR(CURDATE()) 
             GROUP BY month 
             ORDER BY month";
if ($stmt = $conn->prepare($month_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Initialize all months with zero points
    for ($i = 1; $i <= 12; $i++) {
        $month_key = sprintf("%02d", $i);
        $points_by_month[$month_key] = 0;
    }

    // Fill in actual data
    while ($row = $result->fetch_assoc()) {
        $points_by_month[$row['month']] = intval($row['points']);
    }
    $stmt->close();
}

// Get points history
$history_sql = "SELECT p.*, e.title as event_title, e.event_date, c.name as category_name 
               FROM user_points p 
               JOIN events e ON p.event_id = e.id 
               JOIN event_categories c ON e.category_id = c.id
               WHERE p.user_id = ? 
               ORDER BY p.awarded_date DESC 
               LIMIT 10";
if ($stmt = $conn->prepare($history_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $points_history[] = $row;
    }
    $stmt->close();
}

// Get all goals (current and past)
$goals_sql = "SELECT * FROM user_goals WHERE user_id = ? ORDER BY semester DESC";
if ($stmt = $conn->prepare($goals_sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $all_goals[] = $row;
        if ($row['semester'] == $current_semester) {
            $current_goal = $row;
        }
    }
    $stmt->close();
}

// Process goal setting form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['set_goal'])) {
    $new_goal = isset($_POST['points_goal']) ? intval($_POST['points_goal']) : 0;
    $semester = isset($_POST['semester']) ? trim($_POST['semester']) : $current_semester;

    if ($new_goal > 0) {
        // Check if goal already exists for the semester
        $goal_exists = false;
        foreach ($all_goals as $goal) {
            if ($goal['semester'] == $semester) {
                $goal_exists = true;
                break;
            }
        }

        if ($goal_exists) {
            // Update existing goal
            $update_sql = "UPDATE user_goals SET points_goal = ?, updated_at = NOW() WHERE user_id = ? AND semester = ?";
            if ($update_stmt = $conn->prepare($update_sql)) {
                $update_stmt->bind_param("iis", $new_goal, $user_id, $semester);
                if ($update_stmt->execute()) {
                    $_SESSION['success_message'] = "Your points goal for " . $semester . " has been updated.";

                    // Update current goal if this is for the current semester
                    if ($semester == $current_semester) {
                        if ($current_goal) {
                            $current_goal['points_goal'] = $new_goal;
                        } else {
                            $current_goal = [
                                'semester' => $semester,
                                'points_goal' => $new_goal
                            ];
                        }
                    }

                    // Update all_goals array
                    foreach ($all_goals as &$goal) {
                        if ($goal['semester'] == $semester) {
                            $goal['points_goal'] = $new_goal;
                            break;
                        }
                    }
                } else {
                    $_SESSION['error_message'] = "Error updating points goal.";
                }
                $update_stmt->close();
            }
        } else {
            // Insert new goal
            $insert_sql = "INSERT INTO user_goals (user_id, semester, points_goal) VALUES (?, ?, ?)";
            if ($insert_stmt = $conn->prepare($insert_sql)) {
                $insert_stmt->bind_param("isi", $user_id, $semester, $new_goal);
                if ($insert_stmt->execute()) {
                    $_SESSION['success_message'] = "Your points goal for " . $semester . " has been set.";

                    // Add to all_goals array
                    $new_goal_entry = [
                        'id' => $conn->insert_id,
                        'user_id' => $user_id,
                        'semester' => $semester,
                        'points_goal' => $new_goal,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];

                    array_unshift($all_goals, $new_goal_entry);

                    // Set as current goal if for current semester
                    if ($semester == $current_semester) {
                        $current_goal = $new_goal_entry;
                    }
                } else {
                    $_SESSION['error_message'] = "Error setting points goal.";
                }
                $insert_stmt->close();
            }
        }
    } else {
        $_SESSION['error_message'] = "Please enter a valid points goal.";
    }

    // Redirect to prevent form resubmission
    header("Location: goals.php");
    exit;
}

// Get recommendations for how to earn more points
$recommendations = [];

// If they have earned less than 50 points, suggest starting with club events
if ($total_points < 50) {
    $recommendations[] = [
        'title' => 'Start with Club Events',
        'description' => 'Club events are a great way to start earning points. They\'re usually fun and easy to attend.',
        'icon' => 'fas fa-users',
        'color' => 'primary'
    ];
}

// Check which categories they haven't participated in
$missing_categories = ['International', 'National', 'Faculty', 'Club', 'College', 'Local'];
foreach ($points_by_category as $category) {
    $key = array_search($category['category'], $missing_categories);
    if ($key !== false) {
        unset($missing_categories[$key]);
    }
}

if (!empty($missing_categories)) {
    $random_category = $missing_categories[array_rand($missing_categories)];
    $recommendations[] = [
        'title' => 'Try ' . $random_category . ' Events',
        'description' => 'You haven\'t participated in any ' . $random_category . ' events yet. These events can earn you valuable points!',
        'icon' => 'fas fa-globe-asia',
        'color' => 'success'
    ];
}

// If they're close to their goal, encourage them
if ($current_goal && $total_points >= $current_goal['points_goal'] * 0.8 && $total_points < $current_goal['points_goal']) {
    $points_needed = $current_goal['points_goal'] - $total_points;
    $recommendations[] = [
        'title' => 'Almost There!',
        'description' => 'You\'re only ' . $points_needed . ' points away from reaching your semester goal. Keep going!',
        'icon' => 'fas fa-trophy',
        'color' => 'warning'
    ];
}

// Suggest higher-point events if they're mostly doing low-point events
$high_point_events = false;
foreach ($points_by_category as $category) {
    if (in_array($category['category'], ['International', 'National']) && $category['points'] > 0) {
        $high_point_events = true;
        break;
    }
}

if (!$high_point_events) {
    $recommendations[] = [
        'title' => 'Aim Higher',
        'description' => 'International and National events offer the most points (25-30). Consider participating in these to boost your total quickly.',
        'icon' => 'fas fa-rocket',
        'color' => 'danger'
    ];
}

// If no recommendations yet, add a general one
if (empty($recommendations)) {
    $recommendations[] = [
        'title' => 'Stay Consistent',
        'description' => 'Try to attend at least one event per month to steadily accumulate points throughout the semester.',
        'icon' => 'fas fa-calendar-check',
        'color' => 'info'
    ];
}

// Shuffle recommendations to keep it interesting
shuffle($recommendations);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - EventSphere@UPSI</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>
    <div class="container mt-4">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h1 class="mb-0">Champ Points Goals</h1>
                        <p class="text-muted">Set goals and track your progress in earning Champ Points.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Points Overview -->
        <div class="row mb-4">
            <!-- Total Points Card -->
            <div class="col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0">Total Champ Points</h4>
                            <span class="badge badge-primary p-2">Lifetime</span>
                        </div>
                        <div class="text-center">
                            <div class="display-1 text-primary mb-3"><?php echo $total_points; ?></div>
                            <p class="lead">
                                <?php if ($total_points > 0): ?>
                                    Great job! Keep participating to earn more points.
                                <?php else: ?>
                                    Start participating in events to earn Champ Points.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Goal Card -->
            <div class="col-md-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0">Current Goal</h4>
                            <span class="badge badge-info p-2"><?php echo $current_semester; ?></span>
                        </div>

                        <?php if ($current_goal): ?>
                            <div class="text-center mb-3">
                                <div class="display-4 text-success mb-2"><?php echo $current_goal['points_goal']; ?></div>
                                <p>Points Goal</p>
                            </div>

                            <div class="progress mb-3" style="height: 25px;">
                                <?php $progress = min(100, ($total_points / $current_goal['points_goal']) * 100); ?>
                                <div class="progress-bar progress-bar-striped <?php echo ($progress >= 100) ? 'bg-success' : 'bg-primary'; ?>"
                                    role="progressbar" style="width: <?php echo $progress; ?>%"
                                    aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?php echo round($progress); ?>%
                                </div>
                            </div>

                            <div class="text-center">
                                <?php if ($total_points >= $current_goal['points_goal']): ?>
                                    <div class="alert alert-success">
                                        <i class="fas fa-trophy mr-2"></i> Congratulations! You've reached your goal!
                                    </div>
                                    <button type="button" class="btn btn-outline-primary" id="updateGoalBtn">
                                        <i class="fas fa-edit mr-2"></i> Update Goal
                                    </button>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-flag-checkered mr-2"></i>
                                        <strong><?php echo $current_goal['points_goal'] - $total_points; ?> points to
                                            go!</strong>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary" id="updateGoalBtn">
                                        <i class="fas fa-edit mr-2"></i> Update Goal
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center mb-4">
                                <div class="mb-3">
                                    <i class="fas fa-bullseye fa-4x text-muted"></i>
                                </div>
                                <p>You haven't set a goal for this semester yet.</p>
                                <button type="button" class="btn btn-primary" id="setGoalBtn">
                                    <i class="fas fa-plus-circle mr-2"></i> Set a Goal
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommendations & Charts -->
        <div class="row mb-4">
            <!-- Points Recommendations -->
            <div class="col-lg-4 mb-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recommendations</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($recommendations, 0, 3) as $index => $rec): ?>
                                <div
                                    class="list-group-item border-left-0 border-right-0 <?php echo $index === 0 ? 'border-top-0' : ''; ?>">
                                    <div class="d-flex">
                                        <div class="mr-3">
                                            <div class="d-flex align-items-center justify-content-center rounded-circle bg-<?php echo $rec['color']; ?> text-white"
                                                style="width: 40px; height: 40px;">
                                                <i class="<?php echo $rec['icon']; ?>"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?php echo $rec['title']; ?></h6>
                                            <p class="mb-0 small text-muted"><?php echo $rec['description']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="card-footer bg-white">
                            <a href="events.php" class="btn btn-primary btn-block">
                                <i class="fas fa-search mr-2"></i> Find Events
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Points by Category -->
            <div class="col-lg-4 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Points by Category</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($points_by_category)): ?>
                            <?php foreach ($points_by_category as $category): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><?php echo htmlspecialchars($category['category']); ?></span>
                                        <span class="badge badge-primary"><?php echo $category['points']; ?></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <?php
                                        // Calculate percentage (relative to highest category)
                                        $max_points = $points_by_category[0]['points'];
                                        $percent = ($max_points > 0) ? ($category['points'] / $max_points) * 100 : 0;
                                        ?>
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percent; ?>%"
                                            aria-valuenow="<?php echo $percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No points earned yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Points by Month -->
            <div class="col-lg-4 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Monthly Progress (<?php echo date('Y'); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $months = [
                            '01' => 'Jan',
                            '02' => 'Feb',
                            '03' => 'Mar',
                            '04' => 'Apr',
                            '05' => 'May',
                            '06' => 'Jun',
                            '07' => 'Jul',
                            '08' => 'Aug',
                            '09' => 'Sep',
                            '10' => 'Oct',
                            '11' => 'Nov',
                            '12' => 'Dec'
                        ];

                        $max_monthly_points = max($points_by_month);
                        ?>

                        <?php if ($max_monthly_points > 0): ?>
                            <?php foreach ($months as $month_num => $month_name): ?>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span><?php echo $month_name; ?></span>
                                        <span class="badge badge-info"><?php echo $points_by_month[$month_num]; ?></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <?php
                                        // Calculate percentage (relative to highest month)
                                        $percent = ($max_monthly_points > 0) ? ($points_by_month[$month_num] / $max_monthly_points) * 100 : 0;
                                        ?>
                                        <div class="progress-bar bg-info" role="progressbar"
                                            style="width: <?php echo $percent; ?>%" aria-valuenow="<?php echo $percent; ?>"
                                            aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No points earned this year.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Points History -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Points History</h5>
                        <a href="myevents.php?tab=points" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($points_history)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Category</th>
                                            <th>Date</th>
                                            <th>Points</th>
                                            <th>Awarded On</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($points_history as $history): ?>
                                            <tr>
                                                <td>
                                                    <a href="event_details.php?id=<?php echo $history['event_id']; ?>">
                                                        <?php echo htmlspecialchars($history['event_title']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($history['category_name']); ?></td>
                                                <td><?php echo date("M j, Y", strtotime($history['event_date'])); ?></td>
                                                <td><span class="badge badge-warning"><?php echo $history['points']; ?></span>
                                                </td>
                                                <td><?php echo date("M j, Y", strtotime($history['awarded_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-award fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No points earned yet</h4>
                                <p>Attend events to earn Champ Points.</p>
                                <a href="events.php" class="btn btn-primary mt-2">
                                    <i class="fas fa-search mr-2"></i> Browse Events
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Points Categories Info -->
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Points Categories</h5>
                    </div>
                    <div class="card-body">
                        <p>Different event categories award different amounts of points:</p>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-group mb-3">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        International Events
                                        <span class="badge badge-warning badge-pill">30 points</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        National Events
                                        <span class="badge badge-warning badge-pill">25 points</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Faculty Events
                                        <span class="badge badge-warning badge-pill">15 points</span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-group mb-3">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Local Events
                                        <span class="badge badge-warning badge-pill">15 points</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Club Events
                                        <span class="badge badge-warning badge-pill">10 points</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        College Events
                                        <span class="badge badge-warning badge-pill">10 points</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i> Points are awarded when administrators verify your
                            attendance at an event.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Set Goal Modal -->
    <div class="modal fade" id="setGoalModal" tabindex="-1" role="dialog" aria-labelledby="setGoalModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="setGoalModalLabel">Set Points Goal</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="semester">Semester</label>
                            <select class="form-control" id="semester" name="semester">
                                <?php
                                // Get current and next semester
                                $current_year = date('Y');
                                $next_year = date('Y') + 1;
                                $semesters = [
                                    $current_year . ' Spring',
                                    $current_year . ' Fall',
                                    $next_year . ' Spring'
                                ];

                                foreach ($semesters as $sem) {
                                    $selected = ($sem == $current_semester) ? 'selected' : '';
                                    echo "<option value=\"{$sem}\" {$selected}>{$sem}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="points_goal">Points Goal</label>
                            <input type="number" class="form-control" id="points_goal" name="points_goal" min="1"
                                value="<?php echo $current_goal ? $current_goal['points_goal'] : '50'; ?>" required>
                            <small class="form-text text-muted">Set a challenging but achievable goal for the
                                semester.</small>
                        </div>
                        <!-- Suggested goals based on event participation -->
                        <div class="mt-3">
                            <p><strong>Suggested goals:</strong></p>
                            <div class="btn-group-toggle d-flex flex-wrap" data-toggle="buttons">
                                <label class="btn btn-outline-primary btn-sm mr-2 mb-2">
                                    <input type="radio" name="suggested_goal" value="50"> Beginner (50 pts)
                                </label>
                                <label class="btn btn-outline-primary btn-sm mr-2 mb-2">
                                    <input type="radio" name="suggested_goal" value="100"> Regular (100 pts)
                                </label>
                                <label class="btn btn-outline-primary btn-sm mr-2 mb-2">
                                    <input type="radio" name="suggested_goal" value="150"> Engaged (150 pts)
                                </label>
                                <label class="btn btn-outline-primary btn-sm mr-2 mb-2">
                                    <input type="radio" name="suggested_goal" value="200"> Active (200 pts)
                                </label>
                                <label class="btn btn-outline-primary btn-sm mr-2 mb-2">
                                    <input type="radio" name="suggested_goal" value="300"> Champion (300 pts)
                                </label>
                            </div>
                        </div>

                        <!-- Dynamic goal setting tips -->
                        <div id="goalSettingTips" class="alert alert-primary mt-3">
                            <i class="fas fa-lightbulb mr-2"></i> Tip: Goals help you stay motivated throughout the
                            semester!
                        </div>

                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle mr-2"></i> Your goal will be saved for the selected semester.
                            You can update it at any time.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" name="set_goal" class="btn btn-primary">
                            <i class="fas fa-bullseye mr-2"></i> Set Goal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <!-- Custom Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Get modal elements
            const setGoalModal = $('#setGoalModal');
            const setGoalBtn = document.getElementById('setGoalBtn');
            const updateGoalBtn = document.getElementById('updateGoalBtn');
            const suggestedGoalRadios = document.querySelectorAll('input[name="suggested_goal"]');
            const pointsGoalInput = document.getElementById('points_goal');

            // Set up button click handlers
            if (setGoalBtn) {
                setGoalBtn.addEventListener('click', function () {
                    setGoalModal.modal('show');
                });
            }

            if (updateGoalBtn) {
                updateGoalBtn.addEventListener('click', function () {
                    setGoalModal.modal('show');
                });
            }

            // Handle suggested goal button clicks
            suggestedGoalRadios.forEach(function (radio) {
                radio.addEventListener('click', function () {
                    // Set the goal input value to the selected suggested goal value
                    pointsGoalInput.value = this.value;

                    // Update active class for styling
                    suggestedGoalRadios.forEach(function (r) {
                        r.parentElement.classList.remove('active');
                    });
                    this.parentElement.classList.add('active');
                });
            });

            // Set initial suggested goal based on current points when modal opens
            setGoalModal.on('show.bs.modal', function () {
                const currentPoints = <?php echo $total_points; ?>;
                let suggestedGoal = 50; // Default beginner goal

                // Suggest appropriate goal based on current points
                if (currentPoints >= 200) {
                    suggestedGoal = 300; // Champion level
                } else if (currentPoints >= 150) {
                    suggestedGoal = 200; // Active level
                } else if (currentPoints >= 100) {
                    suggestedGoal = 150; // Engaged level
                } else if (currentPoints >= 50) {
                    suggestedGoal = 100; // Regular level
                }

                // Set the suggested goal in the input
                pointsGoalInput.value = suggestedGoal;

                // Select the appropriate suggested goal button
                suggestedGoalRadios.forEach(function (radio) {
                    radio.parentElement.classList.remove('active');
                    if (parseInt(radio.value) === suggestedGoal) {
                        radio.checked = true;
                        radio.parentElement.classList.add('active');
                    } else {
                        radio.checked = false;
                    }
                });

                // Show goal-setting tips based on current points
                const tipElement = document.getElementById('goalSettingTips');
                if (tipElement) {
                    if (currentPoints === 0) {
                        tipElement.innerHTML = '<i class="fas fa-lightbulb mr-2"></i> Tip: Start with attending a few club events to earn your first points!';
                    } else if (currentPoints < 50) {
                        tipElement.innerHTML = '<i class="fas fa-lightbulb mr-2"></i> Tip: Try to attend at least one event per month to reach your goal!';
                    } else if (currentPoints < 100) {
                        tipElement.innerHTML = '<i class="fas fa-lightbulb mr-2"></i> Tip: Consider attending faculty and national events for higher points!';
                    } else {
                        tipElement.innerHTML = '<i class="fas fa-lightbulb mr-2"></i> Tip: International events offer the most points. Check the events page for upcoming opportunities!';
                    }
                }
            });

            // Add animation for progress bars
            const progressBars = document.querySelectorAll('.progress-bar');
            setTimeout(function () {
                progressBars.forEach(function (bar) {
                    bar.style.transition = 'width 1s ease-in-out';
                });
            }, 200);
        });
    </script>

    <footer class="bg-light mt-5 py-3">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> EventSphere@UPSI. All rights reserved.</p>
        </div>
    </footer>
</body>

</html>