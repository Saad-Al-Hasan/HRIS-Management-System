<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/core/Database.php';
require_once __DIR__ . '/app/models/Salary.php';

try {
    $database = new Database();
    $db = $database->connect();

    $salaryModel = new Salary($db);

    echo "<pre>";

    echo "===== SALARY HISTORY =====\n";

    $history = $salaryModel->getSalaryHistory('2026-11');
    print_r($history);

    echo "\n===== SALARY DETAILS (ID: 1) =====\n";

    $details = $salaryModel->getSalaryDetails(1);
    print_r($details);

    echo "</pre>";

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage();
}