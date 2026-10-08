<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::startSession();

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