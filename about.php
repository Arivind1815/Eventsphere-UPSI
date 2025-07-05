<?php
// Start session
session_start();

// Check for session timeout (5 minutes of inactivity)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 300)) {
    // Last activity was more than 5 minutes ago
    session_unset();
    session_destroy();
}
$_SESSION['last_activity'] = time(); // Update last activity time

// Check if user is logged in
$loggedIn = isset($_SESSION['user_id']) ? true : false;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventSphere@UPSI - Navigate, Engage & Excel</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    /* Global Styles */
    :root {
        --primary-color: #3a86ff;
        --secondary-color: #8338ec;
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
        background-image: radial-gradient(circle at top right, rgba(58, 134, 255, 0.1), transparent), 
                          radial-gradient(circle at bottom left, rgba(131, 56, 236, 0.1), transparent);
        background-attachment: fixed;
        color: var(--dark-color);
        padding-top: 50px;
        min-height: 100vh;
        margin: 0;
    }

    /* Container Styling */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 15px;
    }

    /* Navbar Styles */
    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        background-color: rgba(255, 255, 255, 0.95);
        box-shadow: var(--box-shadow);
        backdrop-filter: blur(10px);
        z-index: 1000;
        padding: 15px 0;
    }
    
    .navbar-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 15px;
    }
    
    .navbar-logo {
        display: flex;
        align-items: center;
    }
    
    .navbar-logo h2 {
        font-size: 24px;
        margin: 0;
    }
    
    .navbar-logo span {
        color: var(--primary-color);
        background: var(--gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 800;
    }
    
    .navbar-links {
        display: flex;
        align-items: center;
    }
    
    .navbar-links a {
        margin-left: 30px;
        text-decoration: none;
        color: var(--dark-color);
        font-weight: 500;
        font-size: 16px;
        transition: var(--transition);
    }
    
    .navbar-links a:hover {
        color: var(--primary-color);
    }
    
    .btn {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-primary {
        background: var(--gradient);
        color: white !important;
        border: none;
        box-shadow: 0 4px 15px rgba(58, 134, 255, 0.3);
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 7px 20px rgba(58, 134, 255, 0.4);
    }
    
    .btn-outline {
        background: transparent;
        color: var(--primary-color) !important;
        border: 2px solid var(--primary-color);
    }
    
    .btn-outline:hover {
        background: var(--gradient);
        color: white !important;
        border-color: transparent;
    }
    
    /* Hero Section */
    .hero {
        padding: 120px 0 80px;
        min-height: 80vh;
        display: flex;
        align-items: center;
    }
    
    .hero-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        align-items: center;
    }
    
    .hero-content h1 {
        font-size: 48px;
        font-weight: 800;
        line-height: 1.2;
        margin-bottom: 20px;
        color: var(--dark-color);
    }
    
    .hero-content h1 span {
        color: var(--primary-color);
        background: var(--gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .hero-content p {
        font-size: 18px;
        line-height: 1.6;
        color: var(--dark-color);
        opacity: 0.8;
        margin-bottom: 30px;
    }
    
    .hero-buttons {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }
    
    .hero-image {
        position: relative;
    }
    
    .hero-image img {
        width: 100%;
        border-radius: 15px;
        box-shadow: var(--box-shadow);
    }
    
    /* Features Section */
    .feature-box {
        background-color: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        box-shadow: var(--box-shadow);
        padding: 30px;
        transition: var(--transition);
        margin-bottom: 30px;
        border: 1px solid rgba(255, 255, 255, 0.18);
        backdrop-filter: blur(10px);
    }
    
    .feature-box:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }
    
    .feature-icon {
        width: 70px;
        height: 70px;
        background: var(--gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 15px;
        margin-bottom: 20px;
    }
    
    .feature-icon i {
        font-size: 30px;
        color: white;
    }
    
    .feature-title {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 15px;
    }
    
    .feature-description {
        font-size: 16px;
        line-height: 1.6;
        color: var(--dark-color);
        opacity: 0.8;
    }
    
    .features-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 30px;
        margin-top: 60px;
    }
    
    .section-title {
        text-align: center;
        margin-bottom: 60px;
    }
    
    .section-title h2 {
        font-size: 36px;
        font-weight: 800;
        color: var(--dark-color);
        margin-bottom: 15px;
    }
    
    .section-title h2 span {
        color: var(--primary-color);
        background: var(--gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .section-title p {
        font-size: 18px;
        color: var(--dark-color);
        opacity: 0.8;
        max-width: 700px;
        margin: 0 auto;
    }
    
    .features-section, .categories-section {
        padding: 80px 0;
    }
    
    /* Categories Section */
    .category-card {
        background-color: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        overflow: hidden;
        box-shadow: var(--box-shadow);
        transition: var(--transition);
        position: relative;
        height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .category-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }
    
    .category-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: var(--gradient);
        opacity: 0.8;
        z-index: 1;
    }
    
    .category-content {
        position: relative;
        z-index: 2;
        text-align: center;
        padding: 20px;
        color: white;
    }
    
    .category-content h3 {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 10px;
    }
    
    .category-content p {
        font-size: 16px;
        opacity: 0.9;
    }
    
    .categories-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 30px;
    }
    
    /* Footer */
    .footer {
        background-color: var(--dark-color);
        color: white;
        padding: 60px 0 30px;
    }
    
    .footer-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 40px;
    }
    
    .footer-logo h3 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 15px;
    }
    
    .footer-logo span {
        color: var(--primary-color);
        background: var(--gradient);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 800;
    }
    
    .footer-description {
        font-size: 15px;
        line-height: 1.6;
        opacity: 0.8;
        margin-bottom: 20px;
    }
    
    .footer-social {
        display: flex;
        gap: 15px;
    }
    
    .footer-social a {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        transition: var(--transition);
        text-decoration: none;
    }
    
    .footer-social a:hover {
        background: var(--gradient);
        transform: translateY(-3px);
    }
    
    .footer-heading {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary-color);
        display: inline-block;
    }
    
    .footer-links {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .footer-links li {
        margin-bottom: 10px;
    }
    
    .footer-links a {
        color: white;
        opacity: 0.8;
        text-decoration: none;
        transition: var(--transition);
        font-size: 15px;
    }
    
    .footer-links a:hover {
        opacity: 1;
        color: var(--primary-color);
        padding-left: 5px;
    }
    
    .footer-bottom {
        margin-top: 40px;
        padding-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        text-align: center;
        font-size: 14px;
        opacity: 0.8;
    }
    
    /* Mobile Menu */
    .mobile-menu-toggle {
        display: none;
        background: none;
        border: none;
        font-size: 24px;
        color: var(--dark-color);
        cursor: pointer;
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .hero-container {
            grid-template-columns: 1fr;
            text-align: center;
        }
        
        .hero-image {
            grid-row: 1;
            margin-bottom: 30px;
        }
        
        .hero-buttons {
            justify-content: center;
        }
        
        .features-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .categories-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .footer-grid {
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
    }
    
    @media (max-width: 768px) {
        .navbar-links {
            display: none;
            position: absolute;
            top: 70px;
            left: 0;
            width: 100%;
            background-color: rgba(255, 255, 255, 0.95);
            flex-direction: column;
            padding: 20px 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-links.active {
            display: flex;
        }
        
        .navbar-links a {
            margin: 10px 0;
        }
        
        .mobile-menu-toggle {
            display: block;
        }
        
        .hero-content h1 {
            font-size: 36px;
        }
        
        .features-grid {
            grid-template-columns: 1fr;
        }
        
        .categories-grid {
            grid-template-columns: 1fr;
        }
        
        .footer-grid {
            grid-template-columns: 1fr;
            gap: 40px;
        }
    }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-logo">
                <h2><span>EventSphere</span>@UPSI</h2>
            </div>
            
            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="navbar-links" id="navbarLinks">
                <a href="index.php" class="active">Home</a>
                <a href="about.php">About Us</a>
                <a href="faq.php">FAQ</a>
                
                <?php if($loggedIn): ?>
                    <a href="dashboard.php" class="btn btn-primary">Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline">Login</a>
                    <a href="register.php" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="hero">
        <div class="container hero-container">
            <div class="hero-content">
                <h1>Navigate, Engage & <span>Excel</span> with EventSphere</h1>
                <p>Discover and participate in events across UPSI. Track your attendance, earn points, and build your co-curricular portfolio all in one place.</p>
                
                <div class="hero-buttons">
                    <?php if($loggedIn): ?>
                        <a href="events.php" class="btn btn-primary">Browse Events</a>
                        <a href="dashboard.php" class="btn btn-outline">My Dashboard</a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-primary">Get Started</a>
                        <a href="about.php" class="btn btn-outline">Learn More</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- <div class="hero-image">
                <img src="images/hero-image.jpg" alt="UPSI Students at an event" >
            </div> -->
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="section-title">
                <h2>Key <span>Features</span></h2>
                <p>Discover why students love using EventSphere@UPSI for their campus events</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3 class="feature-title">Easy Event Discovery</h3>
                    <p class="feature-description">Find events based on your interests, faculty, or clubs. Filter by categories and view on calendar.</p>
                </div>
                
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <h3 class="feature-title">QR Attendance</h3>
                    <p class="feature-description">Simply scan the QR code at events to mark your attendance and earn points automatically.</p>
                </div>
                
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h3 class="feature-title">Champ Points System</h3>
                    <p class="feature-description">Earn points for attending events based on category and track your progress toward goals.</p>
                </div>
                
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3 class="feature-title">Event Reminders</h3>
                    <p class="feature-description">Get notified about upcoming events you've registered for so you never miss out.</p>
                </div>
                
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <h3 class="feature-title">Event History</h3>
                    <p class="feature-description">Keep track of all events you've attended and points earned for your co-curricular record.</p>
                </div>
                
                <div class="feature-box">
                    <div class="feature-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3 class="feature-title">Event Feedback</h3>
                    <p class="feature-description">Provide valuable feedback for events you've attended to help improve future experiences.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Categories Section -->
    <section class="categories-section">
        <div class="container">
            <div class="section-title">
                <h2>Event <span>Categories</span></h2>
                <p>Explore events by category to find those that match your interests</p>
            </div>
            
            <div class="categories-grid">
                <div class="category-card">
                    <div class="category-content">
                        <h3>Faculty Events</h3>
                        <p>Academic and faculty-specific activities</p>
                    </div>
                </div>
                
                <div class="category-card">
                    <div class="category-content">
                        <h3>Club Activities</h3>
                        <p>Events organized by student clubs and societies</p>
                    </div>
                </div>
                
                <div class="category-card">
                    <div class="category-content">
                        <h3>College Events</h3>
                        <p>Activities organized by residential colleges</p>
                    </div>
                </div>
                
                <div class="category-card">
                    <div class="category-content">
                        <h3>International Events</h3>
                        <p>Events with international participation</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <div class="footer-logo">
                        <h3><span>EventSphere</span>@UPSI</h3>
                    </div>
                    <p class="footer-description">The all-in-one platform for UPSI students to discover, register, and track campus events and co-curricular activities.</p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-links-column">
                    <h4 class="footer-heading">Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="events.php">Events</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                    </ul>
                </div>
                
                <div class="footer-links-column">
                    <h4 class="footer-heading">Account</h4>
                    <ul class="footer-links">
                        <li><a href="register.php">Register</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="profile.php">My Profile</a></li>
                    </ul>
                </div>
                
                <div class="footer-links-column">
                    <h4 class="footer-heading">Contact</h4>
                    <ul class="footer-links">
                        <li><a href="mailto:support@eventsphere.upsi.edu.my">support@eventsphere.upsi.edu.my</a></li>
                        <li><a href="tel:+60379673103">+603-7967 3103</a></li>
                        <li><a href="#">Universiti Pendidikan Sultan Idris, 35900 Tanjong Malim, Perak, Malaysia</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> EventSphere@UPSI. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        // Mobile Menu Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const navbarLinks = document.getElementById('navbarLinks');
            
            if(mobileMenuToggle && navbarLinks) {
                mobileMenuToggle.addEventListener('click', function() {
                    navbarLinks.classList.toggle('active');
                    
                    if(mobileMenuToggle.innerHTML.includes('fa-bars')) {
                        mobileMenuToggle.innerHTML = '<i class="fas fa-times"></i>';
                    } else {
                        mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                    }
                });
            }
        });
    </script>
</body>
</html><a href="about.php">About Us</a></li>
                        <li><a href="events.php">Events</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                    </ul>
                </div>
                
                <div class="footer-links-column">
                    <h4 class="footer-heading">Account</h4>
                    <ul class="footer-links">
                        <li><a href="register.php">Register</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="profile.php">My Profile</a></li>
                    </ul>
                </div>
                
                <div class="footer-links-column">
                    <h4 class="footer-heading">Contact</h4>
                    <ul class="footer-links">
                        <li><a href="mailto:support@eventsphere.upsi.edu.my">support@eventsphere.upsi.edu.my</a></li>
                        <li><a href="tel:+60379673103">+603-7967 3103</a></li>
                        <li><a href="#">Universiti Pendidikan Sultan Idris, 35900 Tanjong Malim, Perak, Malaysia</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> EventSphere@UPSI. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        // Mobile Menu Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const navbarLinks = document.getElementById('navbarLinks');
            
            if(mobileMenuToggle && navbarLinks) {
                mobileMenuToggle.addEventListener('click', function() {
                    navbarLinks.classList.toggle('active');
                    
                    if(mobileMenuToggle.innerHTML.includes('fa-bars')) {
                        mobileMenuToggle.innerHTML = '<i class="fas fa-times"></i>';
                    } else {
                        mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                    }
                });
            }
        });
    </script>
</body>
</html>