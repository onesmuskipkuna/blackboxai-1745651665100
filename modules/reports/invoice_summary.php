<?php
$page_title = 'Invoice Summary Report';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

// Get filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$status = isset($_GET['status']) ? $_GET['status'] : '';
$class = isset($_GET['class']) ? $_GET['class'] : '';
$education_level = isset($_GET['education_level']) ? $_GET['education_level'] : '';

// Build query
$query = "
    SELECT 
        i.*,
        s.admission_number,
        s.first_name,
        s.last_name,
        s.guardian_name,
        s.phone_number,
        s.class,
        s.education_level,
        GROUP_CONCAT(
            CONCAT(
                fs.fee_item, ':', 
                ii.amount, ':', 
                COALESCE((
                    SELECT SUM(pi.amount) 
                    FROM payment_items pi 
                    WHERE pi.invoice_item_id = ii.id
                ), 0)
            )
            ORDER BY fs.fee_item
        ) as fee_items
    FROM invoices i
    JOIN students s ON i.student_id = s.id
    JOIN invoice_items ii ON i.id = ii.invoice_id
    LEFT JOIN fee_structure fs ON ii.fee_structure_id = fs.id
    WHERE i.created_at BETWEEN ? AND ?
";

$params = [$start_date, $end_date];
$types = 'ss';

if ($status) {
    $query .= " AND i.status = ?";
    $params[] = $status;
    $types .= 's';
}
if ($class) {
    $query .= " AND s.class = ?";
    $params[] = $class;
    $types .= 's';
}
if ($education_level) {
    $query .= " AND s.education_level = ?";
    $params[] = $education_level;
    $types .= 's';
}

$query .= " GROUP BY i.id ORDER BY i.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$invoices = [];
$total_amount = 0;
$total_paid = 0;
$total_balance = 0;
while ($row = $result->fetch_assoc()) {
    $invoices[] = $row;
    $total_amount += $row['total_amount'];
    $total_paid += $row['paid_amount'];
    $total_balance += $row['balance'];
}

