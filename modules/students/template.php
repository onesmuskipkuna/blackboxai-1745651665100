<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db.php';

// Require login
requireLogin();

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="students_template.xls"');
header('Cache-Control: max-age=0');

// Print Excel template with sample data
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
        <tr>
            <td>ADM001</td>
            <td>John</td>
            <td>Doe</td>
            <td>Jane Doe</td>
            <td>0712345678</td>
            <td>primary</td>
            <td>grade1</td>
            <td>active</td>
        </tr>
        <tr>
            <td>ADM002</td>
            <td>Jane</td>
            <td>Smith</td>
            <td>John Smith</td>
            <td>0723456789</td>
            <td>junior_secondary</td>
            <td>grade7</td>
            <td>active</td>
        </tr>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="8">
                Notes:
                - Education Level must be either 'primary' or 'junior_secondary'
                - Primary Classes: pg, pp1, pp2, grade1, grade2, grade3, grade4, grade5, grade6
                - Junior Secondary Classes: grade7, grade8, grade9, grade10
                - Status must be one of: active, inactive, graduated, transferred
            </td>
        </tr>
    </tfoot>
</table>
