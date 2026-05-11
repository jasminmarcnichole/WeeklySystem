<?php
return [
    'host' => getenv('GMAIL_SMTP_HOST') ?: 'smtp.gmail.com',
    'port' => (int) (getenv('GMAIL_SMTP_PORT') ?: 587),
    'username' => getenv('GMAIL_USERNAME') ?: 'slsuhris@gmail.com',
    'password' => getenv('GMAIL_APP_PASSWORD') ?: 'nlel jzad ujek jihd',
    'from_email' => getenv('GMAIL_FROM_EMAIL') ?: 'slsuhris@gmail.com',
    'from_name' => getenv('GMAIL_FROM_NAME') ?: 'Weekly Task System',
];
