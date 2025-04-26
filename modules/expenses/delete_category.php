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

// Check if category exists
$stmt = $conn->prepare("SELECT * FROM expense_categories WHERE id = :id");
$stmt->bindValue(':id', $category_id, SQLITE3_INTEGER);
$result = $stmt->execute();
$category = $result->fetchArray(SQLITE3_ASSOC);

if (!$category) {
    header('Location: index.php');
    exit;
}

// Delete category
$stmt = $conn->prepare("DELETE FROM expense_categories WHERE id = :id");
$stmt->bindValue(':id', $category_id, SQLITE3_INTEGER);
$stmt->execute();

flashMessage('success', 'Expense category deleted successfully');
header('Location: index.php');
exit;
?>
