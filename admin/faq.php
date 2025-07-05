<?php
/**
 * FAQ Management
 * EventSphere@UPSI: Navigate, Engage & Excel
 */

// Include database connection
require_once '../config/db.php';

// Set page title
$page_title = "FAQ Management";

// Include header
include_once '../include/admin_header.php';

// Initialize variables
$question = $answer = $category = "";
$question_err = $answer_err = "";

// Process form data for adding new FAQ
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_faq"])) {

    // Validate question
    if (empty(trim($_POST["question"]))) {
        $question_err = "Please enter the question.";
    } else {
        $question = trim($_POST["question"]);
    }

    // Validate answer
    if (empty(trim($_POST["answer"]))) {
        $answer_err = "Please enter the answer.";
    } else {
        $answer = trim($_POST["answer"]);
    }

    // Get category (handle both existing and new categories)
    if (!empty($_POST["new_category"])) {
        $category = trim($_POST["new_category"]);
    } else {
        $category = !empty($_POST["category"]) ? trim($_POST["category"]) : "General";
    }

    // Check if no errors
    if (empty($question_err) && empty($answer_err)) {

        // Get current max order
        $max_order = 0;
        $order_sql = "SELECT MAX(order_num) as max_order FROM faq";
        $result = $conn->query($order_sql);
        if ($result && $row = $result->fetch_assoc()) {
            $max_order = $row['max_order'] ?? 0;
        }

        // Prepare insert statement
        $sql = "INSERT INTO faq (question, answer, category, order_num) VALUES (?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            // Set parameters
            $order_num = $max_order + 1;

            // Bind variables to the prepared statement
            $stmt->bind_param("sssi", $question, $answer, $category, $order_num);

            // Execute the statement
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "FAQ added successfully to category: " . htmlspecialchars($category);
                // Clear form data
                $question = $answer = "";
                $category = "General";
            } else {
                $_SESSION['error_message'] = "Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
}

// Process FAQ deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $faq_id = intval($_GET['id']);

    // Prepare delete statement
    $sql = "DELETE FROM faq WHERE id = ?";

    if ($stmt = $conn->prepare($sql)) {
        // Bind variable
        $stmt->bind_param("i", $faq_id);

        // Execute the statement
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "FAQ deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Error deleting FAQ.";
        }

        // Close statement
        $stmt->close();
    }

    // Redirect to prevent resubmission
    header("Location: faq.php");
    exit;
}

// Process category deletion
if (isset($_GET['action']) && $_GET['action'] == 'delete_category' && isset($_GET['category'])) {
    $category_name = $_GET['category'];
    
    if ($category_name !== 'General') {
        // Move all FAQs from this category to General
        $move_sql = "UPDATE faq SET category = 'General' WHERE category = ?";
        if ($move_stmt = $conn->prepare($move_sql)) {
            $move_stmt->bind_param("s", $category_name);
            $move_stmt->execute();
            $move_stmt->close();
            $_SESSION['success_message'] = "Category deleted. All FAQs moved to General category.";
        } else {
            $_SESSION['error_message'] = "Error deleting category.";
        }
    } else {
        $_SESSION['error_message'] = "Cannot delete the General category.";
    }

    // Redirect to prevent resubmission
    header("Location: faq.php");
    exit;
}

// Process FAQ editing (AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_faq"])) {
    $faq_id = isset($_POST["faq_id"]) ? intval($_POST["faq_id"]) : 0;
    $edit_question = isset($_POST["edit_question"]) ? trim($_POST["edit_question"]) : "";
    $edit_answer = isset($_POST["edit_answer"]) ? trim($_POST["edit_answer"]) : "";
    
    // Handle category (both existing and new)
    if (!empty($_POST["edit_new_category"])) {
        $edit_category = trim($_POST["edit_new_category"]);
    } else {
        $edit_category = isset($_POST["edit_category"]) ? trim($_POST["edit_category"]) : "General";
    }

    if (empty($edit_question) || empty($edit_answer) || $faq_id <= 0) {
        $_SESSION['error_message'] = "Invalid data provided for editing.";
    } else {
        // Prepare update statement
        $sql = "UPDATE faq SET question = ?, answer = ?, category = ? WHERE id = ?";

        if ($stmt = $conn->prepare($sql)) {
            // Bind variables
            $stmt->bind_param("sssi", $edit_question, $edit_answer, $edit_category, $faq_id);

            // Execute the statement
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "FAQ updated successfully.";
            } else {
                $_SESSION['error_message'] = "Error updating FAQ.";
            }

            // Close statement
            $stmt->close();
        }
    }

    // Redirect to prevent resubmission
    header("Location: faq.php");
    exit;
}

