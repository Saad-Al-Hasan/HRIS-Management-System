<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../core/Auth.php';
Auth::startSession();

$pageTitle = 'Audit Logs';
$auditLogs = $auditLogs ?? [];
$users = $users ?? [];
$filters = $filters ?? [];
$categorySummary = $categorySummary ?? [];
$currentPage = max(1, (int) ($currentPage ?? 1));
$totalPages = max(1, (int) ($totalPages ?? 1));
$totalRecords = max(0, (int) ($totalRecords ?? 0));
$error = $error ?? null;

$fromDate = (string) ($filters['from_date'] ?? '');
$toDate = (string) ($filters['to_date'] ?? '');
$selectedUser = (string) ($filters['user_id'] ?? '');
$selectedModule = (string) ($filters['module'] ?? '');

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

/*
|--------------------------------------------------------------------------
| Module KPI definitions
|--------------------------------------------------------------------------
| Keep these keys synchronized with AuditLog::getCategorySummary().
*/
$kpiCards = [
    ['key' => 'authentication', 'title' => 'Authentication', 'description' => 'Sign-ins and sign-outs', 'icon' => 'shield'],
    ['key' => 'employee_management', 'title' => 'Employee Management', 'description' => 'Employee profile and status changes', 'icon' => 'users'],
    ['key' => 'rfid_management', 'title' => 'RFID Management', 'description' => 'Card assignment and removal', 'icon' => 'credit-card'],
    ['key' => 'attendance_management', 'title' => 'Attendance Management', 'description' => 'Entry, exit and attendance edits', 'icon' => 'calendar-check'],
    ['key' => 'work_schedules', 'title' => 'Work Schedules', 'description' => 'Schedule creation and assignment', 'icon' => 'calendar-clock'],
    ['key' => 'salary_management', 'title' => 'Salary Management', 'description' => 'Salary setup and payroll processing', 'icon' => 'wallet'],
    ['key' => 'leave_management', 'title' => 'Leave Management', 'description' => 'Leave submissions and decisions', 'icon' => 'calendar-days'],
    ['key' => 'holiday_management', 'title' => 'Holiday Management', 'description' => 'Holiday creation and removal', 'icon' => 'calendar-heart'],
];


/*
|--------------------------------------------------------------------------
| Human-readable module labels for individual audit rows
|--------------------------------------------------------------------------
*/
$getActivityModule = static function (string $activity): string {
    $activity = strtolower($activity);

    if (str_contains($activity, 'logged in') || str_contains($activity, 'logged out')) {
        return 'Authentication';
    }

    if (
        str_contains($activity, 'rfid') && (
            str_contains($activity, 'card') ||
            str_contains($activity, 'assigned') ||
            str_contains($activity, 'unassigned')
        )
    ) {
        return 'RFID Management';
    }

    if (
        str_contains($activity, 'attendance') ||
        str_contains($activity, 'rfid entry recorded') ||
        str_contains($activity, 'rfid exit recorded')
    ) {
        return 'Attendance Management';
    }

    if (str_contains($activity, 'work schedule')) {
        return 'Work Schedules';
    }

    if (str_contains($activity, 'salary') || str_contains($activity, 'payroll')) {
        return 'Salary Management';
    }

    if (str_contains($activity, 'leave')) {
        return 'Leave Management';
    }

    if (str_contains($activity, 'holiday')) {
        return 'Holiday Management';
    }

    if (
        str_contains($activity, 'employee') && (
            str_contains($activity, 'created') ||
            str_contains($activity, 'profile updated') ||
            str_contains($activity, 'activated') ||
            str_contains($activity, 'deactivated')
        )
    ) {
        return 'Employee Management';
    }

    return 'Other Activities';
};

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<link rel="stylesheet" href="/HRIS-Management-System/public/assets/css/audit-logs.css">

