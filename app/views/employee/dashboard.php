<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Employee Dashboard';

$employee = $employee ?? [];

$employeeCode = $employee['employee_code'] ?? 'Not assigned';
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

<div class="admin-main">

    <?php require_once __DIR__ . '/../admin/layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>
                    Welcome back,
                    <?= htmlspecialchars(Auth::fullName() ?? 'Employee') ?>!
                </h1>

                <p>
                    Welcome to your MYOL HRIS employee dashboard.
                </p>
            </div>
        </div>

        <!-- Employee Summary Cards -->
        <div class="employee-dashboard-stats">

            <!-- Employee ID -->
            <div class="employee-dashboard-stat-card">
                <div class="employee-dashboard-stat-icon">#</div>

                <div class="employee-dashboard-stat-content">
                    <span class="employee-dashboard-stat-label">
                        Employee ID
                    </span>

                    <strong class="employee-dashboard-stat-value">
                        <?= htmlspecialchars($employeeCode) ?>
                    </strong>

                    <small>Your unique employee code</small>
                </div>
            </div>

            <!-- Department -->
            <div class="employee-dashboard-stat-card">
                <div class="employee-dashboard-stat-icon">⌂</div>

                <div class="employee-dashboard-stat-content">
                    <span class="employee-dashboard-stat-label">
                        Department
                    </span>

                    <strong class="employee-dashboard-stat-value">
                        <?= htmlspecialchars($department) ?>
                    </strong>

                    <small>Your current department</small>
                </div>
            </div>

            <!-- Designation -->
            <div class="employee-dashboard-stat-card">
                <div class="employee-dashboard-stat-icon">★</div>

                <div class="employee-dashboard-stat-content">
                    <span class="employee-dashboard-stat-label">
                        Designation
                    </span>

                    <strong class="employee-dashboard-stat-value">
                        <?= htmlspecialchars($designation) ?>
                    </strong>

                    <small>Your current position</small>
                </div>
            </div>

            <!-- Joining Date -->
            <div class="employee-dashboard-stat-card">
                <div class="employee-dashboard-stat-icon">◷</div>

                <div class="employee-dashboard-stat-content">
                    <span class="employee-dashboard-stat-label">
                        Joining Date
                    </span>

                    <strong class="employee-dashboard-stat-value">
                        <?= htmlspecialchars($joiningDate) ?>
                    </strong>

                    <small>Your date of joining</small>
                </div>
            </div>

        </div>

        
        <!-- Attendance Card -->
        <div class="content-card employee-attendance-card">

            <div class="card-header">
                <div>
                    <h2>Attendance</h2>
                    <p>Your daily entry and exit records.</p>
                </div>
            </div>

            <div class="employee-attendance-empty">
                <div class="employee-attendance-empty-icon">
                    <span>◷</span>
                </div>

                <h3>Coming soon</h3>
                <p>Attendance history will appear here.</p>
            </div>

        </div>

    </main>

    <?php require_once __DIR__ . '/../admin/layouts/footer.php'; ?>

</div>