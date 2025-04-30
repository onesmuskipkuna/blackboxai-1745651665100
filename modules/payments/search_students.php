<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$results = [];

if ($search !== '') {
    $search_param = '%' . $search . '%';
    $stmt = $conn->prepare("
        SELECT id, admission_number, CONCAT(first_name, ' ', last_name) AS name, phone_number
        FROM students
        WHERE status = 'active' AND (
            admission_number LIKE ? OR
            first_name LIKE ? OR
            last_name LIKE ? OR
            phone_number LIKE ?
        )
        ORDER BY admission_number
        LIMIT 20
    ");
    $stmt->bind_param('ssss', $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $results[] = [
            'id' => $row['id'],
            'admission_number' => $row['admission_number'],
            'name' => $row['name'],
            'phone' => $row['phone_number'],
            'text' => $row['admission_number'] . ' - ' . $row['name'] . ' (' . $row['phone_number'] . ')'
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['results' => $results]);
exit;
?>