<div class="admin-main">
    <?php require_once __DIR__ . '/../layouts/navbar.php'; ?>

    <main class="admin-content">
        <div class="audit-console">

            <!-- Page identity and high-level system summary -->
            <section class="audit-intro">
                <div class="audit-intro-main">
                    <div class="audit-kicker"><span class="audit-kicker-mark"></span> SECURITY &amp; ACCOUNTABILITY
                        <span class="audit-kicker-divider"></span>
                    </div>
                    <h1>Audit Trail</h1>
                    <p>Review who performed each action, when it happened, and which HRIS module was affected.</p>
                </div>

                <div class="audit-overview">
                    <div class="audit-overview-icon" aria-hidden="true">Σ</div>
                    <div class="audit-overview-copy">
                        <span class="audit-overview-label">Matching events</span>
                        <strong>
                            <?= number_format($totalRecords) ?>
                        </strong>
                        <small>Across all monitored modules</small>
                    </div>
                    <div class="audit-overview-divider"></div>
                    <div class="audit-overview-meta">
                        <span>
                            <?= count($kpiCards) ?> MODULES
                        </span>
                        <span>NEWEST FIRST</span>
                    </div>
                </div>
            </section>

            <!-- Filters -->
            <section class="audit-filter-console" aria-labelledby="audit-filter-title">
                <div class="audit-section-heading">
                    <div class="audit-heading-symbol" aria-hidden="true">⌕</div>
                    <div>
                        <span class="audit-overline">SEARCH THE AUDIT TRAIL</span>
                        <h2 id="audit-filter-title">Filter events</h2>
                        <p>Combine a date range, user and keyword to narrow the activity history.</p>
                    </div>
                    <span class="audit-secure-tag"><span></span> ADMIN ACCESS</span>
                </div>

                <form method="GET" action="/HRIS-Management-System/public/index.php" class="audit-filter-form">
                    <input type="hidden" name="action" value="audit-logs">

                    <div class="audit-field">
                        <label for="audit-from-date">From date</label>
                        <input type="date" id="audit-from-date" name="from_date" value="<?= $escape($fromDate) ?>">
                    </div>

                    <div class="audit-field">
                        <label for="audit-to-date">To date</label>
                        <input type="date" id="audit-to-date" name="to_date" value="<?= $escape($toDate) ?>">
                    </div>

                    <div class="audit-field audit-field-user">
                        <label for="audit-user-search">Performed by</label>

                        <?php
                        $selectedUsername = '';

                        foreach ($users as $user) {
                            if ((string) $user['id'] === $selectedUser) {
                                $selectedUsername = (string) ($user['username'] ?? '');
                                break;
                            }
                        }
                        ?>

                        <input type="text" id="audit-user-search" placeholder="Search username..." autocomplete="off"
                            value="<?= $escape($selectedUsername) ?>">

                        <input type="hidden" id="audit-user-id" name="user_id" value="<?= $escape($selectedUser) ?>">

                        <div id="audit-user-suggestions" class="audit-user-suggestions" hidden>
                            <?php foreach ($users as $user): ?>
                                <button type="button" class="audit-user-option" data-user-id="<?= (int) $user['id'] ?>"
                                    data-username="<?= $escape($user['username'] ?? '') ?>">
                                    <?= $escape($user['username'] ?? '') ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>


                    <div class="audit-field audit-field-search">
                        <label for="audit-module">Activity Module</label>

                        <select id="audit-module" name="module">
                            <option value="">All Modules</option>

                            <option value="authentication" <?= $selectedModule === 'authentication' ? 'selected' : '' ?>>
                                Authentication
                            </option>

                            <option value="employee_management" <?= $selectedModule === 'employee_management' ? 'selected' : '' ?>>
                                Employee Management
                            </option>

                            <option value="rfid_management" <?= $selectedModule === 'rfid_management' ? 'selected' : '' ?>>
                                RFID Management
                            </option>

                            <option value="attendance_management" <?= $selectedModule === 'attendance_management' ? 'selected' : '' ?>>
                                Attendance Management
                            </option>

                            <option value="work_schedules" <?= $selectedModule === 'work_schedules' ? 'selected' : '' ?>>
                                Work Schedules
                            </option>

                            <option value="salary_management" <?= $selectedModule === 'salary_management' ? 'selected' : '' ?>>
                                Salary Management
                            </option>

                            <option value="leave_management" <?= $selectedModule === 'leave_management' ? 'selected' : '' ?>>
                                Leave Management
                            </option>

                            <option value="holiday_management" <?= $selectedModule === 'holiday_management' ? 'selected' : '' ?>>
                                Holiday Management
                            </option>
                        </select>
                    </div>


                    <div class="audit-filter-actions">
                        <button type="submit" class="audit-btn-primary">Search records</button>
                        <a href="/HRIS-Management-System/public/index.php?action=audit-logs"
                            class="audit-btn-reset">Clear</a>
                    </div>
                </form>
            </section>

            <?php if (!empty($error)): ?>
                <div class="audit-error" role="alert">
                    <strong>Unable to display the requested audit records</strong>
                    <span>
                        <?= $escape($error) ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Module activity breakdown: each module remains an independent KPI -->
            <section class="audit-modules" aria-labelledby="audit-modules-title">
                <div class="audit-section-heading audit-modules-heading">
                    <div class="audit-heading-symbol audit-heading-symbol-gold" aria-hidden="true">▦</div>
                    <div>
                        <span class="audit-overline">ACTIVITY DISTRIBUTION</span>
                        <h2 id="audit-modules-title">Module activity</h2>
                        <p>Event totals for each module, based on the current filters.</p>
                    </div>
                    <span class="audit-section-note">🟢 LIVE FILTER SUMMARY</span>
                </div>

                <div class="audit-module-grid">
                    <?php foreach ($kpiCards as $card): ?>
                        <?php
                        $cardValue = (int) ($categorySummary[$card['key']] ?? 0);
                        ?>

                        <article class="audit-module-tile audit-module-<?= $escape($card['key']) ?>">
                            <div class="audit-module-symbol" aria-hidden="true">
                                <?php
                                $moduleIcons = [
                                    'shield' => '<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"/><path d="m9 12 2 2 4-4"/>',
                                    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.87"/><path d="M17 3.13a4 4 0 0 1 0 7.75"/>',
                                    'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M6 15h3"/>',
                                    'calendar-check' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m8 15 2 2 5-5"/>',
                                    'calendar-clock' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><circle cx="15" cy="16" r="3"/><path d="M15 14v2l1 1"/>',
                                    'wallet' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18"/><path d="M16 15h2"/>',
                                    'calendar-days' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>',
                                    'calendar-heart' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M12 18s-4-2.2-4-4.5a2 2 0 0 1 4-1 2 2 0 0 1 4 1C16 15.8 12 18 12 18z"/>',
                                    'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>'
                                ];

                                $iconPath = $moduleIcons[$card['icon']] ?? $moduleIcons['activity'];
                                ?>

                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="21" height="21"
                                    fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                                    stroke-linejoin="round" aria-hidden="true">
                                    <?= $iconPath ?>
                                </svg>
                            </div>

                            <div class="audit-module-copy">
                                <span class="audit-module-name">
                                    <?= $escape($card['title']) ?>
                                </span>
                                <small>
                                    <?= $escape($card['description']) ?>
                                </small>
                            </div>
                            <strong class="audit-module-count">
                                <?= number_format($cardValue) ?>
                            </strong>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Detailed chronological audit trail -->
            <section class="audit-ledger" aria-labelledby="audit-ledger-title">
                <div class="audit-ledger-header">
                    <div class="audit-section-heading audit-ledger-title-group">
                        <div class="audit-heading-symbol audit-heading-symbol-ledger" aria-hidden="true">≡</div>
                        <div>
                            <span class="audit-overline">IMMUTABLE ACTIVITY HISTORY</span>
                            <h2 id="audit-ledger-title">Event ledger</h2>
                            <p>Detailed records of actions performed by system users.</p>
                        </div>
                    </div>
                    <div class="audit-ledger-count">
                        <strong>
                            <?= number_format($totalRecords) ?>
                        </strong>
                        <span>matching events</span>
                    </div>
                </div>

                <div class="audit-table-wrap">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th scope="col" class="audit-col-id">Event ID</th>
                                <th scope="col" class="audit-col-time">Timestamp</th>
                                <th scope="col" class="audit-col-actor">Actor</th>
                                <th scope="col" class="audit-col-module">Module</th>
                                <th scope="col">Activity details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($auditLogs)): ?>
                                <tr>
                                    <td colspan="5" class="audit-empty-state">
                                        <div class="audit-empty-icon" aria-hidden="true">⌕</div>
                                        <strong>No matching audit events</strong>
                                        <span>Try a wider date range, another user, or a different keyword.</span>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($auditLogs as $log): ?>
                                    <?php
                                    $timestamp = $log['activity_timestamp'] ?? null;
                                    $formattedTimestamp = 'Unavailable';
                                    if (!empty($timestamp)) {
                                        $parsedTimestamp = strtotime((string) $timestamp);
                                        if ($parsedTimestamp !== false) {
                                            $formattedTimestamp = date('d M Y', $parsedTimestamp) . ' · ' . date('h:i:s A', $parsedTimestamp);
                                        }
                                    }

                                    $actorName = trim((string) ($log['actor_name'] ?? ''));
                                    if ($actorName === '') {
                                        $actorName = 'Deleted / Unavailable User';
                                    }

                                    $activity = (string) ($log['activity'] ?? '');
                                    $module = $getActivityModule($activity);
                                    $userIdForRow = $log['user_id'] ?? null;
                                    ?>
                                    <tr>
                                        <td class="audit-id-cell"><span>#
                                                <?= (int) ($log['id'] ?? 0) ?>
                                            </span></td>
                                        <td class="audit-time-cell">
                                            <strong>
                                                <?= $escape($formattedTimestamp) ?>
                                            </strong>
                                        </td>
                                        <td class="audit-actor-cell">
                                            <strong>
                                                <?= $escape($actorName) ?>
                                            </strong>
                                            <small>
                                                <?= $userIdForRow !== null ? 'USER ID ' . (int) $userIdForRow : 'SYSTEM / UNAVAILABLE' ?>
                                            </small>
                                        </td>
                                        <td><span
                                                class="audit-module-pill audit-pill-<?= $escape(strtolower(str_replace(' ', '-', $module))) ?>">
                                                <?= $escape($module) ?>
                                            </span></td>
                                        <td class="audit-description-cell">
                                            <?= $escape($activity) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <?php
                    $paginationFilters = [
                        'action' => 'audit-logs',
                        'from_date' => $fromDate,
                        'to_date' => $toDate,
                        'user_id' => $selectedUser,
                        'module' => $selectedModule,
                    ];
                    ?>
                    <nav class="audit-pagination" aria-label="Audit log pages">
                        <span>Page <strong>
                                <?= $currentPage ?>
                            </strong> of <strong>
                                <?= $totalPages ?>
                            </strong></span>
                        <div class="audit-pagination-actions">
                            <?php if ($currentPage > 1): ?>
                                <a
                                    href="/HRIS-Management-System/public/index.php?<?= $escape(http_build_query(array_merge($paginationFilters, ['page' => $currentPage - 1]))) ?>">←
                                    Previous</a>
                            <?php else: ?>
                                <span class="is-disabled">← Previous</span>
                            <?php endif; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a
                                    href="/HRIS-Management-System/public/index.php?<?= $escape(http_build_query(array_merge($paginationFilters, ['page' => $currentPage + 1]))) ?>">Next
                                    →</a>
                            <?php else: ?>
                                <span class="is-disabled">Next →</span>
                            <?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            </section>

            <footer class="audit-footnote">
                <span class="audit-footnote-mark" aria-hidden="true">✓</span>
                <div>
                    <strong>Audit trail integrity</strong>
                    <p>These records support accountability and administrative review. Events are shown newest first;
                        the page does not modify stored audit history.</p>
                </div>
                <span class="audit-footnote-code"> · MYOL HRIS · </span>
            </footer>

        </div>
    </main>



    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('audit-user-search');
            const userId = document.getElementById('audit-user-id');
            const suggestions = document.getElementById('audit-user-suggestions');

            if (!input || !userId || !suggestions) return;

            const options = Array.from(
                suggestions.querySelectorAll('.audit-user-option')
            );

            function closeSuggestions() {
                suggestions.hidden = true;
            }

            function updateSuggestions() {
                const query = input.value.trim().toLowerCase();

                userId.value = '';

                if (!query) {
                    closeSuggestions();
                    return;
                }

                let matches = 0;

                options.forEach(option => {
                    const username = option.dataset.username.toLowerCase();
                    const matched = username.includes(query);

                    option.hidden = !matched;

                    if (matched) matches++;
                });

                suggestions.hidden = matches === 0;
            }

            function selectUser(option) {
                input.value = option.dataset.username;
                userId.value = option.dataset.userId;
                closeSuggestions();
            }

            input.addEventListener('input', updateSuggestions);



            options.forEach(option => {
                option.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    selectUser(option);
                });
            });

            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeSuggestions();
                }

                if (event.key === 'Enter' && !suggestions.hidden) {
                    event.preventDefault();

                    const firstMatch = options.find(option => !option.hidden);

                    if (firstMatch) {
                        selectUser(firstMatch);
                    }
                }
            });

            document.addEventListener('click', function (event) {
                if (!event.target.closest('.audit-field-user')) {
                    closeSuggestions();
                }
            });


        });
    </script>



    <?php require_once __DIR__ . '/../layouts/footer.php'; ?>
</div>