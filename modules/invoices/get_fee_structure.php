<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance();
$conn = $db->getConnection();

// Get parameters
$class = isset($_GET['class']) ? sanitize($_GET['class']) : '';
$education_level = isset($_GET['education_level']) ? sanitize($_GET['education_level']) : '';
$term = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$academic_year = isset($_GET['academic_year']) ? sanitize($_GET['academic_year']) : '';

if (!$class || !$education_level || !$term || !$academic_year) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// Prepare statement
$stmt = $conn->prepare("
    SELECT id, fee_item, amount 
    FROM fee_structure 
    WHERE class = ?
    AND education_level = ?
    AND term = ?
    AND academic_year = ?
    ORDER BY fee_item
");

$stmt->bind_param('ssis', $class, $education_level, $term, $academic_year);

$stmt->execute();

$result = $stmt->get_result();

$fee_items = [];
while ($row = $result->fetch_assoc()) {
    $fee_items[] = $row;
}

echo json_encode($fee_items);
?>
