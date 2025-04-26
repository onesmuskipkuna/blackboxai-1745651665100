<?php
$page_title = 'Create New Invoice';
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
    SELECT id, admission_number, first_name, last_name, phone_number, class, education_level 
    FROM students 
    WHERE status = 'active' 
    ORDER BY admission_number
");
$students = [];
while ($row = $students_result->fetch_assoc()) {
    $students[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $student_id = (int)$_POST['student_id'];
    $term = (int)$_POST['term'];
    $academic_year = sanitize($_POST['academic_year']);
    $due_date = sanitize($_POST['due_date']);
    $fee_items = isset($_POST['fee_items']) ? $_POST['fee_items'] : [];
    
    if (!$student_id || !$term || !$academic_year || !$due_date || empty($fee_items)) {
        $error = 'All fields are required';
    } else {
        // Debug log
        error_log("Fee items received: " . print_r($fee_items, true));
        
        // Filter only selected fee items
        $selected_items = [];
        foreach ($fee_items as $fee_id => $item) {
            if (isset($item['selected']) && $item['selected'] == '1' && 
                isset($item['amount']) && floatval($item['amount']) > 0) {
                $selected_items[$fee_id] = $item;
                error_log("Selected item $fee_id: " . print_r($item, true));
            }
        }

        if (empty($selected_items)) {
            $error = 'Please select at least one fee item';
            error_log("No items were selected or all selected items had zero amounts");
        } else {
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Generate invoice number (INV/YEAR/SERIAL)
                $year = date('Y');
                $next_serial = 1;
                while (true) {
                    $invoice_number = "INV/$year/" . str_pad($next_serial, 4, '0', STR_PAD_LEFT);
                    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM invoices WHERE invoice_number = ?");
                    $check_stmt->bind_param('s', $invoice_number);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $check_row = $check_result->fetch_assoc();
                    if ($check_row['count'] == 0) {
                        break;
                    }
                    $next_serial++;
                }
                
                // Calculate totals from selected items only
                $total_amount = 0;
                foreach ($selected_items as $item) {
                    $total_amount += (float)$item['amount'];
                }
                
                // Check for previous unpaid balance for the student
                $balance_carry_forward = 0;
                $balance_items = [];
                $prev_invoices_stmt = $conn->prepare("
                    SELECT balance, id FROM invoices 
                    WHERE student_id = ? 
                    AND (academic_year < ? OR (academic_year = ? AND term < ?))
                    AND balance > 0
                ");
                $prev_invoices_stmt->bind_param('issi', $student_id, $academic_year, $academic_year, $term);
                $prev_invoices_stmt->execute();
                $prev_invoices_result = $prev_invoices_stmt->get_result();
                while ($row = $prev_invoices_result->fetch_assoc()) {
                    $balance_carry_forward += $row['balance'];
                    $balance_items[] = $row['id'];
                }
                
                if ($balance_carry_forward > 0) {
                    // Add balance carry forward to total
                    $total_amount += $balance_carry_forward;
                }
                
                // Create invoice
                $stmt = $conn->prepare("INSERT INTO invoices (student_id, invoice_number, total_amount, balance, term, academic_year, due_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isddiss', $student_id, $invoice_number, $total_amount, $total_amount, $term, $academic_year, $due_date);
                $stmt->execute();
                
                $invoice_id = $conn->insert_id;
                
                // Add selected invoice items
                $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, fee_structure_id, amount) VALUES (?, ?, ?)");
                foreach ($selected_items as $item) {
                    $fee_id = (int)$item['fee_id'];
                    $amount = (float)$item['amount'];
                    $stmt->bind_param('iid', $invoice_id, $fee_id, $amount);
                    $stmt->execute();
                }
                
                // Add balance carry forward as a special fee item if any
                if ($balance_carry_forward > 0) {
                    $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, fee_structure_id, amount) VALUES (?, NULL, ?)");
                    $stmt->bind_param('id', $invoice_id, $balance_carry_forward);
                    $stmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                flashMessage('success', 'Invoice created successfully');
                redirect('index.php');
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $error = 'Error creating invoice: ' . $e->getMessage();
            }
        }
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-900">Create New Invoice</h1>
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
            <form method="POST" id="invoiceForm" class="space-y-6">
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                    <div>
                        <label for="student_search" class="block text-sm font-medium text-gray-700">Search Student</label>
                        <div class="mt-1 relative">
                            <input type="text" id="student_search" 
                                   class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                                   placeholder="Search by name, admission no. or phone">
                            <select id="student_id" name="student_id" required
                                    class="hidden">
                                <option value="">Select Student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" 
                                            data-class="<?php echo htmlspecialchars($student['class']); ?>"
                                            data-level="<?php echo htmlspecialchars($student['education_level']); ?>"
                                            data-admission="<?php echo htmlspecialchars($student['admission_number']); ?>"
                                            data-name="<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>"
                                            data-phone="<?php echo htmlspecialchars($student['phone_number']); ?>">
                                        <?php echo htmlspecialchars($student['admission_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['phone_number'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="student_results" class="absolute z-10 w-full bg-white shadow-lg rounded-md mt-1 max-h-60 overflow-auto hidden">
                                <!-- Search results will be populated here -->
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="term" class="block text-sm font-medium text-gray-700">Term</label>
                        <select id="term" name="term" required
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                            <option value="">Select Term</option>
                            <option value="1">Term 1</option>
                            <option value="2">Term 2</option>
                            <option value="3">Term 3</option>
                        </select>
                    </div>

                    <div>
                        <label for="academic_year" class="block text-sm font-medium text-gray-700">Academic Year</label>
                        <input type="number" name="academic_year" id="academic_year" required
                               min="2000" max="2099" step="1" value="<?php echo date('Y'); ?>"
                               class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                    </div>

                    <div>
                        <label for="due_date" class="block text-sm font-medium text-gray-700">Due Date</label>
                        <input type="date" name="due_date" id="due_date" required
                               min="<?php echo date('Y-m-d'); ?>"
                               class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
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
                        <i class="fas fa-save mr-2"></i>Create Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Student search functionality
const studentSearch = document.getElementById('student_search');
const studentSelect = document.getElementById('student_id');
const studentResults = document.getElementById('student_results');
let selectedStudent = null;

function filterStudents(searchText) {
    const options = Array.from(studentSelect.options).slice(1); // Skip the first "Select Student" option
    const results = options.filter(option => {
        const admission = option.getAttribute('data-admission').toLowerCase();
        const name = option.getAttribute('data-name').toLowerCase();
        const phone = option.getAttribute('data-phone').toLowerCase();
        const search = searchText.toLowerCase();
        
        return admission.includes(search) || 
               name.includes(search) || 
               phone.includes(search);
    });

    studentResults.innerHTML = '';
    if (results.length > 0) {
        results.forEach(option => {
            const div = document.createElement('div');
            div.className = 'px-4 py-2 hover:bg-gray-100 cursor-pointer';
            div.innerHTML = `
                <div class="text-sm font-medium text-gray-900">${option.getAttribute('data-name')}</div>
                <div class="text-sm text-gray-500">
                    ${option.getAttribute('data-admission')} | ${option.getAttribute('data-phone')}
                </div>
            `;
            div.addEventListener('click', () => {
                studentSelect.value = option.value;
                studentSearch.value = option.getAttribute('data-name');
                selectedStudent = option;
                studentResults.classList.add('hidden');
                loadFeeStructure();
            });
            studentResults.appendChild(div);
        });
        studentResults.classList.remove('hidden');
    } else {
        studentResults.classList.add('hidden');
    }
}

studentSearch.addEventListener('input', (e) => {
    if (e.target.value) {
        filterStudents(e.target.value);
    } else {
        studentResults.classList.add('hidden');
        studentSelect.value = '';
        selectedStudent = null;
    }
});

studentSearch.addEventListener('focus', () => {
    if (studentSearch.value) {
        filterStudents(studentSearch.value);
    }
});

// Close results when clicking outside
document.addEventListener('click', (e) => {
    if (!studentSearch.contains(e.target) && !studentResults.contains(e.target)) {
        studentResults.classList.add('hidden');
    }
});

document.getElementById('term').addEventListener('change', loadFeeStructure);
document.getElementById('academic_year').addEventListener('change', loadFeeStructure);

function loadFeeStructure() {
    const studentId = document.getElementById('student_id').value;
    const term = document.getElementById('term').value;
    const academicYear = document.getElementById('academic_year').value;
    
    if (!studentId || !term || !academicYear) {
        return;
    }
    
    const selectedOption = document.getElementById('student_id').selectedOptions[0];
    const studentClass = selectedOption.dataset.class;
    const educationLevel = selectedOption.dataset.level;
    
    // AJAX request to get fee structure
    fetch(`get_fee_structure.php?class=${studentClass}&education_level=${educationLevel}&term=${term}&academic_year=${academicYear}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('feeItemsContainer');
            container.innerHTML = '';
            
            data.forEach(item => {
                const div = document.createElement('div');
                div.className = 'grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-4 border-b pb-4';
                div.innerHTML = `
                    <div class="sm:col-span-1 flex items-center">
                        <input type="checkbox" name="fee_items[${item.id}][selected]" value="1" 
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded fee-item-checkbox"
                               onchange="toggleFeeItem(this)">
                        <label class="ml-2 block text-sm font-medium text-gray-700">${item.fee_item}</label>
                        <input type="hidden" name="fee_items[${item.id}][fee_id]" value="${item.id}">
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">Amount (KES)</label>
                        <input type="number" name="fee_items[${item.id}][amount]" value="${item.amount}" 
                               data-default-amount="${item.amount}"
                               class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md fee-amount"
                               onchange="calculateTotal()" disabled>
                    </div>
                `;
                container.appendChild(div);
            });
        })
        .catch(error => console.error('Error:', error));
}

function toggleFeeItem(checkbox) {
    const container = checkbox.closest('.grid');
    const amountInput = container.querySelector('.fee-amount');
    const defaultAmount = amountInput.getAttribute('data-default-amount');
    
    amountInput.disabled = !checkbox.checked;
    
    if (!checkbox.checked) {
        amountInput.value = '';
    } else {
        // Reset to default amount from fee structure
        amountInput.value = defaultAmount || '0.00';
    }
    
    calculateTotal();
    
    // Debug log
    console.log('Toggle fee item:', {
        checked: checkbox.checked,
        defaultAmount: defaultAmount,
        currentValue: amountInput.value,
        feeId: container.querySelector('input[name*="[fee_id]"]').value
    });
}

function calculateTotal() {
    const checkboxes = document.getElementsByClassName('fee-item-checkbox');
    let total = 0;
    let selectedItems = [];
    
    for (let checkbox of checkboxes) {
        if (checkbox.checked) {
            const container = checkbox.closest('.grid');
            const amountInput = container.querySelector('.fee-amount');
            const amount = parseFloat(amountInput.value) || 0;
            const feeId = container.querySelector('input[name*="[fee_id]"]').value;
            
            total += amount;
            selectedItems.push({
                feeId: feeId,
                amount: amount
            });
        }
    }
    
    document.getElementById('totalAmount').textContent = total.toFixed(2);
    
    // Debug log
    console.log('Calculate total:', {
        selectedItems: selectedItems,
        total: total
    });
}

// Form validation
document.getElementById('invoiceForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Prevent default submission first

    const selectedItems = document.querySelectorAll('.fee-item-checkbox:checked');
    if (selectedItems.length === 0) {
        alert('Please select at least one fee item');
        return;
    }
    
    let total = 0;
    let hasInvalidAmount = false;
    let selectedData = [];
    
    selectedItems.forEach(checkbox => {
        const container = checkbox.closest('.grid');
        const amountInput = container.querySelector('.fee-amount');
        const amount = parseFloat(amountInput.value);
        const feeId = container.querySelector('input[name*="[fee_id]"]').value;
        
        if (!amount || amount <= 0) {
            hasInvalidAmount = true;
        }
        
        selectedData.push({
            feeId: feeId,
            amount: amount
        });
        
        total += amount || 0;
    });
    
    if (hasInvalidAmount) {
        alert('All selected fee items must have amounts greater than 0');
        return;
    }
    
    if (total <= 0) {
        alert('Total amount must be greater than 0');
        return;
    }

    console.log('Selected items:', selectedData);
    console.log('Total amount:', total);
    
    // If all validation passes, submit the form
    this.submit();
});
</script>

<?php require_once '../../includes/footer.php'; ?>
