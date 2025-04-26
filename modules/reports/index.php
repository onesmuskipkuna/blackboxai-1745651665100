<?php
$page_title = 'Reports Dashboard';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900 mb-6">Reports Dashboard</h1>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <a href="student_fee_payments.php" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                <h2 class="text-lg font-medium text-blue-600">Student Fee Payments Report</h2>
                <p class="mt-2 text-gray-600">View payments made by students and outstanding balances.</p>
            </a>
            <a href="invoice_summary.php" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                <h2 class="text-lg font-medium text-blue-600">Invoice Summary Report</h2>
                <p class="mt-2 text-gray-600">View invoices with statuses and totals by term and year.</p>
            </a>
            <a href="expense_report.php" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                <h2 class="text-lg font-medium text-blue-600">Expense Report</h2>
                <p class="mt-2 text-gray-600">View expenses grouped by category and date range.</p>
            </a>
            <a href="payroll_report.php" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                <h2 class="text-lg font-medium text-blue-600">Payroll Report</h2>
                <p class="mt-2 text-gray-600">View payroll payments by employee and date range.</p>
            </a>
            <a href="profit_loss_report.php" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                <h2 class="text-lg font-medium text-blue-600">Profit and Loss Report</h2>
                <p class="mt-2 text-gray-600">View total income, expenses, and net profit/loss over a period.</p>
            </a>
            <a href="user_report.php" class="block p-6 bg-white rounded-lg shadow hover:bg-gray-50">
                <h2 class="text-lg font-medium text-blue-600">User-based Report</h2>
                <p class="mt-2 text-gray-600">View user activities and user-specific data.</p>
            </a>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
