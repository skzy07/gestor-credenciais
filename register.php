<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }
$pageTitle = 'Criar Conta';
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
  <div class="auth-card" style="max-width:500px">
    <div class="auth-logo">
      <div class="auth-logo-icon">🔐</div>
      <div class="auth-title">Criar Conta</div>
      <div class="auth-subtitle">Serão geradas chaves criptográficas para proteger os teus dados</div>
    </div>

    <div id="alert-box" class="alert alert-error" style="display:none"></div>

    <!-- Passo 1: Formulário -->
    <div id="step-form">
      <form id="register-form">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input type="text" name="username" id="username" class="form-input" placeholder="tecnico_silva" required minlength="3" maxlength="50">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" id="email" class="form-input" placeholder="tecnico@empresa.pt" required>
        </div>
        <div class="form-group">
          <label class="form-label">Palavra-Passe</label>
          <div class="input-group">
            <input type="password" name="password" id="password" class="form-input" placeholder="Mínimo 8 caracteres" required minlength="8">
            <span class="input-suffix toggle-password">👁</span>
          </div>
          <span class="form-hint">Usa uma password forte — ela protege as tuas chaves criptográficas</span>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar Palavra-Passe</label>
          <input type="password" name="password_confirm" id="password_confirm" class="form-input" placeholder="Repetir password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full btn-lg" id="register-btn">Criar Conta</button>
      </form>
    </div>

    <!-- Passo 2: Generating keys loading -->
    <div id="step-generating" style="display:none">
      <div class="loading-overlay" style="flex-direction:column;gap:24px">
        <span class="spinner spinner-lg"></span>
        <div style="text-align:center">
          <div style="font-size:1rem;font-weight:600;margin-bottom:8px" id="gen-status">A gerar par de chaves RSA-4096...</div>
          <div style="font-size:.83rem;color:var(--t2)">Isto pode demorar alguns segundos. Por favor não feches a página.</div>
        </div>
      </div>
    </div>

    <!-- Passo 3: Recovery code -->
    <div id="step-recovery" style="display:none">
      <div class="alert alert-warn">⚠️ <strong>Guarda este código!</strong> Sem ele, se esqueceres a password perderás acesso permanente às tuas credenciais.</div>
      <div class="recovery-code" id="recovery-code-display"></div>
      <div style="display:flex;gap:10px;margin-bottom:20px">
        <button class="btn btn-ghost btn-full" id="copy-recovery-btn">📋 Copiar</button>
      </div>
      <div class="form-group">
        <label style="display:flex;gap:10px;align-items:center;cursor:pointer;font-size:.875rem;color:var(--t2)">
          <input type="checkbox" id="recovery-confirm"> Guardei o código de recuperação em local seguro
        </label>
      </div>
      <button class="btn btn-success btn-full btn-lg" id="confirm-recovery-btn" disabled>Continuar para o Dashboard →</button>
    </div>

    <div class="auth-footer">Já tens conta? <a href="<?= APP_URL ?>/login.php">Entrar</a></div>
  </div>
</div>
<div class="toast-container" id="toast-container"></div>

<script src="<?= APP_URL ?>/assets/js/crypto.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
let _privateKey, _recoveryCode;

document.getElementById('register-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const alert    = document.getElementById('alert-box');
    alert.style.display = 'none';

    const username = document.getElementById('username').value.trim();
    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirm  = document.getElementById('password_confirm').value;

    if (password !== confirm) {
        alert.textContent = 'As passwords não coincidem.';
        alert.style.display = 'flex'; return;
    }

    document.getElementById('step-form').style.display = 'none';
    document.getElementById('step-generating').style.display = 'block';

    try {
        const status = document.getElementById('gen-status');

        status.textContent = 'A gerar par de chaves RSA-4096...';
        const { publicKey, privateKey } = await VaultCrypto.generateKeyPair();
        _privateKey = privateKey;

        status.textContent = 'A encriptar chave privada com a tua password...';
        const { encryptedPrivateKey, iv: pkIv, salt: pkSalt }
            = await VaultCrypto.encryptPrivateKeyWithPassword(privateKey, password);

        status.textContent = 'A gerar código de recuperação...';
        _recoveryCode = VaultCrypto.generateRecoveryCode();
        const { encryptedPrivateKey: recPriv, iv: recIv, salt: recSalt }
            = await VaultCrypto.encryptPrivateKeyWithRecovery(privateKey, _recoveryCode);

        status.textContent = 'A registar conta...';
        const publicKeyJwk = await VaultCrypto.exportPublicKey(publicKey);

        const r = await fetch('<?= APP_URL ?>/api/auth.php?action=register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                username, email, password,
                public_key:                       publicKeyJwk,
                encrypted_private_key:            encryptedPrivateKey,
                private_key_salt:                 pkSalt,
                private_key_iv:                   pkIv,
                recovery_encrypted_private_key:   recPriv,
                recovery_key_salt:                recSalt,
                recovery_key_iv:                  recIv
            })
        }).then(r => r.json());

        if (!r.success) {
            alert.textContent = r.error;
            alert.style.display = 'flex';
            document.getElementById('step-generating').style.display = 'none';
            document.getElementById('step-form').style.display = 'block';
            return;
        }

        document.getElementById('step-generating').style.display = 'none';
        document.getElementById('recovery-code-display').textContent = _recoveryCode;
        document.getElementById('step-recovery').style.display = 'block';

    } catch (err) {
        alert.textContent = 'Erro: ' + err.message;
        alert.style.display = 'flex';
        document.getElementById('step-generating').style.display = 'none';
        document.getElementById('step-form').style.display = 'block';
    }
});

document.getElementById('copy-recovery-btn').addEventListener('click', () => {
    copyToClipboard(_recoveryCode, 'Código de recuperação');
});

document.getElementById('recovery-confirm').addEventListener('change', function() {
    document.getElementById('confirm-recovery-btn').disabled = !this.checked;
});

document.getElementById('confirm-recovery-btn').addEventListener('click', async () => {
    // Fazer login automático
    const r = await fetch('<?= APP_URL ?>/api/auth.php?action=login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            email: document.getElementById('email').value.trim(),
            password: document.getElementById('password').value
        })
    }).then(r => r.json());

    if (r.success) {
        await VaultCrypto.savePrivateKeyToSession(_privateKey);
        VaultCrypto.saveUserToSession({
            id: r.data.user_id, username: r.data.username,
            email: r.data.email, avatar_color: r.data.avatar_color,
            public_key: r.data.public_key
        });
        window.location.href = '<?= APP_URL ?>/dashboard.php';
    } else {
        window.location.href = '<?= APP_URL ?>/login.php';
    }
});
</script>
</div></body></html>
