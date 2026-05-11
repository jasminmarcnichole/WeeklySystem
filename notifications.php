<?php
require_once __DIR__ . '/app.php';

$user = require_auth($pdo);
$snapshot = fetch_dashboard_snapshot($pdo, (int) $user['id']);
$planningOpen = (bool) $snapshot['planning_open'];
$displayName = $user['name'] ?: $user['username'];
$initial = strtoupper(substr($displayName, 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notifications | Weekly Task System</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="app-page">
    <div
        id="dashboard-app"
        class="dashboard-shell"
        data-csrf="<?php echo e(csrf_token()); ?>"
    >
        <aside class="sidebar">
            <a class="brand" href="dashboard.php">
                <span class="brand-mark">W</span>
                <span>Weekly Task System</span>
            </a>

            <nav class="side-nav" aria-label="Dashboard sections">
                <a class="side-link" href="dashboard.php">Board</a>
                <a class="side-link" href="create_task.php">New task</a>
                <a class="side-link" href="history.php">History</a>
                <a class="side-link active" href="notifications.php">Notices</a>
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

            <section class="notification-panel">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Gmail queue</p>
                        <h2>Notifications</h2>
                    </div>
                </div>
                <div class="notification-list" data-notifications></div>
            </section>
        </main>
    </div>

    <div class="toast" data-toast hidden></div>
    <script src="assets/app.js"></script>
</body>
</html>
