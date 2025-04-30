<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Create tables
    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            first_name VARCHAR(255) NOT NULL,
            last_name VARCHAR(255) NOT NULL,
            role ENUM('admin', 'accountant') NOT NULL,
            password_reset_token VARCHAR(255),
            password_reset_expires DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS students (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admission_number VARCHAR(255) UNIQUE NOT NULL,
            first_name VARCHAR(255) NOT NULL,
            last_name VARCHAR(255) NOT NULL,
            guardian_name VARCHAR(255) NOT NULL,
            phone_number VARCHAR(255) NOT NULL,
            education_level ENUM('primary', 'junior_secondary') NOT NULL,
            class VARCHAR(255) NOT NULL,
            status ENUM('active', 'inactive', 'graduated', 'transferred') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS fee_structure (
            id INT PRIMARY KEY AUTO_INCREMENT,
            class VARCHAR(255) NOT NULL,
            education_level ENUM('primary', 'junior_secondary') NOT NULL,
            fee_item VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            term INT NOT NULL,
            academic_year VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS invoices (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT NOT NULL,
            invoice_number VARCHAR(255) UNIQUE NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            paid_amount DECIMAL(10,2) DEFAULT 0,
            balance DECIMAL(10,2) NOT NULL,
            status ENUM('due', 'partially_paid', 'fully_paid') DEFAULT 'due',
            term INT NOT NULL,
            academic_year VARCHAR(255) NOT NULL,
            due_date DATE NOT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )",
        "CREATE TABLE IF NOT EXISTS invoice_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            invoice_id INT NOT NULL,
            fee_structure_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id),
            FOREIGN KEY (fee_structure_id) REFERENCES fee_structure(id)
        )",
        "CREATE TABLE IF NOT EXISTS payments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            invoice_id INT NOT NULL,
            payment_number VARCHAR(255) UNIQUE NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_mode ENUM('cash', 'mpesa', 'bank') NOT NULL,
            reference_number VARCHAR(255),
            remarks TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )",
        "CREATE TABLE IF NOT EXISTS payment_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            payment_id INT NOT NULL,
            invoice_item_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (payment_id) REFERENCES payments(id),
            FOREIGN KEY (invoice_item_id) REFERENCES invoice_items(id)
        )",
        "CREATE TABLE IF NOT EXISTS expense_categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS expenses (
            id INT PRIMARY KEY AUTO_INCREMENT,
            category_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            date DATE NOT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES expense_categories(id),
            FOREIGN KEY (created_by) REFERENCES users(id)
        )",
        "CREATE TABLE IF NOT EXISTS payroll (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_name VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($queries as $query) {
        $conn->query($query);
    }

    // Insert default admin user if not exists
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT IGNORE INTO users (username, password, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)");
    $username = 'admin';
    $email = 'admin@school.com';
    $first_name = 'System';
    $last_name = 'Administrator';
    $role = 'admin';
    $stmt->bind_param('ssssss', $username, $password_hash, $email, $first_name, $last_name, $role);
    $stmt->execute();

    // Get admin user ID and update existing records
    $admin_result = $conn->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    $admin_user = $admin_result->fetch_assoc();
    $admin_id = $admin_user['id'];

    // Update existing records to set created_by to admin
    if ($admin_id) {
        $updates = [
            "UPDATE invoices SET created_by = ? WHERE created_by IS NULL",
            "UPDATE payments SET created_by = ? WHERE created_by IS NULL",
            "UPDATE expenses SET created_by = ? WHERE created_by IS NULL"
        ];

        foreach ($updates as $query) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $admin_id);
            $stmt->execute();
            $stmt->close();
        }
    }
        "CREATE TABLE IF NOT EXISTS payroll (
            id INT PRIMARY KEY AUTO_INCREMENT,
            employee_name VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];

    foreach ($queries as $query) {
        $conn->query($query);
    }

    // Insert default admin user if not exists
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT IGNORE INTO users (username, password, email, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, ?)");
    $username = 'admin';
    $email = 'admin@school.com';
    $first_name = 'System';
    $last_name = 'Administrator';
    $role = 'admin';
    $stmt->bind_param('ssssss', $username, $password_hash, $email, $first_name, $last_name, $role);
    $stmt->execute();
    $stmt->close();

    // Get admin user ID and update existing records
    $admin_result = $conn->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
    $admin_user = $admin_result->fetch_assoc();
    $admin_id = $admin_user['id'];

    // Update existing records to set created_by to admin
    if ($admin_id) {
        $updates = [
            "UPDATE invoices SET created_by = ? WHERE created_by IS NULL",
            "UPDATE payments SET created_by = ? WHERE created_by IS NULL",
            "UPDATE expenses SET created_by = ? WHERE created_by IS NULL"
        ];

        foreach ($updates as $query) {
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $admin_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo "Database initialized successfully!\n";
    echo "Default admin credentials:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";

} catch (Exception $e) {
    die("Error initializing database: " . $e->getMessage() . "\n");
}
?>
