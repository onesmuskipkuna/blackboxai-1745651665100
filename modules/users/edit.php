<?php
$page_title = 'Edit User';
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

$db = Database::getInstance();
$conn = $db->getConnection();

$error = '';
$success = '';

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    header('Location: index.php');
    exit;
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $role = sanitize($_POST['role']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (!$username || !$email || !$first_name || !$last_name || !$role) {
        $error = 'All fields except password are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif ($password && $password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif ($password && !checkPasswordStrength($password)) {
        $error = 'Password does not meet strength requirements';
    } else {
        // Check if username or email already exists for other users
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->bind_param('ssi', $username, $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        if ($row['count'] > 0) {
            $error = 'Username or email already exists';
        } else {
            // Update user
            if ($password) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, role = ?, password = ? WHERE id = ?");
                $stmt->bind_param('ssssssi', $username, $email, $first_name, $last_name, $role, $password_hash, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, role = ? WHERE id = ?");
                $stmt->bind_param('sssssi', $username, $email, $first_name, $last_name, $role, $user_id);
            }
            $stmt->execute();

            flashMessage('success', 'User updated successfully');
            header('Location: index.php');
            exit;
        }
    }
}

require_once '../../includes/header.php';
require_once '../../includes/navigation.php';
?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-semibold text-gray-900">Edit User</h1>

        <?php if ($error): ?>
            <div class="mt-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="mt-6 space-y-6 max-w-lg">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                <input type="text" name="username" id="username" required value="<?php echo htmlspecialchars($user['username']); ?>"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" id="email" required value="<?php echo htmlspecialchars($user['email']); ?>"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                <input type="text" name="first_name" id="first_name" required value="<?php echo htmlspecialchars($user['first_name']); ?>"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                <input type="text" name="last_name" id="last_name" required value="<?php echo htmlspecialchars($user['last_name']); ?>"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                <select name="role" id="role" required
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <option value="">Select Role</option>
                    <option value="admin" <?php if ($user['role'] === 'admin') echo 'selected'; ?>>Admin</option>
                    <option value="accountant" <?php if ($user['role'] === 'accountant') echo 'selected'; ?>>Accountant</option>
                </select>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password (leave blank to keep current)</label>
                <input type="password" name="password" id="password"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Update User
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
