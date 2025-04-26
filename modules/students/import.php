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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    // Validate file
    $allowed_types = ['application/vnd.ms-excel', 'text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if (!in_array($file['type'], $allowed_types)) {
        $error = 'Invalid file type. Please upload an Excel file (.xls, .xlsx, or .csv)';
    } else {
        try {
            // Begin transaction
            $conn->exec('BEGIN');
            
            // Read CSV/Excel file
            $handle = fopen($file['tmp_name'], 'r');
            
            // Skip header row
            $header = fgetcsv($handle);
            
            // Prepare insert statement
            $stmt = $conn->prepare("
                INSERT INTO students (
                    admission_number, first_name, last_name, guardian_name, 
                    phone_number, education_level, class, status
                ) VALUES (
                    :admission_number, :first_name, :last_name, :guardian_name,
                    :phone_number, :education_level, :class, :status
                )
            ");
            
            $row_count = 0;
            $errors = [];
            
            // Process each row
            while (($row = fgetcsv($handle)) !== false) {
                // Validate row data
                if (count($row) < 8) {
                    $errors[] = "Row " . ($row_count + 2) . ": Insufficient columns";
                    continue;
                }
                
                // Check if admission number already exists
                $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE admission_number = :admission_number");
                $check_stmt->bindValue(':admission_number', $row[0], SQLITE3_TEXT);
                $result = $check_stmt->execute();
                $exists = $result->fetchArray(SQLITE3_ASSOC)['count'] > 0;
                
                if ($exists) {
                    $errors[] = "Row " . ($row_count + 2) . ": Admission number {$row[0]} already exists";
                    continue;
                }
                
                // Validate education level
                if (!in_array($row[5], ['primary', 'junior_secondary'])) {
                    $errors[] = "Row " . ($row_count + 2) . ": Invalid education level. Must be 'primary' or 'junior_secondary'";
                    continue;
                }
                
                // Validate class based on education level
                $valid_primary = ['pg', 'pp1', 'pp2', 'grade1', 'grade2', 'grade3', 'grade4', 'grade5', 'grade6'];
                $valid_secondary = ['grade7', 'grade8', 'grade9', 'grade10'];
                
                if ($row[5] === 'primary' && !in_array($row[6], $valid_primary)) {
                    $errors[] = "Row " . ($row_count + 2) . ": Invalid class for primary level";
                    continue;
                }
                
                if ($row[5] === 'junior_secondary' && !in_array($row[6], $valid_secondary)) {
                    $errors[] = "Row " . ($row_count + 2) . ": Invalid class for junior secondary level";
                    continue;
                }
                
                // Validate status
                if (!in_array($row[7], ['active', 'inactive', 'graduated', 'transferred'])) {
                    $errors[] = "Row " . ($row_count + 2) . ": Invalid status";
                    continue;
                }
                
                // Insert student
                $stmt->bindValue(':admission_number', $row[0], SQLITE3_TEXT);
                $stmt->bindValue(':first_name', $row[1], SQLITE3_TEXT);
                $stmt->bindValue(':last_name', $row[2], SQLITE3_TEXT);
                $stmt->bindValue(':guardian_name', $row[3], SQLITE3_TEXT);
                $stmt->bindValue(':phone_number', $row[4], SQLITE3_TEXT);
                $stmt->bindValue(':education_level', $row[5], SQLITE3_TEXT);
                $stmt->bindValue(':class', $row[6], SQLITE3_TEXT);
                $stmt->bindValue(':status', $row[7], SQLITE3_TEXT);
                
                $stmt->execute();
                $row_count++;
            }
            
            fclose($handle);
            
            if (!empty($errors)) {
                throw new Exception("Import completed with errors:\n" . implode("\n", $errors));
            }
            
            // Commit transaction
            $conn->exec('COMMIT');
            
            $success = "Successfully imported $row_count students";
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->exec('ROLLBACK');
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
                            <li>Admission Number</li>
                            <li>First Name</li>
                            <li>Last Name</li>
                            <li>Guardian Name</li>
                            <li>Phone Number</li>
                            <li>Education Level (primary/junior_secondary)</li>
                            <li>Class</li>
                            <li>Status (active/inactive/graduated/transferred)</li>
                        </ul>
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
