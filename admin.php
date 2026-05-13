<?php
require_once __DIR__ . '/app.php';

$admin = require_admin($pdo);
$displayName = $admin['name'] ?: $admin['username'];
$initial = strtoupper(substr($displayName, 0, 1));

// Fetch all users with role 'user'
$users = $pdo->query(
    "SELECT u.id, u.name, u.username, u.email, u.role, u.created_at,
            COUNT(t.id) AS task_count
     FROM users u
     LEFT JOIN tasks t ON t.user_id = u.id
     WHERE u.role = 'user'
     GROUP BY u.id
     ORDER BY u.created_at DESC"
)->fetchAll();

// Selected user's tasks
$viewUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
$viewUser = null;
$tasks = [];

if ($viewUserId) {
    $stmt = $pdo->prepare('SELECT id, name, username, email, role FROM users WHERE id = ? AND role = "user"');
    $stmt->execute([$viewUserId]);
    $viewUser = $stmt->fetch();

    if ($viewUser) {
        $stmt = $pdo->prepare(
            "SELECT t.*, c.name AS category_name, c.color AS category_color
             FROM tasks t
             LEFT JOIN categories c ON c.id = t.category_id
             WHERE t.user_id = ?
             ORDER BY t.week_start DESC, t.created_at DESC"
        );
        $stmt->execute([$viewUserId]);
        $tasks = $stmt->fetchAll();

        foreach ($tasks as &$task) {
            $stepStmt = $pdo->prepare(
                "SELECT step_title, status, start_date, due_date
                 FROM task_steps WHERE task_id = ? ORDER BY sort_order ASC, id ASC"
            );
            $stepStmt->execute([(int) $task['id']]);
            $task['steps'] = $stepStmt->fetchAll();
        }
        unset($task);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | Weekly Task System</title>
    <link rel="stylesheet" href="assets/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                    <h1>Users</h1>
                </div>
            </header>

            <div class="admin-layout">
                <section class="board-panel">
                    <div class="section-heading">
                        <h2>All users</h2>
                    </div>
                    <?php if ($users): ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Tasks</th>
                                <th>Joined</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr class="<?php echo $viewUserId === (int) $u['id'] ? 'is-selected' : ''; ?>">
                                <td class="user-name-cell"><?php echo e($u['name'] ?: '—'); ?></td>
                                <td><?php echo e($u['username']); ?></td>
                                <td class="user-email-cell"><?php echo e($u['email'] ?: '—'); ?></td>
                                <td>
                                    <span class="task-count-badge"><?php echo (int) $u['task_count']; ?></span>
                                </td>
                                <td class="date-cell"><?php echo e(substr($u['created_at'], 0, 10)); ?></td>
                                <td>
                                    <div style="display:flex;gap:6px;justify-content:flex-end;">
                                        <a class="button button-ghost button-small"
                                           href="admin.php?user_id=<?php echo (int) $u['id']; ?>"
                                           title="View tasks">
                                            View tasks
                                        </a>
                                        <button class="button button-ghost button-small"
                                                onclick="openEditModal(<?php echo (int) $u['id']; ?>, '<?php echo e($u['name']); ?>', '<?php echo e($u['username']); ?>', '<?php echo e($u['email']); ?>')"
                                                title="Edit user">
                                            Edit
                                        </button>
                                        <button class="button button-ghost button-small"
                                                onclick="deleteUser(<?php echo (int) $u['id']; ?>, '<?php echo e($u['username']); ?>')"
                                                title="Delete user"
                                                style="color:var(--rose);border-color:rgba(196,69,105,0.3);">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">No users registered yet.</div>
                    <?php endif; ?>
                </section>

                <?php if ($viewUser): ?>
                <section class="board-panel" style="margin-top:18px">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">Viewing tasks for</p>
                            <h2><?php echo e($viewUser['name'] ?: $viewUser['username']); ?></h2>
                        </div>
                        <a class="button button-ghost button-small" href="admin.php">Clear</a>
                    </div>

                    <div class="user-info-grid">
                        <div class="user-info-item">
                            <span class="user-info-label">Name</span>
                            <span class="user-info-value"><?php echo e($viewUser['name'] ?: '—'); ?></span>
                        </div>
                        <div class="user-info-item">
                            <span class="user-info-label">Username</span>
                            <span class="user-info-value"><?php echo e($viewUser['username']); ?></span>
                        </div>
                        <div class="user-info-item">
                            <span class="user-info-label">Email</span>
                            <span class="user-info-value"><?php echo e($viewUser['email'] ?: '—'); ?></span>
                        </div>
                        <div class="user-info-item">
                            <span class="user-info-label">Total Tasks</span>
                            <span class="user-info-value highlight"><?php echo count($tasks); ?></span>
                        </div>
                    </div>

                    <?php if ($tasks): ?>
                    <div class="task-board">
                        <?php foreach ($tasks as $task): ?>
                        <div class="task-card <?php echo e($task['status']); ?>">
                            <div class="task-top">
                                <div>
                                    <div class="task-title-row">
                                        <h3><?php echo e($task['title']); ?></h3>
                                        <?php if ($task['category_name']): ?>
                                        <span class="category-chip"
                                              style="background:<?php echo e($task['category_color']); ?>">
                                            <?php echo e($task['category_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                        <span class="status-chip <?php echo e($task['status']); ?>">
                                            <?php echo e(status_text($task['status'])); ?>
                                        </span>
                                    </div>
                                    <?php if ($task['description']): ?>
                                    <p class="task-description"><?php echo e($task['description']); ?></p>
                                    <?php endif; ?>
                                    <p class="task-description" style="margin-top:6px;font-size:0.82rem">
                                        <?php echo e($task['start_date'] ?? '—'); ?> → <?php echo e($task['due_date'] ?? '—'); ?>
                                        &nbsp;·&nbsp; Week of <?php echo e($task['week_start'] ?? '—'); ?>
                                    </p>
                                </div>
                            </div>
                            <?php if ($task['steps']): ?>
                            <ul class="admin-step-list">
                                <?php foreach ($task['steps'] as $step): ?>
                                <li>
                                    <span class="status-chip <?php echo e($step['status']); ?>" style="font-size:0.7rem">
                                        <?php echo e(status_text($step['status'])); ?>
                                    </span>
                                    <?php echo e($step['step_title']); ?>
                                    <small><?php echo e($step['start_date'] ?? ''); ?> → <?php echo e($step['due_date'] ?? ''); ?></small>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">This user has no tasks.</div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit User</h2>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editUserForm" class="modal-body">
                <input type="hidden" id="editUserId" name="id">
                
                <label>
                    <span>Name</span>
                    <input type="text" id="editName" name="name">
                </label>

                <label>
                    <span>Username *</span>
                    <input type="text" id="editUsername" name="username" required>
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" id="editEmail" name="email">
                </label>

                <label>
                    <span>New Password (leave blank to keep current)</span>
                    <input type="password" id="editPassword" name="password" autocomplete="new-password">
                </label>

                <div class="modal-footer">
                    <button type="button" class="button button-ghost" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="button button-dark">Update User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, name, username, email) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editUsername').value = username;
            document.getElementById('editEmail').value = email;
            document.getElementById('editPassword').value = '';
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        document.getElementById('editUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('update_user.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: result.message,
                        confirmButtonColor: '#1f8a70'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message,
                        confirmButtonColor: '#1f8a70'
                    });
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to update user',
                    confirmButtonColor: '#1f8a70'
                });
            }
        });

        function deleteUser(id, username) {
            Swal.fire({
                title: 'Delete User?',
                html: `Are you sure you want to delete <strong>${username}</strong>?<br><small style="color:#667085;">This will permanently delete the user and all their tasks.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#c44569',
                cancelButtonColor: '#667085',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('delete_user.php?id=' + id)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: data.message,
                                    confirmButtonColor: '#1f8a70'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message,
                                    confirmButtonColor: '#1f8a70'
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Failed to delete user',
                                confirmButtonColor: '#1f8a70'
                            });
                        });
                }
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }

        <?php if (isset($_GET['deleted']) && $_GET['deleted'] === 'success'): ?>
        Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'User has been deleted successfully',
            confirmButtonColor: '#1f8a70'
        });
        <?php endif; ?>

        <?php if (isset($_GET['updated']) && $_GET['updated'] === 'success'): ?>
        Swal.fire({
            icon: 'success',
            title: 'Updated!',
            text: 'User has been updated successfully',
            confirmButtonColor: '#1f8a70'
        });
        <?php endif; ?>
    </script>
</body>
</html>
