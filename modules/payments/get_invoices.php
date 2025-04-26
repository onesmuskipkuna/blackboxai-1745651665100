<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance();
$conn = $db->getConnection();

// Get student ID
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (!$student_id) {
    echo json_encode(['error' => 'Invalid student ID']);
    exit;
}

// Get student's unpaid/partially paid invoices
$stmt = $conn->prepare("
    SELECT id, invoice_number, total_amount, paid_amount, balance, term, academic_year
    FROM invoices 
    WHERE student_id = :student_id 
    AND status != 'fully_paid'
    ORDER BY created_at DESC
");

$stmt->bindValue(':student_id', $student_id, SQLITE3_INTEGER);
$result = $stmt->execute();

$invoices = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $invoices[] = $row;
}

echo json_encode($invoices);
?>
