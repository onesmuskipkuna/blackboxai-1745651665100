<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$category_id = (int)$_GET['id'];

$db = Database::getInstance();
$conn = $db->getConnection();

// Begin transaction
$conn->begin_transaction();

try {
    // Check if category exists
    $stmt = $conn->prepare("SELECT * FROM expense_categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $category = $result->fetch_assoc();

    if (!$category) {
        throw new Exception('Category not found');
    }

    // Check if category has any expenses
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM expenses WHERE category_id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['count'] > 0) {
        throw new Exception('Cannot delete category: It has associated expenses');
    }

    // Delete category
    $stmt = $conn->prepare("DELETE FROM expense_categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    flashMessage('success', 'Expense category deleted successfully');
    header('Location: index.php');
    exit;

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    flashMessage('error', $e->getMessage());
    header('Location: index.php');
    exit;
}
?>
