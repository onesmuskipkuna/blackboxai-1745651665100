<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

// Get all students
$query = "SELECT * FROM students ORDER BY admission_number";
$result = $conn->query($query);
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="students.xls"');
header('Cache-Control: max-age=0');

// Print Excel content
?>
<table border="1">
    <thead>
        <tr>
            <th>Admission Number</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Guardian Name</th>
            <th>Phone Number</th>
            <th>Education Level</th>
            <th>Class</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($students as $student): ?>
        <tr>
            <td><?php echo $student['admission_number']; ?></td>
            <td><?php echo $student['first_name']; ?></td>
            <td><?php echo $student['last_name']; ?></td>
            <td><?php echo $student['guardian_name']; ?></td>
            <td><?php echo $student['phone_number']; ?></td>
            <td><?php echo $student['education_level']; ?></td>
            <td><?php echo $student['class']; ?></td>
            <td><?php echo $student['status']; ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
