<?php
$page_title = 'Import Students';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

$error = '';
$success = '';

// Define valid classes
$valid_primary = ['pg', 'pp1', 'pp2', 'grade1', 'grade2', 'grade3', 'grade4', 'grade5', 'grade6'];
$valid_secondary = ['grade7', 'grade8', 'grade9', 'grade10'];

// Function to normalize class name
function normalizeClassName($class) {
    $class = strtolower(trim($class));
    // Remove any spaces and convert common variations
    $class = str_replace(' ', '', $class);
    $class = str_replace(['grade', 'gr', 'g'], 'grade', $class);
    $class = str_replace(['playgroup', 'play group'], 'pg', $class);
    $class = str_replace(['pp one', 'ppone', 'pp-1'], 'pp1', $class);
    $class = str_replace(['pp two', 'pptwo', 'pp-2'], 'pp2', $class);
    
    // Convert numeric grades to full format
    if (is_numeric($class)) {
        $class = 'grade' . $class;
    }
    
    return $class;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    // Check file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['csv', 'xls', 'xlsx'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        $error = 'Invalid file type. Please upload a .csv, .xls, or .xlsx file';
    } else {
        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // Read CSV/Excel file
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle === false) {
                throw new Exception('Unable to open file');
            }
            
            // Skip header row
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new Exception('Empty file');
            }
            
            // Prepare insert statement
            $stmt = $conn->prepare("
                INSERT INTO students (
                    admission_number, first_name, last_name, guardian_name, 
                    phone_number, education_level, class, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $row_count = 0;
            $errors = [];
            $line_number = 2; // Start from line 2 (after header)
            
            // Process each row
            while (($row = fgetcsv($handle)) !== false) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    $line_number++;
                    continue;
                }
                
                // Clean up row values
                $row = array_map('trim', $row);
                
                // Validate required fields
                if (empty($row[0])) { // Admission Number
                    $errors[] = "Row $line_number: Admission Number is required";
                    $line_number++;
                    continue;
                }
                if (empty($row[1])) { // First Name
                    $errors[] = "Row $line_number: First Name is required";
                    $line_number++;
                    continue;
                }
                
                // Set optional fields
                $last_name = isset($row[2]) ? $row[2] : '';
                $guardian_name = isset($row[3]) ? $row[3] : '';
                $phone_number = isset($row[4]) ? $row[4] : '';
                
                // Validate and normalize education level
                $education_level = strtolower(trim($row[5] ?? ''));
                if (empty($education_level)) {
                    $errors[] = "Row $line_number: Education Level is required";
                    $line_number++;
                    continue;
                }
                
                // Normalize education level
                if (in_array($education_level, ['primary', 'p'])) {
                    $education_level = 'primary';
                } elseif (in_array($education_level, ['junior_secondary', 'junior secondary', 'js', 'secondary'])) {
                    $education_level = 'junior_secondary';
                } else {
                    $errors[] = "Row $line_number: Invalid education level. Must be 'primary' or 'junior_secondary'";
                    $line_number++;
                    continue;
                }
                
                // Validate and normalize class
                $class = isset($row[6]) ? normalizeClassName($row[6]) : '';
                if (empty($class)) {
                    $errors[] = "Row $line_number: Class is required";
                    $line_number++;
                    continue;
                }
                
                // Determine valid class based on education level
                $is_valid_class = false;
                if ($education_level === 'primary') {
                    $is_valid_class = in_array($class, $valid_primary);
                    if (!$is_valid_class) {
                        // Try to convert numeric grade to proper format
                        if (preg_match('/^grade?(\d+)$/', $class, $matches)) {
                            $grade_num = (int)$matches[1];
                            if ($grade_num >= 1 && $grade_num <= 6) {
                                $class = 'grade' . $grade_num;
                                $is_valid_class = true;
                            }
                        }
                    }
                } else {
                    $is_valid_class = in_array($class, $valid_secondary);
                    if (!$is_valid_class) {
                        // Try to convert numeric grade to proper format
                        if (preg_match('/^grade?(\d+)$/', $class, $matches)) {
                            $grade_num = (int)$matches[1];
                            if ($grade_num >= 7 && $grade_num <= 10) {
                                $class = 'grade' . $grade_num;
                                $is_valid_class = true;
                            }
                        }
                    }
                }
                
                if (!$is_valid_class) {
                    $errors[] = "Row $line_number: Invalid class '$class' for $education_level level";
                    $line_number++;
                    continue;
                }
                
                // Set default status if not provided or invalid
                $status = isset($row[7]) && !empty($row[7]) ? strtolower($row[7]) : 'active';
                if (!in_array($status, ['active', 'inactive', 'graduated', 'transferred'])) {
                    $status = 'active';
                }
                
                // Check if admission number already exists
                $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE admission_number = ?");
                $check_stmt->bind_param('s', $row[0]);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $exists = $check_result->fetch_assoc()['count'] > 0;
                
                if ($exists) {
                    $errors[] = "Row $line_number: Admission number {$row[0]} already exists";
                    $line_number++;
                    continue;
                }
                
                // Insert student
                $stmt->bind_param('ssssssss', 
                    $row[0],        // admission_number
                    $row[1],        // first_name
                    $last_name,     // last_name
                    $guardian_name, // guardian_name
                    $phone_number,  // phone_number
                    $education_level,
                    $class,
                    $status
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Error inserting row $line_number: " . $stmt->error);
                }
                
                $row_count++;
                $line_number++;
            }
            
            fclose($handle);
            
            if (!empty($errors)) {
                throw new Exception("Import completed with errors:\n" . implode("\n", $errors));
            }
            
            // Commit transaction
            $conn->commit();
            
            $success = "Successfully imported $row_count students";
            
        } catch (Exception $e) {
            // Rollback on error
            if (isset($conn)) {
                $conn->rollback();
            }
            $error = $e->getMessage();
        }
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-900">Import Students</h1>
            <a href="index.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-arrow-left mr-2"></i>Back to List
            </a>
        </div>

        <?php if ($error): ?>
            <div class="mt-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo nl2br(htmlspecialchars($error)); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mt-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <div class="mt-6 bg-white shadow px-4 py-5 sm:rounded-lg sm:p-6">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-1">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Import Students from Excel</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Upload an Excel file (.xls, .xlsx) or CSV file containing student data.
                        <br><br>
                        Required columns:
                        <ul class="list-disc pl-5 mt-2">
                            <li>Admission Number <span class="text-red-500">*</span></li>
                            <li>First Name <span class="text-red-500">*</span></li>
                            <li>Last Name <span class="text-gray-400">(Optional)</span></li>
                            <li>Guardian Name <span class="text-gray-400">(Optional)</span></li>
                            <li>Phone Number <span class="text-gray-400">(Optional)</span></li>
                            <li>Education Level <span class="text-red-500">*</span></li>
                            <li>Class <span class="text-red-500">*</span></li>
                            <li>Status <span class="text-gray-400">(Optional, defaults to 'active')</span></li>
                        </ul>
                        <br>
                        Valid Classes:<br>
                        Primary: pg, pp1, pp2, grade1-grade6<br>
                        Junior Secondary: grade7-grade10
                    </p>
                    <div class="mt-4">
                        <a href="template.php" class="text-blue-600 hover:text-blue-900">
                            <i class="fas fa-download mr-1"></i>Download Template
                        </a>
                    </div>
                </div>
                <div class="mt-5 md:mt-0 md:col-span-2">
                    <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST" enctype="multipart/form-data">
                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6">
                                <label for="excel_file" class="block text-sm font-medium text-gray-700">Excel File</label>
                                <input type="file" name="excel_file" id="excel_file" accept=".xls,.xlsx,.csv" required
                                       class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div class="mt-5">
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-upload mr-2"></i>Upload and Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
