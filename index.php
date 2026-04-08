<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }
header('Location: ' . APP_URL . '/login.php'); exit;
