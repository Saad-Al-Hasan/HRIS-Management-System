
<?php

require_once __DIR__ . '/../../core/Auth.php';

Auth::startSession();

$pageTitle = 'Employee Dashboard';

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

        <div class="content-card">

            <div class="card-header">
                <div>
                    <h2>Employee Dashboard</h2>
                    <p>
                        Access your personal information and HR-related details.
                    </p>
                </div>
            </div>

        </div>

    </main>

</div>

<?php

require_once __DIR__ . '/../admin/layouts/footer.php';

?>
