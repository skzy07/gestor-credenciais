<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers.php';

$currentUser    = currentUser();
$unreadCount    = $currentUser ? getUnreadNotifCount((int)$currentUser['id']) : 0;
$csrfToken      = generateCsrfToken();
$avatarColor    = $currentUser['avatar_color'] ?? '#4F8EF7';
$avatarInitials = $currentUser ? initials($currentUser['username']) : '';

// Determine active nav link
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$activeClass = fn(string $page) => $currentPage === $page ? 'active' : '';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <script>
    const savedTheme = localStorage.getItem('vk_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= APP_NAME ?> — Gestor de credenciais seguro com criptografia E2EE">
  <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <meta name="csrf-token" content="<?= $csrfToken ?>">
</head>
<body>
<div class="app-wrapper">

<?php if ($currentUser): ?>
<nav class="navbar">
  <div class="container">
    <div class="nav-inner">

      <a href="<?= APP_URL ?>/dashboard.php" class="nav-logo" style="text-decoration:none">
        <div class="logo-icon">🔐</div>
        <span><?= APP_NAME ?></span>
      </a>

      <div class="nav-links">
        <a href="<?= APP_URL ?>/dashboard.php" class="nav-link <?= $activeClass('dashboard') ?>">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
          <span>Feed</span>
        </a>
        <a href="<?= APP_URL ?>/company/create.php" class="nav-link <?= $activeClass('create') ?>">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
          <span>Empresa</span>
        </a>
        <a href="<?= APP_URL ?>/notifications/index.php" class="nav-link <?= $activeClass('index') ?>" style="position:relative" id="nav-notif-link">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
          <span>Notificações</span>
          <?php if ($unreadCount > 0): ?>
          <span class="notif-badge" id="notif-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
          <?php endif; ?>
        </a>
      </div>

      <div style="display:flex; align-items:center; gap:10px;">
        <button class="btn-icon theme-toggle" title="Alterar Tema" style="border:none;background:transparent;box-shadow:none;color:var(--t2)">🌓</button>
        <div class="nav-user" id="nav-user-menu">
          <div class="nav-avatar" style="background:<?= sanitize($avatarColor) ?>"><?= sanitize($avatarInitials) ?></div>
        <span class="nav-username"><?= sanitize($currentUser['username']) ?></span>
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color:var(--t3)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        <div class="user-dropdown">
          <a href="<?= APP_URL ?>/company/manage.php" class="dropdown-item">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/></svg>
            Gerir a minha empresa
          </a>
          <hr class="dropdown-divider">
          <a href="<?= APP_URL ?>/api/auth.php?action=logout" class="dropdown-item danger" id="logout-btn">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Terminar sessão
          </a>
        </div>
      </div>
      </div>

    </div>
  </div>
</nav>
<?php endif; ?>

<div class="container">
