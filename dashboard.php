<?php
require_once __DIR__ . '/app.php';

$user = require_auth($pdo);
$snapshot = fetch_dashboard_snapshot($pdo, (int) $user['id']);
$weekStart = $snapshot['week_start'];
$weekEnd = $snapshot['week_end'];
$planningOpen = (bool) $snapshot['planning_open'];
$displayName = $user['name'] ?: $user['username'];
$initial = strtoupper(substr($displayName, 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | Weekly Task System</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="app-page">
    <div
        id="dashboard-app"
        class="dashboard-shell"
        data-csrf="<?php echo e(csrf_token()); ?>"
        data-week-start="<?php echo e($weekStart); ?>"
        data-week-end="<?php echo e($weekEnd); ?>"
    >
        <aside class="sidebar">
            <a class="brand" href="dashboard.php">
                <span class="brand-mark">W</span>
                <span>Weekly Task System</span>
            </a>

            <nav class="side-nav" aria-label="Dashboard sections">
                <a class="side-link active" href="dashboard.php">Board</a>
                <a class="side-link" href="create_task.php">New task</a>
                <a class="side-link" href="history.php">History</a>
                <a class="side-link" href="notifications.php">Notices</a>
                <?php if (has_role($user, 'admin')): ?>
                <a class="side-link" href="admin.php">Admin</a>
                <?php endif; ?>
            </nav>

            <div class="profile-card">
                <span class="avatar"><?php echo e($initial); ?></span>
                <div>
                    <strong><?php echo e($displayName); ?></strong>
                    <small><?php echo e($user['email'] ?: 'No email set'); ?></small>
                </div>
            </div>
            <a class="button button-light sidebar-logout" href="logout.php">Logout</a>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-header">
                <div>
                    <p class="eyebrow">Weekly execution board</p>
                    <h1><?php echo e($snapshot['week_label']); ?></h1>
                </div>
                <div class="status-tile <?php echo $planningOpen ? 'is-open' : 'is-locked'; ?>">
                    <span><?php echo $planningOpen ? 'Planning open' : 'Planning locked'; ?></span>
                    <strong><?php echo $planningOpen ? 'Sunday/Monday' : 'Execution mode'; ?></strong>
                </div>
            </header>

            <section class="metric-grid" id="summary">
                <article class="metric-card"><span>Total</span><strong>0</strong></article>
                <article class="metric-card"><span>In progress</span><strong>0</strong></article>
                <article class="metric-card"><span>Completed</span><strong>0</strong></article>
                <article class="metric-card"><span>Week progress</span><strong>0%</strong></article>
            </section>

            <section class="board-panel">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Tasks, steps, details, days</p>
                        <h2>Weekly Gantt board</h2>
                    </div>
                    <span class="mini-pill" data-updated>Syncing</span>
                </div>
                <div class="board-days" data-days></div>
                <div class="task-board" data-board></div>
            </section>
        </main>
    </div>

    <div class="toast" data-toast hidden></div>
    <script src="assets/app.js"></script>
</body>
</html>
