<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$token = trim($_GET['token'] ?? '');
$valid = false;
$username = '';

if ($token) {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT prt.user_id, u.username FROM password_reset_tokens prt
         JOIN users u ON u.id = prt.user_id
         WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used_at IS NULL LIMIT 1'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if ($row) { $valid = true; $username = $row['username']; }
}

$pageTitle = 'Nova Palavra-Passe';
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
  <div class="auth-card" style="max-width:480px">
    <div class="auth-logo">
      <div class="auth-logo-icon">🔐</div>
      <div class="auth-title">Nova Palavra-Passe</div>
    </div>

    <?php if (!$valid): ?>
      <div class="alert alert-error">❌ Link inválido ou expirado. <a href="<?= APP_URL ?>/reset-password.php">Pede um novo link.</a></div>
    <?php else: ?>
      <div class="alert alert-info" style="margin-bottom:20px">
        Olá <strong><?= htmlspecialchars($username) ?></strong>! Define a tua nova palavra-passe abaixo.
      </div>
      <div class="alert alert-warn" style="margin-bottom:20px;font-size:.82rem">
        ⚠️ Se tiveres o <strong>Código de Recuperação</strong>, podes preservar o acesso às tuas credenciais. Caso contrário, as credenciais antigas ficarão inacessíveis.
      </div>

      <div id="alert-box" class="alert alert-error" style="display:none"></div>

      <form id="reset-form">
        <div class="form-group">
          <label class="form-label">Nova Palavra-Passe *</label>
          <div class="input-group">
            <input type="password" name="new_password" id="new_password" class="form-input" placeholder="Mínimo 8 caracteres" required minlength="8">
            <span class="input-suffix toggle-password">👁</span>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmar Nova Palavra-Passe *</label>
          <input type="password" name="confirm" id="confirm_password" class="form-input" placeholder="Repetir nova password" required>
        </div>

        <div class="section-divider">Opcional — para preservar credenciais</div>

        <div class="form-group">
          <label class="form-label">Código de Recuperação (opcional)</label>
          <input type="text" name="recovery_code" id="recovery_code" class="form-input"
                 placeholder="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX" maxlength="32"
                 style="font-family:monospace;letter-spacing:.1em">
          <span class="form-hint">Se guardaste o código no registo, insere-o para manter acesso às credenciais.</span>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg" id="reset-btn">Guardar Nova Palavra-Passe</button>
      </form>

      <div id="step-success" style="display:none;text-align:center">
        <div style="font-size:3rem;margin-bottom:16px">✅</div>
        <div style="font-size:1rem;font-weight:600;margin-bottom:8px">Palavra-passe alterada!</div>
        <div style="font-size:.875rem;color:var(--t2);margin-bottom:24px">Já podes entrar com a nova password.</div>
        <a href="<?= APP_URL ?>/login.php" class="btn btn-primary">Entrar →</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<div class="toast-container" id="toast-container"></div>
<script src="<?= APP_URL ?>/assets/js/crypto.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<?php if ($valid): ?>
<script>
const RESET_TOKEN = <?= json_encode($token) ?>;

document.getElementById('reset-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn           = document.getElementById('reset-btn');
    const alertBox      = document.getElementById('alert-box');
    const newPassword   = document.getElementById('new_password').value;
    const confirm       = document.getElementById('confirm_password').value;
    const recoveryCode  = document.getElementById('recovery_code').value.trim().toUpperCase();
    alertBox.style.display = 'none';

    if (newPassword !== confirm) {
        alertBox.textContent = 'As passwords não coincidem.';
        alertBox.style.display = 'flex'; return;
    }

    setLoading(btn, true, 'A processar...');

    // Fetch the user's encrypted private key
    let encryptedPrivateKey, privateKeySalt, privateKeyIv;

    try {
        // We need to get user data for re-encryption - call the reset endpoint
        // First we need to load the user's keys using the recovery code if provided
        // The flow: reset endpoint accepts the new encrypted private key

        let privateKey;

        if (recoveryCode) {
            setLoading(btn, true, 'A desencriptar com código de recuperação...');
            // We need to get the recovery-encrypted private key
            const keyRes = await fetch('<?= APP_URL ?>/api/auth.php?action=get_reset_data', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ token: RESET_TOKEN })
            }).then(r => r.json());

            if (!keyRes.success) {
                alertBox.textContent = 'Erro ao obter dados. ' + keyRes.error;
                alertBox.style.display = 'flex';
                setLoading(btn, false); return;
            }

            privateKey = await VaultCrypto.decryptPrivateKeyWithRecovery(
                keyRes.data.recovery_encrypted_private_key,
                keyRes.data.recovery_key_iv,
                keyRes.data.recovery_key_salt,
                recoveryCode
            );
        } else {
            // Generate new keypair (old credentials become inaccessible)
            setLoading(btn, true, 'A gerar novas chaves...');
            const { privateKey: pk } = await VaultCrypto.generateKeyPair();
            privateKey = pk;
        }

        setLoading(btn, true, 'A encriptar com nova password...');
        const { encryptedPrivateKey: epk, iv, salt }
            = await VaultCrypto.encryptPrivateKeyWithPassword(privateKey, newPassword);

        encryptedPrivateKey = epk;
        privateKeySalt      = salt;
        privateKeyIv        = iv;

    } catch (err) {
        alertBox.textContent = recoveryCode
            ? 'Código de recuperação incorreto.'
            : 'Erro ao processar: ' + err.message;
        alertBox.style.display = 'flex';
        setLoading(btn, false); return;
    }

    const r = await fetch('<?= APP_URL ?>/api/auth.php?action=reset_password', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            token: RESET_TOKEN,
            new_password: newPassword,
            encrypted_private_key: encryptedPrivateKey,
            private_key_salt:      privateKeySalt,
            private_key_iv:        privateKeyIv
        })
    }).then(r => r.json());

    setLoading(btn, false);
    if (r.success) {
        document.getElementById('reset-form').style.display = 'none';
        document.getElementById('step-success').style.display = 'block';
    } else {
        alertBox.textContent = r.error;
        alertBox.style.display = 'flex';
    }
});

// Toggle password
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', function() {
        const input = this.closest('.input-group')?.querySelector('input');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        this.textContent = input.type === 'password' ? '👁' : '🙈';
    });
});
</script>
<?php endif; ?>
</div></body></html>
