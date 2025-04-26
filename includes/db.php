<?php
require_once 'config.php';

class Database {
    private $connection;
    private static $instance = null;

    private function __construct() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            $this->connection->set_charset("utf8mb4");
            $this->createTables();
        } catch (Exception $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql) {
        $result = $this->connection->query($sql);
        if ($result === false) {
            throw new Exception("Database query error: " . $this->connection->error);
        }
        return $result;
    }

    public function escape($value) {
        return $this->connection->real_escape_string($value);
    }

    private function createTables() {
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES students(id)
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id)
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES expense_categories(id)
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
            $this->connection->query($query);
        }

        // Insert default admin user if not exists
        $passwordHash = password_hash('admin', PASSWORD_DEFAULT);
        $insertAdmin = "INSERT IGNORE INTO users (username, password, email, first_name, last_name, role)
                        VALUES ('admin', '{$passwordHash}', 'admin@school.com', 'System', 'Administrator', 'admin')";
        $this->connection->query($insertAdmin);
    }
}
?>
