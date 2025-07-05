<?php
/**
 * Student Registration Page
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once 'config/db.php';

// Initialize variables
$name = $matric_id = $email = $phone = $password = $confirm_password = "";
$name_err = $matric_id_err = $email_err = $phone_err = $password_err = $confirm_password_err = "";
$success_message = "";

// Process form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate name
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter your name.";
    } else {
        $name = trim($_POST["name"]);
    }
    
    // Validate matric ID
    if (empty(trim($_POST["matric_id"]))) {
        $matric_id_err = "Please enter your matric ID.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE matric_id = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_matric_id);
            
            // Set parameters
            $param_matric_id = trim($_POST["matric_id"]);
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $matric_id_err = "This matric ID is already registered.";
                } else {
                    $matric_id = trim($_POST["matric_id"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = trim($_POST["email"]);
        // Check if email ends with @siswa.upsi.edu.my
        if (!preg_match('/@siswa\.upsi\.edu\.my$/', $email)) {
            $email_err = "Please use your UPSI student email (@siswa.upsi.edu.my).";
        } else {
            // Check if email already exists
            $sql = "SELECT id FROM users WHERE email = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $param_email);
                $param_email = $email;
                
                if ($stmt->execute()) {
                    $stmt->store_result();
                    
                    if ($stmt->num_rows == 1) {
                        $email_err = "This email is already registered.";
                    }
                } else {
                    echo "Oops! Something went wrong. Please try again later.";
                }
                
                $stmt->close();
            }
        }
    }
    
    // Validate phone number
    if (empty(trim($_POST["phone"]))) {
        $phone_err = "Please enter your phone number.";
    } else {
        $phone = trim($_POST["phone"]);
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if ($password != $confirm_password) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Check input errors before inserting into database
    if (empty($name_err) && empty($matric_id_err) && empty($email_err) && empty($phone_err) && empty($password_err) && empty($confirm_password_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (name, matric_id, email, phone, password, role) VALUES (?, ?, ?, ?, ?, 'student')";
         
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("sssss", $param_name, $param_matric_id, $param_email, $param_phone, $param_password);
            
            // Set parameters
            $param_name = $name;
            $param_matric_id = $matric_id;
            $param_email = $email;
            $param_phone = $phone;
            $param_password = $password; // Store password as plain text
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Registration successful
                $success_message = "Registration successful! You can now <a href='login.php'>login</a>.";
                // Reset form fields
                $name = $matric_id = $email = $phone = $password = $confirm_password = "";
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
    <title>Student Registration - EventSphere@UPSI</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="logo">
                <h1>Event<span>Sphere</span>@UPSI</h1>
                <p>Navigate, Engage & Excel</p>
            </div>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <h2 class="text-center mb-4">Student Registration</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control <?php echo (!empty($name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $name; ?>">
                    <span class="invalid-feedback"><?php echo $name_err; ?></span>
                </div>    
                <div class="form-group">
                    <label>Matric ID</label>
                    <input type="text" name="matric_id" class="form-control <?php echo (!empty($matric_id_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $matric_id; ?>">
                    <span class="invalid-feedback"><?php echo $matric_id_err; ?></span>
                </div>
                <div class="form-group">
                    <label>Student Email</label>
                    <input type="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" placeholder="example@siswa.upsi.edu.my">
                    <span class="invalid-feedback"><?php echo $email_err; ?></span>
                    <small class="form-text text-muted">Must end with @siswa.upsi.edu.my</small>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" class="form-control <?php echo (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $phone; ?>">
                    <span class="invalid-feedback"><?php echo $phone_err; ?></span>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>">
                    <span class="invalid-feedback"><?php echo $password_err; ?></span>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $confirm_password; ?>">
                    <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-register">Register</button>
                </div>
                <div class="login-link">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>