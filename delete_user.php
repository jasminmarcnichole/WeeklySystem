<?php
require_once __DIR__ . '/app.php';

header('Content-Type: application/json');

$admin = require_admin($pdo);
$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Verify user exists and is not an admin
$stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ? AND role = "user"');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

try {
    // Delete user's task steps first
    $pdo->prepare('DELETE ts FROM task_steps ts INNER JOIN tasks t ON ts.task_id = t.id WHERE t.user_id = ?')
        ->execute([$userId]);

    // Delete user's tasks
    $pdo->prepare('DELETE FROM tasks WHERE user_id = ?')->execute([$userId]);

    // Delete user's notifications
    $pdo->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$userId]);

    // Delete user's categories
    $pdo->prepare('DELETE FROM categories WHERE user_id = ?')->execute([$userId]);

    // Delete password reset codes
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);

    // Finally delete the user
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
}
