<?php
$page_title = 'View Invoice';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

// Get invoice ID
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$invoice_id) {
    flashMessage('error', 'Invalid invoice ID');
    redirect('index.php');
}

// Get invoice details with student information
$stmt = $conn->prepare("
    SELECT i.*, 
           s.first_name, s.last_name, s.admission_number, s.guardian_name, s.phone_number,
           s.class, s.education_level
    FROM invoices i
    JOIN students s ON i.student_id = s.id
    WHERE i.id = ?
");
$stmt->bind_param('i', $invoice_id);
$stmt->execute();
$result = $stmt->get_result();
$invoice = $result->fetch_assoc();

if (!$invoice) {
    flashMessage('error', 'Invoice not found');
    redirect('index.php');
}

// Get invoice items
$stmt = $conn->prepare("
    SELECT ii.*, fs.fee_item
    FROM invoice_items ii
    LEFT JOIN fee_structure fs ON ii.fee_structure_id = fs.id
    WHERE ii.invoice_id = ?
");
$stmt->bind_param('i', $invoice_id);
$stmt->execute();
$result = $stmt->get_result();

$invoice_items = [];
while ($row = $result->fetch_assoc()) {
    // Handle NULL fee_structure_id for balance carry forward
    if ($row['fee_structure_id'] === null) {
        $row['fee_item'] = 'Balance Carried Forward';
    }
    $invoice_items[] = $row;
}

// Get payment history
$stmt = $conn->prepare("
    SELECT p.*, pi.amount as item_amount, fs.fee_item
    FROM payments p
    LEFT JOIN payment_items pi ON p.id = pi.payment_id
    LEFT JOIN invoice_items ii ON pi.invoice_item_id = ii.id
    LEFT JOIN fee_structure fs ON ii.fee_structure_id = fs.id
    WHERE p.invoice_id = ?
    ORDER BY p.created_at DESC
");
$stmt->bind_param('i', $invoice_id);
$stmt->execute();
$result = $stmt->get_result();

$payments = [];
while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-4">
    <div class="max-w-4xl mx-auto px-2 sm:px-4 lg:px-6">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-xl font-semibold text-gray-900">Invoice Details</h1>
            <div class="flex space-x-2">
                <a href="print.php?id=<?php echo $invoice_id; ?>" class="inline-flex items-center px-3 py-1 border border-transparent rounded-md shadow-sm text-xs font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <i class="fas fa-print mr-1"></i>Print Invoice
                </a>
                <a href="index.php" class="inline-flex items-center px-3 py-1 border border-transparent rounded-md shadow-sm text-xs font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-arrow-left mr-1"></i>Back to List
                </a>
            </div>
        </div>

        <!-- Invoice Header -->
        <div class="mt-4 bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-3 py-4 sm:px-4">
                <h3 class="text-md font-medium text-gray-900">Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h3>
                <p class="mt-1 max-w-2xl text-xs text-gray-500">
                    Created on <?php echo date('F j, Y', strtotime($invoice['created_at'])); ?>
                </p>
            </div>
            <div class="border-t border-gray-200 px-3 py-4 sm:px-4">
                <dl class="grid grid-cols-1 gap-x-2 gap-y-4 sm:grid-cols-2">
                    <div class="sm:col-span-1">
                        <dt class="text-xs font-medium text-gray-500">Student Name</dt>
                        <dd class="mt-1 text-xs text-gray-900">
                            <?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?>
                        </dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-xs font-medium text-gray-500">Admission Number</dt>
                        <dd class="mt-1 text-xs text-gray-900"><?php echo htmlspecialchars($invoice['admission_number']); ?></dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-xs font-medium text-gray-500">Class</dt>
                        <dd class="mt-1 text-xs text-gray-900">
                            <?php echo ucfirst($invoice['class']); ?> (<?php echo ucfirst(str_replace('_', ' ', $invoice['education_level'])); ?>)
                        </dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-xs font-medium text-gray-500">Term/Year</dt>
                        <dd class="mt-1 text-xs text-gray-900">Term <?php echo $invoice['term']; ?> / <?php echo $invoice['academic_year']; ?></dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-xs font-medium text-gray-500">Guardian Name</dt>
                        <dd class="mt-1 text-xs text-gray-900"><?php echo htmlspecialchars($invoice['guardian_name']); ?></dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-xs font-medium text-gray-500">Phone Number</dt>
                        <dd class="mt-1 text-xs text-gray-900"><?php echo htmlspecialchars($invoice['phone_number']); ?></dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-xs font-medium text-gray-500">Due Date</dt>
                        <dd class="mt-1 text-xs text-gray-900"><?php echo date('F j, Y', strtotime($invoice['due_date'])); ?></dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-xs font-medium text-gray-500">Status</dt>
                        <dd class="mt-1 text-xs">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php 
                                echo match($invoice['status']) {
                                    'fully_paid' => 'bg-green-100 text-green-800',
                                    'partially_paid' => 'bg-yellow-100 text-yellow-800',
                                    default => 'bg-red-100 text-red-800'
                                };
                                ?>">
                                <?php echo ucwords(str_replace('_', ' ', $invoice['status'])); ?>
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Fee Items -->
        <div class="mt-4 bg-white shadow sm:rounded-lg">
            <div class="px-3 py-4 sm:px-4">
                <h3 class="text-md font-medium text-gray-900">Fee Breakdown</h3>
            </div>
            <div class="border-t border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">
                                Fee Item
                            </th>
                            <th scope="col" class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">
                                Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($invoice_items as $item): ?>
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-900">
                                <?php echo htmlspecialchars($item['fee_item']); ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-900 text-right">
                                KES <?php echo number_format($item['amount'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-50">
                            <td class="px-3 py-2 whitespace-nowrap font-medium text-gray-900">
                                Total Amount
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap font-medium text-gray-900 text-right">
                                KES <?php echo number_format($invoice['total_amount'], 2); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap font-medium text-green-600">
                                Amount Paid
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap font-medium text-green-600 text-right">
                                KES <?php echo number_format($invoice['paid_amount'], 2); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap font-medium text-red-600">
                                Balance
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap font-medium text-red-600 text-right">
                                KES <?php echo number_format($invoice['balance'], 2); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
        <div class="mt-4 bg-white shadow sm:rounded-lg">
            <div class="px-3 py-4 sm:px-4">
                <h3 class="text-md font-medium text-gray-900">Payment History</h3>
            </div>
            <div class="border-t border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">
                                Receipt Number
                            </th>
                            <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                            <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">
                                Payment Mode
                            </th>
                            <th scope="col" class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">
                                Reference
                            </th>
                            <th scope="col" class="px-3 py-2 text-right font-medium text-gray-500 uppercase tracking-wider">
                                Amount
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-900">
                                <?php echo htmlspecialchars($payment['payment_number']); ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-900">
                                <?php echo date('M j, Y', strtotime($payment['created_at'])); ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-900">
                                <?php echo ucfirst($payment['payment_mode']); ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-900">
                                <?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-gray-900 text-right">
                                KES <?php echo number_format($payment['amount'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
