<?php
require_once __DIR__ . '/../../../core/Auth.php';

Auth::startSession();

$activeTab = $activeTab ?? 'attendance';
$error = $error ?? '';
$success = $success ?? '';

$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$formatMoney = static function ($amount): string {
    return '৳' . number_format((float) ($amount ?? 0), 2);
};

$tabs = [
    'attendance' => 'Attendance Report',
    'employee' => 'Employee Report',
    'leave' => 'Leave Report',
    'salary' => 'Salary Report',
];
?>

<?php require_once __DIR__ . '/../layouts/header.php'; ?>
<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<link rel="stylesheet" href="/HRIS-Management-System/public/assets/css/reports.css">

<div class="admin-main reports-page">

    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">

        <div class="content-header">
            <div>
                <h1>Reports</h1>
                <p>Generate reports and export them to Excel.</p>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= $e($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $e($error) ?></div>
        <?php endif; ?>

        <!-- TAB BAR -->
        <nav class="report-tabs" aria-label="Report categories">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="/HRIS-Management-System/public/index.php?action=reports&amp;tab=<?= $e($key) ?>"
                    class="report-tab <?= $activeTab === $key ? 'active' : '' ?>"
                    <?= $activeTab === $key ? 'aria-current="page"' : '' ?>>
                    <?= $e($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- TAB CONTENT -->
        <?php
        switch ($activeTab) {
            case 'leave':
                require __DIR__ . '/_leave.php';
                break;

            case 'salary':
                require __DIR__ . '/_salary.php';
                break;

            case 'employee':
                require __DIR__ . '/_employee.php';
                break;

            default:
                require __DIR__ . '/_attendance.php';
                break;
        }
        ?>

    </main>

    <?php /* footer.php also closes .admin-main and .admin-layout */ ?>
    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</div>
