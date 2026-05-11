<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$user = require_auth($pdo);
$userId = (int) $user['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'snapshot';

function api_error(string $message, int $status = 422): void
{
    json_response([
        'ok' => false,
        'message' => $message,
    ], $status);
}

function owned_task(PDO $pdo, int $taskId, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ? AND user_id = ?');
    $stmt->execute([$taskId, $userId]);
    $task = $stmt->fetch();

    return $task ?: null;
}

function owned_step(PDO $pdo, int $stepId, int $userId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT s.*, t.user_id, t.status AS task_status, t.week_start, t.week_end, t.start_date AS task_start_date, t.due_date AS task_due_date
         FROM task_steps s
         INNER JOIN tasks t ON t.id = s.task_id
         WHERE s.id = ? AND t.user_id = ?"
    );
    $stmt->execute([$stepId, $userId]);
    $step = $stmt->fetch();

    return $step ?: null;
}

function assert_week_date(string $date, string $weekStart, string $weekEnd, string $label): void
{
    if ($date < $weekStart || $date > $weekEnd) {
        api_error("{$label} must stay inside the selected weekly board.");
    }
}

try {
    if ($action !== 'snapshot') {
        verify_csrf($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    }

    if ($action === 'snapshot') {
        json_response([
            'ok' => true,
            'snapshot' => fetch_dashboard_snapshot($pdo, $userId),
        ]);
    }

    if ($action === 'create_task') {
        if (!is_planning_open()) {
            api_error('Task entry is locked. Create or plan weekly tasks on Sunday or Monday.');
        }

        $title = trim((string) ($_POST['task_title'] ?? $_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $startDate = normalize_date($_POST['start_date'] ?? null);
        $dueDate = normalize_date($_POST['due_date'] ?? null);

        if ($title === '') {
            api_error('Task title is required.');
        }
        if (!$startDate || !$dueDate) {
            api_error('Start date and deadline are required.');
        }
        if ($startDate > $dueDate) {
            api_error('Start date cannot be after the deadline.');
        }

        $range = planning_week_range();
        $weekStart = $range['start']->format('Y-m-d');
        $weekEnd = $range['end']->format('Y-m-d');
        assert_week_date($startDate, $weekStart, $weekEnd, 'Start date');
        assert_week_date($dueDate, $weekStart, $weekEnd, 'Deadline');

        $stmt = $pdo->prepare(
            "INSERT INTO tasks (user_id, category_id, title, description, week_start, week_end, start_date, due_date)
             VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $categoryId, $title, $description, $weekStart, $weekEnd, $startDate, $dueDate]);

        json_response([
            'ok' => true,
            'message' => 'Task added to the weekly board.',
            'snapshot' => fetch_dashboard_snapshot($pdo, $userId),
        ]);
    }

    if ($action === 'create_step') {
        if (!is_planning_open()) {
            api_error('Steps can be added during Sunday/Monday planning only.');
        }

        $taskId = (int) ($_POST['task_id'] ?? 0);
        $task = owned_task($pdo, $taskId, $userId);
        if (!$task) {
            api_error('Task not found.', 404);
        }
        if (in_array($task['status'], ['in_progress', 'completed', 'failed'], true)) {
            api_error('Cannot add steps once task has been started.');
        }

        $title = trim((string) ($_POST['step_title'] ?? ''));
        $description = trim((string) ($_POST['step_description'] ?? ''));
        $startDate = normalize_date($_POST['start_date'] ?? null);
        $dueDate = normalize_date($_POST['due_date'] ?? null);

        if ($title === '') {
            api_error('Step title is required.');
        }
        if (!$startDate || !$dueDate) {
            api_error('Step start date and deadline are required.');
        }
        if ($startDate > $dueDate) {
            api_error('Step start date cannot be after its deadline.');
        }

        assert_week_date($startDate, $task['week_start'], $task['week_end'], 'Step start date');
        assert_week_date($dueDate, $task['week_start'], $task['week_end'], 'Step deadline');
        if ($startDate < $task['start_date'] || $dueDate > $task['due_date']) {
            api_error('Step dates must stay inside the parent task dates.');
        }

        $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM task_steps WHERE task_id = ?');
        $orderStmt->execute([$taskId]);
        $sortOrder = (int) $orderStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "INSERT INTO task_steps (task_id, step_title, step_description, start_date, due_date, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$taskId, $title, $description, $startDate, $dueDate, $sortOrder]);

        json_response([
            'ok' => true,
            'message' => 'Step added.',
            'snapshot' => fetch_dashboard_snapshot($pdo, $userId),
        ]);
    }

    if ($action === 'start_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $task = owned_task($pdo, $taskId, $userId);
        if (!$task) {
            api_error('Task not found.', 404);
        }
        if ($task['status'] === 'failed') {
            api_error('Failed tasks are locked until next week.');
        }
        if ($task['status'] === 'completed') {
            api_error('This task is already completed.');
        }

        $stmt = $pdo->prepare(
            "UPDATE tasks
             SET status = 'in_progress', started_at = COALESCE(started_at, NOW())
             WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$taskId, $userId]);

        json_response([
            'ok' => true,
            'message' => 'Task started.',
            'snapshot' => fetch_dashboard_snapshot($pdo, $userId),
        ]);
    }

    if ($action === 'complete_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $task = owned_task($pdo, $taskId, $userId);
        if (!$task) {
            api_error('Task not found.', 404);
        }
        if ($task['status'] === 'failed') {
            api_error('Failed tasks are locked until next week.');
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM task_steps
             WHERE task_id = ? AND status <> 'completed'"
        );
        $stmt->execute([$taskId]);
        if ((int) $stmt->fetchColumn() > 0) {
            api_error('Complete all steps before finishing the parent task.');
        }

        $stmt = $pdo->prepare(
            "UPDATE tasks
             SET status = 'completed', completed_at = COALESCE(completed_at, NOW())
             WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$taskId, $userId]);

        json_response([
            'ok' => true,
            'message' => 'Task completed.',
            'snapshot' => fetch_dashboard_snapshot($pdo, $userId),
        ]);
    }

    if ($action === 'start_step') {
        $stepId = (int) ($_POST['step_id'] ?? 0);
        $step = owned_step($pdo, $stepId, $userId);
        if (!$step) {
            api_error('Step not found.', 404);
        }
        if ($step['status'] === 'failed' || $step['task_status'] === 'failed') {
            api_error('Failed steps are locked until next week.');
        }
        if ($step['status'] === 'completed') {
            api_error('This step is already completed.');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "UPDATE task_steps
             SET status = 'in_progress', started_at = COALESCE(started_at, NOW())
             WHERE id = ?"
        );
        $stmt->execute([$stepId]);
        $stmt = $pdo->prepare(
            "UPDATE tasks
             SET status = 'in_progress', started_at = COALESCE(started_at, NOW())
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([(int) $step['task_id']]);
        $pdo->commit();

        json_response([
            'ok' => true,
            'message' => 'Step started.',
            'snapshot' => fetch_dashboard_snapshot($pdo, $userId),
        ]);
    }

    if ($action === 'complete_step') {
        $stepId = (int) ($_POST['step_id'] ?? 0);
        $step = owned_step($pdo, $stepId, $userId);
        if (!$step) {
            api_error('Step not found.', 404);
        }
        if ($step['status'] === 'failed' || $step['task_status'] === 'failed') {
            api_error('Failed steps are locked until next week.');
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "UPDATE task_steps
             SET status = 'completed', completed_at = COALESCE(completed_at, NOW())
             WHERE id = ?"
        );
        $stmt->execute([$stepId]);

        $stmt = $pdo->prepare(
            "UPDATE tasks t
             SET t.status = 'completed', t.completed_at = COALESCE(t.completed_at, NOW())
             WHERE t.id = ?
               AND NOT EXISTS (
                    SELECT 1 FROM task_steps s
                    WHERE s.task_id = t.id AND s.status <> 'completed'
               )"
        );
        $stmt->execute([(int) $step['task_id']]);

        $stmt = $pdo->prepare(
            "UPDATE tasks
             SET status = 'in_progress', started_at = COALESCE(started_at, NOW())
             WHERE id = ? AND status = 'pending'"
        );
        $stmt->execute([(int) $step['task_id']]);
        $pdo->commit();

        json_response([
            'ok' => true,
            'message' => 'Step completed.',
            'snapshot' => fetch_dashboard_snapshot($pdo, $userId),
        ]);
    }

    if ($action === 'read_notification') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        $stmt = $pdo->prepare(
            "UPDATE notifications
             SET status = 'read', read_at = COALESCE(read_at, NOW())
             WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$notificationId, $userId]);

        json_response([
            'ok' => true,
            'snapshot' => fetch_dashboard_snapshot($pdo, $userId),
        ]);
    }

    api_error('Unknown action.', 404);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response([
        'ok' => false,
        'message' => 'Something went wrong while updating the board.',
    ], 500);
}
