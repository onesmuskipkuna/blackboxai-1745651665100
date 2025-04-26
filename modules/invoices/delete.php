<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;

    if (!$invoice_id) {
        flashMessage('error', 'Invalid invoice ID');
        redirect('index.php');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Begin transaction
    $conn->exec('BEGIN');

    try {
        // Delete invoice items
        $stmt = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = :invoice_id");
        $stmt->bindValue(':invoice_id', $invoice_id, SQLITE3_INTEGER);
        $stmt->execute();

        // Delete payments and payment items related to this invoice
        $payments_stmt = $conn->prepare("SELECT id FROM payments WHERE invoice_id = :invoice_id");
        $payments_stmt->bindValue(':invoice_id', $invoice_id, SQLITE3_INTEGER);
        $payments_result = $payments_stmt->execute();

        while ($payment = $payments_result->fetchArray(SQLITE3_ASSOC)) {
            $payment_id = $payment['id'];

            $delete_payment_items_stmt = $conn->prepare("DELETE FROM payment_items WHERE payment_id = :payment_id");
            $delete_payment_items_stmt->bindValue(':payment_id', $payment_id, SQLITE3_INTEGER);
            $delete_payment_items_stmt->execute();

            $delete_payment_stmt = $conn->prepare("DELETE FROM payments WHERE id = :payment_id");
            $delete_payment_stmt->bindValue(':payment_id', $payment_id, SQLITE3_INTEGER);
            $delete_payment_stmt->execute();
        }

        // Delete invoice
        $delete_invoice_stmt = $conn->prepare("DELETE FROM invoices WHERE id = :invoice_id");
        $delete_invoice_stmt->bindValue(':invoice_id', $invoice_id, SQLITE3_INTEGER);
        $delete_invoice_stmt->execute();

        // Commit transaction
        $conn->exec('COMMIT');

        flashMessage('success', 'Invoice deleted successfully');
    } catch (Exception $e) {
        $conn->exec('ROLLBACK');
        flashMessage('error', 'Error deleting invoice: ' . $e->getMessage());
    }
}

redirect('index.php');
?>
