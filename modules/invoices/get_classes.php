<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance();
$conn = $db->getConnection();

$education_level = isset($_GET['education_level']) ? sanitize($_GET['education_level']) : '';

if (!$education_level) {
    echo json_encode([]);
    exit;
}

// Fetch distinct classes for the selected education level
$stmt = $conn->prepare("SELECT DISTINCT class FROM students WHERE education_level = ? AND status = 'active' ORDER BY class");
$stmt->bind_param('s', $education_level);
$stmt->execute();

$result = $stmt->get_result();

$classes = [];
while ($row = $result->fetch_assoc()) {
    $classes[] = $row['class'];
}

echo json_encode($classes);
?>
