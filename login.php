<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }
$pageTitle = 'Entrar';
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
      <div class="auth-title"><?= APP_NAME ?></div>
      <div class="auth-subtitle">Gestor de credenciais seguro com E2EE</div>
    </div>

    <div id="alert-box" class="alert alert-error" style="display:none"></div>

    <form id="login-form">
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" name="email" id="email" class="form-input" placeholder="tecnico@empresa.pt" required autocomplete="email">
      </div>
      <div class="form-group">
        <label class="form-label">Palavra-Passe</label>
        <div class="input-group">
          <input type="password" name="password" id="password" class="form-input" placeholder="••••••••" required autocomplete="current-password">
          <span class="input-suffix toggle-password">👁</span>
        </div>
      </div>

      <div style="text-align:right;margin-bottom:20px">
        <a href="<?= APP_URL ?>/reset-password.php" style="font-size:.82rem;color:var(--t3)">Esqueceste a palavra-passe?</a>
      </div>

      <button type="submit" class="btn btn-primary btn-full btn-lg" id="login-btn">Entrar</button>
    </form>

    <div class="auth-footer">
      Ainda não tens conta? <a href="<?= APP_URL ?>/register.php">Criar conta</a>
    </div>
  </div>
</div>
<div class="toast-container" id="toast-container"></div>

<script src="<?= APP_URL ?>/assets/js/crypto.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
document.getElementById('login-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn   = document.getElementById('login-btn');
    const alert = document.getElementById('alert-box');
    alert.style.display = 'none';

    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    setLoading(btn, true, 'A verificar...');

    try {
        const r = await fetch('<?= APP_URL ?>/api/auth.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        }).then(r => r.json());

        if (!r.success) {
            alert.textContent = r.error || 'Erro ao entrar.';
            alert.style.display = 'flex';
            setLoading(btn, false);
            return;
        }

        // Desencriptar chave privada com a password
        setLoading(btn, true, 'A carregar chaves...');
        const { encrypted_private_key, private_key_salt, private_key_iv } = r.data;
        const privateKey = await VaultCrypto.decryptPrivateKeyWithPassword(
            encrypted_private_key, private_key_iv, private_key_salt, password
        );

        // Guardar na sessão JS
        await VaultCrypto.savePrivateKeyToSession(privateKey);
        VaultCrypto.saveUserToSession({
            id:           r.data.user_id,
            username:     r.data.username,
            email:        r.data.email,
            avatar_color: r.data.avatar_color,
            public_key:   r.data.public_key
        });

        window.location.href = '<?= APP_URL ?>/dashboard.php';

    } catch (err) {
        alert.textContent = 'Erro inesperado: ' + err.message;
        alert.style.display = 'flex';
        setLoading(btn, false);
    }
});
</script>
</div>
</body>
</html>