// Process order updates (AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_order"])) {
    if (isset($_POST["faq_order"]) && is_array($_POST["faq_order"])) {
        $orders = $_POST["faq_order"];
        $success = true;

        foreach ($orders as $id => $order) {
            $sql = "UPDATE faq SET order_num = ? WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ii", $order, $id);
                if (!$stmt->execute()) {
                    $success = false;
                }
                $stmt->close();
            }
        }

        if ($success) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "Error updating orders."]);
        }

        exit;
    }
}

// Get all FAQs
$faqs = [];
$sql = "SELECT * FROM faq ORDER BY category, order_num";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $faqs[] = $row;
    }
}

// Get unique categories with FAQ counts
$categories = ["General"]; // Default category
$category_counts = [];
$category_sql = "SELECT category, COUNT(*) as count FROM faq GROUP BY category ORDER BY category";
$category_result = $conn->query($category_sql);

if ($category_result && $category_result->num_rows > 0) {
    while ($row = $category_result->fetch_assoc()) {
        $category_counts[$row['category']] = $row['count'];
        if (!empty($row['category']) && !in_array($row['category'], $categories)) {
            $categories[] = $row['category'];
        }
    }
}

// Ensure General category is first and exists in counts
if (!isset($category_counts['General'])) {
    $category_counts['General'] = 0;
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="mb-0">FAQ Management</h1>
                        <p class="text-muted">Create and manage frequently asked questions and categories</p>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-success mr-2" data-toggle="modal" data-target="#manageCategoriesModal">
                            <i class="fas fa-tags mr-2"></i> Manage Categories
                        </button>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addFaqModal">
                            <i class="fas fa-plus-circle mr-2"></i> Add New FAQ
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i><?php echo $_SESSION['success_message']; ?>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $_SESSION['error_message']; ?>
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <!-- FAQ Categories Tabs -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white p-0">
                    <ul class="nav nav-tabs" id="faqTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="all-tab" data-toggle="tab" href="#all" role="tab"
                                aria-controls="all" aria-selected="true">
                                All FAQs <span class="badge badge-secondary ml-1"><?php echo count($faqs); ?></span>
                            </a>
                        </li>
                        <?php foreach ($categories as $cat): ?>
                            <li class="nav-item">
                                <a class="nav-link" id="<?php echo strtolower(str_replace(' ', '-', $cat)); ?>-tab"
                                    data-toggle="tab" href="#<?php echo strtolower(str_replace(' ', '-', $cat)); ?>"
                                    role="tab">
                                    <?php echo htmlspecialchars($cat); ?>
                                    <span class="badge badge-secondary ml-1"><?php echo $category_counts[$cat] ?? 0; ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="faqTabsContent">
                        <!-- All FAQs Tab -->
                        <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                            <?php if (count($faqs) > 0): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i> Drag and drop FAQs to rearrange their order.
                                </div>
                                <div class="faq-list" id="sortableFaqs">
                                    <?php foreach ($faqs as $faq): ?>
                                        <div class="card mb-3 faq-item" data-id="<?php echo $faq['id']; ?>">
                                            <div class="card-header bg-white d-flex align-items-center">
                                                <div class="mr-3 handle" style="cursor: grab;">
                                                    <i class="fas fa-grip-vertical text-muted"></i>
                                                </div>
                                                <div class="flex-grow-1 d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <span class="badge badge-info mr-2"><?php echo htmlspecialchars($faq['category']); ?></span>
                                                        <strong><?php echo htmlspecialchars($faq['question']); ?></strong>
                                                    </div>
                                                    <div>
                                                        <button type="button" class="btn btn-sm btn-outline-primary edit-faq"
                                                            data-id="<?php echo $faq['id']; ?>"
                                                            data-question="<?php echo htmlspecialchars($faq['question']); ?>"
                                                            data-answer="<?php echo htmlspecialchars($faq['answer']); ?>"
                                                            data-category="<?php echo htmlspecialchars($faq['category']); ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-faq"
                                                            data-id="<?php echo $faq['id']; ?>"
                                                            data-question="<?php echo htmlspecialchars($faq['question']); ?>">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-question-circle fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">No FAQs found</h5>
                                    <p>Click the "Add New FAQ" button to create your first FAQ.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Category-specific Tabs -->
                        <?php foreach ($categories as $cat): ?>
                            <div class="tab-pane fade" id="<?php echo strtolower(str_replace(' ', '-', $cat)); ?>"
                                role="tabpanel">
                                <?php
                                $has_faqs = false;
                                foreach ($faqs as $faq) {
                                    if ($faq['category'] == $cat) {
                                        $has_faqs = true;
                                        break;
                                    }
                                }

                                if ($has_faqs):
                                    ?>
                                    <div class="faq-list">
                                        <?php foreach ($faqs as $faq): ?>
                                            <?php if ($faq['category'] == $cat): ?>
                                                <div class="card mb-3">
                                                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                                        <strong><?php echo htmlspecialchars($faq['question']); ?></strong>
                                                        <div>
                                                            <button type="button" class="btn btn-sm btn-outline-primary edit-faq"
                                                                data-id="<?php echo $faq['id']; ?>"
                                                                data-question="<?php echo htmlspecialchars($faq['question']); ?>"
                                                                data-answer="<?php echo htmlspecialchars($faq['answer']); ?>"
                                                                data-category="<?php echo htmlspecialchars($faq['category']); ?>">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger delete-faq"
                                                                data-id="<?php echo $faq['id']; ?>"
                                                                data-question="<?php echo htmlspecialchars($faq['question']); ?>">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="card-body">
                                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-question-circle fa-4x text-muted mb-3"></i>
                                        <h5 class="text-muted">No FAQs in this category</h5>
                                        <p>Click the "Add New FAQ" button to add an FAQ to this category.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manage Categories Modal -->
<div class="modal fade" id="manageCategoriesModal" tabindex="-1" role="dialog" aria-labelledby="manageCategoriesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="manageCategoriesModalLabel">
                    <i class="fas fa-tags mr-2"></i>Manage Categories
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Existing Categories</h6>
                        <div class="list-group" id="categoriesList">
                            <?php foreach ($categories as $cat): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($cat); ?></strong>
                                        <span class="badge badge-primary ml-2"><?php echo $category_counts[$cat] ?? 0; ?> FAQs</span>
                                    </div>
                                    <div>
                                        <?php if ($cat !== 'General'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-category"
                                                data-category="<?php echo htmlspecialchars($cat); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">Default</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Add New Category</h6>
                        <div class="form-group">
                            <input type="text" id="newCategoryName" class="form-control" placeholder="Enter category name" maxlength="50">
                        </div>
                        <button type="button" class="btn btn-success" id="addCategoryBtn">
                            <i class="fas fa-plus mr-2"></i>Add Category
                        </button>
                        <hr>
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>Note:</strong> Deleting a category will move all its FAQs to the "General" category.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Add FAQ Modal -->
<div class="modal fade" id="addFaqModal" tabindex="-1" role="dialog" aria-labelledby="addFaqModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFaqModalLabel">Add New FAQ</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="question">Question <span class="text-danger">*</span></label>
                        <input type="text" name="question" id="question"
                            class="form-control <?php echo (!empty($question_err)) ? 'is-invalid' : ''; ?>"
                            value="<?php echo htmlspecialchars($question); ?>" required>
                        <span class="invalid-feedback"><?php echo $question_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="answer">Answer <span class="text-danger">*</span></label>
                        <textarea name="answer" id="answer" rows="5"
                            class="form-control <?php echo (!empty($answer_err)) ? 'is-invalid' : ''; ?>"
                            required><?php echo htmlspecialchars($answer); ?></textarea>
                        <span class="invalid-feedback"><?php echo $answer_err; ?></span>
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <div class="input-group">
                            <select name="category" id="category" class="form-control">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category == $cat) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="newCategoryBtn">New Category</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="newCategoryGroup" style="display: none;">
                        <label for="newCategory">New Category Name</label>
                        <input type="text" name="new_category" id="newCategory" class="form-control" placeholder="Enter new category name" maxlength="50">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_faq" class="btn btn-primary">Add FAQ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit FAQ Modal -->
<div class="modal fade" id="editFaqModal" tabindex="-1" role="dialog" aria-labelledby="editFaqModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="editFaqModalLabel">Edit FAQ</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="faq_id" id="edit_faq_id">
                    <div class="form-group">
                        <label for="edit_question">Question <span class="text-danger">*</span></label>
                        <input type="text" name="edit_question" id="edit_question" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_answer">Answer <span class="text-danger">*</span></label>
                        <textarea name="edit_answer" id="edit_answer" rows="5" class="form-control" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_category">Category</label>
                        <div class="input-group">
                            <select name="edit_category" id="edit_category" class="form-control">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" id="editNewCategoryBtn">New Category</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="editNewCategoryGroup" style="display: none;">
                        <label for="editNewCategory">New Category Name</label>
                        <input type="text" name="edit_new_category" id="editNewCategory" class="form-control" placeholder="Enter new category name" maxlength="50">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_faq" class="btn btn-primary">Update FAQ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete FAQ Confirmation Modal -->
<div class="modal fade" id="deleteFaqModal" tabindex="-1" role="dialog" aria-labelledby="deleteFaqModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteFaqModalLabel">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Confirm FAQ Deletion
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this FAQ?</p>
                <div class="alert alert-warning">
                    <strong id="faqToDelete"></strong>
                </div>
                <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteFaqBtn" class="btn btn-danger">
                    <i class="fas fa-trash mr-2"></i>Delete FAQ
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Delete Category Confirmation Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" role="dialog" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="deleteCategoryModalLabel">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Confirm Category Deletion
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the category <strong id="categoryToDelete"></strong>?</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle mr-2"></i>
                    All FAQs in this category will be moved to the "General" category.
                </div>
                <p class="text-warning"><i class="fas fa-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteCategoryBtn" class="btn btn-warning">
                    <i class="fas fa-trash mr-2"></i>Delete Category
                </a>
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
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

<script>
    $(document).ready(function () {
        // Show/hide new category input in add modal
        $("#newCategoryBtn").click(function () {
            $("#newCategoryGroup").toggle();
            if ($("#newCategoryGroup").is(":visible")) {
                $("#category").prop("disabled", true);
                $("#newCategory").focus();
                $(this).text("Use Existing");
            } else {
                $("#category").prop("disabled", false);
                $("#newCategory").val("");
                $(this).text("New Category");
            }
        });

        // Show/hide new category input in edit modal
        $("#editNewCategoryBtn").click(function () {
            $("#editNewCategoryGroup").toggle();
            if ($("#editNewCategoryGroup").is(":visible")) {
                $("#edit_category").prop("disabled", true);
                $("#editNewCategory").focus();
                $(this).text("Use Existing");
            } else {
                $("#edit_category").prop("disabled", false);
                $("#editNewCategory").val("");
                $(this).text("New Category");
            }
        });

        // Handle edit button clicks
        $(".edit-faq").click(function () {
            var id = $(this).data("id");
            var question = $(this).data("question");
            var answer = $(this).data("answer");
            var category = $(this).data("category");

            $("#edit_faq_id").val(id);
            $("#edit_question").val(question);
            $("#edit_answer").val(answer);
            $("#edit_category").val(category);

            // Reset the new category fields
            $("#editNewCategoryGroup").hide();
            $("#edit_category").prop("disabled", false);
            $("#editNewCategory").val("");
            $("#editNewCategoryBtn").text("New Category");

            $("#editFaqModal").modal("show");
        });

        // Handle FAQ delete button clicks
        $(".delete-faq").click(function(e) {
            e.preventDefault();
            var faqId = $(this).data("id");
            var question = $(this).data("question");
            
            $("#faqToDelete").text(question);
            $("#confirmDeleteFaqBtn").attr("href", "faq.php?action=delete&id=" + faqId);
            $("#deleteFaqModal").modal("show");
        });

        // Handle category delete button clicks
        $(".delete-category").click(function(e) {
            e.preventDefault();
            var category = $(this).data("category");
            
            $("#categoryToDelete").text(category);
            $("#confirmDeleteCategoryBtn").attr("href", "faq.php?action=delete_category&category=" + encodeURIComponent(category));
            $("#deleteCategoryModal").modal("show");
        });

        // Add new category functionality
        $("#addCategoryBtn").click(function() {
            var categoryName = $("#newCategoryName").val().trim();
            
            if (categoryName === "") {
                alert("Please enter a category name.");
                return;
            }
            
            if (categoryName.length > 50) {
                alert("Category name must be 50 characters or less.");
                return;
            }

            // Check if category already exists
            var exists = false;
            $("#categoriesList .list-group-item").each(function() {
                var existingCategory = $(this).find("strong").text().trim();
                if (existingCategory.toLowerCase() === categoryName.toLowerCase()) {
                    exists = true;
                    return false;
                }
            });

            if (exists) {
                alert("Category already exists.");
                return;
            }

            // Add to the list
            var newCategoryHtml = `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${categoryName}</strong>
                        <span class="badge badge-primary ml-2">0 FAQs</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-category"
                            data-category="${categoryName}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            $("#categoriesList").append(newCategoryHtml);
            
            // Add to select dropdowns
            $("#category").append(`<option value="${categoryName}">${categoryName}</option>`);
            $("#edit_category").append(`<option value="${categoryName}">${categoryName}</option>`);
            
            // Clear input
            $("#newCategoryName").val("");
            
            // Show success message
            var successAlert = `
                <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                    <i class="fas fa-check-circle mr-2"></i>Category "${categoryName}" added successfully!
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
            `;
            $(".modal-body").prepend(successAlert);
            
            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $(".alert-success").fadeOut();
            }, 3000);

            // Re-bind delete event for new category
            bindCategoryDeleteEvents();
        });

        // Function to bind delete events for categories
        function bindCategoryDeleteEvents() {
            $(".delete-category").off("click").on("click", function(e) {
                e.preventDefault();
                var category = $(this).data("category");
                
                $("#categoryToDelete").text(category);
                $("#confirmDeleteCategoryBtn").attr("href", "faq.php?action=delete_category&category=" + encodeURIComponent(category));
                $("#deleteCategoryModal").modal("show");
            });
        }

        // Enter key support for new category
        $("#newCategoryName").keypress(function(e) {
            if (e.which === 13) {
                $("#addCategoryBtn").click();
            }
        });

        // Make FAQs sortable
        $("#sortableFaqs").sortable({
            handle: ".handle",
            update: function (event, ui) {
                var faqOrder = {};
                $(".faq-item").each(function (index) {
                    faqOrder[$(this).data("id")] = index + 1;
                });

                // Send order to server via AJAX
                $.ajax({
                    url: "faq.php",
                    type: "POST",
                    data: {
                        update_order: 1,
                        faq_order: faqOrder
                    },
                    success: function (response) {
                        var result = JSON.parse(response);
                        if (!result.success) {
                            alert("Error updating FAQ order: " + result.message);
                        }
                    },
                    error: function () {
                        alert("An error occurred while updating FAQ order.");
                    }
                });
            }
        });

        // Update tab UI on hash change
        function activateTabFromHash() {
            var hash = window.location.hash;
            if (hash) {
                $('.nav-tabs a[href="' + hash + '"]').tab('show');
            }
        }

        // Check for hash on page load
        activateTabFromHash();

        // Update hash on tab change
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            window.location.hash = e.target.hash;
        });

        // Listen for hash changes
        $(window).on('hashchange', function () {
            activateTabFromHash();
        });

        // Reset modals when closed
        $('#addFaqModal').on('hidden.bs.modal', function () {
            $("#newCategoryGroup").hide();
            $("#category").prop("disabled", false);
            $("#newCategory").val("");
            $("#newCategoryBtn").text("New Category");
        });

        $('#editFaqModal').on('hidden.bs.modal', function () {
            $("#editNewCategoryGroup").hide();
            $("#edit_category").prop("disabled", false);
            $("#editNewCategory").val("");
            $("#editNewCategoryBtn").text("New Category");
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);

        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>
</body>
</html>