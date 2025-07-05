<?php
/**
 * Frequently Asked Questions
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "Frequently Asked Questions";

// Include header
include_once '../include/student_header.php';

// Get all FAQs
$faqs = [];
$sql = "SELECT * FROM faq ORDER BY category, order_num";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $faqs[] = $row;
    }
}

// Get unique categories
$categories = ["General"]; // Default category
$category_sql = "SELECT DISTINCT category FROM faq WHERE category != 'General' ORDER BY category";
$category_result = $conn->query($category_sql);

if ($category_result && $category_result->num_rows > 0) {
    while ($row = $category_result->fetch_assoc()) {
        if (!empty($row['category']) && !in_array($row['category'], $categories)) {
            $categories[] = $row['category'];
        }
    }
}

// Group FAQs by category
$faqs_by_category = [];
foreach ($categories as $category) {
    $faqs_by_category[$category] = [];
}

foreach ($faqs as $faq) {
    $category = $faq['category'];
    if (!isset($faqs_by_category[$category])) {
        $faqs_by_category[$category] = [];
    }
    $faqs_by_category[$category][] = $faq;
}
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
    <style>
        .faq-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .faq-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
        }
        .faq-card .card-header {
            cursor: pointer;
            background-color: white;
        }
        .faq-card .card-body {
            display: none;
        }
        .faq-card.active .card-body {
            display: block;
        }
        .faq-category-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            border-radius: 50%;
            background-color: #f8f9fa;
            margin-right: 1rem;
        }
        .nav-pills .nav-link.active {
            background-color: #007bff;
        }
        .search-highlight {
            background-color: #ffff99;
            padding: 2px;
            font-weight: bold;
        }

        /* Enhanced Contact Section Styles for FAQ Page */
.contact-support-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border: none;
    border-radius: 15px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    overflow: hidden;
}

.contact-support-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.contact-header {
    color: #2c3e50;
    font-weight: 600;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}

