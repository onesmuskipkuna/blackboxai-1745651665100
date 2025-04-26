# Deployment Guide for School Fees Management System on Shared Hosting

This guide provides step-by-step instructions to deploy the School Fees Management System on a shared hosting environment.

## Prerequisites

- Access to your shared hosting control panel (e.g., cPanel, Plesk)
- FTP or File Manager access to upload files
- MySQL database credentials (host, username, password, database name)
- PHP version 7.4 or higher with MySQLi extension enabled

## Steps

### 1. Upload Files

- Upload all project files and folders to the `public_html` or `www` directory of your hosting account using FTP or File Manager.
- Ensure the directory structure is preserved.

### 2. Configure Database

- Create a new MySQL database via your hosting control panel.
- Create a MySQL user and assign it to the database with all privileges.
- Import the `database.sql` file located in the project root into your MySQL database using phpMyAdmin or command line:
  - Using phpMyAdmin:
    - Log in to phpMyAdmin.
    - Select your database.
    - Click on the "Import" tab.
    - Choose the `database.sql` file and import.
  - Using command line:
    ```
    mysql -u your_db_user -p your_db_name < /path/to/database.sql
    ```

### 3. Update Configuration

- Edit the `includes/config.php` file to set your database connection details:
  ```php
  define('DB_TYPE', 'mysqli');
  define('DB_HOST', 'your_db_host');
  define('DB_USER', 'your_db_user');
  define('DB_PASS', 'your_db_password');
  define('DB_NAME', 'your_db_name');
  ```
- Update other configuration settings as needed (e.g., SMTP settings).

### 4. Set File Permissions

- Ensure that the `database.sqlite` file (if still present) is removed or not used.
- Set appropriate permissions for files and folders (usually 644 for files and 755 for folders).

### 5. Verify PHP Extensions

- Ensure your hosting environment has the required PHP extensions enabled:
  - `mysqli`
  - `pdo`
  - `mbstring`
  - `openssl`
  - `curl`
  - `json`
  - `xml`
- You can check this via `phpinfo()` or contact your hosting provider.

### 6. Access the Application

- Open your website URL in a browser.
- You should see the login page.
- Use the default admin credentials to log in:
  - Username: `admin`
  - Password: `admin123`

### 7. Troubleshooting

- If you encounter errors, check the following:
  - Database connection details in `includes/config.php`.
  - File permissions.
  - PHP error logs (usually accessible via hosting control panel).
  - Ensure MySQLi extension is enabled.

### 8. Security Recommendations

- Change the default admin password after first login.
- Use HTTPS for secure communication.
- Regularly update your hosting environment and PHP version.

---

If you need further assistance with deployment, please contact your hosting provider or reach out for support.
