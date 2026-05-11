<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/gmail_mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('forgot_password.php');
}

verify_csrf($_POST['csrf_token'] ?? null);

$email = strtolower(trim((string) ($_POST['email'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['reset_error'] = 'Enter the email address registered to your account.';
    redirect('forgot_password.php');
}

$stmt = $pdo->prepare('SELECT id, name, username, email FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['reset_notice'] = 'If that email exists, a reset code has been sent.';
    redirect('reset_password.php');
}

$recentStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM password_resets
     WHERE user_id = ?
       AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)"
);
$recentStmt->execute([(int) $user['id']]);
if ((int) $recentStmt->fetchColumn() > 0) {
    $_SESSION['reset_error'] = 'A reset code was sent recently. Please wait a moment before requesting another one.';
    $_SESSION['reset_email'] = $email;
    redirect('reset_password.php');
}

$code = (string) random_int(100000, 999999);
$config = require __DIR__ . '/mail_config.php';
$recipientName = $user['name'] ?: $user['username'];
$subject = 'Your Weekly Task System password reset code';
$message = "Your password reset code is {$code}.\n\nThis code expires in 15 minutes. If you did not request this, you can ignore this email.";
$resetId = null;

try {
    $pdo->beginTransaction();
    $expireOld = $pdo->prepare(
        "UPDATE password_resets
         SET used_at = COALESCE(used_at, NOW())
         WHERE user_id = ? AND used_at IS NULL"
    );
    $expireOld->execute([(int) $user['id']]);

    $insert = $pdo->prepare(
        "INSERT INTO password_resets (user_id, code_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))"
    );
    $insert->execute([(int) $user['id'], password_hash($code, PASSWORD_DEFAULT)]);
    $resetId = (int) $pdo->lastInsertId();
    $pdo->commit();

    send_gmail_message($config, (string) $user['email'], (string) $recipientName, $subject, $message);

    $_SESSION['reset_notice'] = 'A 6-digit reset code was sent to your email.';
    $_SESSION['reset_email'] = $email;
    redirect('reset_password.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($resetId) {
        $invalidate = $pdo->prepare('UPDATE password_resets SET used_at = COALESCE(used_at, NOW()) WHERE id = ?');
        $invalidate->execute([$resetId]);
    }

    $_SESSION['reset_error'] = 'Could not send the reset code. Check Gmail SMTP settings and try again.';
    redirect('forgot_password.php');
}
