<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('reset_password.php');
}

verify_csrf($_POST['csrf_token'] ?? null);

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$code = trim((string) ($_POST['code'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirmation = (string) ($_POST['password_confirmation'] ?? '');

$_SESSION['reset_email'] = $email;

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\d{6}$/', $code)) {
    $_SESSION['reset_error'] = 'Enter your registered email and the 6-digit code.';
    redirect('reset_password.php');
}

if (strlen($password) < 8) {
    $_SESSION['reset_error'] = 'Your new password must have at least 8 characters.';
    redirect('reset_password.php');
}

if ($password !== $confirmation) {
    $_SESSION['reset_error'] = 'Password confirmation does not match.';
    redirect('reset_password.php');
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['reset_error'] = 'The reset code is invalid or expired.';
    redirect('reset_password.php');
}

$resetStmt = $pdo->prepare(
    "SELECT *
     FROM password_resets
     WHERE user_id = ?
       AND used_at IS NULL
       AND expires_at >= NOW()
     ORDER BY id DESC
     LIMIT 1"
);
$resetStmt->execute([(int) $user['id']]);
$reset = $resetStmt->fetch();

if (!$reset || (int) $reset['attempts'] >= 5) {
    $_SESSION['reset_error'] = 'The reset code is invalid or expired.';
    redirect('reset_password.php');
}

if (!password_verify($code, (string) $reset['code_hash'])) {
    $attempt = $pdo->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?');
    $attempt->execute([(int) $reset['id']]);

    $_SESSION['reset_error'] = 'The reset code is invalid or expired.';
    redirect('reset_password.php');
}

$pdo->beginTransaction();
try {
    $updateUser = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $updateUser->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);

    $consume = $pdo->prepare(
        "UPDATE password_resets
         SET used_at = COALESCE(used_at, NOW())
         WHERE user_id = ? AND used_at IS NULL"
    );
    $consume->execute([(int) $user['id']]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['reset_error'] = 'Could not reset the password. Try again.';
    redirect('reset_password.php');
}

unset($_SESSION['reset_email']);
session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
redirect('dashboard.php');
