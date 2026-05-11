<?php
declare(strict_types=1);

function smtp_read($socket): array
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return [(int) substr($response, 0, 3), $response];
}

function smtp_command($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    [$code, $response] = smtp_read($socket);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException(trim($response) ?: 'Unexpected SMTP response.');
    }

    return $response;
}

function smtp_mailbox(string $email, string $name = ''): string
{
    $email = trim(str_replace(["\r", "\n"], '', $email));
    $name = trim(str_replace(["\r", "\n", '"'], '', $name));

    if ($name === '') {
        return "<{$email}>";
    }

    return '"' . addslashes($name) . "\" <{$email}>";
}

function smtp_subject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', $subject)) . '?=';
}

function smtp_body(string $message): string
{
    $message = preg_replace("/\r\n|\r|\n/", "\r\n", $message);
    $lines = explode("\r\n", $message);
    foreach ($lines as &$line) {
        if (str_starts_with($line, '.')) {
            $line = '.' . $line;
        }
    }
    unset($line);

    return implode("\r\n", $lines);
}

function send_gmail_message(array $config, string $toEmail, string $toName, string $subject, string $message): void
{
    $host = (string) ($config['host'] ?? 'smtp.gmail.com');
    $port = (int) ($config['port'] ?? 587);
    $username = (string) ($config['username'] ?? '');
    $password = (string) ($config['password'] ?? '');
    $fromEmail = (string) ($config['from_email'] ?? $username);
    $fromName = (string) ($config['from_name'] ?? 'Weekly Task System');

    if ($username === '' || $password === '' || $fromEmail === '') {
        throw new RuntimeException('Gmail SMTP is not configured. Set GMAIL_USERNAME and GMAIL_APP_PASSWORD.');
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Recipient email is invalid.');
    }

    $transport = $port === 465 ? 'ssl' : 'tcp';
    $socket = stream_socket_client(
        "{$transport}://{$host}:{$port}",
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new RuntimeException("SMTP connection failed: {$errstr}");
    }

    stream_set_timeout($socket, 30);

    try {
        [$code, $response] = smtp_read($socket);
        if ($code !== 220) {
            throw new RuntimeException(trim($response));
        }

        smtp_command($socket, 'EHLO localhost', [250]);

        if ($port !== 465) {
            smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to start TLS for Gmail SMTP.');
            }
            smtp_command($socket, 'EHLO localhost', [250]);
        }

        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($username), [334]);
        smtp_command($socket, base64_encode($password), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $headers = [
            'From: ' . smtp_mailbox($fromEmail, $fromName),
            'To: ' . smtp_mailbox($toEmail, $toName),
            'Subject: ' . smtp_subject($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . smtp_body($message);
        fwrite($socket, $payload . "\r\n.\r\n");
        [$code, $response] = smtp_read($socket);
        if ($code !== 250) {
            throw new RuntimeException(trim($response));
        }

        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
