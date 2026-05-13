<?php
require_once __DIR__ . '/app.php';
$user = current_user($pdo);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weekly Task System</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="landing-page">
    <header class="site-header">
        <a class="brand" href="index.php">
            <span class="brand-mark">W</span>
            <span>Weekly Task System</span>
        </a>
        <nav class="site-nav" aria-label="Main navigation">
            <?php if ($user): ?>
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="button button-dark" href="logout.php">Logout</a>
            <?php else: ?>
                <a class="nav-link" href="login.php">Login</a>
                <a class="button button-dark" href="register.php">Register</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <section class="hero-shell">
            <div class="hero-copy">
                <p class="eyebrow">Sunday/Monday weekly planning</p>
                <h1>Plan the week once, move every step with intention.</h1>
                <p class="hero-text">
                    A weekly execution board for large tasks, smaller steps, day-by-day progress,
                    locked failed schedules, and Gmail deadline notices.
                </p>
                <div class="hero-actions">
                    <a class="button button-dark" href="<?php echo $user ? (has_role($user, 'admin') ? 'admin.php' : 'dashboard.php') : 'register.php'; ?>">
                        Open board
                    </a>
                    <a class="button button-light" href="<?php echo $user ? (has_role($user, 'admin') ? 'admin.php' : 'dashboard.php#new-task') : 'login.php'; ?>">
                        Start planning
                    </a>
                </div>
            </div>

            <div class="hero-board" aria-label="Weekly board preview">
                <div class="preview-toolbar">
                    <span>Week of May 4</span>
                    <span class="preview-pill">Live board</span>
                </div>
                <div class="preview-grid">
                    <div class="preview-label">Task</div>
                    <div>Mon</div>
                    <div>Tue</div>
                    <div>Wed</div>
                    <div>Thu</div>
                    <div>Fri</div>
                    <div>Sat</div>
                    <div>Sun</div>

                    <div class="preview-task">
                        <strong>Client launch</strong>
                        <small>Work</small>
                    </div>
                    <div class="preview-cell active span-2"></div>
                    <div class="preview-cell active"></div>
                    <div class="preview-cell"></div>
                    <div class="preview-cell active warn"></div>
                    <div class="preview-cell active done"></div>
                    <div class="preview-cell"></div>
                    <div class="preview-cell"></div>

                    <div class="preview-task">
                        <strong>Research module</strong>
                        <small>Study</small>
                    </div>
                    <div class="preview-cell"></div>
                    <div class="preview-cell active"></div>
                    <div class="preview-cell active"></div>
                    <div class="preview-cell active"></div>
                    <div class="preview-cell"></div>
                    <div class="preview-cell"></div>
                    <div class="preview-cell"></div>
                </div>
            </div>
        </section>

        <section class="feature-band">
            <div>
                <span class="feature-kicker">01</span>
                <h2>Weekly entry discipline</h2>
                <p>Create the plan during Sunday/Monday and keep the rest of the week focused on execution.</p>
            </div>
            <div>
                <span class="feature-kicker">02</span>
                <h2>Gantt-style step flow</h2>
                <p>Every task carries details, category color, start date, deadline, and step bars across the days.</p>
            </div>
            <div>
                <span class="feature-kicker">03</span>
                <h2>Gmail notification queue</h2>
                <p>Start reminders, deadline warnings, and failed-lock notices are prepared for Gmail delivery.</p>
            </div>
        </section>
    </main>
</body>
</html>
