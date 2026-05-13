<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connection.php';

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Manila');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function db_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function db_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!db_has_column($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

function db_has_index(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
    );
    $stmt->execute([$table, $index]);

    return (int) $stmt->fetchColumn() > 0;
}

function db_add_index(PDO $pdo, string $table, string $indexName, string $definition): void
{
    if (!db_has_index($pdo, $table, $indexName)) {
        try {
            $pdo->exec("ALTER TABLE {$table} ADD {$definition}");
        } catch (PDOException $e) {
            // Existing local data may contain duplicates. The app still works without the extra index.
        }
    }
}

function ensure_schema(PDO $pdo): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NULL,
            email VARCHAR(190) NULL,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db_add_column($pdo, 'users', 'name', 'VARCHAR(120) NULL AFTER id');
    db_add_column($pdo, 'users', 'email', 'VARCHAR(190) NULL AFTER name');
    db_add_column($pdo, 'users', 'role', "ENUM('user','admin') NOT NULL DEFAULT 'user' AFTER password");
    db_add_index($pdo, 'users', 'users_email_unique', 'UNIQUE KEY users_email_unique (email)');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            color VARCHAR(20) NOT NULL DEFAULT '#1f8a70',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db_add_column($pdo, 'categories', 'color', "VARCHAR(20) NOT NULL DEFAULT '#1f8a70' AFTER name");
    db_add_column($pdo, 'categories', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    db_add_index($pdo, 'categories', 'categories_name_unique', 'UNIQUE KEY categories_name_unique (name)');

    $categorySeeds = [
        ['Work', '#355c7d'],
        ['Personal', '#1f8a70'],
        ['Study', '#d9822b'],
        ['Health', '#c44569'],
        ['Admin', '#6c5ce7'],
    ];
    $seedCategory = $pdo->prepare(
        "INSERT INTO categories (name, color)
         SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = ?)"
    );
    foreach ($categorySeeds as [$name, $color]) {
        $seedCategory->execute([$name, $color, $name]);
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            category_id INT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            week_start DATE NULL,
            week_end DATE NULL,
            start_date DATE NULL,
            due_date DATE NULL,
            status ENUM('pending','in_progress','completed','failed') DEFAULT 'pending',
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            failed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX tasks_user_week_idx (user_id, week_start),
            CONSTRAINT tasks_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT tasks_category_fk FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db_add_column($pdo, 'tasks', 'week_start', 'DATE NULL AFTER description');
    db_add_column($pdo, 'tasks', 'week_end', 'DATE NULL AFTER week_start');
    db_add_column($pdo, 'tasks', 'start_date', 'DATE NULL AFTER week_end');
    db_add_column($pdo, 'tasks', 'started_at', 'DATETIME NULL AFTER status');
    db_add_column($pdo, 'tasks', 'completed_at', 'DATETIME NULL AFTER started_at');
    db_add_column($pdo, 'tasks', 'failed_at', 'DATETIME NULL AFTER completed_at');
    db_add_index($pdo, 'tasks', 'tasks_user_week_idx', 'INDEX tasks_user_week_idx (user_id, week_start)');
    try {
        $pdo->exec("ALTER TABLE tasks MODIFY status ENUM('pending','in_progress','completed','failed') DEFAULT 'pending'");
    } catch (PDOException $e) {
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS task_steps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            step_title VARCHAR(255) NOT NULL,
            step_description TEXT NULL,
            start_date DATE NULL,
            due_date DATE NULL,
            status ENUM('pending','in_progress','completed','failed') DEFAULT 'pending',
            sort_order INT NOT NULL DEFAULT 0,
            started_at DATETIME NULL,
            completed_at DATETIME NULL,
            failed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX task_steps_task_idx (task_id),
            CONSTRAINT task_steps_task_fk FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    db_add_column($pdo, 'task_steps', 'start_date', 'DATE NULL AFTER step_description');
    db_add_column($pdo, 'task_steps', 'sort_order', 'INT NOT NULL DEFAULT 0 AFTER status');
    db_add_column($pdo, 'task_steps', 'started_at', 'DATETIME NULL AFTER sort_order');
    db_add_column($pdo, 'task_steps', 'completed_at', 'DATETIME NULL AFTER started_at');
    db_add_column($pdo, 'task_steps', 'failed_at', 'DATETIME NULL AFTER completed_at');
    db_add_index($pdo, 'task_steps', 'task_steps_task_idx', 'INDEX task_steps_task_idx (task_id)');
    try {
        $pdo->exec("ALTER TABLE task_steps MODIFY status ENUM('pending','in_progress','completed','failed') DEFAULT 'pending'");
    } catch (PDOException $e) {
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            task_id INT NULL,
            step_id INT NULL,
            notification_key VARCHAR(191) NOT NULL,
            channel ENUM('gmail') NOT NULL DEFAULT 'gmail',
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('queued','sent','failed','read') NOT NULL DEFAULT 'queued',
            scheduled_for DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME NULL,
            read_at DATETIME NULL,
            last_error TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY notifications_key_unique (notification_key),
            INDEX notifications_user_status_idx (user_id, status),
            CONSTRAINT notifications_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT notifications_task_fk FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            CONSTRAINT notifications_step_fk FOREIGN KEY (step_id) REFERENCES task_steps(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            attempts INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX password_resets_user_idx (user_id, used_at, expires_at),
            CONSTRAINT password_resets_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec("UPDATE tasks SET start_date = COALESCE(start_date, due_date, CURDATE()) WHERE start_date IS NULL");
    $pdo->exec(
        "UPDATE tasks
         SET week_start = DATE_SUB(COALESCE(start_date, due_date, CURDATE()), INTERVAL WEEKDAY(COALESCE(start_date, due_date, CURDATE())) DAY)
         WHERE week_start IS NULL"
    );
    $pdo->exec("UPDATE tasks SET week_end = DATE_ADD(week_start, INTERVAL 6 DAY) WHERE week_end IS NULL AND week_start IS NOT NULL");
    $pdo->exec("UPDATE task_steps SET start_date = COALESCE(start_date, due_date, CURDATE()) WHERE start_date IS NULL");

    $ready = true;
}

ensure_schema($pdo);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): void
{
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('Your session expired. Refresh the page and try again.');
    }
}

function current_user(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_auth(PDO $pdo): array
{
    $user = current_user($pdo);

    if (!$user) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function has_role(array $user, string $role): bool
{
    return ($user['role'] ?? 'user') === $role;
}

function require_role(PDO $pdo, string $role): array
{
    $user = require_auth($pdo);

    if (!has_role($user, $role)) {
        http_response_code(403);
        exit('Access denied.');
    }

    return $user;
}

function require_admin(PDO $pdo): array
{
    return require_role($pdo, 'admin');
}

function redirect(string $path): void
{
    header("Location: {$path}");
    exit;
}

function today(): DateTimeImmutable
{
    return new DateTimeImmutable('today');
}

function normalize_date(?string $date): ?string
{
    if (!$date) {
        return null;
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$parsed || ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }

    return $parsed->format('Y-m-d');
}

function planning_week_start(?DateTimeImmutable $base = null): DateTimeImmutable
{
    $base = $base ?: today();
    $day = (int) $base->format('N');

    if ($day === 7) {
        return $base->modify('next monday');
    }

    return $base->modify('monday this week');
}

function planning_week_range(?DateTimeImmutable $base = null): array
{
    $start = planning_week_start($base);

    return [
        'start' => $start,
        'end' => $start->modify('+6 days'),
    ];
}

function is_planning_open(?DateTimeImmutable $base = null): bool
{
    $day = (int) ($base ?: today())->format('N');

    return $day === 1 || $day === 7;
}

function week_days(DateTimeImmutable $weekStart): array
{
    $days = [];
    $now = today()->format('Y-m-d');

    for ($i = 0; $i < 7; $i++) {
        $day = $weekStart->modify("+{$i} days");
        $date = $day->format('Y-m-d');
        $days[] = [
            'date' => $date,
            'name' => $day->format('D'),
            'label' => $day->format('M j'),
            'is_today' => $date === $now,
        ];
    }

    return $days;
}

function date_between(string $date, ?string $start, ?string $end): bool
{
    return $start !== null && $end !== null && $date >= $start && $date <= $end;
}

function queue_notification(PDO $pdo, int $userId, ?int $taskId, ?int $stepId, string $key, string $subject, string $message): void
{
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO notifications
            (user_id, task_id, step_id, notification_key, subject, message, scheduled_for)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$userId, $taskId, $stepId, $key, $subject, $message]);
}

function refresh_week_state(PDO $pdo, ?int $userId = null): void
{
    $userClause = $userId ? ' AND t.user_id = :user_id' : '';
    $params = $userId ? ['user_id' => $userId] : [];

    $stmt = $pdo->prepare(
        "UPDATE task_steps s
         INNER JOIN tasks t ON t.id = s.task_id
         SET s.status = 'failed', s.failed_at = COALESCE(s.failed_at, NOW())
         WHERE s.status IN ('pending','in_progress')
           AND s.due_date < CURDATE()
           {$userClause}"
    );
    $stmt->execute($params);

    $stmt = $pdo->prepare(
        "UPDATE tasks t
         SET t.status = 'completed', t.completed_at = COALESCE(t.completed_at, NOW())
         WHERE t.status <> 'failed'
           AND EXISTS (SELECT 1 FROM task_steps s WHERE s.task_id = t.id)
           AND NOT EXISTS (
                SELECT 1 FROM task_steps s
                WHERE s.task_id = t.id AND s.status <> 'completed'
           )
           {$userClause}"
    );
    $stmt->execute($params);

    $stmt = $pdo->prepare(
        "UPDATE tasks t
         SET t.status = 'failed', t.failed_at = COALESCE(t.failed_at, NOW())
         WHERE t.status IN ('pending','in_progress')
           AND (
                t.due_date < CURDATE()
                OR EXISTS (
                    SELECT 1 FROM task_steps s
                    WHERE s.task_id = t.id AND s.status = 'failed'
                )
           )
           {$userClause}"
    );
    $stmt->execute($params);

    $selectParams = $userId ? [$userId] : [];

    $taskSql =
        "SELECT t.id, t.user_id, t.title, t.start_date, t.due_date, t.status
         FROM tasks t
         WHERE t.status IN ('pending','in_progress','failed')" .
        ($userId ? ' AND t.user_id = ?' : '');
    $stmt = $pdo->prepare($taskSql);
    $stmt->execute($selectParams);
    $tasks = $stmt->fetchAll();

    $today = today()->format('Y-m-d');
    $tomorrow = today()->modify('+1 day')->format('Y-m-d');

    foreach ($tasks as $task) {
        $taskId = (int) $task['id'];
        $ownerId = (int) $task['user_id'];

        if ($task['status'] === 'pending' && $task['start_date'] === $today) {
            queue_notification(
                $pdo,
                $ownerId,
                $taskId,
                null,
                "user-{$ownerId}-task-start-{$taskId}-{$today}",
                'Start today: ' . $task['title'],
                "Your weekly task '{$task['title']}' is scheduled to start today."
            );
        }

        if (in_array($task['status'], ['pending', 'in_progress'], true)
            && $task['due_date'] >= $today
            && $task['due_date'] <= $tomorrow
        ) {
            queue_notification(
                $pdo,
                $ownerId,
                $taskId,
                null,
                "user-{$ownerId}-task-deadline-{$taskId}-{$task['due_date']}",
                'Deadline near: ' . $task['title'],
                "The deadline for '{$task['title']}' is near. Update it before {$task['due_date']}."
            );
        }

        if ($task['status'] === 'failed') {
            queue_notification(
                $pdo,
                $ownerId,
                $taskId,
                null,
                "user-{$ownerId}-task-failed-{$taskId}",
                'Task locked until next week: ' . $task['title'],
                "The task '{$task['title']}' missed its weekly deadline and is locked until next week's planning window."
            );
        }
    }

    $stepSql =
        "SELECT s.id, s.task_id, s.step_title, s.start_date, s.due_date, s.status, t.user_id, t.title AS task_title
         FROM task_steps s
         INNER JOIN tasks t ON t.id = s.task_id
         WHERE s.status IN ('pending','in_progress','failed')" .
        ($userId ? ' AND t.user_id = ?' : '');
    $stmt = $pdo->prepare($stepSql);
    $stmt->execute($selectParams);
    $steps = $stmt->fetchAll();

    foreach ($steps as $step) {
        $stepId = (int) $step['id'];
        $taskId = (int) $step['task_id'];
        $ownerId = (int) $step['user_id'];

        if ($step['status'] === 'pending' && $step['start_date'] === $today) {
            queue_notification(
                $pdo,
                $ownerId,
                $taskId,
                $stepId,
                "user-{$ownerId}-step-start-{$stepId}-{$today}",
                'Start step today: ' . $step['step_title'],
                "Start '{$step['step_title']}' for '{$step['task_title']}' today."
            );
        }

        if (in_array($step['status'], ['pending', 'in_progress'], true)
            && $step['due_date'] >= $today
            && $step['due_date'] <= $tomorrow
        ) {
            queue_notification(
                $pdo,
                $ownerId,
                $taskId,
                $stepId,
                "user-{$ownerId}-step-deadline-{$stepId}-{$step['due_date']}",
                'Step deadline near: ' . $step['step_title'],
                "The step '{$step['step_title']}' is due on {$step['due_date']}."
            );
        }

        if ($step['status'] === 'failed') {
            queue_notification(
                $pdo,
                $ownerId,
                $taskId,
                $stepId,
                "user-{$ownerId}-step-failed-{$stepId}",
                'Step locked until next week: ' . $step['step_title'],
                "The step '{$step['step_title']}' missed its weekly deadline and cannot be rescheduled this week."
            );
        }
    }
}

function task_progress(array $task): int
{
    $steps = $task['steps'] ?? [];

    if (!$steps) {
        return $task['status'] === 'completed' ? 100 : ($task['status'] === 'in_progress' ? 45 : 0);
    }

    $completed = 0;
    foreach ($steps as $step) {
        if ($step['status'] === 'completed') {
            $completed++;
        }
    }

    return (int) round(($completed / count($steps)) * 100);
}

function status_text(string $status): string
{
    return [
        'pending' => 'Pending',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'failed' => 'Failed',
    ][$status] ?? ucfirst($status);
}

function fetch_categories(PDO $pdo): array
{
    return $pdo->query('SELECT id, name, color FROM categories ORDER BY name')->fetchAll();
}

function fetch_dashboard_snapshot(PDO $pdo, int $userId): array
{
    refresh_week_state($pdo, $userId);

    $range = planning_week_range();
    $weekStart = $range['start']->format('Y-m-d');
    $weekEnd = $range['end']->format('Y-m-d');

    $stmt = $pdo->prepare(
        "SELECT t.*, c.name AS category_name, c.color AS category_color
         FROM tasks t
         LEFT JOIN categories c ON c.id = t.category_id
         WHERE t.user_id = ? AND t.week_start = ? AND t.status <> 'completed'
         ORDER BY t.created_at DESC, t.id DESC"
    );
    $stmt->execute([$userId, $weekStart]);
    $tasks = $stmt->fetchAll();

    $stepsByTask = [];
    if ($tasks) {
        $taskIds = array_map(static fn (array $task): int => (int) $task['id'], $tasks);
        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stepStmt = $pdo->prepare(
            "SELECT *
             FROM task_steps
             WHERE task_id IN ({$placeholders})
             ORDER BY sort_order ASC, start_date ASC, id ASC"
        );
        $stepStmt->execute($taskIds);

        foreach ($stepStmt->fetchAll() as $step) {
            $stepsByTask[(int) $step['task_id']][] = $step;
        }
    }

    foreach ($tasks as &$task) {
        $task['steps'] = $stepsByTask[(int) $task['id']] ?? [];
        $task['progress'] = task_progress($task);
        $task['status_label'] = status_text($task['status']);
        $task['is_locked'] = $task['status'] === 'failed';
        foreach ($task['steps'] as &$step) {
            $step['status_label'] = status_text($step['status']);
            $step['is_locked'] = $step['status'] === 'failed';
        }
    }
    unset($task, $step);

    $notificationsStmt = $pdo->prepare(
        "SELECT id, subject, message, status, scheduled_for, sent_at, created_at
         FROM notifications
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 8"
    );
    $notificationsStmt->execute([$userId]);

    $summary = [
        'total' => count($tasks),
        'pending' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'failed' => 0,
        'progress' => 0,
    ];
    $progressTotal = 0;
    foreach ($tasks as $task) {
        $summary[$task['status']]++;
        $progressTotal += (int) $task['progress'];
    }
    $summary['progress'] = count($tasks) ? (int) round($progressTotal / count($tasks)) : 0;

    return [
        'today' => today()->format('Y-m-d'),
        'week_start' => $weekStart,
        'week_end' => $weekEnd,
        'week_label' => $range['start']->format('M j') . ' - ' . $range['end']->format('M j, Y'),
        'planning_open' => is_planning_open(),
        'planning_message' => is_planning_open()
            ? 'Weekly planning is open.'
            : 'Weekly planning opens every Sunday and Monday.',
        'days' => week_days($range['start']),
        'categories' => fetch_categories($pdo),
        'tasks' => $tasks,
        'notifications' => $notificationsStmt->fetchAll(),
        'summary' => $summary,
    ];
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
