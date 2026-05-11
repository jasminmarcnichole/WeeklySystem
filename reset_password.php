<?php
require_once __DIR__ . '/app.php';

if (current_user($pdo)) {
    redirect('dashboard.php');
}

$error = $_SESSION['reset_error'] ?? null;
$notice = $_SESSION['reset_notice'] ?? null;
$email = $_SESSION['reset_email'] ?? '';
unset($_SESSION['reset_error'], $_SESSION['reset_notice']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password | Weekly Task System</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <a class="brand auth-brand" href="index.php">
            <span class="brand-mark">W</span>
            <span>Weekly Task System</span>
        </a>

        <section class="auth-card">
            <p class="eyebrow">Verify code</p>
            <h1>Create a new password</h1>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($notice): ?>
                <div class="alert alert-success"><?php echo e($notice); ?></div>
            <?php endif; ?>
            <form action="reset_password_action.php" method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <label>
                    <span>Registered email</span>
                    <input type="email" name="email" autocomplete="email" value="<?php echo e($email); ?>" required>
                </label>
                <label>
                    <span>6-digit code</span>
                    <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                </label>
                <label>
                    <span>New password</span>
                    <input type="password" name="password" autocomplete="new-password" minlength="8" required>
                </label>
                <label>
                    <span>Confirm password</span>
                    <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                </label>
                <button class="button button-dark" type="submit">Reset password</button>
            </form>
            <p class="auth-switch">Need a new code? <a href="forgot_password.php">Send another code</a></p>
            <p class="auth-switch">Back to <a href="login.php">login</a></p>
        </section>
    </main>
</body>
</html>
