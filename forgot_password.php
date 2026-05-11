<?php
require_once __DIR__ . '/app.php';

if (current_user($pdo)) {
    redirect('dashboard.php');
}

$error = $_SESSION['reset_error'] ?? null;
$notice = $_SESSION['reset_notice'] ?? null;
unset($_SESSION['reset_error'], $_SESSION['reset_notice']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | Weekly Task System</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <a class="brand auth-brand" href="index.php">
            <span class="brand-mark">W</span>
            <span>Weekly Task System</span>
        </a>

        <section class="auth-card">
            <p class="eyebrow">Password recovery</p>
            <h1>Send a reset code to Gmail</h1>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($notice): ?>
                <div class="alert alert-success"><?php echo e($notice); ?></div>
            <?php endif; ?>
            <form action="forgot_password_action.php" method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <label>
                    <span>Registered email</span>
                    <input type="email" name="email" autocomplete="email" required>
                </label>
                <button class="button button-dark" type="submit">Email reset code</button>
            </form>
            <p class="auth-switch">Already have a code? <a href="reset_password.php">Reset password</a></p>
            <p class="auth-switch">Remembered it? <a href="login.php">Back to login</a></p>
        </section>
    </main>
</body>
</html>
