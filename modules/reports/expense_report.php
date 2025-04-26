<?php
$page_title = 'Expense Report';
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
        <h1 class="text-2xl font-semibold text-gray-900">Expense Report</h1>
        <p class="mt-4 text-gray-600">This report will show expenses grouped by category and date range. (Under construction)</p>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
