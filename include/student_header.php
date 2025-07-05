<?php
/**
 * Student Header Include File
 * EventSphere@UPSI: Navigate, Engage & Excel
 */
ob_start();
// Check if the user is logged in as student
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "student") {
    header("location: ../login.php");
    exit;
}

// Check session timeout (5 minutes = 300 seconds)
$session_timeout = 3000;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // Last activity was more than 5 minutes ago
    session_unset();     // Unset $_SESSION variable for the session
    session_destroy();   // Destroy session data
    header("location: ../login.php");
    exit;
}

// Update last activity time stamp
$_SESSION['last_activity'] = time();

// Get current page for active nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Check if there are notifications
$has_notifications = false;
$notification_count = 0;

// You can uncomment and use this code when you implement the notifications table

if (isset($_SESSION["id"])) {
    $user_id = $_SESSION["id"];
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $notification_count = $row['count'];
            $has_notifications = ($notification_count > 0);
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - " : ""; ?>EventSphere@UPSI</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/styles.css">

    <?php if (isset($extra_css)): ?>
        <?php echo $extra_css; ?>
    <?php endif; ?>

    <style>
        /* Custom navbar styles */
        .navbar {
            background: linear-gradient(135deg, #3a86ff 0%, #8338ec 100%) !important;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 10px 0;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            font-family: 'Poppins', sans-serif;
        }

        .navbar-brand img {
            height: 50px;
            margin-right: 10px;
        }

        .brand-text {
            font-weight: 700;
            letter-spacing: -0.5px;
            font-size: 22px;
        }

        .brand-highlight {
            color: #fff;
            font-weight: 800;
        }

        .navbar-nav .nav-item {
            margin: 0 2px;
        }

        .navbar-nav .nav-link {
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 14px;
        }

        .navbar-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .navbar-nav .active .nav-link {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }

        .dropdown-item {
            padding: 10px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background-color: rgba(58, 134, 255, 0.1);
        }

        .notification-badge {
            position: absolute;
            top: 0px;
            right: 2px;
            font-size: 0.6rem;
            padding: 3px 6px;
            background-color: #ff006e;
            border: 2px solid #ffffff;
        }

        /* Alert styling */
        .alert {
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            font-weight: 500;
        }

        .alert-success {
            background-color: rgba(56, 176, 0, 0.1);
            border-color: rgba(56, 176, 0, 0.2);
            color: #38b000;
        }

        .alert-danger {
            background-color: rgba(255, 0, 110, 0.1);
            border-color: rgba(255, 0, 110, 0.2);
            color: #ff006e;
        }

        /* Profile dropdown positioning */
        .profile-dropdown {
            margin-left: 10px;
        }

        /* For smaller screens */
        @media (max-width: 992px) {
            .navbar-collapse {
                background-color: rgba(58, 134, 255, 0.95);
                border-radius: 10px;
                padding: 15px;
                margin-top: 10px;
                backdrop-filter: blur(10px);
            }

            .navbar-nav .nav-link {
                padding: 12px 15px;
                margin-bottom: 5px;
            }

            .notification-badge {
                position: static;
                margin-left: 5px;
                vertical-align: top;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="../uploads/img/navlogo.png" alt="EventSphere Logo">
                <span class="brand-text">Event<span class="brand-highlight">Sphere</span>@UPSI</span>
            </a>

            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home mr-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item <?php echo ($current_page == 'events.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="events.php">
                            <i class="fas fa-compass mr-1"></i> Discover Events
                        </a>
                    </li>
                    <li class="nav-item <?php echo ($current_page == 'myevents.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="myevents.php">
                            <i class="fas fa-calendar-check mr-1"></i> My Events
                        </a>
                    </li>
                    <li class="nav-item <?php echo ($current_page == 'goals.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="goals.php">
                            <i class="fas fa-bullseye mr-1"></i> My Goals
                        </a>
                    </li>
                    <li class="nav-item <?php echo ($current_page == 'faq.php') ? 'active' : ''; ?>">
                        <a class="nav-link" href="faq.php">
                            <i class="fas fa-question-circle mr-1"></i> FAQ
                        </a>
                    </li>
                    <li class="nav-item <?php echo ($current_page == 'notifications.php') ? 'active' : ''; ?>">
                        <a class="nav-link position-relative" href="notifications.php">
                            <i class="fas fa-bell mr-1"></i> Notifications
                            <?php if ($has_notifications): ?>
                                <span
                                    class="badge badge-pill badge-danger notification-badge"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav">

                    <li class="nav-item dropdown profile-dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-user-circle mr-1"></i> <?php echo htmlspecialchars($_SESSION["name"]); ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-id-card mr-2"></i> Profile
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="../logout_student.php">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Notification area for alerts -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="container mt-3">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="container mt-3">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>