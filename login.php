<?php
require_once __DIR__ . '/app.php';

if (current_user($pdo)) {
    redirect('dashboard.php');
}

$error = $_SESSION['auth_error'] ?? null;
$notice = $_SESSION['auth_notice'] ?? null;
unset($_SESSION['auth_error']);
unset($_SESSION['auth_notice']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Weekly Task System</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <a class="brand auth-brand" href="index.php">
            <span class="brand-mark">W</span>
            <span>Weekly Task System</span>
        </a>

        <section class="auth-card">
            <p class="eyebrow">Welcome back</p>
            <h1>Login to your weekly board</h1>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($notice): ?>
                <div class="alert alert-success"><?php echo e($notice); ?></div>
            <?php endif; ?>
            <form action="login_action.php" method="post" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <label>
                    <span>Username or email</span>
                    <input type="text" name="login" autocomplete="username" required>
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
                <button class="button button-dark" type="submit">Login</button>
            </form>
            <p class="auth-switch">Forgot your password? <a href="forgot_password.php">Send reset code</a></p>
            <p class="auth-switch">New here? <a href="register.php">Create an account</a></p>
        </section>
    </main>
</body>
</html>
