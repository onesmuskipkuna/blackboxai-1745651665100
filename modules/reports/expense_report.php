<?php
$page_title = 'Expense Report';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

// Get date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Get categories for filter
$categories_result = $conn->query("SELECT id, name FROM expense_categories ORDER BY name");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Get expense details with monthly breakdown
$params = [];
$query = "
    WITH monthly_expenses AS (
        SELECT 
            DATE_FORMAT(e.date, '%Y-%m') as month,
            ec.id as category_id,
            ec.name as category_name,
            e.description,
            e.amount,
            e.date
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.date BETWEEN ? AND ?
";
$params[] = $start_date;
$params[] = $end_date;

if ($category_id) {
    $query .= " AND ec.id = ?";
    $params[] = $category_id;
}

$query .= "
    )
    SELECT 
        category_id,
        category_name,
        GROUP_CONCAT(
            CONCAT(
                DATE_FORMAT(date, '%Y-%m-%d'), ':', 
                amount, ':', 
                REPLACE(description, ',', ' ')
            ) 
            ORDER BY date DESC
        ) as transactions,
        COUNT(*) as transaction_count,
        SUM(amount) as total_amount,
        GROUP_CONCAT(DISTINCT month) as months
    FROM monthly_expenses
    GROUP BY category_id, category_name
    ORDER BY total_amount DESC
";

$stmt = $conn->prepare($query);
if ($category_id) {
    $stmt->bind_param('ssi', $start_date, $end_date, $category_id);
} else {
    $stmt->bind_param('ss', $start_date, $end_date);
}
$stmt->execute();
$result = $stmt->get_result();

$expenses = [];
$total_amount = 0;
$total_transactions = 0;
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
    $total_amount += $row['total_amount'];
    $total_transactions += $row['transaction_count'];
}

// Get monthly totals
$months = [];
$start = new DateTime($start_date);
$end = new DateTime($end_date);
$interval = DateInterval::createFromDateString('1 month');
$period = new DatePeriod($start, $interval, $end);
foreach ($period as $dt) {
    $months[] = $dt->format('Y-m');
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-900">Expense Report</h1>
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
                    <label for="category_id" class="block text-sm font-medium text-gray-700">Category</label>
                    <select name="category_id" id="category_id"
                            class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_id === $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
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
        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
            <!-- Total Expenses -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-file-invoice-dollar text-red-500 text-3xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Expenses</dt>
                                <dd class="text-lg font-semibold text-red-600">KES <?php echo number_format($total_amount, 2); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Transactions -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-receipt text-blue-500 text-3xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Transactions</dt>
                                <dd class="text-lg font-semibold text-gray-900"><?php echo number_format($total_transactions); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense Details -->
        <div class="mt-6">
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Expense Details by Category</h3>
                </div>
                <div class="border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Average</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($expense['category_name']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    KES <?php echo number_format($expense['total_amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                    <?php echo number_format($expense['transaction_count']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    KES <?php echo number_format($expense['total_amount'] / $expense['transaction_count'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                                    <button onclick="toggleDetails(<?php echo $expense['category_id']; ?>)" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-chevron-down" id="icon-<?php echo $expense['category_id']; ?>"></i>
                                    </button>
                                </td>
                            </tr>
                            <!-- Transaction Details Row -->
                            <tr class="hidden" id="details-<?php echo $expense['category_id']; ?>">
                                <td colspan="5" class="px-6 py-4 bg-gray-50">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-100">
                                                <tr>
                                                    <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500">Date</th>
                                                    <th scope="col" class="px-6 py-2 text-left text-xs font-medium text-gray-500">Description</th>
                                                    <th scope="col" class="px-6 py-2 text-right text-xs font-medium text-gray-500">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php
                                                $transactions = explode(',', $expense['transactions']);
                                                foreach ($transactions as $transaction) {
                                                    list($date, $amount, $description) = explode(':', $transaction);
                                                ?>
                                                <tr>
                                                    <td class="px-6 py-2 whitespace-nowrap text-xs text-gray-900">
                                                        <?php echo date('M j, Y', strtotime($date)); ?>
                                                    </td>
                                                    <td class="px-6 py-2 text-xs text-gray-900">
                                                        <?php echo htmlspecialchars($description); ?>
                                                    </td>
                                                    <td class="px-6 py-2 whitespace-nowrap text-xs text-gray-900 text-right">
                                                        KES <?php echo number_format($amount, 2); ?>
                                                    </td>
                                                </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="bg-gray-50 font-bold">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Total</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    KES <?php echo number_format($total_amount, 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?php echo number_format($total_transactions); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    KES <?php echo number_format($total_amount / $total_transactions, 2); ?>
                                </td>
                                <td></td>
                            </tr>
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
            .text-red-600 { color: #dc2626 !important; print-color-adjust: exact; }
            tr[id^="details-"] { display: table-row !important; }
        </style>

        <script>
            function toggleDetails(categoryId) {
                const detailsRow = document.getElementById(`details-${categoryId}`);
                const icon = document.getElementById(`icon-${categoryId}`);
                
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
