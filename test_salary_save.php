<?php

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/core/Database.php';
require_once __DIR__ . '/app/models/Salary.php';

try {
    $db = new Database();
    $pdo = $db->connect();

    $salaryModel = new Salary();

    $employeeId = 10;
    $salaryMonth = '2026-11';

    echo "<pre>";
    echo "====================================\n";
    echo "       SALARY STORAGE TEST\n";
    echo "====================================\n\n";

    // Calculate monthly salary
    $result = $salaryModel->calculateMonthlySalary(
        $employeeId,
        $salaryMonth
    );

    echo "Salary calculation completed.\n";
    echo "Employee: " . $result['employee']['full_name'] . "\n";
    echo "Month: " . $salaryMonth . "\n";
    echo "Basic Salary: " . $result['monthly_salary'] . "\n";
    echo "Deductions: " . $result['total_deductions'] . "\n";
    echo "Final Salary: " . $result['payable_salary'] . "\n\n";

    // Save or update salary record
    $saved = $salaryModel->saveMonthlySalary($result);

    if (!$saved) {
        throw new RuntimeException("Salary record could not be saved.");
    }

    echo "Salary record saved successfully!\n\n";

    // Retrieve the saved record for verification
    $stmt = $pdo->prepare("
        SELECT
            id,
            employee_id,
            basic_salary,
            deductions,
            final_salary,
            calculation_details,
            calculated_at,
            salary_month
        FROM salary
        WHERE employee_id = :employee_id
          AND salary_month = :salary_month
    ");

    $stmt->execute([
        ':employee_id' => $employeeId,
        ':salary_month' => $salaryMonth . '-01'
    ]);

    $savedRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "========== SAVED DATABASE RECORD ==========\n\n";

    print_r($savedRecord);

    echo "</pre>";

} catch (Throwable $e) {
    echo "Salary Storage Test Error: " . $e->getMessage();
}
