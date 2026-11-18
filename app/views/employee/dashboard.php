<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Employee Dashboard';

$employee = $employee ?? [];

$employeeCode = $employee['employee_code'] ?? 'Not assigned';
$rfidIdentifier = $rfidIdentifier ?? 'Not assigned';
$assignedSchedule = $assignedSchedule ?? null;
$scheduleName = $assignedSchedule['name'] ?? 'Not available';
$department = $employee['department'] ?? 'Not assigned';
$designation = $employee['designation'] ?? 'Not assigned';

$joiningDate = 'Not available';

if (!empty($employee['joining_date'])) {
    $timestamp = strtotime($employee['joining_date']);

    if ($timestamp !== false) {
        $joiningDate = date('d M Y', $timestamp);
    }
}

require_once __DIR__ . '/../admin/layouts/header.php';
require_once __DIR__ . '/layouts/sidebar.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Employee Dashboard - HRIS</title>
</head>

<body>

    <h1>HRIS Management System</h1>

    <h2>Employee Dashboard</h2>

    <p>
        Welcome,
        <strong>
            <?= htmlspecialchars($_SESSION['full_name'] ?? 'Employee') ?>
        </strong>
    </p>

    <p>
        Username:
        <?= htmlspecialchars($_SESSION['username'] ?? '') ?>
    </p>

    <a href="index.php?action=logout">
        Logout
    </a>

</body>

</html>