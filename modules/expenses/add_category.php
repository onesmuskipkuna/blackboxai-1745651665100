<?php
$page_title = 'Add Expense Category';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);

    if (!$name) {
        $error = 'Category name is required';
    } else {
        // Check if category already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM expense_categories WHERE name = :name");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row['count'] > 0) {
            $error = 'Category name already exists';
        } else {
            // Insert new category
            $stmt = $conn->prepare("INSERT INTO expense_categories (name, description) VALUES (:name, :description)");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            $stmt->execute();

            flashMessage('success', 'Expense category added successfully');
            header('Location: index.php');
            exit;
        }
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Add Expense Category</h1>

        <?php if ($error): ?>
            <div class="mt-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="mt-6 space-y-6 max-w-lg">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Category Name</label>
                <input type="text" name="name" id="name" required
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea name="description" id="description" rows="3"
                          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Add Category
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
