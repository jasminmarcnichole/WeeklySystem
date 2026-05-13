<?php
require_once __DIR__ . '/app.php';

$admin = require_admin($pdo);
$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$userId) {
    header('Location: admin.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, name, username, email, role FROM users WHERE id = ? AND role = "user"');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: admin.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$username) {
        $error = 'Username is required.';
    } else {
        // Check if username is taken by another user
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            $error = 'Username already taken.';
        } else {
            // Update user
            if ($password) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, email = ?, password = ? WHERE id = ?');
                $stmt->execute([$name, $username, $email, $hashedPassword, $userId]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, email = ? WHERE id = ?');
                $stmt->execute([$name, $username, $email, $userId]);
            }
            $success = 'User updated successfully.';
            // Refresh user data
            $user['name'] = $name;
            $user['username'] = $username;
            $user['email'] = $email;
        }
    }
}

$displayName = $admin['name'] ?: $admin['username'];
$initial = strtoupper(substr($displayName, 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit User | Weekly Task System</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="app-page">
    <div class="dashboard-shell">
        <aside class="sidebar">
            <a class="brand" href="dashboard.php">
                <span class="brand-mark">W</span>
                <span>Weekly Task System</span>
            </a>
            <nav class="side-nav" aria-label="Dashboard sections">
                <a class="side-link active" href="admin.php">Admin</a>
            </nav>
            <div class="profile-card">
                <span class="avatar"><?php echo e($initial); ?></span>
                <div>
                    <strong><?php echo e($displayName); ?></strong>
                    <small><?php echo e($admin['email'] ?: 'No email set'); ?></small>
                </div>
            </div>
            <a class="button button-light sidebar-logout" href="logout.php">Logout</a>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-header">
                <div>
                    <p class="eyebrow">Admin panel</p>
                    <h1>Edit User</h1>
                </div>
                <a class="button button-ghost" href="admin.php">Back to Users</a>
            </header>

            <div class="board-panel" style="max-width:720px;">
                <div class="section-heading">
                    <h2>Edit <?php echo e($user['username']); ?></h2>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo e($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert alert-success"><?php echo e($success); ?></div>
                <?php endif; ?>

                <form method="post" class="auth-form">
                    <label>
                        <span>Name</span>
                        <input type="text" name="name" value="<?php echo e($user['name']); ?>">
                    </label>

                    <label>
                        <span>Username *</span>
                        <input type="text" name="username" value="<?php echo e($user['username']); ?>" required>
                    </label>

                    <label>
                        <span>Email</span>
                        <input type="email" name="email" value="<?php echo e($user['email']); ?>">
                    </label>

                    <label>
                        <span>New Password (leave blank to keep current)</span>
                        <input type="password" name="password" autocomplete="new-password">
                    </label>

                    <button type="submit" class="button button-dark">Update User</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
