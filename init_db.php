<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Create users table
    $createUsersTable = "
        CREATE TABLE IF NOT EXISTS users (
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
        )
    ";
    if (!$conn->query($createUsersTable)) {
        throw new Exception("Error creating users table: " . $conn->error);
    }

    // Insert default admin user
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $insertAdmin = "
        INSERT INTO users (id, username, password, email, first_name, last_name, role)
        VALUES (1, 'admin', '{$password_hash}', 'admin@school.com', 'System', 'Administrator', 'admin')
        ON DUPLICATE KEY UPDATE username=username
    ";
    if (!$conn->query($insertAdmin)) {
        throw new Exception("Error inserting default admin user: " . $conn->error);
    }

    echo "Database initialized successfully!\n";
    echo "Default admin credentials:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";

} catch (Exception $e) {
    die("Error initializing database: " . $e->getMessage() . "\n");
}
?>
