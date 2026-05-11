<?php
require_once __DIR__ . '/app.php';

$user = require_auth($pdo);
$displayName = $user['name'] ?: $user['username'];
$initial = strtoupper(substr($displayName, 0, 1));

$stmt = $pdo->prepare(
    "SELECT t.*, c.name AS category_name, c.color AS category_color
     FROM tasks t
     LEFT JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? AND t.status = 'completed'
     ORDER BY t.completed_at DESC, t.id DESC"
);
$stmt->execute([$user['id']]);
$tasks = $stmt->fetchAll();

$taskIds = array_map(fn($t) => $t['id'], $tasks);
$stepsByTask = [];
if ($taskIds) {
    $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
    $stepStmt = $pdo->prepare(
        "SELECT * FROM task_steps
         WHERE task_id IN ({$placeholders})
         ORDER BY sort_order ASC, id ASC"
    );
    $stepStmt->execute($taskIds);
    foreach ($stepStmt->fetchAll() as $step) {
        $stepsByTask[(int)$step['task_id']][] = $step;
    }
}

foreach ($tasks as &$task) {
    $task['steps'] = $stepsByTask[(int)$task['id']] ?? [];
    $task['days'] = week_days(new DateTimeImmutable($task['week_start']));
}
unset($task);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>History | Weekly Task System</title>
    <link rel="stylesheet" href="assets/styles.css">
    <style>
        .history-task-panel { transition: all 0.3s ease; }
        .history-task-panel.collapsed .history-task-content { display: none; }
        .history-task-panel .collapse-icon { transition: transform 0.3s ease; font-size: 1.2rem; }
        .history-task-panel.collapsed .collapse-icon { transform: rotate(-90deg); }
        .history-task-header { display: flex; justify-content: space-between; align-items: center; }
        .history-task-panel.collapsed { cursor: pointer; }
    </style>
</head>
<body class="app-page">
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.history-task-panel').forEach(panel => panel.classList.add('collapsed'));
        });
    </script>
    <div class="dashboard-shell">
        <aside class="sidebar">
            <a class="brand" href="dashboard.php">
                <span class="brand-mark">W</span>
                <span>Weekly Task System</span>
            </a>

            <nav class="side-nav" aria-label="Dashboard sections">
                <a class="side-link" href="dashboard.php">Board</a>
                <a class="side-link" href="create_task.php">New task</a>
                <a class="side-link active" href="history.php">History</a>
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
                    <p class="eyebrow">Completed tasks</p>
                    <h1>History</h1>
                </div>
                <div class="status-tile">
                    <span>Total completed</span>
                    <strong><?php echo count($tasks); ?></strong>
                </div>
            </header>

            <?php if (empty($tasks)): ?>
                <section class="board-panel">
                    <p style="padding: 2rem; text-align: center; color: #666;">No completed tasks yet.</p>
                </section>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <section class="board-panel history-task-panel">
                        <div class="history-task-header" style="cursor: pointer;" onclick="this.parentElement.classList.toggle('collapsed')">
                            <div>
                                <h3><?php echo e($task['title']); ?></h3>
                                <div class="history-task-meta">
                                    <?php if ($task['category_name']): ?>
                                        <span class="category-badge" style="background-color: <?php echo e($task['category_color']); ?>">
                                            <?php echo e($task['category_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <small>Completed: <?php echo date('M j, Y', strtotime($task['completed_at'])); ?></small>
                                </div>
                            </div>
                            <span class="collapse-icon">▼</span>
                        </div>

                        <div class="history-task-content">
                        <?php if (!empty($task['steps'])): ?>
                            <div class="history-gantt-scroll">
                                <div class="history-gantt-grid">
                                    <div class="history-axis">Step</div>
                                    <?php foreach ($task['days'] as $day): ?>
                                        <div class="history-day <?php echo $day['is_today'] ? 'is-today' : ''; ?>">
                                            <strong><?php echo e($day['name']); ?></strong>
                                            <span><?php echo e($day['label']); ?></span>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php foreach ($task['steps'] as $step): ?>
                                        <div class="history-step-info">
                                            <strong><?php echo e($step['step_title']); ?></strong>
                                        </div>
                                        <?php foreach ($task['days'] as $day): ?>
                                            <?php
                                            $isCompleted = $step['status'] === 'completed' && $step['completed_at'];
                                            $completedDate = $isCompleted ? date('Y-m-d', strtotime($step['completed_at'])) : null;
                                            $isCompletedOnDay = $completedDate === $day['date'];
                                            ?>
                                            <div class="history-step-cell <?php echo $isCompletedOnDay ? 'completed' : ''; ?>">
                                                <?php if ($isCompletedOnDay): ?>
                                                    <span class="completion-mark">✓</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <p style="padding: 1rem 0; color: #666; font-size: 0.9rem;">No steps recorded.</p>
                        <?php endif; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
