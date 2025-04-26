# Installation Guide for School Fees Management System on Shared Server

This guide provides step-by-step instructions to install and configure the School Fees Management System on a shared hosting environment.

---

## Prerequisites

- A shared hosting account with PHP 7.4 or higher installed.
- SQLite3 extension enabled in PHP.
- Access to the hosting control panel (cPanel, Plesk, etc.) or FTP access.
- Ability to create and upload files to the server.
- A web browser to access the application.

---

## Installation Steps

### 1. Upload Files

- Upload all project files and folders to the desired directory on your shared server, typically `public_html` or a subdirectory like `public_html/school-fees`.

### 2. Set File Permissions

- Ensure the web server has read and write permissions to the following directories and files:
  - `database.sqlite` (SQLite database file)
  - `includes/` directory (if it contains any writable files)
  
- Typically, set permissions to `755` for directories and `644` for files. For the SQLite database file, ensure it is writable by the web server (e.g., `chmod 664 database.sqlite`).

### 3. Configure PHP Settings

- Verify that the PHP version is compatible (7.4 or higher).
- Ensure the SQLite3 extension is enabled. You can check this by creating a `phpinfo.php` file with the following content:

  ```php
  <?php phpinfo(); ?>
  ```

- Access this file via browser and search for `SQLite3` to confirm it is enabled.

### 4. Initialize the Database

- The system uses SQLite for the database.
- If the `database.sqlite` file is not present, create an empty file named `database.sqlite` in the project root.
- Upload the `init_db.php` script to the server.
- Access `init_db.php` via your browser (e.g., `https://yourdomain.com/init_db.php`) to initialize the database and create necessary tables.
- After successful initialization, delete or restrict access to `init_db.php` for security.

### 5. Configure Application Settings

- Open `includes/config.php` and update any necessary configuration such as:
  - Site name
  - Email settings
  - Other environment-specific settings

### 6. Access the Application

- Navigate to the application URL in your browser (e.g., `https://yourdomain.com/` or `https://yourdomain.com/school-fees/`).
- You should see the login page.
- Use the default admin credentials to log in:
  - Username: `admin`
  - Password: `admin123`

### 7. Secure Your Installation

- Remove or restrict access to installation and setup scripts like `init_db.php`.
- Change the default admin password after first login.
- Regularly back up your `database.sqlite` file.

---

## Troubleshooting

- **500 Internal Server Error:** Check PHP error logs for details.
- **Database Connection Issues:** Ensure `database.sqlite` is writable and in the correct location.
- **Missing PHP Extensions:** Enable required PHP extensions like SQLite3.
- **File Permission Errors:** Adjust file and directory permissions as needed.

---

## Additional Notes

- This application is designed for SQLite. If you want to use MySQL or another database, additional configuration and code changes are required.
- For better performance and security, consider using a VPS or dedicated server.

---

If you encounter any issues during installation, please consult your hosting provider or contact the application developer for support.
