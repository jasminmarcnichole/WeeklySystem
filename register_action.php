<?php
require_once __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}

verify_csrf($_POST['csrf_token'] ?? null);

$name = trim((string) ($_POST['name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($name === '' || $email === '' || $username === '' || strlen($password) < 8) {
    $_SESSION['auth_error'] = 'Complete all fields and use a password with at least 8 characters.';
    redirect('register.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['auth_error'] = 'Enter a valid email address for Gmail notifications.';
    redirect('register.php');
}

try {
    $stmt = $pdo->prepare('INSERT INTO users (name, email, username, password) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, $username, password_hash($password, PASSWORD_DEFAULT)]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
    redirect('dashboard.php');
} catch (PDOException $e) {
    $_SESSION['auth_error'] = 'That username or email is already registered.';
    redirect('register.php');
}
