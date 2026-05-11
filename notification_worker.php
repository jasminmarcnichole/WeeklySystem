<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/gmail_mailer.php';

$config = require __DIR__ . '/mail_config.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

refresh_week_state($pdo);

if (empty($config['username']) || empty($config['password']) || empty($config['from_email'])) {
    echo "Gmail SMTP is not configured.\n";
    echo "Set GMAIL_USERNAME, GMAIL_APP_PASSWORD, and optionally GMAIL_FROM_EMAIL.\n";
    exit(2);
}

$limit = 25;
if (PHP_SAPI === 'cli' && isset($argv[1])) {
    $limit = max(1, min(100, (int) $argv[1]));
} elseif (isset($_GET['limit'])) {
    $limit = max(1, min(100, (int) $_GET['limit']));
}

$stmt = $pdo->prepare(
    "SELECT n.*, u.email, u.name, u.username
     FROM notifications n
     INNER JOIN users u ON u.id = n.user_id
     WHERE n.status = 'queued'
       AND n.scheduled_for <= NOW()
     ORDER BY n.scheduled_for ASC
     LIMIT {$limit}"
);
$stmt->execute();
$notifications = $stmt->fetchAll();

$sent = 0;
$failed = 0;

foreach ($notifications as $notice) {
    $recipientName = $notice['name'] ?: $notice['username'];
    $body = $notice['message'] . "\n\nStatus: " . strtoupper($notice['status']) . "\nWeekly Task System";

    try {
        send_gmail_message(
            $config,
            (string) $notice['email'],
            (string) $recipientName,
            (string) $notice['subject'],
            $body
        );

        $update = $pdo->prepare(
            "UPDATE notifications
             SET status = 'sent', sent_at = NOW(), last_error = NULL
             WHERE id = ?"
        );
        $update->execute([(int) $notice['id']]);
        $sent++;
    } catch (Throwable $e) {
        $update = $pdo->prepare(
            "UPDATE notifications
             SET status = 'failed', last_error = ?
             WHERE id = ?"
        );
        $update->execute([$e->getMessage(), (int) $notice['id']]);
        $failed++;
    }
}

echo "Processed " . count($notifications) . " notification(s). Sent: {$sent}. Failed: {$failed}.\n";
