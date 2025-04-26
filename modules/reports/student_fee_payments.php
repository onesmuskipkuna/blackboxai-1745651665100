<?php
$page_title = 'Student Fee Payments Report';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

// Fetch students with total payments and outstanding balances
$query = "
    SELECT 
        s.id,
        s.admission_number,
        s.first_name,
        s.last_name,
        s.class,
        s.education_level,
        IFNULL((SELECT SUM(amount) FROM payments p JOIN invoices i ON p.invoice_id = i.id WHERE i.student_id = s.id), 0) AS total_payments,
        IFNULL((SELECT SUM(balance) FROM invoices WHERE student_id = s.id), 0) AS total_balance
    FROM students s
    WHERE s.status = 'active'
    ORDER BY s.admission_number
";

$result = $conn->query($query);

$students = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $students[] = $row;
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900 mb-6">Student Fee Payments Report</h1>
        <table class="min-w-full divide-y divide-gray-200 shadow rounded-lg overflow-hidden">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admission Number</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Education Level</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Payments (KES)</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Outstanding Balance (KES)</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No students found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['admission_number']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['class']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($student['education_level']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-green-600"><?php echo number_format($student['total_payments'], 2); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-red-600"><?php echo number_format($student['total_balance'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
