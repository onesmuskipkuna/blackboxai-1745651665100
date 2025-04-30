<?php
$page_title = 'User Activity Report';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Get current user's role and ID
    $current_user_id = $_SESSION['user_id'];
    $current_user_role = $_SESSION['role'];

    // Base query for users
    $user_query = "SELECT id, username, first_name, last_name, role FROM users";
    if ($current_user_role !== 'admin') {
        // Non-admin users can only see their own data
        $user_query .= " WHERE id = ?";
    }
    $user_query .= " ORDER BY username";

    $stmt = $conn->prepare($user_query);
    if ($current_user_role !== 'admin') {
        $stmt->bind_param('i', $current_user_id);
    }
    $stmt->execute();
    $users_result = $stmt->get_result();
    $stmt->close();

    $users = [];
    while ($user = $users_result->fetch_assoc()) {
        // Get payment totals by mode for this user
        $payment_query = "
            SELECT 
                payment_mode,
                COUNT(*) as count,
                SUM(amount) as total
            FROM payments 
            WHERE created_by = ?
            GROUP BY payment_mode
        ";
        $stmt = $conn->prepare($payment_query);
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $payments_result = $stmt->get_result();
        $user['payments'] = [
            'cash' => ['count' => 0, 'total' => 0],
            'mpesa' => ['count' => 0, 'total' => 0],
            'bank' => ['count' => 0, 'total' => 0]
        ];
        while ($payment = $payments_result->fetch_assoc()) {
            $user['payments'][$payment['payment_mode']] = [
                'count' => $payment['count'],
                'total' => $payment['total']
            ];
        }
        $stmt->close();

        // Get total payments for this user
        $total_query = "
            SELECT 
                COUNT(*) as count,
                SUM(amount) as total
            FROM payments 
            WHERE created_by = ?
        ";
        $stmt = $conn->prepare($total_query);
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $total_result = $stmt->get_result();
        $total_data = $total_result->fetch_assoc();
        $user['total_payments'] = [
            'count' => $total_data['count'] ?? 0,
            'total' => $total_data['total'] ?? 0
        ];
        $stmt->close();

        // Get expenses for this user
        $expense_query = "
            SELECT 
                COUNT(*) as count,
                SUM(amount) as total
            FROM expenses 
            WHERE created_by = ?
        ";
        $stmt = $conn->prepare($expense_query);
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $expense_result = $stmt->get_result();
        $expense_data = $expense_result->fetch_assoc();
        $user['expenses'] = [
            'count' => $expense_data['count'] ?? 0,
            'total' => $expense_data['total'] ?? 0
        ];
        $stmt->close();

        // Calculate net total (payments - expenses)
        $user['net_total'] = ($user['total_payments']['total'] ?? 0) - ($user['expenses']['total'] ?? 0);

        $users[] = $user;
    }

    // Calculate grand totals
    $grand_totals = [
        'payments' => [
            'cash' => ['count' => 0, 'total' => 0],
            'mpesa' => ['count' => 0, 'total' => 0],
            'bank' => ['count' => 0, 'total' => 0]
        ],
        'total_payments' => ['count' => 0, 'total' => 0],
        'expenses' => ['count' => 0, 'total' => 0],
        'net_total' => 0
    ];

    foreach ($users as $user) {
        foreach ($user['payments'] as $mode => $data) {
            $grand_totals['payments'][$mode]['count'] += $data['count'];
            $grand_totals['payments'][$mode]['total'] += $data['total'];
        }
        $grand_totals['total_payments']['count'] += $user['total_payments']['count'];
        $grand_totals['total_payments']['total'] += $user['total_payments']['total'];
        $grand_totals['expenses']['count'] += $user['expenses']['count'];
        $grand_totals['expenses']['total'] += $user['expenses']['total'];
        $grand_totals['net_total'] += $user['net_total'];
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">User Activity Report</h1>
            <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                <i class="fas fa-print mr-2"></i>Print Report
            </button>
        </div>

        <!-- Grand Totals -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 lg:grid-cols-4 mb-6">
            <!-- Cash Total -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-money-bill text-green-500 text-3xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Cash Payments</dt>
                                <dd class="text-lg font-semibold text-green-600">
                                    KES <?php echo number_format($grand_totals['payments']['cash']['total'], 2); ?>
                                </dd>
                                <dd class="text-sm text-gray-500">
                                    <?php echo number_format($grand_totals['payments']['cash']['count']); ?> transactions
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- M-Pesa Total -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-mobile-alt text-green-500 text-3xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">M-Pesa Payments</dt>
                                <dd class="text-lg font-semibold text-green-600">
                                    KES <?php echo number_format($grand_totals['payments']['mpesa']['total'], 2); ?>
                                </dd>
                                <dd class="text-sm text-gray-500">
                                    <?php echo number_format($grand_totals['payments']['mpesa']['count']); ?> transactions
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank Total -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-university text-green-500 text-3xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Bank Payments</dt>
                                <dd class="text-lg font-semibold text-green-600">
                                    KES <?php echo number_format($grand_totals['payments']['bank']['total'], 2); ?>
                                </dd>
                                <dd class="text-sm text-gray-500">
                                    <?php echo number_format($grand_totals['payments']['bank']['count']); ?> transactions
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Net Total -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-calculator text-blue-500 text-3xl"></i>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Net Total</dt>
                                <dd class="text-lg font-semibold text-blue-600">
                                    KES <?php echo number_format($grand_totals['net_total'], 2); ?>
                                </dd>
                                <dd class="text-sm text-gray-500">
                                    After expenses
                                </dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Details -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Cash
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            M-Pesa
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Bank
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Total Payments
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Expenses
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Net Total
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800'; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                <div class="text-gray-900">KES <?php echo number_format($user['payments']['cash']['total'], 2); ?></div>
                                <div class="text-gray-500"><?php echo $user['payments']['cash']['count']; ?> payments</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                <div class="text-gray-900">KES <?php echo number_format($user['payments']['mpesa']['total'], 2); ?></div>
                                <div class="text-gray-500"><?php echo $user['payments']['mpesa']['count']; ?> payments</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                <div class="text-gray-900">KES <?php echo number_format($user['payments']['bank']['total'], 2); ?></div>
                                <div class="text-gray-500"><?php echo $user['payments']['bank']['count']; ?> payments</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                <div class="text-green-600 font-medium">KES <?php echo number_format($user['total_payments']['total'], 2); ?></div>
                                <div class="text-gray-500"><?php echo $user['total_payments']['count']; ?> total</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                <div class="text-red-600 font-medium">KES <?php echo number_format($user['expenses']['total'], 2); ?></div>
                                <div class="text-gray-500"><?php echo $user['expenses']['count']; ?> expenses</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">
                                <div class="<?php echo $user['net_total'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                    KES <?php echo number_format($user['net_total'], 2); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Grand Totals Row -->
                    <tr class="bg-gray-50 font-medium">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            Grand Totals
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <div class="text-gray-900">KES <?php echo number_format($grand_totals['payments']['cash']['total'], 2); ?></div>
                            <div class="text-gray-500"><?php echo $grand_totals['payments']['cash']['count']; ?> payments</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <div class="text-gray-900">KES <?php echo number_format($grand_totals['payments']['mpesa']['total'], 2); ?></div>
                            <div class="text-gray-500"><?php echo $grand_totals['payments']['mpesa']['count']; ?> payments</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <div class="text-gray-900">KES <?php echo number_format($grand_totals['payments']['bank']['total'], 2); ?></div>
                            <div class="text-gray-500"><?php echo $grand_totals['payments']['bank']['count']; ?> payments</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <div class="text-green-600">KES <?php echo number_format($grand_totals['total_payments']['total'], 2); ?></div>
                            <div class="text-gray-500"><?php echo $grand_totals['total_payments']['count']; ?> total</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <div class="text-red-600">KES <?php echo number_format($grand_totals['expenses']['total'], 2); ?></div>
                            <div class="text-gray-500"><?php echo $grand_totals['expenses']['count']; ?> expenses</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                            <div class="<?php echo $grand_totals['net_total'] >= 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                KES <?php echo number_format($grand_totals['net_total'], 2); ?>
                            </div>
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
    nav, button { display: none !important; }
    .shadow { box-shadow: none !important; }
    .bg-gray-50 { background-color: #f9fafb !important; print-color-adjust: exact; }
    .bg-white { background-color: white !important; print-color-adjust: exact; }
    .text-green-600 { color: #059669 !important; print-color-adjust: exact; }
    .text-red-600 { color: #dc2626 !important; print-color-adjust: exact; }
    .text-blue-600 { color: #2563eb !important; print-color-adjust: exact; }
</style>

<?php require_once '../../includes/footer.php'; ?>
