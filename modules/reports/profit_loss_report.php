<?php
$page_title = 'Profit & Loss Report';
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

// Get Income Details
$stmt = $conn->prepare("
    WITH monthly_income AS (
        SELECT 
            DATE_FORMAT(p.created_at, '%Y-%m') as month,
            fs.fee_item,
            SUM(pi.amount) as total_amount,
            COUNT(DISTINCT p.id) as transaction_count
        FROM payment_items pi
        JOIN invoice_items ii ON pi.invoice_item_id = ii.id
        JOIN fee_structure fs ON ii.fee_structure_id = fs.id
        JOIN payments p ON pi.payment_id = p.id
        WHERE date(p.created_at) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(p.created_at, '%Y-%m'), fs.id, fs.fee_item
    )
    SELECT 
        fee_item,
        SUM(total_amount) as total_amount,
        SUM(transaction_count) as transaction_count,
        GROUP_CONCAT(CONCAT(month, ':', total_amount) ORDER BY month) as monthly_breakdown
    FROM monthly_income
    GROUP BY fee_item
    ORDER BY total_amount DESC
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$income_items = [];
$total_income = 0;
while ($row = $result->fetch_assoc()) {
    $income_items[] = $row;
    $total_income += $row['total_amount'];
}

// Get Expense Details
$stmt = $conn->prepare("
    WITH monthly_expenses AS (
        SELECT 
            DATE_FORMAT(e.date, '%Y-%m') as month,
            ec.name as category_name,
            SUM(e.amount) as total_amount,
            COUNT(*) as transaction_count
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        WHERE date(e.date) BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(e.date, '%Y-%m'), ec.id, ec.name
    )
    SELECT 
        category_name,
        SUM(total_amount) as total_amount,
        SUM(transaction_count) as transaction_count,
        GROUP_CONCAT(CONCAT(month, ':', total_amount) ORDER BY month) as monthly_breakdown
    FROM monthly_expenses
    GROUP BY category_name
    ORDER BY total_amount DESC
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$expenses = [];
$total_expenses = 0;
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
    $total_expenses += $row['total_amount'];
}

// Get Monthly Totals
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
            <h1 class="text-2xl font-semibold text-gray-900">Profit & Loss Report</h1>
            <div class="flex space-x-2">
                <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <i class="fas fa-print mr-2"></i>Print Report
                </button>
            </div>
        </div>

        <!-- Date Range Filter -->
        <form method="GET" class="mt-6 bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
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
            </div>
            <div class="mt-4">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>
            </div>
        </form>

        <!-- Summary Cards -->
        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <!-- Total Income -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-money-bill-wave text-green-500 text-3xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Income</dt>
                                <dd class="text-lg font-semibold text-green-600">KES <?php echo number_format($total_income, 2); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Expenses -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-file-invoice text-red-500 text-3xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total Expenses</dt>
                                <dd class="text-lg font-semibold text-red-600">KES <?php echo number_format($total_expenses, 2); ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Net Profit/Loss -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-chart-line text-blue-500 text-3xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Net Profit/Loss</dt>
                                <dd class="text-lg font-semibold <?php echo ($total_income - $total_expenses) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    KES <?php echo number_format($total_income - $total_expenses, 2); ?>
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Income Section -->
        <div class="mt-6">
            <h2 class="text-lg font-medium text-gray-900">Income Details</h2>
            <div class="mt-4 bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Income by Fee Item</h3>
                </div>
                <div class="border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fee Item</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                <?php foreach ($months as $month): ?>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <?php echo date('M Y', strtotime($month)); ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($income_items as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($item['fee_item']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    KES <?php echo number_format($item['total_amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                    <?php echo number_format($item['transaction_count']); ?>
                                </td>
                                <?php
                                $monthly = [];
                                foreach (explode(',', $item['monthly_breakdown']) as $breakdown) {
                                    list($month, $amount) = explode(':', $breakdown);
                                    $monthly[$month] = $amount;
                                }
                                foreach ($months as $month):
                                ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?php echo isset($monthly[$month]) ? number_format($monthly[$month], 2) : '-'; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="bg-gray-50 font-bold">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Total</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    KES <?php echo number_format($total_income, 2); ?>
                                </td>
                                <td></td>
                                <?php foreach ($months as $month): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?php
                                    $month_total = 0;
                                    foreach ($income_items as $item) {
                                        $monthly = [];
                                        foreach (explode(',', $item['monthly_breakdown']) as $breakdown) {
                                            list($m, $amount) = explode(':', $breakdown);
                                            $monthly[$m] = $amount;
                                        }
                                        if (isset($monthly[$month])) {
                                            $month_total += $monthly[$month];
                                        }
                                    }
                                    echo $month_total > 0 ? number_format($month_total, 2) : '-';
                                    ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Expenses Section -->
        <div class="mt-6">
            <h2 class="text-lg font-medium text-gray-900">Expense Details</h2>
            <div class="mt-4 bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Expenses by Category</h3>
                </div>
                <div class="border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Transactions</th>
                                <?php foreach ($months as $month): ?>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <?php echo date('M Y', strtotime($month)); ?>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($expense['category_name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    KES <?php echo number_format($expense['total_amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">
                                    <?php echo number_format($expense['transaction_count']); ?>
                                </td>
                                <?php
                                $monthly = [];
                                foreach (explode(',', $expense['monthly_breakdown']) as $breakdown) {
                                    list($month, $amount) = explode(':', $breakdown);
                                    $monthly[$month] = $amount;
                                }
                                foreach ($months as $month):
                                ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?php echo isset($monthly[$month]) ? number_format($monthly[$month], 2) : '-'; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="bg-gray-50 font-bold">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Total</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    KES <?php echo number_format($total_expenses, 2); ?>
                                </td>
                                <td></td>
                                <?php foreach ($months as $month): ?>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    <?php
                                    $month_total = 0;
                                    foreach ($expenses as $expense) {
                                        $monthly = [];
                                        foreach (explode(',', $expense['monthly_breakdown']) as $breakdown) {
                                            list($m, $amount) = explode(':', $breakdown);
                                            $monthly[$m] = $amount;
                                        }
                                        if (isset($monthly[$month])) {
                                            $month_total += $monthly[$month];
                                        }
                                    }
                                    echo $month_total > 0 ? number_format($month_total, 2) : '-';
                                    ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Monthly Summary -->
        <div class="mt-6">
            <h2 class="text-lg font-medium text-gray-900">Monthly Summary</h2>
            <div class="mt-4 bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Profit/Loss by Month</h3>
                </div>
                <div class="border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Income</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expenses</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Profit/Loss</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($months as $month): ?>
                            <?php
                            // Calculate monthly income
                            $month_income = 0;
                            foreach ($income_items as $item) {
                                $monthly = [];
                                foreach (explode(',', $item['monthly_breakdown']) as $breakdown) {
                                    list($m, $amount) = explode(':', $breakdown);
                                    if ($m === $month) {
                                        $month_income += $amount;
                                    }
                                }
                            }

                            // Calculate monthly expenses
                            $month_expenses = 0;
                            foreach ($expenses as $expense) {
                                $monthly = [];
                                foreach (explode(',', $expense['monthly_breakdown']) as $breakdown) {
                                    list($m, $amount) = explode(':', $breakdown);
                                    if ($m === $month) {
                                        $month_expenses += $amount;
                                    }
                                }
                            }

                            $month_profit = $month_income - $month_expenses;
                            ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo date('F Y', strtotime($month)); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    KES <?php echo number_format($month_income, 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    KES <?php echo number_format($month_expenses, 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right <?php echo $month_profit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    KES <?php echo number_format($month_profit, 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="bg-gray-50 font-bold">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Total</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    KES <?php echo number_format($total_income, 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    KES <?php echo number_format($total_expenses, 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-right <?php echo ($total_income - $total_expenses) >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    KES <?php echo number_format($total_income - $total_expenses, 2); ?>
                                </td>
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
            .text-green-600 { color: #059669 !important; print-color-adjust: exact; }
            .text-red-600 { color: #dc2626 !important; print-color-adjust: exact; }
        </style>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
