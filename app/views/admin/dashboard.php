<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::startSession();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard - HRIS</title>
</head>

<body>

    <h1>HRIS Management System</h1>

    <h2>Administrator Dashboard</h2>

    <p>
        Welcome,
        <strong>
            <?= htmlspecialchars($_SESSION['full_name'] ?? 'Administrator') ?>
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