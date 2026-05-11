<?php
require_once __DIR__ . '/app.php';

if (current_user($pdo)) {
    redirect('dashboard.php');
}

$error = $_SESSION['auth_error'] ?? null;
unset($_SESSION['auth_error']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | Weekly Task System</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <a class="brand auth-brand" href="index.php">
            <span class="brand-mark">W</span>
            <span>Weekly Task System</span>
        </a>

        <section class="auth-card auth-card-wide">
            <p class="eyebrow">Start the system</p>
            <h1>Create your weekly command account</h1>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo e($error); ?></div>
            <?php endif; ?>
            <form action="register_action.php" method="post" class="auth-form grid-form">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                <label>
                    <span>Full name</span>
                    <input type="text" name="name" autocomplete="name" required>
                </label>
                <label>
                    <span>Email for Gmail notices</span>
                    <input type="email" name="email" autocomplete="email" required>
                </label>
                <label>
                    <span>Username</span>
                    <input type="text" name="username" autocomplete="username" required>
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" name="password" autocomplete="new-password" minlength="8" required>
                </label>
                <button class="button button-dark grid-span" type="submit">Create account</button>
            </form>
            <p class="auth-switch">Already have access? <a href="login.php">Login</a></p>
        </section>
    </main>
</body>
</html>
