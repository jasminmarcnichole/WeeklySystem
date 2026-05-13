<?php
require_once __DIR__ . '/app.php';

header('Content-Type: application/json');

$admin = require_admin($pdo);
$userId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE id = ? AND role = "user"');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (!$username) {
    echo json_encode(['success' => false, 'message' => 'Username is required']);
    exit;
}

// Check if username is taken by another user
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
$stmt->execute([$username, $userId]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Username already taken']);
    exit;
}

try {
    // Update user
    if ($password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, email = ?, password = ? WHERE id = ?');
        $stmt->execute([$name, $username, $email, $hashedPassword, $userId]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, email = ? WHERE id = ?');
        $stmt->execute([$name, $username, $email, $userId]);
    }
    
    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to update user']);
}
