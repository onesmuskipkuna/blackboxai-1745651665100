<?php
$page_title = 'Profit and Loss Report';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

$start_date = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : '';

$whereClausesPayments = [];
$whereClausesExpenses = [];
$paramsPayments = [];
$paramsExpenses = [];

if ($start_date) {
    $whereClausesPayments[] = 'created_at >= ?';
    $whereClausesExpenses[] = 'date >= ?';
    $paramsPayments[] = $start_date;
    $paramsExpenses[] = $start_date;
}

if ($end_date) {
    $whereClausesPayments[] = 'created_at <= ?';
    $whereClausesExpenses[] = 'date <= ?';
    $paramsPayments[] = $end_date;
    $paramsExpenses[] = $end_date;
}

$whereSqlPayments = '';
$whereSqlExpenses = '';

if (!empty($whereClausesPayments)) {
    $whereSqlPayments = 'WHERE ' . implode(' AND ', $whereClausesPayments);
}

if (!empty($whereClausesExpenses)) {
    $whereSqlExpenses = 'WHERE ' . implode(' AND ', $whereClausesExpenses);
}

// Calculate total income (payments)
$queryPayments = "SELECT IFNULL(SUM(amount), 0) as total_income FROM payments $whereSqlPayments";
$stmtPayments = $conn->prepare($queryPayments);
if (!empty($paramsPayments)) {
    $types = str_repeat('s', count($paramsPayments));
    $stmtPayments->bind_param($types, ...$paramsPayments);
}
$stmtPayments->execute();
$resultPayments = $stmtPayments->get_result();
$total_income = $resultPayments->fetch_assoc()['total_income'] ?? 0;

// Calculate total expenses
$queryExpenses = "SELECT IFNULL(SUM(amount), 0) as total_expenses FROM expenses $whereSqlExpenses";
$stmtExpenses = $conn->prepare($queryExpenses);
if (!empty($paramsExpenses)) {
    $types = str_repeat('s', count($paramsExpenses));
    $stmtExpenses->bind_param($types, ...$paramsExpenses);
}
$stmtExpenses->execute();
$resultExpenses = $stmtExpenses->get_result();
$total_expenses = $resultExpenses->fetch_assoc()['total_expenses'] ?? 0;

// Calculate net profit/loss
$net_profit = $total_income - $total_expenses;

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900 mb-6">Profit and Loss Report</h1>

        <form method="GET" class="mb-6 flex space-x-4">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                       class="mt-1 block w-40 pl-3 pr-10 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                       class="mt-1 block w-40 pl-3 pr-10 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div class="flex items-end">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Filter
                </button>
            </div>
        </form>

        <div class="bg-white shadow rounded-lg p-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
                <div>
                    <h2 class="text-lg font-medium text-gray-700">Total Income</h2>
                    <p class="mt-2 text-2xl font-bold text-green-600">KES <?php echo number_format($total_income, 2); ?></p>
                </div>
                <div>
                    <h2 class="text-lg font-medium text-gray-700">Total Expenses</h2>
                    <p class="mt-2 text-2xl font-bold text-red-600">KES <?php echo number_format($total_expenses, 2); ?></p>
                </div>
                <div>
                    <h2 class="text-lg font-medium text-gray-700">Net Profit/Loss</h2>
                    <p class="mt-2 text-2xl font-bold <?php echo $net_profit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        KES <?php echo number_format($net_profit, 2); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
