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
    SELECT DISTINCT s.id, s.admission_number, s.first_name, s.last_name, s.phone_number 
    FROM students s 
    JOIN invoices i ON s.id = i.student_id 
    WHERE s.status = 'active' 
    AND i.status != 'fully_paid'
    ORDER BY s.admission_number
");
$students = [];
while ($row = $students_result->fetch_assoc()) {
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
        $error = 'Student, invoice, and payment mode are required';
    } else {
        // Validate fee items before processing
        $valid_items = false;
        $total_amount = 0;
        
        foreach ($fee_items as $item) {
            if (isset($item['selected']) && $item['selected'] == '1') {
                $valid_items = true;
                $amount = isset($item['amount']) && $item['amount'] !== '' ? (float)$item['amount'] : 0;
                if ($amount < 0) {
                    $error = 'Amount cannot be negative';
                    break;
                }
                $total_amount += $amount;
            }
        }
        
        if (!$valid_items) {
            $error = 'Please select at least one fee item to pay';
        } elseif ($total_amount <= 0) {
            $error = 'Total payment amount must be greater than 0';
        }
    }

    if (!$error) {
        // Begin transaction
        $conn->begin_transaction();

        try {
            // Generate payment number (RCP/YEAR/SERIAL)
            $year = date('Y');
            $stmt = $conn->prepare("
                SELECT MAX(CAST(SUBSTRING(payment_number, LENGTH('RCP/$year/') + 1) AS UNSIGNED)) as max_serial 
                FROM payments 
                WHERE payment_number LIKE CONCAT('RCP/', ?, '/%')
            ");
            $stmt->bind_param('s', $year);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $next_serial = ($row['max_serial'] ?? 0) + 1;
            $payment_number = "RCP/$year/" . str_pad($next_serial, 4, '0', STR_PAD_LEFT);

            // Create payment record
            $stmt = $conn->prepare("INSERT INTO payments (invoice_id, payment_number, amount, payment_mode, reference_number, remarks) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isdsss', $invoice_id, $payment_number, $total_amount, $payment_mode, $reference_number, $remarks);
            $stmt->execute();

            $payment_id = $conn->insert_id;

            // Add payment items and update invoice item balances
            $stmt = $conn->prepare("INSERT INTO payment_items (payment_id, invoice_item_id, amount) VALUES (?, ?, ?)");
            
            // Get current invoice total paid amount
            $stmt_invoice = $conn->prepare("
                SELECT total_amount, paid_amount 
                FROM invoices 
                WHERE id = ?
            ");
            $stmt_invoice->bind_param('i', $invoice_id);
            $stmt_invoice->execute();
            $result = $stmt_invoice->get_result();
            $invoice_data = $result->fetch_assoc();
            $current_paid = $invoice_data['paid_amount'];
            $total_invoice_amount = $invoice_data['total_amount'];

            // Process each fee item
            foreach ($fee_items as $item) {
                if (!isset($item['selected']) || $item['selected'] != '1') continue;
                
                $invoice_item_id = (int)$item['invoice_item_id'];
                $amount = (float)$item['amount'];
                
                if ($amount <= 0) continue;
                
                // Insert payment item
                $stmt->bind_param('iid', $payment_id, $invoice_item_id, $amount);
                $stmt->execute();
                
                // Update current paid amount
                $current_paid += $amount;
            }

            // Calculate new balance and status
            $new_balance = $total_invoice_amount - $current_paid;
            $new_status = 'due';
            if ($current_paid >= $total_invoice_amount) {
                $new_status = 'fully_paid';
            } elseif ($current_paid > 0) {
                $new_status = 'partially_paid';
            }

            // Update invoice paid amount and balance
            $stmt = $conn->prepare("
                UPDATE invoices 
                SET paid_amount = ?,
                    balance = ?,
                    status = ?
                WHERE id = ?
            ");
            $stmt->bind_param('ddsi', $current_paid, $new_balance, $new_status, $invoice_id);
            $stmt->execute();

            // Commit transaction
            $conn->commit();

            // Redirect to print receipt
            header('Location: print.php?id=' . $payment_id);
            exit();

        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
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
    <label for="student_search" class="block text-sm font-medium text-gray-700">Student</label>
    <input type="text" id="student_search" name="student_search" placeholder="Search by phone number or name" autocomplete="off"
           class="mt-1 block w-full pl-3 pr-10 py-2 text-base border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" />
    <input type="hidden" id="student_id" name="student_id" required />
    <div id="student_search_results" class="border border-gray-300 rounded-md mt-1 max-h-48 overflow-y-auto hidden bg-white z-10 absolute w-full"></div>
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
                            <option value="waiver">Waiver</option>
                        </select>
                    </div>

                    <div>
                        <label for="reference_number" class="block text-sm font-medium text-gray-700">Reference Number</label>
                        <input type="text" name="reference_number" id="reference_number"
                               class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                               placeholder="Transaction/Receipt Number (Optional)">
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
document.addEventListener('DOMContentLoaded', function() {
    const studentSearchInput = document.getElementById('student_search');
    const studentSearchResults = document.getElementById('student_search_results');
    const studentIdInput = document.getElementById('student_id');
    const invoiceContainer = document.getElementById('invoiceContainer');
    const invoiceSelect = document.getElementById('invoice_id');
    const feeItemsContainer = document.getElementById('feeItemsContainer');
    const totalAmount = document.getElementById('totalAmount');

    let debounceTimeout = null;

    studentSearchInput.addEventListener('input', function() {
        const query = this.value.trim();

        studentIdInput.value = '';
        invoiceContainer.classList.add('hidden');
        invoiceSelect.innerHTML = '<option value="">Select Invoice</option>';
        feeItemsContainer.innerHTML = '';
        totalAmount.textContent = '0.00';

        if (debounceTimeout) {
            clearTimeout(debounceTimeout);
        }

        if (query.length < 2) {
            studentSearchResults.classList.add('hidden');
            studentSearchResults.innerHTML = '';
            return;
        }

        debounceTimeout = setTimeout(() => {
            fetch(`search_students.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    studentSearchResults.innerHTML = '';
                    if (data.results.length > 0) {
                        data.results.forEach(student => {
                            const div = document.createElement('div');
                            div.className = 'px-3 py-2 cursor-pointer hover:bg-gray-100';
                            div.textContent = student.text;
                            div.dataset.studentId = student.id;
                            div.addEventListener('click', () => {
                                studentSearchInput.value = div.textContent;
                                studentIdInput.value = div.dataset.studentId;
                                studentSearchResults.classList.add('hidden');
                                loadInvoices(div.dataset.studentId);
                            });
                            studentSearchResults.appendChild(div);
                        });
                        studentSearchResults.classList.remove('hidden');
                    } else {
                        studentSearchResults.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error searching students:', error);
                    studentSearchResults.classList.add('hidden');
                });
        }, 300);
    });

    document.addEventListener('click', function(event) {
        if (!studentSearchResults.contains(event.target) && event.target !== studentSearchInput) {
            studentSearchResults.classList.add('hidden');
        }
    });

    function loadInvoices(studentId) {
        invoiceSelect.innerHTML = '<option value="">Select Invoice</option>';
        feeItemsContainer.innerHTML = '';
        totalAmount.textContent = '0.00';
        invoiceContainer.classList.add('hidden');

        if (!studentId) return;

        fetch(`get_invoices.php?student_id=${studentId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
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
    }

    document.getElementById('invoice_id').addEventListener('change', function() {
        const invoiceId = this.value;
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
                            <label class="block text-sm font-medium text-gray-700">Original Amount: KES ${parseFloat(item.original_amount).toFixed(2)}</label>
                        </div>
                        <div class="sm:col-span-1">
                            <label class="block text-sm font-medium text-gray-700">Paid: KES ${parseFloat(item.paid_amount).toFixed(2)}</label>
                        </div>
                        <div class="sm:col-span-1">
                            <label class="block text-sm font-medium text-gray-700">Amount to Pay</label>
                            <input type="number" name="fee_items[${index}][amount]" min="0" step="0.01"
                                   class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md fee-amount"
                                   onchange="calculateTotal()" onkeyup="calculateTotal()" 
                                   oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\\..*)\\./g, '$1');"
                                   placeholder="Enter amount (optional)">
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
        const container = checkbox.closest('.grid');
        const amountInput = container.querySelector('.fee-amount');
        if (checkbox.checked) {
            amountInput.disabled = false;
        } else {
            amountInput.disabled = true;
            amountInput.value = '';
            calculateTotal();
        }
    }

    function calculateTotal() {
        const amounts = document.getElementsByClassName('fee-amount');
        let total = 0;

        for (let amount of amounts) {
            if (!amount.disabled) {
                const value = parseFloat(amount.value);
                
                // Only validate for negative values
                if (!isNaN(value)) {
                    if (value < 0) {
                        amount.classList.add('border-red-500');
                        const warningDiv = amount.parentElement.querySelector('.amount-warning');
                        if (!warningDiv) {
                            const warning = document.createElement('div');
                            warning.className = 'text-red-500 text-xs mt-1 amount-warning';
                            warning.textContent = 'Amount cannot be negative';
                            amount.parentElement.appendChild(warning);
                        }
                    } else {
                        amount.classList.remove('border-red-500');
                        const warningDiv = amount.parentElement.querySelector('.amount-warning');
                        if (warningDiv) {
                            warningDiv.remove();
                        }
                        total += value;
                    }
                } else {
                    // Remove any warning for empty values since they're now optional
                    amount.classList.remove('border-red-500');
                    const warningDiv = amount.parentElement.querySelector('.amount-warning');
                    if (warningDiv) {
                        warningDiv.remove();
                    }
                }
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
        let hasInvalidAmount = false;

        for (let item of feeItems) {
            if (!item.disabled) {
                hasSelectedItems = true;
                const amount = parseFloat(item.value);
                
                // Only validate if amount is entered and check for negative values
                if (!isNaN(amount)) {
                    if (amount < 0) {
                        hasInvalidAmount = true;
                        break;
                    }
                    total += amount;
                }
            }
        }

        if (!hasSelectedItems) {
            e.preventDefault();
            alert('Please select at least one fee item to pay');
            return;
        }

        if (hasInvalidAmount) {
            e.preventDefault();
            alert('Please enter valid payment amounts. Amount cannot be negative.');
            return;
        }

        if (total === 0) {
            e.preventDefault();
            alert('Total payment amount cannot be zero. Please enter at least one amount.');
            return;
        }
    });
});
</script>

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
                        <label class="block text-sm font-medium text-gray-700">Original Amount: KES ${parseFloat(item.original_amount).toFixed(2)}</label>
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">Paid: KES ${parseFloat(item.paid_amount).toFixed(2)}</label>
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">Amount to Pay</label>
                        <input type="number" name="fee_items[${index}][amount]" min="0" step="0.01"
                               class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md fee-amount"
                               onchange="calculateTotal()" onkeyup="calculateTotal()" 
                               oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\\..*)\\./g, '$1');"
                               placeholder="Enter amount (optional)">
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
    const container = checkbox.closest('.grid');
    const amountInput = container.querySelector('.fee-amount');
    if (checkbox.checked) {
        amountInput.disabled = false;
    } else {
        amountInput.disabled = true;
        amountInput.value = '';
        calculateTotal();
    }
}

function calculateTotal() {
    const amounts = document.getElementsByClassName('fee-amount');
    let total = 0;

    for (let amount of amounts) {
        if (!amount.disabled) {
            const value = parseFloat(amount.value);
            
            // Only validate for negative values
            if (!isNaN(value)) {
                if (value < 0) {
                    amount.classList.add('border-red-500');
                    const warningDiv = amount.parentElement.querySelector('.amount-warning');
                    if (!warningDiv) {
                        const warning = document.createElement('div');
                        warning.className = 'text-red-500 text-xs mt-1 amount-warning';
                        warning.textContent = 'Amount cannot be negative';
                        amount.parentElement.appendChild(warning);
                    }
                } else {
                    amount.classList.remove('border-red-500');
                    const warningDiv = amount.parentElement.querySelector('.amount-warning');
                    if (warningDiv) {
                        warningDiv.remove();
                    }
                    total += value;
                }
            } else {
                // Remove any warning for empty values since they're now optional
                amount.classList.remove('border-red-500');
                const warningDiv = amount.parentElement.querySelector('.amount-warning');
                if (warningDiv) {
                    warningDiv.remove();
                }
            }
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
    let hasInvalidAmount = false;

    for (let item of feeItems) {
        if (!item.disabled) {
            hasSelectedItems = true;
            const amount = parseFloat(item.value);
            
            // Only validate if amount is entered and check for negative values
            if (!isNaN(amount)) {
                if (amount < 0) {
                    hasInvalidAmount = true;
                    break;
                }
                total += amount;
            }
        }
    }

    if (!hasSelectedItems) {
        e.preventDefault();
        alert('Please select at least one fee item to pay');
        return;
    }

    if (hasInvalidAmount) {
        e.preventDefault();
        alert('Please enter valid payment amounts. Amount cannot be negative.');
        return;
    }

    if (total === 0) {
        e.preventDefault();
        alert('Total payment amount cannot be zero. Please enter at least one amount.');
        return;
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
