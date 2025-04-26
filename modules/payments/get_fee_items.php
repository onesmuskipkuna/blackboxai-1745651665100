<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance();
$conn = $db->getConnection();

// Get invoice ID
$invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;

if (!$invoice_id) {
    echo json_encode(['error' => 'Invalid invoice ID']);
    exit;
}

// Get invoice items with their balances
// Fix: Use LEFT JOIN on payment_items with correct alias and group by all non-aggregated columns
$stmt = $conn->prepare("
    SELECT 
        ii.id,
        fs.fee_item,
        ii.amount as original_amount,
        COALESCE(SUM(pi.amount), 0) as paid_amount,
        (ii.amount - COALESCE(SUM(pi.amount), 0)) as balance
    FROM invoice_items ii
    JOIN fee_structure fs ON ii.fee_structure_id = fs.id
    LEFT JOIN payment_items pi ON pi.invoice_item_id = ii.id
    WHERE ii.invoice_id = :invoice_id
    GROUP BY ii.id, fs.fee_item, ii.amount
    HAVING balance > 0
    ORDER BY fs.fee_item
");

$stmt->bindValue(':invoice_id', $invoice_id, SQLITE3_INTEGER);
$result = $stmt->execute();

$fee_items = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $fee_items[] = $row;
}

echo json_encode($fee_items);
?>
