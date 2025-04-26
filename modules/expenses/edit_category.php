<?php
$page_title = 'Edit Expense Category';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$category_id) {
    header('Location: index.php');
    exit;
}

// Fetch category data
$stmt = $conn->prepare("SELECT * FROM expense_categories WHERE id = :id");
$stmt->bindValue(':id', $category_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$category = $result->fetchArray(SQLITE3_ASSOC);

if (!$category) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);

    if (!$name) {
        $error = 'Category name is required';
    } else {
        // Check if category name exists for other categories
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM expense_categories WHERE name = :name AND id != :id");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':id', $category_id, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row['count'] > 0) {
            $error = 'Category name already exists';
        } else {
            // Update category
            $stmt = $conn->prepare("UPDATE expense_categories SET name = :name, description = :description WHERE id = :id");
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            $stmt->bindValue(':id', $category_id, SQLITE3_INTEGER);
            $stmt->execute();

            flashMessage('success', 'Expense category updated successfully');
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
        <h1 class="text-2xl font-semibold text-gray-900">Edit Expense Category</h1>

        <?php if ($error): ?>
            <div class="mt-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="mt-6 space-y-6 max-w-lg">
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Category Name</label>
                <input type="text" name="name" id="name" required value="<?php echo htmlspecialchars($category['name']); ?>"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea name="description" id="description" rows="3"
                          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"><?php echo htmlspecialchars($category['description']); ?></textarea>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Update Category
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
