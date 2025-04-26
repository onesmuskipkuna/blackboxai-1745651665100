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
    $conn->begin_transaction();

    try {
        // Delete invoice items
        $stmt = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt->bind_param('i', $invoice_id);
        $stmt->execute();

        // Delete payments and payment items related to this invoice
        $payments_stmt = $conn->prepare("SELECT id FROM payments WHERE invoice_id = ?");
        $payments_stmt->bind_param('i', $invoice_id);
        $payments_stmt->execute();
        $payments_result = $payments_stmt->get_result();

        while ($payment = $payments_result->fetch_assoc()) {
            $payment_id = $payment['id'];

            $delete_payment_items_stmt = $conn->prepare("DELETE FROM payment_items WHERE payment_id = ?");
            $delete_payment_items_stmt->bind_param('i', $payment_id);
            $delete_payment_items_stmt->execute();

            $delete_payment_stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
            $delete_payment_stmt->bind_param('i', $payment_id);
            $delete_payment_stmt->execute();
        }

        // Delete invoice
        $delete_invoice_stmt = $conn->prepare("DELETE FROM invoices WHERE id = ?");
        $delete_invoice_stmt->bind_param('i', $invoice_id);
        $delete_invoice_stmt->execute();

        // Commit transaction
        $conn->commit();

        flashMessage('success', 'Invoice deleted successfully');
    } catch (Exception $e) {
        $conn->rollback();
        flashMessage('error', 'Error deleting invoice: ' . $e->getMessage());
    }
}

redirect('index.php');
?>
