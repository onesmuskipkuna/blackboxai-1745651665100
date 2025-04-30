<?php
$page_title = 'Reports Dashboard';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

// Get date range for charts
$start_date = date('Y-m-d', strtotime('-11 months'));
$end_date = date('Y-m-d');

// Get monthly income data
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(p.created_at, '%Y-%m') as month,
        SUM(p.amount) as total_amount
    FROM payments p
    WHERE p.created_at BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
    ORDER BY month
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$monthly_income = [];
$income_labels = [];
$income_data = [];
while ($row = $result->fetch_assoc()) {
    $monthly_income[$row['month']] = $row['total_amount'];
    $income_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $income_data[] = $row['total_amount'];
}

// Get monthly expenses data
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(e.date, '%Y-%m') as month,
        SUM(e.amount) as total_amount
    FROM expenses e
    WHERE e.date BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(e.date, '%Y-%m')
    ORDER BY month
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$monthly_expenses = [];
$expense_data = [];
while ($row = $result->fetch_assoc()) {
    $monthly_expenses[$row['month']] = $row['total_amount'];
    $expense_data[] = $row['total_amount'];
}

// Get expense categories breakdown
$stmt = $conn->prepare("
    SELECT 
        ec.name,
        SUM(e.amount) as total_amount
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    WHERE e.date BETWEEN ? AND ?
    GROUP BY ec.id, ec.name
    ORDER BY total_amount DESC
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$expense_categories = [];
$expense_category_labels = [];
$expense_category_data = [];
while ($row = $result->fetch_assoc()) {
    $expense_categories[$row['name']] = $row['total_amount'];
    $expense_category_labels[] = $row['name'];
    $expense_category_data[] = $row['total_amount'];
}

// Get fee item breakdown
$stmt = $conn->prepare("
    SELECT 
        fs.fee_item,
        SUM(pi.amount) as total_amount
    FROM payment_items pi
    JOIN invoice_items ii ON pi.invoice_item_id = ii.id
    JOIN fee_structure fs ON ii.fee_structure_id = fs.id
    JOIN payments p ON pi.payment_id = p.id
    WHERE p.created_at BETWEEN ? AND ?
    GROUP BY fs.id, fs.fee_item
    ORDER BY total_amount DESC
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$fee_items = [];
$fee_item_labels = [];
$fee_item_data = [];
while ($row = $result->fetch_assoc()) {
    $fee_items[$row['fee_item']] = $row['total_amount'];
    $fee_item_labels[] = $row['fee_item'];
    $fee_item_data[] = $row['total_amount'];
}

// Get class-wise student distribution
$stmt = $conn->prepare("
    SELECT 
        class,
        COUNT(*) as student_count
    FROM students
    WHERE status = 'active'
    GROUP BY class
    ORDER BY 
        CASE 
            WHEN class = 'pg' THEN 1
            WHEN class = 'pp1' THEN 2
            WHEN class = 'pp2' THEN 3
            ELSE CAST(SUBSTRING(class, 6) AS UNSIGNED) + 3
        END
");
$stmt->execute();
$result = $stmt->get_result();

$class_distribution = [];
$class_labels = [];
$class_data = [];
while ($row = $result->fetch_assoc()) {
    $class_distribution[$row['class']] = $row['student_count'];
    $class_labels[] = ucfirst($row['class']);
    $class_data[] = $row['student_count'];
}

// Get payment mode distribution
$stmt = $conn->prepare("
    SELECT 
        payment_mode,
        COUNT(*) as payment_count,
        SUM(amount) as total_amount
    FROM payments
    WHERE created_at BETWEEN ? AND ?
    GROUP BY payment_mode
");
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$payment_modes = [];
$payment_mode_labels = [];
$payment_mode_data = [];
while ($row = $result->fetch_assoc()) {
    $payment_modes[$row['payment_mode']] = $row['total_amount'];
    $payment_mode_labels[] = ucfirst($row['payment_mode']);
    $payment_mode_data[] = $row['total_amount'];
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Reports Dashboard</h1>

        <!-- Income vs Expenses Chart -->
        <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <h3 class="text-lg font-medium text-gray-900">Income vs Expenses (Last 12 Months)</h3>
                    <div class="mt-4" style="height: 300px;">
                        <canvas id="incomeExpensesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Fee Items Distribution -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <h3 class="text-lg font-medium text-gray-900">Fee Items Distribution</h3>
                    <div class="mt-4" style="height: 300px;">
                        <canvas id="feeItemsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row -->
        <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
            <!-- Expense Categories -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <h3 class="text-lg font-medium text-gray-900">Expense Categories</h3>
                    <div class="mt-4" style="height: 300px;">
                        <canvas id="expenseCategoriesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Payment Modes -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <h3 class="text-lg font-medium text-gray-900">Payment Modes Distribution</h3>
                    <div class="mt-4" style="height: 300px;">
                        <canvas id="paymentModesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Third Row -->
        <div class="mt-6">
            <!-- Class Distribution -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <h3 class="text-lg font-medium text-gray-900">Student Distribution by Class</h3>
                    <div class="mt-4" style="height: 300px;">
                        <canvas id="classDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Links -->
        <div class="mt-8">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Detailed Reports</h2>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Balance Sheet -->
                <a href="balance_sheet.php" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-200">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-balance-scale text-purple-500 text-2xl"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <h3 class="text-lg font-medium text-gray-900">Balance Sheet</h3>
                                <p class="mt-1 text-sm text-gray-500">View assets, liabilities, and equity position</p>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Profit & Loss -->
                <a href="profit_loss_report.php" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-200">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-chart-line text-blue-500 text-2xl"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <h3 class="text-lg font-medium text-gray-900">Profit & Loss</h3>
                                <p class="mt-1 text-sm text-gray-500">Monthly income and expense analysis</p>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Invoice Summary -->
                <a href="invoice_summary.php" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-200">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-file-invoice text-yellow-500 text-2xl"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <h3 class="text-lg font-medium text-gray-900">Invoice Summary</h3>
                                <p class="mt-1 text-sm text-gray-500">Overview of all invoices and their status</p>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Student Fee Payments -->
                <a href="student_fee_payments.php" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-200">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user-graduate text-green-500 text-2xl"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <h3 class="text-lg font-medium text-gray-900">Student Fee Payments</h3>
                                <p class="mt-1 text-sm text-gray-500">Track student-wise fee payments and balances</p>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- Expense Report -->
                <a href="expense_report.php" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-200">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-file-invoice-dollar text-red-500 text-2xl"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <h3 class="text-lg font-medium text-gray-900">Expense Report</h3>
                                <p class="mt-1 text-sm text-gray-500">Detailed expense analysis by category</p>
                            </div>
                        </div>
                    </div>
                </a>

                <!-- User Report -->
                <a href="user_report.php" class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-200">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-users-cog text-gray-500 text-2xl"></i>
                            </div>
