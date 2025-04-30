<?php
$page_title = 'Add New Student';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name'] ?? '');
    $guardian_name = sanitize($_POST['guardian_name'] ?? '');
    $phone_number = sanitize($_POST['phone_number'] ?? '');
    $education_level = sanitize($_POST['education_level']);
    $class = sanitize($_POST['class']);
    
    if (empty($first_name) || empty($education_level) || empty($class)) {
        $error = 'First Name, Education Level, and Class are required';
    } else {
        // Generate admission number (Year/Serial Number format)
        $year = date('Y');
        $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(admission_number, '/', -1) AS UNSIGNED)) as max_serial FROM students WHERE admission_number LIKE CONCAT(?, '/%')");
        $stmt->bind_param('s', $year);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $next_serial = ($row['max_serial'] ?? 0) + 1;
        $admission_number = $year . '/' . str_pad($next_serial, 4, '0', STR_PAD_LEFT);
        
        // Insert student
        $stmt = $conn->prepare("INSERT INTO students (admission_number, first_name, last_name, guardian_name, phone_number, education_level, class) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssss', $admission_number, $first_name, $last_name, $guardian_name, $phone_number, $education_level, $class);
        
        if ($stmt->execute()) {
            flashMessage('success', 'Student added successfully.');
            redirect('index.php');
        } else {
            $error = 'Error adding student: ' . $conn->error;
        }
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-900">Add New Student</h1>
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
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700">
                            First Name <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <input type="text" name="first_name" id="first_name" required
                                   class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>

                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700">
                            Last Name <span class="text-gray-400">(Optional)</span>
                        </label>
                        <div class="mt-1">
                            <input type="text" name="last_name" id="last_name"
                                   class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>

                    <div>
                        <label for="guardian_name" class="block text-sm font-medium text-gray-700">
                            Parent/Guardian Name <span class="text-gray-400">(Optional)</span>
                        </label>
                        <div class="mt-1">
                            <input type="text" name="guardian_name" id="guardian_name"
                                   class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>

                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-gray-700">
                            Phone Number <span class="text-gray-400">(Optional)</span>
                        </label>
                        <div class="mt-1">
                            <input type="tel" name="phone_number" id="phone_number"
                                   pattern="[0-9]{10,}"
                                   class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>

                    <div>
                        <label for="education_level" class="block text-sm font-medium text-gray-700">
                            Education Level <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <select id="education_level" name="education_level" required
                                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                <option value="">Select Level</option>
                                <option value="primary">Primary</option>
                                <option value="junior_secondary">Junior Secondary</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="class" class="block text-sm font-medium text-gray-700">
                            Class <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1">
                            <select id="class" name="class" required
                                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                <option value="">Select Class</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-save mr-2"></i>Save Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const primaryClasses = ['pg', 'pp1', 'pp2', 'grade1', 'grade2', 'grade3', 'grade4', 'grade5', 'grade6'];
const secondaryClasses = ['grade7', 'grade8', 'grade9', 'grade10'];

document.getElementById('education_level').addEventListener('change', function() {
    const classSelect = document.getElementById('class');
    const selectedLevel = this.value;
    
    // Clear existing options
    classSelect.innerHTML = '<option value="">Select Class</option>';
    
    if (selectedLevel === 'primary') {
        primaryClasses.forEach(className => {
            const option = document.createElement('option');
            option.value = className;
            option.textContent = className.charAt(0).toUpperCase() + className.slice(1);
            classSelect.appendChild(option);
        });
    } else if (selectedLevel === 'junior_secondary') {
        secondaryClasses.forEach(className => {
            const option = document.createElement('option');
            option.value = className;
            option.textContent = className.charAt(0).toUpperCase() + className.slice(1);
            classSelect.appendChild(option);
        });
    }
});

// Phone number validation (only if provided)
document.getElementById('phone_number').addEventListener('input', function() {
    if (this.value) {
        this.value = this.value.replace(/[^0-9]/g, '');
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
