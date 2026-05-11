<?php
require_once __DIR__ . '/app.php';
require_auth($pdo);

redirect('dashboard.php#board');
