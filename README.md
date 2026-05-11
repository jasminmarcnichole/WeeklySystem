# Weekly Task System

Premium weekly planning board for Sunday/Monday task entry, task steps, day-by-day Gantt tracking, status updates, and Gmail reminder delivery.

## Run locally

1. Start MySQL in XAMPP.
2. Open the app from Apache at `http://localhost/WeeklySystem/`, or run the PHP server:

```powershell
php -S 127.0.0.1:8018 -t .
```

The app creates and upgrades its database schema automatically. You can also import `database.sql` manually.

## Gmail notifications

The dashboard queues reminder emails. Password reset codes are sent immediately through the same Gmail SMTP settings. Send queued task reminders with:

```powershell
php notification_worker.php
```

Set these environment variables before running the worker:

```powershell
$env:GMAIL_USERNAME="your-gmail-address@gmail.com"
$env:GMAIL_APP_PASSWORD="your-google-app-password"
$env:GMAIL_FROM_EMAIL="your-gmail-address@gmail.com"
```

Use a Google App Password, not the normal Gmail login password.

## Forgot password

Users can open `forgot_password.php`, enter their registered email, and receive a 6-digit code. The code expires after 15 minutes and is consumed after a successful password reset.
