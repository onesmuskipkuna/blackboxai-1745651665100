<?php
$page_title = 'Invoice Summary Report';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

$term = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$academic_year = isset($_GET['academic_year']) ? sanitize($_GET['academic_year']) : date('Y');

$whereClauses = [];
$params = [];

if ($term > 0) {
    $whereClauses[] = 'i.term = ?';
    $params[] = $term;
}

if ($academic_year) {
    $whereClauses[] = 'i.academic_year = ?';
    $params[] = $academic_year;
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
}

$query = "
    SELECT 
        i.id,
        i.invoice_number,
        i.total_amount,
        i.paid_amount,
        i.balance,
        i.status,
        i.term,
        i.academic_year,
        s.admission_number,
        s.first_name,
        s.last_name
    FROM invoices i
    JOIN students s ON i.student_id = s.id
    $whereSql
    ORDER BY i.academic_year DESC, i.term DESC, i.invoice_number DESC
";

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $types = '';
    foreach ($params as $param) {
        $types .= is_int($param) ? 'i' : 's';
    }
    $stmt->bind_param($types, ...$params);
}

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

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900 mb-6">Invoice Summary Report</h1>

        <form method="GET" class="mb-6 flex space-x-4">
            <div>
                <label for="term" class="block text-sm font-medium text-gray-700">Term</label>
                <select name="term" id="term" class="mt-1 block pl-3 pr-10 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="0">All Terms</option>
                    <option value="1" <?php if ($term == 1) echo 'selected'; ?>>Term 1</option>
                    <option value="2" <?php if ($term == 2) echo 'selected'; ?>>Term 2</option>
                    <option value="3" <?php if ($term == 3) echo 'selected'; ?>>Term 3</option>
                </select>
            </div>

            <div>
                <label for="academic_year" class="block text-sm font-medium text-gray-700">Academic Year</label>
                <input type="number" name="academic_year" id="academic_year" min="2000" max="2099" step="1" value="<?php echo htmlspecialchars($academic_year); ?>"
                       class="mt-1 block w-32 pl-3 pr-10 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div class="flex items-end">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Filter
                </button>
            </div>
        </form>

        <table class="min-w-full divide-y divide-gray-200 shadow rounded-lg overflow-hidden">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice Number</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Term</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount (KES)</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Paid Amount (KES)</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance (KES)</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No invoices found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($invoice['term']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($invoice['academic_year']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900"><?php echo number_format($invoice['total_amount'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900"><?php echo number_format($invoice['paid_amount'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900"><?php echo number_format($invoice['balance'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo ucfirst($invoice['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="font-bold bg-gray-100">
                        <td colspan="4" class="px-6 py-4 text-right">Totals:</td>
                        <td class="px-6 py-4 text-right"><?php echo number_format($total_amount, 2); ?></td>
                        <td class="px-6 py-4 text-right"><?php echo number_format($total_paid, 2); ?></td>
                        <td class="px-6 py-4 text-right"><?php echo number_format($total_balance, 2); ?></td>
                        <td></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
