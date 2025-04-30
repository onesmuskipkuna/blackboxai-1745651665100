<?php
$page_title = 'Bulk Promote Students';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

$error = '';
$success = '';

// Define class progression
$class_progression = [
    'pg' => 'pp1',
    'pp1' => 'pp2',
    'pp2' => 'grade1',
    'grade1' => 'grade2',
    'grade2' => 'grade3',
    'grade3' => 'grade4',
    'grade4' => 'grade5',
    'grade5' => 'grade6',
    'grade6' => 'grade7', // Transition to junior secondary
    'grade7' => 'grade8',
    'grade8' => 'grade9',
    'grade9' => 'grade10'
];

// Handle bulk promotion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote_students'])) {
    $student_ids = isset($_POST['students']) ? $_POST['students'] : [];
    
    if (empty($student_ids)) {
        $error = 'Please select at least one student to promote';
    } else {
        try {
            // Begin transaction
            $conn->begin_transaction();
            
            $promoted_count = 0;
            $errors = [];
            
            foreach ($student_ids as $student_id) {
                // Get student's current class
                $stmt = $conn->prepare("SELECT class, education_level FROM students WHERE id = ? AND status = 'active'");
                $stmt->bind_param("i", $student_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $student = $result->fetch_assoc();
                
                if (!$student) {
                    $errors[] = "Student ID $student_id not found or not active";
                    continue;
                }
                
                $current_class = $student['class'];
                if (!isset($class_progression[$current_class])) {
                    $errors[] = "No promotion path available for class: $current_class";
                    continue;
                }
                
                $new_class = $class_progression[$current_class];
                $new_education_level = $student['education_level'];
                
                // Update education level if moving to junior secondary
                if ($current_class === 'grade6' && $new_class === 'grade7') {
                    $new_education_level = 'junior_secondary';
                }
                
                // Update student's class and education level
                $update_stmt = $conn->prepare("
                    UPDATE students 
                    SET class = ?, 
                        education_level = ? 
                    WHERE id = ?
                ");
                $update_stmt->bind_param("ssi", $new_class, $new_education_level, $student_id);
                $update_stmt->execute();
                
                $promoted_count++;
            }
            
            // Commit transaction
            $conn->commit();
            
            if (!empty($errors)) {
                $error = "Promotion completed with errors:\n" . implode("\n", $errors);
            }
            
            $success = "Successfully promoted $promoted_count students";
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error = 'Error promoting students: ' . $e->getMessage();
        }
    }
}

// Get students eligible for promotion (active students not in final grade)
$query = "
    SELECT id, admission_number, first_name, last_name, class, education_level 
    FROM students 
    WHERE status = 'active' 
    AND class != 'grade10'
    ORDER BY class, admission_number
";
$result = $conn->query($query);
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-900">Bulk Promote Students</h1>
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

        <form method="POST" class="mt-6">
            <input type="hidden" name="promote_students" value="1">
            
            <div class="bg-white shadow overflow-hidden sm:rounded-md">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            Select Students to Promote
                        </h3>
                        <div class="flex items-center">
                            <button type="button" onclick="selectAll(true)" class="mr-2 inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Select All
                            </button>
                            <button type="button" onclick="selectAll(false)" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-gray-700 bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                Deselect All
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($students as $student): ?>
                            <div class="relative flex items-start">
                                <div class="flex items-center h-5">
                                    <input type="checkbox" name="students[]" value="<?php echo $student['id']; ?>" 
                                           class="student-checkbox focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label class="font-medium text-gray-700">
                                        <?php echo htmlspecialchars($student['admission_number']); ?> - 
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </label>
                                    <p class="text-gray-500">
                                        Current: <?php echo ucfirst($student['class']); ?> 
                                        → Next: <?php echo ucfirst($class_progression[$student['class']]); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-level-up-alt mr-2"></i>Promote Selected Students
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function selectAll(checked) {
    document.querySelectorAll('.student-checkbox').forEach(checkbox => {
        checkbox.checked = checked;
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>
