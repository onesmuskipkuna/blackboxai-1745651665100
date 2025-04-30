<?php
$page_title = 'Print Receipt';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

// Get payment ID
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$payment_id) {
    flashMessage('error', 'Invalid payment ID');
    redirect('index.php');
}

try {
    // Get payment details with student and invoice information
    $stmt = $conn->prepare("
        SELECT p.*, 
               i.invoice_number, i.term, i.academic_year, i.total_amount as invoice_total, 
               i.paid_amount as invoice_paid, i.balance as invoice_balance,
               s.first_name, s.last_name, s.admission_number, s.guardian_name, s.phone_number,
               s.class, s.education_level
        FROM payments p
        JOIN invoices i ON p.invoice_id = i.id
        JOIN students s ON i.student_id = s.id
        WHERE p.id = ?
    ");
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();

    if (!$payment) {
        throw new Exception('Payment not found');
    }

    // Get invoice items with their total amounts and balances
    $stmt = $conn->prepare("
        SELECT 
            ii.id as invoice_item_id,
            ii.amount as original_amount,
            fs.fee_item,
            (
                SELECT COALESCE(SUM(pi.amount), 0)
                FROM payment_items pi
                WHERE pi.invoice_item_id = ii.id
            ) as total_paid
        FROM invoice_items ii
        LEFT JOIN fee_structure fs ON ii.fee_structure_id = fs.id
        WHERE ii.invoice_id = ?
        ORDER BY fs.fee_item
    ");
    $stmt->bind_param('i', $payment['invoice_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $fee_items = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['fee_item'] === null) {
            $row['fee_item'] = 'Balance Carried Forward';
        }
        $row['balance'] = $row['original_amount'] - $row['total_paid'];
        
        // Get amount paid in current payment
        $stmt2 = $conn->prepare("
            SELECT COALESCE(amount, 0) as amount
            FROM payment_items
            WHERE payment_id = ? AND invoice_item_id = ?
        ");
        $stmt2->bind_param('ii', $payment_id, $row['invoice_item_id']);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        $current_payment = $result2->fetch_assoc();
        
        $row['current_payment'] = $current_payment['amount'] ?? 0;
        $fee_items[] = $row;
    }

    // Get payment history for this invoice
    $stmt = $conn->prepare("
        SELECT 
            payment_number,
            amount,
            payment_mode,
            reference_number,
            created_at
        FROM payments 
        WHERE invoice_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->bind_param('i', $payment['invoice_id']);
    $stmt->execute();
    $history_result = $stmt->get_result();
    $payment_history = [];
    while ($row = $history_result->fetch_assoc()) {
        $payment_history[] = $row;
    }

} catch (Exception $e) {
    flashMessage('error', $e->getMessage());
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo htmlspecialchars($payment['payment_number']); ?></title>
    <style>
        /* Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Base styles */
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.5;
            margin: 0 auto;
            padding: 10mm;
        }

        /* Base styles */
        table {
            table-layout: fixed;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
            font-size: 10px;
        }

        td, th {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding: 1mm;
            text-align: left;
        }

        th {
            border-bottom: 1px solid #000;
            font-weight: bold;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 5mm;
        }

        .header h1 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2mm;
        }

        /* Receipt title */
        .receipt-title {
            text-align: center;
            margin-bottom: 5mm;
            border-bottom: 1px dashed #000;
            padding-bottom: 2mm;
        }

        /* Details sections */
        .details {
            margin-bottom: 5mm;
        }

        .details-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1mm;
        }

        .details-label {
            font-weight: bold;
        }

        /* Section headers */
        .section-header {
            font-weight: bold;
            text-align: center;
            margin: 5mm 0 2mm;
            padding: 1mm 0;
            border-bottom: 1px dashed #000;
        }

        .amount {
            text-align: right;
        }

        .total-row {
            border-top: 1px solid #000;
            font-weight: bold;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 5mm;
            padding-top: 2mm;
            border-top: 1px dashed #000;
        }

        .signature {
            margin-top: 10mm;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 50%;
            margin: 10mm auto 2mm;
        }

        /* Print specific styles */
        @media print {
            @page {
                margin: 5mm;
                size: auto;
            }

            body {
                width: 100%;
                min-width: 0;
                margin: 0;
                padding: 5mm;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            table {
                page-break-inside: avoid;
                width: 100% !important;
                font-size: 9px !important;
            }

            td, th {
                font-size: 9px !important;
                padding: 2mm 3mm !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }

            .section-header {
                page-break-before: auto;
                page-break-after: avoid;
                font-size: 11px !important;
            }

            .mb-5 {
                margin-bottom: 4mm !important;
            }

            .mb-3 {
                margin-bottom: 2mm !important;
            }

            .px-4 {
                padding-left: 2mm !important;
                padding-right: 2mm !important;
            }

            .py-3 {
                padding-top: 1.5mm !important;
                padding-bottom: 1.5mm !important;
            }

            .no-print {
                display: none !important;
            }

            /* Ensure tables don't overflow */
            .table-container {
                overflow-x: hidden !important;
                width: 100% !important;
                margin-bottom: 4mm !important;
            }

            /* Ensure text colors print properly */
            .text-green-700 {
                color: #047857 !important;
            }

            .text-red-700 {
                color: #b91c1c !important;
            }

            /* Ensure backgrounds print properly */
            .bg-gray-100 {
                background-color: #f3f4f6 !important;
            }
        }
    </style>
</head>
<body>
    <!-- Print/Back Buttons -->
    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" style="padding: 10px; margin-right: 10px; cursor: pointer;">Print</button>
        <button onclick="window.location.href='index.php'" style="padding: 10px; cursor: pointer;">Back</button>
    </div>

    <!-- Header -->
    <div class="header flex items-center justify-center space-x-4">
        <img src="/path/to/logo.png" alt="Site Logo" class="h-12 w-auto" />
        <h1 class="text-2xl font-bold"><?php echo SITE_NAME; ?></h1>
    </div>
    <div class="text-center text-sm text-gray-600 mb-4">
        <div>P.O. Box 123, City</div>
        <div>Phone: +254 123 456 789</div>
        <div>Email: info@school.com</div>
    </div>

    <!-- Receipt Title -->
    <div class="receipt-title">
        <h2>PAYMENT RECEIPT</h2>
        <div>Receipt #: <?php echo htmlspecialchars($payment['payment_number']); ?></div>
        <div>Date: <?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></div>
    </div>

    <!-- Student Details -->
    <div class="details">
        <div class="details-row">
            <span class="details-label">Student:</span>
            <span><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></span>
        </div>
        <div class="details-row">
            <span class="details-label">Adm No:</span>
            <span><?php echo htmlspecialchars($payment['admission_number']); ?></span>
        </div>
        
        <div class="details-row">
            <span class="details-label">Class:</span>
            <span><?php echo ucfirst($payment['class']); ?></span>
        </div>
    </div>

    <!-- Payment Details -->
    <div class="details">
        <div class="details-row">
            <span class="details-label">Invoice #:</span>
            <span><?php echo htmlspecialchars($payment['invoice_number']); ?></span>
        </div>
        <div class="details-row">
            <span class="details-label">Term:</span>
            <span><?php echo $payment['term']; ?></span>
        </div>
        <div class="details-row">
            <span class="details-label">Mode:</span>
            <span><?php echo ucfirst($payment['payment_mode']); ?></span>
        </div>
        <?php if ($payment['reference_number']): ?>
        <div class="details-row">
            <span class="details-label">Ref #:</span>
            <span><?php echo htmlspecialchars($payment['reference_number']); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Fee Breakdown Section -->
    <div class="section-header mb-3">FEE BREAKDOWN</div>
    
    <!-- Fee Items Table -->
    <div class="mb-5 table-container">
        <div class="text-sm font-bold mb-2 px-1">Fee Items & Total Amount</div>
        <table class="table-auto border-collapse border border-gray-400 w-full text-sm font-semibold" style="min-width: 400px;">
            <thead>
                <tr class="bg-gray-100 font-bold">
                    <th class="border border-gray-300 px-4 py-3 text-left">Fee Item</th>
                    <th class="border border-gray-300 px-4 py-3 text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            $total_amount = 0;
            foreach ($fee_items as $item): 
                $total_amount += $item['original_amount'];
            ?>
                <tr class="border-b border-gray-300">
                    <td class="border border-gray-300 px-4 py-3"><?php echo htmlspecialchars($item['fee_item']); ?></td>
                    <td class="border border-gray-300 px-4 py-3 text-right"><?php echo number_format($item['original_amount'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="font-bold bg-gray-100">
                <tr>
                    <td class="border border-gray-300 px-4 py-3 text-right">Total:</td>
                    <td class="border border-gray-300 px-4 py-3 text-right"><?php echo number_format($total_amount, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Payment Details Table -->
    <div class="mb-5 table-container">
        <div class="text-sm font-bold mb-2 px-1">Payment Details</div>
        <table class="table-auto border-collapse border border-gray-400 w-full text-sm font-semibold" style="min-width: 500px;">
            <thead>
                <tr class="bg-gray-100 font-bold">
                    <th class="border border-gray-300 px-4 py-3 text-left">Fee Item</th>
                    <th class="border border-gray-300 px-4 py-3 text-right">Previously Paid</th>
                    <th class="border border-gray-300 px-4 py-3 text-right">Current Payment</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            $total_prev_paid = 0;
            $total_current = 0;
            foreach ($fee_items as $item): 
                $prev_paid = $item['total_paid'] - $item['current_payment'];
                $total_prev_paid += $prev_paid;
                $total_current += $item['current_payment'];
            ?>
                <tr class="border-b border-gray-300">
                    <td class="border border-gray-300 px-4 py-3"><?php echo htmlspecialchars($item['fee_item']); ?></td>
                    <td class="border border-gray-300 px-4 py-3 text-right"><?php echo number_format($prev_paid, 2); ?></td>
                    <td class="border border-gray-300 px-4 py-3 text-right text-green-700"><?php echo number_format($item['current_payment'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="font-bold bg-gray-100">
                <tr>
                    <td class="border border-gray-300 px-4 py-3 text-right">Total:</td>
                    <td class="border border-gray-300 px-4 py-3 text-right"><?php echo number_format($total_prev_paid, 2); ?></td>
                    <td class="border border-gray-300 px-4 py-3 text-right text-green-700"><?php echo number_format($total_current, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Balance Table -->
    <div class="mb-5 table-container">
        <div class="text-sm font-bold mb-2 px-1">Balance Details</div>
        <table class="table-auto border-collapse border border-gray-400 w-full text-sm font-semibold" style="min-width: 400px;">
            <thead>
                <tr class="bg-gray-100 font-bold">
                    <th class="border border-gray-300 px-4 py-3 text-left">Fee Item</th>
                    <th class="border border-gray-300 px-4 py-3 text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            $total_balance = 0;
            foreach ($fee_items as $item): 
                $total_balance += $item['balance'];
            ?>
                <tr class="border-b border-gray-300">
                    <td class="border border-gray-300 px-4 py-3"><?php echo htmlspecialchars($item['fee_item']); ?></td>
                    <td class="border border-gray-300 px-4 py-3 text-right text-red-700"><?php echo number_format($item['balance'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="font-bold bg-gray-100">
                <tr>
                    <td class="border border-gray-300 px-4 py-3 text-right">Total Balance:</td>
                    <td class="border border-gray-300 px-4 py-3 text-right text-red-700"><?php echo number_format($total_balance, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Payment History -->
    <div class="section-header mb-3">PAYMENT HISTORY</div>
    <div class="mb-5 table-container">
        <table class="table-auto border-collapse border border-gray-400 w-full text-sm font-semibold" style="min-width: 400px;">
            <thead>
                <tr class="bg-gray-100 font-bold">
                    <th class="border border-gray-300 px-4 py-3 text-left">Date</th>
                    <th class="border border-gray-300 px-4 py-3 text-left">Receipt #</th>
                    <th class="border border-gray-300 px-4 py-3 text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            $total_paid = 0;
            foreach ($payment_history as $hist): 
                $total_paid += $hist['amount'];
            ?>
                <tr class="border-b border-gray-300">
                    <td class="border border-gray-300 px-4 py-3"><?php echo date('d/m/y', strtotime($hist['created_at'])); ?></td>
                    <td class="border border-gray-300 px-4 py-3"><?php echo htmlspecialchars($hist['payment_number']); ?></td>
                    <td class="border border-gray-300 px-4 py-3 text-right"><?php echo number_format($hist['amount'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="font-bold bg-gray-100">
                <tr>
                    <td class="border border-gray-300 px-4 py-3 text-right" colspan="2">Total Paid:</td>
                    <td class="border border-gray-300 px-4 py-3 text-right"><?php echo number_format($total_paid, 2); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Balance Summary -->
    <div class="section-header mb-3">BALANCE SUMMARY</div>
    <div class="mb-5 table-container">
        <table class="table-auto border-collapse border border-gray-400 w-full text-sm font-semibold" style="min-width: 300px;">
            <tbody>
                <tr class="border-b border-gray-300">
                    <td class="border border-gray-300 px-4 py-3">Invoice Total:</td>
                    <td class="border border-gray-300 px-4 py-3 text-right"><?php echo number_format($payment['invoice_total'], 2); ?></td>
                </tr>
                <tr class="border-b border-gray-300">
                    <td class="border border-gray-300 px-4 py-3">Total Paid:</td>
                    <td class="border border-gray-300 px-4 py-3 text-right text-green-700"><?php echo number_format($payment['invoice_paid'], 2); ?></td>
                </tr>
                <tr class="bg-gray-100 font-bold">
                    <td class="border border-gray-300 px-4 py-3">Balance:</td>
                    <td class="border border-gray-300 px-4 py-3 text-right text-red-700"><?php echo number_format($payment['invoice_balance'], 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php if ($payment['remarks']): ?>
    <div class="details">
        <div class="details-label">Remarks:</div>
        <div><?php echo nl2br(htmlspecialchars($payment['remarks'])); ?></div>
    </div>
    <?php endif; ?>

    <!-- Signature -->
    <div class="signature">
        <div class="signature-line"></div>
        <div>Authorized Signature</div>
    </div>

        <!-- Footer -->
        <div class="footer">
            <div>Thank you for your payment</div>
            <div>This is a computer generated receipt</div>
            <div>Printed on: <?php echo date('d/m/Y H:i:s'); ?></div>
            <div>Served By: <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></div>
        </div>

    <script>
        // Auto-print when the page loads
        window.onload = function() {
            if (!window.location.search.includes('noprint')) {
                window.print();
            }
        };
    </script>
</body>
</html>
