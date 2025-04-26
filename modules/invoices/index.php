<?php
$page_title = 'Invoices';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$class = isset($_GET['class']) ? sanitize($_GET['class']) : '';
$education_level = isset($_GET['education_level']) ? sanitize($_GET['education_level']) : '';
$student = isset($_GET['student']) ? sanitize($_GET['student']) : '';

// Fetch invoices with student info and filters
$query = "
    SELECT i.*, s.first_name, s.last_name, s.admission_number, s.class, s.education_level
    FROM invoices i
    JOIN students s ON i.student_id = s.id
    WHERE 1=1
";

if ($status) {
    $query .= " AND i.status = '$status'";
}
if ($class) {
    $query .= " AND s.class = '$class'";
}
if ($education_level) {
    $query .= " AND s.education_level = '$education_level'";
}
if ($student) {
    $query .= " AND (
        s.first_name LIKE '%$student%' OR 
        s.last_name LIKE '%$student%' OR 
        s.admission_number LIKE '%$student%' OR 
        s.phone_number LIKE '%$student%'
    )";
}

$query .= " ORDER BY i.created_at DESC";

$result = $conn->query($query);
$invoices = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $invoices[] = $row;
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-900">Invoices</h1>
            <a href="add.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-plus mr-2"></i>Add Invoice
            </a>
        </div>

        <form method="GET" class="mt-4 grid grid-cols-1 sm:grid-cols-5 gap-4 bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
            <div>
                <label for="education_level" class="block text-sm font-medium text-gray-700">Education Level</label>
                <select id="education_level" name="education_level" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                    <option value="">All</option>
                    <option value="primary" <?php echo $education_level === 'primary' ? 'selected' : ''; ?>>Primary</option>
                    <option value="junior_secondary" <?php echo $education_level === 'junior_secondary' ? 'selected' : ''; ?>>Junior Secondary</option>
                </select>
            </div>
            <div>
                <label for="class" class="block text-sm font-medium text-gray-700">Class</label>
                <select id="class" name="class" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md" disabled>
                    <option value="">Select Education Level First</option>
                </select>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                <select id="status" name="status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                    <option value="">All</option>
                    <option value="due" <?php echo $status === 'due' ? 'selected' : ''; ?>>Due</option>
                    <option value="partially_paid" <?php echo $status === 'partially_paid' ? 'selected' : ''; ?>>Partially Paid</option>
                    <option value="fully_paid" <?php echo $status === 'fully_paid' ? 'selected' : ''; ?>>Fully Paid</option>
                </select>
            </div>
            <div>
                <label for="student" class="block text-sm font-medium text-gray-700">Student</label>
                <input type="text" id="student" name="student" value="<?php echo htmlspecialchars($student); ?>" 
                       class="mt-1 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" 
                       placeholder="Search by name, admission no. or phone">
            </div>
            <div class="flex items-end">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-search mr-2"></i>Filter
                </button>
            </div>
        </form>

        <div class="mt-6 bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice Number</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admission Number</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Class</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Term</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Academic Year</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Fee Amount</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Paid</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($invoice['admission_number']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($invoice['class']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($invoice['term']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($invoice['academic_year']); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo number_format($invoice['total_amount'], 2); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium"><?php echo number_format($invoice['paid_amount'], 2); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium"><?php echo number_format($invoice['balance'], 2); ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php 
                                echo match($invoice['status']) {
                                    'fully_paid' => 'bg-green-100 text-green-800',
                                    'partially_paid' => 'bg-yellow-100 text-yellow-800',
                                    default => 'bg-red-100 text-red-800'
                                };
                                ?>">
                                <?php echo ucwords(str_replace('_', ' ', $invoice['status'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                            <div class="flex items-center justify-end space-x-2">
                                <a href="view.php?id=<?php echo $invoice['id']; ?>" 
                                   class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                <a href="print.php?id=<?php echo $invoice['id']; ?>" 
                                   class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    <i class="fas fa-print mr-1"></i> Print
                                </a>
                                <form method="POST" action="delete.php" class="inline-block" 
                                      onsubmit="return confirm('Are you sure you want to delete this invoice? This action cannot be undone.');">
                                    <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                                    <button type="submit" 
                                            class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        <i class="fas fa-trash mr-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</div>

<script>
// Function to update class dropdown
function updateClassDropdown(educationLevel) {
    const classSelect = document.getElementById('class');
    
    if (!educationLevel) {
        classSelect.innerHTML = '<option value="">Select Education Level First</option>';
        classSelect.disabled = true;
        return;
    }
    
    classSelect.disabled = true;
    classSelect.innerHTML = '<option value="">Loading...</option>';
    
    // Fetch classes for selected education level
    fetch(`get_classes.php?education_level=${encodeURIComponent(educationLevel)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(classes => {
            classSelect.innerHTML = '<option value="">All Classes</option>';
            classes.forEach(cls => {
                const option = document.createElement('option');
                option.value = cls;
                option.textContent = cls;
                if (cls === '<?php echo addslashes($class); ?>') {
                    option.selected = true;
                }
                classSelect.appendChild(option);
            });
            classSelect.disabled = false;
        })
        .catch(error => {
            console.error('Error loading classes:', error);
            classSelect.innerHTML = '<option value="">Error loading classes</option>';
            classSelect.disabled = true;
        });
}

// Add event listener for education level change
document.getElementById('education_level').addEventListener('change', function() {
    updateClassDropdown(this.value);
});

// Initialize class dropdown based on selected education level
const selectedEducationLevel = document.getElementById('education_level').value;
if (selectedEducationLevel) {
    updateClassDropdown(selectedEducationLevel);
}
</script>

<?php require_once '../../includes/footer.php'; ?>
