<?php
$page_title = 'User-based Report';
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
        <h1 class="text-2xl font-semibold text-gray-900">User-based Report</h1>
        <p class="mt-4 text-gray-600">This report will show user activities and user-specific data. (Under construction)</p>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
