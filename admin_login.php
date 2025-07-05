<?php
/**
 * Admin Login Page
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once 'config/db.php';

// Initialize variables
$matric_id = $password = "";
$matric_id_err = $password_err = $login_err = "";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if matric ID is empty
    if (empty(trim($_POST["matric_id"]))) {
        $matric_id_err = "Please enter your Staff ID.";
    } else {
        $matric_id = trim($_POST["matric_id"]);
    }
    
    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if (empty($matric_id_err) && empty($password_err)) {
        // Prepare a select statement - only select users with admin role
        $sql = "SELECT id, name, matric_id, email, password, role FROM users WHERE matric_id = ? AND role = 'admin'";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_matric_id);
            
            // Set parameters
            $param_matric_id = $matric_id;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                // Check if matric ID exists with admin role, if yes then verify password
                if ($stmt->num_rows == 1) {
                    // Bind result variables
                    $stmt->bind_result($id, $name, $matric_id, $email, $db_password, $role);
                    
                    if ($stmt->fetch()) {
                        // Compare plain text passwords
                        if ($password === $db_password) {
                            // Password is correct, so start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["name"] = $name;
                            $_SESSION["matric_id"] = $matric_id;
                            $_SESSION["email"] = $email;
                            $_SESSION["role"] = $role;
                            
                            // Set last activity timestamp for session timeout
                            $_SESSION["last_activity"] = time();
                            
                            // Redirect admin to dashboard
                            header("location: admin/index.php");
                            exit;
                        } else {
                            // Password is not valid
                            $login_err = "Invalid Staff ID or password.";
                        }
                    }
                } else {
                    // Matric ID doesn't exist or user is not an admin
                    $login_err = "Invalid administrator credentials or you don't have administrative access.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - EventSphere@UPSI</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    /* Global Styles */
    :root {
        --primary-color: #8338ec;
        --secondary-color: #3a86ff;
        --accent-color: #ff006e;
        --light-color: #f8f9fa;
        --dark-color: #212529;
        --success-color: #38b000;
        --gradient: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s ease;
    }

    body {
        font-family: 'Poppins', 'Segoe UI', sans-serif;
        background-color: #f0f2f5;
        background-image: radial-gradient(circle at top right, rgba(131, 56, 236, 0.1), transparent), 
                      radial-gradient(circle at bottom left, rgba(58, 134, 255, 0.1), transparent);
        background-attachment: fixed;
        color: var(--dark-color);
        min-height: 100vh;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    /* Form Container */
    .form-container {
        max-width: 450px;
        width: 100%;
        background-color: rgba(255, 255, 255, 0.95);
        padding: 40px;
        border-radius: 15px;
        box-shadow: var(--box-shadow);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        position: relative;
        overflow: hidden;
    }

    .form-container::before {
        content: '';
        position: absolute;
        top: -50px;
        right: -50px;
        width: 100px;
        height: 100px;
        background: var(--gradient);
        border-radius: 50%;
        opacity: 0.2;
        z-index: 0;
    }

    /* Logo Styling */
    .logo {
        text-align: center;
        margin-bottom: 30px;
        position: relative;
    }

    .logo img {
        max-width: 300px;
        height: auto;
        margin-bottom: 15px;
    }

    .logo h1 {
        font-size: 32px;
        font-weight: 700;
        letter-spacing: -0.5px;
        margin-bottom: 5px;
        color: var(--dark-color);
    }

    .logo span {
        color: var(--primary-color);
        background: var(--gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 800;
    }

    .logo p {
        font-size: 14px;
        color: var(--dark-color);
        opacity: 0.7;
        letter-spacing: 0.5px;
    }

    /* Admin Label */
    .admin-label {
        position: absolute;
        top: 15px;
        right: 15px;
        background: var(--gradient);
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        box-shadow: 0 3px 10px rgba(131, 56, 236, 0.2);
    }

    /* Heading */
    h2 {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 25px;
        color: var(--dark-color);
        text-align: center;
    }

    /* Alert */
    .alert {
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .alert-danger {
        background-color: rgba(255, 0, 110, 0.1);
        border: 1px solid rgba(255, 0, 110, 0.3);
        color: var(--accent-color);
    }

    /* Form Elements */
    .form-group {
        margin-bottom: 25px;
        position: relative;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        font-size: 14px;
        color: var(--dark-color);
        letter-spacing: 0.3px;
    }

    .form-control {
        height: 50px;
        border-radius: 8px;
        border: 2px solid #e9ecef;
        padding: 10px 15px;
        font-size: 15px;
        transition: var(--transition);
        background-color: rgba(255, 255, 255, 0.9);
        width: 100%;
        box-sizing: border-box;
    }

    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(131, 56, 236, 0.2);
        outline: none;
    }

    .form-control.is-invalid {
        border-color: var(--accent-color);
    }

    .invalid-feedback {
        font-size: 12px;
        color: var(--accent-color);
        margin-top: 5px;
        display: block;
    }

    /* Button Styling */
    .btn {
        width: 100%;
        height: 50px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        letter-spacing: 0.5px;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        display: inline-block;
        text-align: center;
        line-height: 50px;
        text-decoration: none;
    }

    .btn-primary {
        background: var(--gradient);
        color: white;
        box-shadow: 0 4px 15px rgba(131, 56, 236, 0.3);
        position: relative;
        overflow: hidden;
    }

    .btn-primary:before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: 0.5s;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 7px 20px rgba(131, 56, 236, 0.4);
    }

    .btn-primary:hover:before {
        left: 100%;
    }

    .btn-primary:active {
        transform: translateY(0);
    }

    /* Security Notice */
    .security-notice {
        margin-top: 20px;
        padding: 10px 15px;
        background-color: rgba(131, 56, 236, 0.05);
        border-radius: 8px;
        font-size: 12px;
        color: #6c757d;
        text-align: center;
    }

    .security-notice i {
        color: var(--primary-color);
        margin-right: 5px;
    }

    /* Student Notice */
    .student-notice {
        text-align: center;
        margin-top: 20px;
        font-size: 13px;
        color: #6c757d;
    }

    .student-notice a {
        color: var(--dark-color);
        font-weight: 500;
        text-decoration: none;
    }

    .student-notice a:hover {
        text-decoration: underline;
    }

    /* Responsive adjustments */
    @media (max-width: 576px) {
        .form-container {
            padding: 30px 20px;
        }
        
        body {
            padding: 10px;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="admin-label">
                <i class="fas fa-user-shield"></i> Administrators Only
            </div>
            
            <div class="logo">
                <img src="uploads/img/navlogo.png" alt="EventSphere@UPSI Logo" width="120" >
                <h1>Event<span>Sphere</span>@UPSI</h1>
                <p>Navigate, Engage & Excel</p>
            </div>
            
            <h2>Administrator Login</h2>
            
            <?php if (!empty($login_err)): ?>
                <div class="alert alert-danger"><?php echo $login_err; ?></div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label>Staff ID</label>
                    <input type="text" name="matric_id" class="form-control <?php echo (!empty($matric_id_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $matric_id; ?>" placeholder="Enter your Staff ID">
                    <span class="invalid-feedback"><?php echo $matric_id_err; ?></span>
                </div>    
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" placeholder="Enter your password">
                    <span class="invalid-feedback"><?php echo $password_err; ?></span>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Access Admin Dashboard</button>
                </div>
                
                <div class="security-notice">
                    <i class="fas fa-lock"></i> This secure area is for authorized administrators only. Unauthorized access attempts are logged and monitored.
                </div>
                
                <div class="student-notice">
                    <p>Looking for student login? <a href="index.php">Go to Student Portal</a></p>
                </div>
            </form>
        </div>
    </div>
</body>
</html>