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
$stmt = $conn->prepare("
    WITH payment_totals AS (
        SELECT 
            invoice_item_id,
            COALESCE(SUM(amount), 0) as total_paid
        FROM payment_items
        GROUP BY invoice_item_id
    )
    SELECT 
        ii.id,
        fs.fee_item,
        ii.amount as original_amount,
        COALESCE(pt.total_paid, 0) as paid_amount,
        (ii.amount - COALESCE(pt.total_paid, 0)) as balance
    FROM invoice_items ii
    LEFT JOIN fee_structure fs ON ii.fee_structure_id = fs.id
    LEFT JOIN payment_totals pt ON pt.invoice_item_id = ii.id
    WHERE ii.invoice_id = ?
    HAVING balance > 0
    ORDER BY fs.fee_item
");

$stmt->bind_param('i', $invoice_id);
$stmt->execute();
$result = $stmt->get_result();

$fee_items = [];
while ($row = $result->fetch_assoc()) {
    // Handle NULL fee_item (for balance carried forward)
    if ($row['fee_item'] === null) {
        $row['fee_item'] = 'Balance Carried Forward';
    }
    
    // Ensure numeric values
    $row['original_amount'] = (float)$row['original_amount'];
    $row['paid_amount'] = (float)$row['paid_amount'];
    $row['balance'] = (float)$row['balance'];
    
    $fee_items[] = $row;
}

echo json_encode($fee_items);
?>
