<?php
$page_title = 'View Payment';
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

} catch (Exception $e) {
    flashMessage('error', $e->getMessage());
    redirect('index.php');
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-900">Payment Details</h1>
            <div class="flex space-x-2">
                <a href="print.php?id=<?php echo $payment_id; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <i class="fas fa-print mr-2"></i>Print Receipt
                </a>
                <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="mt-6 bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Receipt #<?php echo htmlspecialchars($payment['payment_number']); ?>
                </h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">
                    Payment recorded on <?php echo date('F j, Y', strtotime($payment['created_at'])); ?>
                </p>
            </div>
            <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Student Name</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                        </dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Admission Number</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo htmlspecialchars($payment['admission_number']); ?>
                        </dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Class</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo ucfirst($payment['class']); ?> (<?php echo ucfirst(str_replace('_', ' ', $payment['education_level'])); ?>)
                        </dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Term/Year</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            Term <?php echo $payment['term']; ?> / <?php echo $payment['academic_year']; ?>
                        </dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Invoice Number</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo htmlspecialchars($payment['invoice_number']); ?>
                        </dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Payment Mode</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            <?php echo ucfirst($payment['payment_mode']); ?>
                            <?php if ($payment['reference_number']): ?>
                                <br><span class="text-gray-500">Ref: <?php echo htmlspecialchars($payment['reference_number']); ?></span>
                            <?php endif; ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Payment Items -->
        <div class="mt-6 bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Payment Breakdown</h3>
            </div>
            <div class="border-t border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee Item</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Previous Paid</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current Payment</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($fee_items as $item): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo htmlspecialchars($item['fee_item']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?php echo number_format($item['original_amount'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                <?php echo number_format($item['total_paid'] - $item['current_payment'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium text-right">
                                <?php echo number_format($item['current_payment'], 2); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium text-right">
                                <?php echo number_format($item['balance'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr class="font-bold">
                            <td colspan="3" class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                Total Current Payment
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium text-right">
                                KES <?php echo number_format($payment['amount'], 2); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Invoice Summary -->
        <div class="mt-6 bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Invoice Summary</h3>
            </div>
            <div class="border-t border-gray-200">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-3 p-6">
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Total Invoice Amount</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            KES <?php echo number_format($payment['invoice_total'], 2); ?>
                        </dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Total Amount Paid</dt>
                        <dd class="mt-1 text-sm text-green-600 font-medium">
                            KES <?php echo number_format($payment['invoice_paid'], 2); ?>
                        </dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Outstanding Balance</dt>
                        <dd class="mt-1 text-sm text-red-600 font-medium">
                            KES <?php echo number_format($payment['invoice_balance'], 2); ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <?php if ($payment['remarks']): ?>
        <!-- Remarks -->
        <div class="mt-6 bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Remarks</h3>
                <div class="mt-2 text-sm text-gray-600">
                    <?php echo nl2br(htmlspecialchars($payment['remarks'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
