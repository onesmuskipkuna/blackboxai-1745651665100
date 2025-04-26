<?php
$page_title = 'Record New Payment';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

$error = '';
$success = '';

// Get active students for dropdown
$students_result = $conn->query("
    SELECT DISTINCT s.id, s.admission_number, s.first_name, s.last_name 
    FROM students s 
    JOIN invoices i ON s.id = i.student_id 
    WHERE s.status = 'active' 
    AND i.status != 'fully_paid'
    ORDER BY s.admission_number
");
$students = [];
while ($row = $students_result->fetchArray(SQLITE3_ASSOC)) {
    $students[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $student_id = (int)$_POST['student_id'];
    $invoice_id = (int)$_POST['invoice_id'];
    $payment_mode = sanitize($_POST['payment_mode']);
    $reference_number = sanitize($_POST['reference_number']);
    $remarks = sanitize($_POST['remarks']);
    $fee_items = isset($_POST['fee_items']) ? $_POST['fee_items'] : [];

    if (!$student_id || !$invoice_id || !$payment_mode || empty($fee_items)) {
        $error = 'All fields are required';
    } else {
        // Begin transaction
        $conn->exec('BEGIN');

        try {
            // Generate payment number (RCP/YEAR/SERIAL)
            $year = date('Y');
            $result = $conn->query("
                SELECT MAX(CAST(
                    substr(payment_number, 
                        length('RCP/$year/') + 1
                    ) AS INTEGER)
                ) as max_serial 
                FROM payments 
                WHERE payment_number LIKE 'RCP/$year/%'
            ");
            $row = $result->fetchArray(SQLITE3_ASSOC);
            $next_serial = ($row['max_serial'] ?? 0) + 1;
            $payment_number = "RCP/$year/" . str_pad($next_serial, 4, '0', STR_PAD_LEFT);

            // Calculate total payment amount
            $total_amount = 0;
            foreach ($fee_items as $item) {
                if (isset($item['selected']) && $item['selected'] == '1' && isset($item['amount'])) {
                    $total_amount += (float)$item['amount'];
                }
            }

            // Create payment record
            $stmt = $conn->prepare("INSERT INTO payments (invoice_id, payment_number, amount, payment_mode, reference_number, remarks) VALUES (:invoice_id, :payment_number, :amount, :payment_mode, :reference_number, :remarks)");
            $stmt->bindValue(':invoice_id', $invoice_id, SQLITE3_INTEGER);
            $stmt->bindValue(':payment_number', $payment_number, SQLITE3_TEXT);
            $stmt->bindValue(':amount', $total_amount, SQLITE3_FLOAT);
            $stmt->bindValue(':payment_mode', $payment_mode, SQLITE3_TEXT);
            $stmt->bindValue(':reference_number', $reference_number, SQLITE3_TEXT);
            $stmt->bindValue(':remarks', $remarks, SQLITE3_TEXT);
            $stmt->execute();

            $payment_id = $conn->lastInsertRowID();

            // Add payment items
            $stmt = $conn->prepare("INSERT INTO payment_items (payment_id, invoice_item_id, amount) VALUES (:payment_id, :invoice_item_id, :amount)");
            foreach ($fee_items as $item) {
                if (!isset($item['selected']) || $item['selected'] != '1') continue;
                
                $invoice_item_id = (int)$item['invoice_item_id'];
                $amount = (float)$item['amount'];
                
                if ($amount <= 0) continue;
                
                $stmt->bindValue(':payment_id', $payment_id, SQLITE3_INTEGER);
                $stmt->bindValue(':invoice_item_id', $invoice_item_id, SQLITE3_INTEGER);
                $stmt->bindValue(':amount', $amount, SQLITE3_FLOAT);
                $stmt->execute();
            }

            // Update invoice paid amount and balance
            $stmt = $conn->prepare("
                UPDATE invoices 
                SET paid_amount = paid_amount + :amount,
                    balance = total_amount - (paid_amount + :amount),
                    status = CASE 
                        WHEN (paid_amount + :amount) >= total_amount THEN 'fully_paid'
                        WHEN (paid_amount + :amount) > 0 THEN 'partially_paid'
                        ELSE 'due'
                    END
                WHERE id = :invoice_id
            ");
            $stmt->bindValue(':amount', $total_amount, SQLITE3_FLOAT);
            $stmt->bindValue(':invoice_id', $invoice_id, SQLITE3_INTEGER);
            $stmt->execute();

            // Commit transaction
            $conn->exec('COMMIT');

            // Redirect to print receipt
            header('Location: print.php?id=' . $payment_id);
            exit();

        } catch (Exception $e) {
            // Rollback on error
            $conn->exec('ROLLBACK');
            $error = 'Error recording payment: ' . $e->getMessage();
        }
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-900">Record New Payment</h1>
            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left mr-2"></i>Back to List
            </a>
        </div>

        <?php if ($error): ?>
            <div class="mt-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <div class="mt-6 bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
            <form method="POST" id="paymentForm" class="space-y-6">
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                    <div>
                        <label for="student_id" class="block text-sm font-medium text-gray-700">Student</label>
                        <select id="student_id" name="student_id" required
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['admission_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="invoiceContainer" class="hidden">
                        <label for="invoice_id" class="block text-sm font-medium text-gray-700">Invoice</label>
                        <select id="invoice_id" name="invoice_id" required
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="">Select Invoice</option>
                        </select>
                    </div>

                    <div>
                        <label for="payment_mode" class="block text-sm font-medium text-gray-700">Payment Mode</label>
                        <select id="payment_mode" name="payment_mode" required
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="">Select Payment Mode</option>
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>

                    <div>
                        <label for="reference_number" class="block text-sm font-medium text-gray-700">Reference Number</label>
                        <input type="text" name="reference_number" id="reference_number"
                               class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                               placeholder="Transaction/Receipt Number">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="remarks" class="block text-sm font-medium text-gray-700">Remarks</label>
                        <textarea name="remarks" id="remarks" rows="2"
                                  class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                  placeholder="Any additional notes"></textarea>
                    </div>
                </div>

                <!-- Fee Items Section -->
                <div class="mt-6">
                    <h3 class="text-lg font-medium text-gray-900">Fee Items</h3>
                    <div id="feeItemsContainer" class="mt-4 space-y-4">
                        <!-- Fee items will be loaded here dynamically -->
                    </div>

                    <div class="mt-4 flex justify-between items-center">
                        <div class="text-lg font-bold text-gray-900">
                            Total Amount: KES <span id="totalAmount">0.00</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-save mr-2"></i>Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('student_id').addEventListener('change', function() {
    const studentId = this.value;
    const invoiceContainer = document.getElementById('invoiceContainer');
    const invoiceSelect = document.getElementById('invoice_id');
    const feeItemsContainer = document.getElementById('feeItemsContainer');
    const totalAmount = document.getElementById('totalAmount');

    invoiceSelect.innerHTML = '<option value="">Select Invoice</option>';
    feeItemsContainer.innerHTML = '';
    totalAmount.textContent = '0.00';

    if (!studentId) {
        invoiceContainer.classList.add('hidden');
        return;
    }

    fetch(`get_invoices.php?student_id=${studentId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log('Invoices data:', data);
            if (data.length > 0) {
                invoiceContainer.classList.remove('hidden');
                data.forEach(invoice => {
                    const option = document.createElement('option');
                    option.value = invoice.id;
                    option.textContent = `${invoice.invoice_number} - Balance: KES ${parseFloat(invoice.balance).toFixed(2)}`;
                    invoiceSelect.appendChild(option);
                });
            } else {
                invoiceContainer.classList.add('hidden');
            }
        })
        .catch(error => {
            console.error('Error loading invoices:', error);
            alert('Error loading invoices: ' + error.message);
        });
});

document.getElementById('invoice_id').addEventListener('change', function() {
    const invoiceId = this.value;
    const feeItemsContainer = document.getElementById('feeItemsContainer');
    const totalAmount = document.getElementById('totalAmount');

    feeItemsContainer.innerHTML = '';
    totalAmount.textContent = '0.00';

    if (!invoiceId) return;

    fetch(`get_fee_items.php?invoice_id=${invoiceId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log('Fee items data:', data);
            data.forEach((item, index) => {
                const div = document.createElement('div');
                div.className = 'grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-4 border-b pb-4';
                div.innerHTML = `
                    <div class="sm:col-span-1 flex items-center">
                        <input type="checkbox" name="fee_items[${index}][selected]" value="1" class="mr-2 checkbox-select" checked onchange="toggleAmountInput(this)">
                        <label class="block text-sm font-medium text-gray-700">${item.fee_item}</label>
                        <input type="hidden" name="fee_items[${index}][invoice_item_id]" value="${item.id}">
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">Balance: KES ${parseFloat(item.balance).toFixed(2)}</label>
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">Amount to Pay</label>
                        <input type="number" name="fee_items[${index}][amount]" required min="0" max="${item.balance}" step="0.01"
                               class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md fee-amount"
                               onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>
                `;
                feeItemsContainer.appendChild(div);
            });
        })
        .catch(error => {
            console.error('Error loading fee items:', error);
            alert('Error loading fee items: ' + error.message);
        });
});

function toggleAmountInput(checkbox) {
    const amountInput = checkbox.closest('div').nextElementSibling.nextElementSibling.querySelector('input[type="number"]');
    if (checkbox.checked) {
        amountInput.disabled = false;
        amountInput.required = true;
    } else {
        amountInput.disabled = true;
        amountInput.required = false;
        amountInput.value = '';
        calculateTotal();
    }
}

function calculateTotal() {
    const amounts = document.getElementsByClassName('fee-amount');
    let total = 0;

    for (let amount of amounts) {
        if (!amount.disabled) {
            total += parseFloat(amount.value) || 0;
        }
    }

    document.getElementById('totalAmount').textContent = total.toFixed(2);
}

// Form validation
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    const feeItems = document.getElementsByClassName('fee-amount');
    if (feeItems.length === 0) {
        e.preventDefault();
        alert('Please select an invoice to load fee items');
        return;
    }

    let total = 0;
    let hasSelectedItems = false;

    for (let item of feeItems) {
        if (!item.disabled) {
            hasSelectedItems = true;
            if (!item.value || parseFloat(item.value) < 0 || parseFloat(item.value) > parseFloat(item.max)) {
                e.preventDefault();
                alert('Invalid payment amount. Amount must be between 0 and the remaining balance.');
                return;
            }
            total += parseFloat(item.value);
        }
    }

    if (!hasSelectedItems) {
        e.preventDefault();
        alert('Please select at least one fee item to pay');
        return;
    }

    if (total <= 0) {
        e.preventDefault();
        alert('Total payment amount must be greater than 0');
        return;
    }

    const paymentMode = document.getElementById('payment_mode').value;
    const reference = document.getElementById('reference_number').value;

    if ((paymentMode === 'mpesa' || paymentMode === 'bank') && !reference) {
        e.preventDefault();
        alert('Reference number is required for M-Pesa and Bank payments');
        return;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
