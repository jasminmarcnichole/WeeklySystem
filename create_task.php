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
    <title>Create Task | Weekly Task System</title>
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
                <a class="side-link" href="dashboard.php">Board</a>
                <a class="side-link active" href="create_task.php">New task</a>
                <a class="side-link" href="history.php">History</a>
                <a class="side-link" href="notifications.php">Notices</a>
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

            <section class="composer-panel">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Weekly entry</p>
                        <h2>Create a task</h2>
                    </div>
                    <span class="mini-pill"><?php echo e($snapshot['planning_message']); ?></span>
                </div>

                <form class="composer-form js-task-form" <?php echo $planningOpen ? '' : 'aria-disabled="true"'; ?>>
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <label>
                        <span>Task title</span>
                        <input type="text" name="task_title" placeholder="Launch payroll report" <?php echo $planningOpen ? 'required' : 'disabled'; ?>>
                    </label>
                    <label>
                        <span>Category</span>
                        <select name="category_id" <?php echo $planningOpen ? '' : 'disabled'; ?>>
                            <?php foreach ($snapshot['categories'] as $category): ?>
                                <option value="<?php echo (int) $category['id']; ?>">
                                    <?php echo e($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="grid-span">
                        <span>Details</span>
                        <textarea name="description" rows="4" placeholder="Outcome, constraints, and what done means" <?php echo $planningOpen ? '' : 'disabled'; ?>></textarea>
                    </label>
                    <label>
                        <span>Start</span>
                        <input type="date" name="start_date" min="<?php echo e($weekStart); ?>" max="<?php echo e($weekEnd); ?>" value="<?php echo e($weekStart); ?>" <?php echo $planningOpen ? 'required' : 'disabled'; ?>>
                    </label>
                    <label>
                        <span>Deadline</span>
                        <input type="date" name="due_date" min="<?php echo e($weekStart); ?>" max="<?php echo e($weekEnd); ?>" value="<?php echo e($weekEnd); ?>" <?php echo $planningOpen ? 'required' : 'disabled'; ?>>
                    </label>
                    <button class="button button-dark grid-span" type="submit" <?php echo $planningOpen ? '' : 'disabled'; ?>>
                        Add to board
                    </button>
                </form>
            </section>
        </main>
    </div>

    <div class="toast" data-toast hidden></div>
    <script src="assets/app.js"></script>
</body>
</html>
