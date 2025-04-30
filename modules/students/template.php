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
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Pragma: public');
?>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        td { mso-number-format:\@; }
        .required { color: #FF0000; }
        .note { color: #666666; }
    </style>
</head>
<body>
    <table border="1">
        <thead>
            <tr style="background-color: #f0f0f0;">
                <th>Admission Number *</th>
                <th>First Name *</th>
                <th>Last Name (Optional)</th>
                <th>Guardian Name (Optional)</th>
                <th>Phone Number (Optional)</th>
                <th>Education Level *</th>
                <th>Class *</th>
                <th>Status (Optional)</th>
            </tr>
        </thead>
        <tbody>
            <!-- Sample data rows showing different variations -->
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
                <td></td>
                <td></td>
                <td></td>
                <td>primary</td>
                <td>pp1</td>
                <td></td>
            </tr>
            <tr>
                <td>ADM003</td>
                <td>Alice</td>
                <td>Smith</td>
                <td></td>
                <td></td>
                <td>junior_secondary</td>
                <td>grade7</td>
                <td>active</td>
            </tr>
            <tr>
                <td>ADM004</td>
                <td>Bob</td>
                <td></td>
                <td></td>
                <td></td>
                <td>primary</td>
                <td>pg</td>
                <td></td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="8" style="text-align: left; padding: 5px;">
                    <strong>Notes:</strong><br>
                    1. Fields marked with * are required<br>
                    2. Education Level must be either:<br>
                       - primary (or p)<br>
                       - junior_secondary (or js, junior secondary, secondary)<br>
                    3. Valid Primary Classes:<br>
                       - pg (or playgroup, play group)<br>
                       - pp1 (or pp one, ppone, pp-1)<br>
                       - pp2 (or pp two, pptwo, pp-2)<br>
                       - grade1 through grade6 (or g1-g6, 1-6)<br>
                    4. Valid Junior Secondary Classes:<br>
                       - grade7 through grade10 (or g7-g10, 7-10)<br>
                    5. Status (if provided) must be one of:<br>
                       - active (default if left blank)<br>
                       - inactive<br>
                       - graduated<br>
                       - transferred<br>
                    6. Last Name, Guardian Name, and Phone Number are optional<br>
                    7. Do not modify the column headers
                </td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