// Get class list
$classes_result = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");
$classes = [];
while ($row = $classes_result->fetch_assoc()) {
    $classes[] = $row['class'];
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-900">Invoice Summary Report</h1>
            <div class="flex space-x-2">
                <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <i class="fas fa-print mr-2"></i>Print Report
                </button>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="mt-6 bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-3">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>"
                           class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>"
                           class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                    <select name="status" id="status"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                        <option value="">All Statuses</option>
                        <option value="due" <?php echo $status === 'due' ? 'selected' : ''; ?>>Due</option>
                        <option value="partially_paid" <?php echo $status === 'partially_paid' ? 'selected' : ''; ?>>Partially Paid</option>
                        <option value="fully_paid" <?php echo $status === 'fully_paid' ? 'selected' : ''; ?>>Fully Paid</option>
                    </select>
                </div>
                <div>
                    <label for="education_level" class="block text-sm font-medium text-gray-700">Education Level</label>
                    <select name="education_level" id="education_level"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                        <option value="">All Levels</option>
                        <option value="primary" <?php echo $education_level === 'primary' ? 'selected' : ''; ?>>Primary</option>
                        <option value="junior_secondary" <?php echo $education_level === 'junior_secondary' ? 'selected' : ''; ?>>Junior Secondary</option>
                    </select>
                </div>
                <div>
                    <label for="class" class="block text-sm font-medium text-gray-700">Class</label>
                    <select name="class" id="class"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class_option): ?>
                            <option value="<?php echo $class_option; ?>" <?php echo $class === $class_option ? 'selected' : ''; ?>>
                                <?php echo ucfirst($class_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
            </div>
        </form>

        <!-- Summary Cards -->
        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <!-- Total Amount -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-file-invoice-dollar text-blue-500 text-3xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Amount</dt>
                                <dd class="text-lg font-semibold text-gray-900">KES <?php echo number_format($total_amount, 2); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Amount Paid -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-money-bill-wave text-green-500 text-3xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Amount Paid</dt>
                                <dd class="text-lg font-semibold text-green-600">KES <?php echo number_format($total_paid, 2); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outstanding Balance -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-balance-scale text-red-500 text-3xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Outstanding Balance</dt>
                                <dd class="text-lg font-semibold text-red-600">KES <?php echo number_format($total_balance, 2); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Details -->
        <div class="mt-6">
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Invoice Details</h3>
                </div>
                <div class="border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice #</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Term/Year</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Paid</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($invoices as $invoice): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($invoice['admission_number'] . ' - ' . $invoice['first_name'] . ' ' . $invoice['last_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo ucfirst($invoice['class']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    Term <?php echo $invoice['term']; ?> / <?php echo $invoice['academic_year']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?php echo number_format($invoice['total_amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right">
                                    <?php echo number_format($invoice['paid_amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 text-right">
                                    <?php echo number_format($invoice['balance'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
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
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                    <button onclick="toggleDetails(<?php echo $invoice['id']; ?>)" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-chevron-down" id="icon-<?php echo $invoice['id']; ?>"></i>
                                    </button>
                                </td>
                            </tr>
                            <!-- Fee Items Details Row -->
                            <tr class="hidden" id="details-<?php echo $invoice['id']; ?>">
                                <td colspan="9" class="px-6 py-4 bg-gray-50">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-100">
                                                <tr>
                                                    <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500">Fee Item</th>
                                                    <th scope="col" class="px-6 py-2 text-right text-xs font-medium text-gray-500">Amount</th>
                                                    <th scope="col" class="px-6 py-2 text-right text-xs font-medium text-gray-500">Paid</th>
                                                    <th scope="col" class="px-6 py-2 text-right text-xs font-medium text-gray-500">Balance</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php
                                                $fee_items = explode(',', $invoice['fee_items']);
                                                foreach ($fee_items as $item) {
                                                    list($name, $amount, $paid) = explode(':', $item);
                                                    $balance = $amount - $paid;
                                                ?>
                                                <tr>
                                                    <td class="px-6 py-2 text-xs text-gray-900">
                                                        <?php echo htmlspecialchars($name); ?>
                                                    </td>
                                                    <td class="px-6 py-2 text-xs text-gray-900 text-right">
                                                        <?php echo number_format($amount, 2); ?>
                                                    </td>
                                                    <td class="px-6 py-2 text-xs text-green-600 text-right">
                                                        <?php echo number_format($paid, 2); ?>
                                                    </td>
                                                    <td class="px-6 py-2 text-xs text-red-600 text-right">
                                                        <?php echo number_format($balance, 2); ?>
                                                    </td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Print Styles -->
        <style type="text/css" media="print">
            @page { size: landscape; }
            nav, form, button { display: none !important; }
            .shadow { box-shadow: none !important; }
            .bg-gray-50 { background-color: #f9fafb !important; print-color-adjust: exact; }
            .bg-white { background-color: white !important; print-color-adjust: exact; }
            .text-green-600 { color: #059669 !important; print-color-adjust: exact; }
            .text-red-600 { color: #dc2626 !important; print-color-adjust: exact; }
            tr[id^="details-"] { display: table-row !important; }
        </style>

        <script>
            function toggleDetails(invoiceId) {
                const detailsRow = document.getElementById(`details-${invoiceId}`);
                const icon = document.getElementById(`icon-${invoiceId}`);
                
                if (detailsRow.classList.contains('hidden')) {
                    detailsRow.classList.remove('hidden');
                    icon.classList.remove('fa-chevron-down');
                    icon.classList.add('fa-chevron-up');
                } else {
                    detailsRow.classList.add('hidden');
                    icon.classList.remove('fa-chevron-up');
                    icon.classList.add('fa-chevron-down');
                }
            }
        </script>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
