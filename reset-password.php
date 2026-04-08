<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }
$pageTitle = 'Recuperar Palavra-Passe';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <script>
    const savedTheme = localStorage.getItem('vk_theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
  </script>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="app-url" content="<?= APP_URL ?>">
  <title><?= $pageTitle ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
<button class="theme-toggle" style="position:fixed; top:20px; right:24px; border:none; background:var(--glass); border-radius:50%; width:40px; height:40px; font-size:1.2rem; cursor:pointer; color:var(--t2); box-shadow:var(--sh-sm); z-index:999; border:1px solid var(--glass-b);">🌓</button>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="auth-logo-icon">🔐</div>
      <div class="auth-title">Recuperar Acesso</div>
      <div class="auth-subtitle">Envia-te-emos um email com um link para redefinires a password</div>
    </div>

    <div id="alert-box" class="alert" style="display:none"></div>

    <div id="step-form">
      <form id="forgot-form">
        <div class="form-group">
          <label class="form-label">Email da conta</label>
          <input type="email" name="email" id="email" class="form-input" placeholder="tecnico@empresa.pt" required autocomplete="email">
        </div>
        <button type="submit" class="btn btn-primary btn-full btn-lg" id="send-btn">Enviar Email de Recuperação</button>
      </form>
    </div>

    <div id="step-sent" style="display:none;text-align:center">
      <div style="font-size:3rem;margin-bottom:16px">📬</div>
      <div style="font-size:1rem;font-weight:600;margin-bottom:8px">Email enviado!</div>
      <div style="font-size:.875rem;color:var(--t2);margin-bottom:24px">
        Se o email existir na nossa base de dados, receberás um link de recuperação em breve.<br>Verifica também a pasta de spam.
      </div>
      <a href="<?= APP_URL ?>/login.php" class="btn btn-ghost">← Voltar ao login</a>
    </div>

    <div class="auth-footer"><a href="<?= APP_URL ?>/login.php">← Voltar ao login</a></div>
  </div>
</div>
<div class="toast-container" id="toast-container"></div>
<script src="<?= APP_URL ?>/assets/js/crypto.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
document.getElementById('forgot-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn   = document.getElementById('send-btn');
    const alert = document.getElementById('alert-box');
    alert.style.display = 'none';

    setLoading(btn, true, 'A enviar...');
    const r = await fetch('<?= APP_URL ?>/api/auth.php?action=forgot_password', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ email: document.getElementById('email').value.trim() })
    }).then(r => r.json());

    setLoading(btn, false);
    if (r.success) {
        document.getElementById('step-form').style.display = 'none';
        document.getElementById('step-sent').style.display = 'block';
    } else {
        alert.className = 'alert alert-error';
        alert.textContent = r.error;
        alert.style.display = 'flex';
    }
});
</script>
</div></body></html>