.contact-header i {
    background: linear-gradient(45deg, #007bff, #0056b3);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-size: 1.2em;
}

.contact-info-item {
    background: #ffffff;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.contact-info-item:hover {
    background: #f8f9fa;
    border-color: #007bff;
    transform: translateX(5px);
}

.contact-info-item i {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
    border-radius: 50%;
    font-size: 0.8em;
    margin-right: 0.75rem;
    flex-shrink: 0;
}

.contact-info-label {
    font-size: 0.75em;
    color: #6c757d;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.contact-info-value {
    color: #2c3e50;
    font-weight: 600;
    font-size: 0.95em;
    transition: color 0.3s ease;
}

.contact-info-item:hover .contact-info-value {
    color: #007bff;
}

.contact-btn {
    border-radius: 10px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    border: none;
    position: relative;
    overflow: hidden;
}

.contact-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.contact-btn:hover::before {
    left: 100%;
}

.btn-email {
    background: linear-gradient(45deg, #007bff, #0056b3);
    color: white;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
}

.btn-email:hover {
    background: linear-gradient(45deg, #0056b3, #004085);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
}

.btn-phone {
    background: transparent;
    color: #007bff;
    border: 2px solid #007bff;
    box-shadow: 0 4px 15px rgba(0, 123, 255, 0.1);
}

.btn-phone:hover {
    background: #007bff;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
}

.support-hours {
    background: linear-gradient(45deg, #e3f2fd, #f3e5f5);
    border: none;
    border-radius: 10px;
    border-left: 4px solid #007bff;
    margin-top: 1.5rem;
    margin-bottom: 0;
}

.support-hours .alert-content {
    display: flex;
    align-items: center;
    font-size: 0.9em;
}

.support-hours i {
    color: #007bff;
    margin-right: 0.5rem;
}

/* Animation for icons */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.contact-info-item:hover i {
    animation: pulse 0.6s ease-in-out;
}

/* Accessibility improvements */
.contact-btn:focus {
    outline: 3px solid rgba(0, 123, 255, 0.5);
    outline-offset: 2px;
}

.contact-info-item:focus-within {
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}

/* Loading state for buttons */
.contact-btn.loading {
    pointer-events: none;
    opacity: 0.7;
}

.contact-btn.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 20px;
    height: 20px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Mobile responsiveness for contact section */
@media (max-width: 768px) {
    .contact-btn {
        margin-bottom: 0.75rem;
    }
    
    .contact-info-item {
        padding: 0.75rem;
    }
}
    </style>
</head>
<body>
<div class="container mt-4">
    <!-- Page Header -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h1 class="mb-0">Frequently Asked Questions</h1>
                    <p class="text-muted">Find answers to common questions about EventSphere@UPSI</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search Bar -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-white border-right-0">
                                <i class="fas fa-search text-primary"></i>
                            </span>
                        </div>
                        <input type="text" class="form-control border-left-0" id="searchFaq" placeholder="Search for answers...">
                    </div>
                    <div id="searchResults" class="mt-3" style="display: none;">
                        <div class="alert alert-info">
                            <span id="resultCount">0</span> results found for "<span id="searchQuery"></span>"
                        </div>
                        <div id="searchResultsList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- FAQ Content -->
    <div class="row">
        <div class="col-md-3 mb-4">
            <!-- Category Navigation -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Categories</h5>
                </div>
                <div class="card-body p-0">
                    <div class="nav flex-column nav-pills" id="faq-categories" role="tablist">
                        <a class="nav-link active" id="all-tab" data-toggle="pill" href="#all" role="tab">
                            <i class="fas fa-list-ul mr-2"></i> All Categories
                        </a>
                        <?php foreach ($categories as $index => $category): ?>
                        <a class="nav-link" id="<?php echo strtolower(str_replace(' ', '-', $category)); ?>-tab" data-toggle="pill" href="#<?php echo strtolower(str_replace(' ', '-', $category)); ?>" role="tab">
                            <i class="<?php echo getCategoryIcon($category); ?> mr-2"></i> <?php echo htmlspecialchars($category); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Need Help Box -->
           <!-- Enhanced Need Help Box -->
<div class="card contact-support-card border-0 shadow-sm mt-4">
    <div class="card-body">
        <h5 class="contact-header">
            <i class="fas fa-headset mr-2"></i> 
            Need More Help?
        </h5>
        <p class="mb-3 text-muted">Can't find what you're looking for? Contact our support team for assistance.</p>
        
        <!-- Contact Information -->
        <div class="row mb-3">
            <div class="col-md-12 mb-2">
                <div class="contact-info-item d-flex align-items-center">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <div class="contact-info-label">Email</div>
                        <div class="contact-info-value">portal@ict.upsi.edu.my</div>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="contact-info-item d-flex align-items-center">
                    <i class="fas fa-phone"></i>
                    <div>
                        <div class="contact-info-label">Contact</div>
                        <div class="contact-info-value">+605-4505826</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Buttons -->
        <div class="row">
            <div class="col-md-12 mb-2">
                <a href="mailto:portal@ict.upsi.edu.my?subject=EventSphere Support Request&body=Hello,%0D%0A%0D%0AI need assistance with:%0D%0A%0D%0APlease describe your issue here...%0D%0A%0D%0AThank you!" 
                   class="btn contact-btn btn-email btn-block">
                    <i class="fas fa-envelope mr-2"></i> Send Email
                </a>
            </div>
            <div class="col-md-12">
                <a href="tel:+6054505826" class="btn contact-btn btn-phone btn-block">
                    <i class="fas fa-phone mr-2"></i> Call Support
                </a>
            </div>
        </div>
        
        <!-- Additional Info -->
        <div class="alert support-hours">
            <div class="alert-content">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Support Hours:</strong> Monday - Friday, 8:00 AM - 5:00 PM (GMT+8)
                </div>
            </div>
        </div>
    </div>
</div>
        </div>
        
        <div class="col-md-9">
            <div class="tab-content" id="faq-content">
                <!-- All FAQs Tab -->
                <div class="tab-pane fade show active" id="all" role="tabpanel">
                    <?php if (count($faqs) > 0): ?>
                        <?php foreach ($categories as $category): ?>
                            <?php if (!empty($faqs_by_category[$category])): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="faq-category-icon">
                                        <i class="<?php echo getCategoryIcon($category); ?> text-primary"></i>
                                    </div>
                                    <h4><?php echo htmlspecialchars($category); ?></h4>
                                </div>
                                <?php foreach ($faqs_by_category[$category] as $faq): ?>
                                    <div class="card mb-3 faq-card" data-category="<?php echo htmlspecialchars($category); ?>">
                                        <div class="card-header d-flex align-items-center" onclick="toggleFaq(this)">
                                            <div class="flex-grow-1">
                                                <h5 class="mb-0"><?php echo htmlspecialchars($faq['question']); ?></h5>
                                            </div>
                                            <i class="fas fa-chevron-down ml-3"></i>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <hr class="my-4">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-question-circle fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No FAQs available</h5>
                            <p>Please check back later for updates.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Category-specific Tabs -->
                <?php foreach ($categories as $category): ?>
                <div class="tab-pane fade" id="<?php echo strtolower(str_replace(' ', '-', $category)); ?>" role="tabpanel">
                    <div class="d-flex align-items-center mb-4">
                        <div class="faq-category-icon">
                            <i class="<?php echo getCategoryIcon($category); ?> text-primary"></i>
                        </div>
                        <h4><?php echo htmlspecialchars($category); ?></h4>
                    </div>
                    
                    <?php if (!empty($faqs_by_category[$category])): ?>
                        <?php foreach ($faqs_by_category[$category] as $faq): ?>
                            <div class="card mb-3 faq-card">
                                <div class="card-header d-flex align-items-center" onclick="toggleFaq(this)">
                                    <div class="flex-grow-1">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($faq['question']); ?></h5>
                                    </div>
                                    <i class="fas fa-chevron-down ml-3"></i>
                                </div>
                                <div class="card-body">
                                    <p class="card-text"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-exclamation-circle fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No FAQs in this category</h5>
                            <p>Please check back later for updates.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<footer class="bg-light mt-5 py-3">
    <div class="container text-center">
        <p class="mb-0">&copy; <?php echo date("Y"); ?> EventSphere@UPSI. All rights reserved.</p>
    </div>
</footer>

<!-- Bootstrap JS and jQuery -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<script>
// Toggle FAQ card
function toggleFaq(element) {
    const card = $(element).closest('.faq-card');
    const chevron = $(element).find('.fas');
    
    // Close all other FAQs
    $('.faq-card').not(card).removeClass('active');
    $('.faq-card').not(card).find('.fas').removeClass('fa-chevron-up').addClass('fa-chevron-down');
    $('.faq-card').not(card).find('.card-body').slideUp(200);
    
    // Toggle current FAQ
    if (card.hasClass('active')) {
        card.removeClass('active');
        chevron.removeClass('fa-chevron-up').addClass('fa-chevron-down');
        card.find('.card-body').slideUp(200);
    } else {
        card.addClass('active');
        chevron.removeClass('fa-chevron-down').addClass('fa-chevron-up');
        card.find('.card-body').slideDown(200);
    }
}

// Search functionality
$(document).ready(function() {
    // Save original FAQ content
    const originalContent = $('#faq-content').html();
    
    // Search functionality
    $('#searchFaq').on('keyup', function() {
        const query = $(this).val().trim().toLowerCase();
        
        if (query.length < 2) {
            // Reset to original state if query is too short
            $('#faq-content').html(originalContent);
            $('#searchResults').hide();
            return;
        }
        
        // Search through FAQs
        let results = [];
        let count = 0;
        
        $('.faq-card').each(function() {
            const question = $(this).find('.card-header h5').text().toLowerCase();
            const answer = $(this).find('.card-body p').text().toLowerCase();
            const category = $(this).data('category');
            
            if (question.includes(query) || answer.includes(query)) {
                const clonedCard = $(this).clone();
                
                // Highlight matched text
                const highlightedQuestion = highlightText($(this).find('.card-header h5').text(), query);
                const highlightedAnswer = highlightText($(this).find('.card-body p').text(), query);
                
                clonedCard.find('.card-header h5').html(highlightedQuestion);
                clonedCard.find('.card-body p').html(highlightedAnswer);
                clonedCard.addClass('active');
                clonedCard.find('.card-body').show();
                clonedCard.find('.fas').removeClass('fa-chevron-down').addClass('fa-chevron-up');
                
                results.push({
                    element: clonedCard,
                    category: category
                });
                
                count++;
            }
        });
        
        // Update search results
        $('#searchQuery').text(query);
        $('#resultCount').text(count);
        $('#searchResults').show();
        
        // Display results
        if (count > 0) {
            $('#searchResultsList').empty();
            
            // Group results by category
            const resultsByCategory = {};
            
            results.forEach(result => {
                if (!resultsByCategory[result.category]) {
                    resultsByCategory[result.category] = [];
                }
                
                resultsByCategory[result.category].push(result.element);
            });
            
            // Display results grouped by category
            for (const category in resultsByCategory) {
                const categoryContainer = $('<div class="mb-4"></div>');
                const categoryHeader = $(`
                    <div class="d-flex align-items-center mb-3">
                        <div class="faq-category-icon">
                            <i class="${getCategoryIconJS(category)} text-primary"></i>
                        </div>
                        <h4>${category}</h4>
                    </div>
                `);
                
                categoryContainer.append(categoryHeader);
                
                resultsByCategory[category].forEach(element => {
                    categoryContainer.append(element);
                });
                
                $('#searchResultsList').append(categoryContainer);
            }
            
            // Rebind onclick event
            $('#searchResultsList .card-header').on('click', function() {
                toggleFaq(this);
            });
        } else {
            $('#searchResultsList').html(`
                <div class="text-center py-4">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No matching FAQs found</h5>
                    <p>Try a different search term or browse all FAQs.</p>
                </div>
            `);
        }
    });
    
    // Handle tab changes
    $('.nav-pills a').on('click', function() {
        // Reset search
        $('#searchFaq').val('');
        $('#searchResults').hide();
    });
    
    // Open specific FAQ from search results
    $('#searchResultsList').on('click', '.card-header', function() {
        toggleFaq(this);
    });
});

// Highlight search text
function highlightText(text, query) {
    if (!query) return text;
    
    // Escape special characters
    query = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    
    // Create regex with word boundaries
    const regex = new RegExp(`(${query})`, 'gi');
    
    // Replace with highlight class
    return text.replace(regex, '<span class="search-highlight">$1</span>');
}

// Get category icon (JavaScript version)
function getCategoryIconJS(category) {
    switch(category.toLowerCase()) {
        case 'general':
            return 'fas fa-info-circle';
        case 'registration':
            return 'fas fa-user-plus';
        case 'events':
            return 'fas fa-calendar-alt';
        case 'attendance':
            return 'fas fa-clipboard-check';
        case 'points':
            return 'fas fa-star';
        case 'technical':
            return 'fas fa-cogs';
        case 'account':
            return 'fas fa-user-cog';
        case 'feedback':
            return 'fas fa-comment-alt';
        default:
            return 'fas fa-question-circle';
    }
}

// Enhanced Contact Section JavaScript
$(document).ready(function() {
    // Add loading state to contact buttons when clicked
    $('.contact-btn').click(function() {
        $(this).addClass('loading');
        
        // Remove loading state after 2 seconds
        setTimeout(() => {
            $(this).removeClass('loading');
        }, 2000);
    });

    // Copy email to clipboard functionality (optional)
    $('.contact-info-value').click(function() {
        if ($(this).text().includes('@')) {
            // Try to copy to clipboard if supported
            if (navigator.clipboard) {
                navigator.clipboard.writeText($(this).text()).then(function() {
                    // Show temporary feedback
                    const originalText = $(this).text();
                    $(this).text('Email copied!').css('color', '#28a745');
                    
                    setTimeout(() => {
                        $(this).text(originalText).css('color', '');
                    }, 1500);
                }.bind(this));
            }
        }
    });
});
</script>

<?php
// Helper function to get icon for category
function getCategoryIcon($category) {
    switch(strtolower($category)) {
        case 'general':
            return 'fas fa-info-circle';
        case 'registration':
            return 'fas fa-user-plus';
        case 'events':
            return 'fas fa-calendar-alt';
        case 'attendance':
            return 'fas fa-clipboard-check';
        case 'points':
            return 'fas fa-star';
        case 'technical':
            return 'fas fa-cogs';
        case 'account':
            return 'fas fa-user-cog';
        case 'feedback':
            return 'fas fa-comment-alt';
        default:
            return 'fas fa-question-circle';
    }
}
?>
</body>
</html>