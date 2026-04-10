<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$pageTitle = 'Minhas Credenciais';
$extraScripts = ['credentials.js'];
include __DIR__ . '/../includes/header.php';
?>
<meta name="app-url" content="<?= APP_URL ?>">
<div class="page-content">
  <div class="page-header">
    <h1 class="page-title">Minhas Credenciais 🔑</h1>
    <p class="page-subtitle">Todas as credenciais às quais tens acesso, com identificação da empresa.</p>
  </div>

  <div id="global-credentials-list">
    <div class="loading-overlay"><span class="spinner"></span> A carregar...</div>
  </div>
</div>

<!-- Modal: Ver Credencial -->
<div class="modal-overlay" id="reveal-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">🔑 Credencial</div>
      <button class="modal-close">✕</button>
    </div>
    <div class="credential-reveal">
      <div class="reveal-row">
        <span class="reveal-label">Criado por</span>
        <span class="reveal-value" id="reveal-added-by" style="font-weight:600;color:var(--t1)"></span>
      </div>
      <div class="reveal-row">
        <span class="reveal-label">Username</span>
        <span class="reveal-value"><span id="reveal-username"></span>
          <button class="copy-btn" data-target="reveal-username" data-label="Username">📋</button></span>
      </div>
      <div class="reveal-row">
        <span class="reveal-label">Password</span>
        <span class="reveal-value"><span id="reveal-password"></span>
          <button class="copy-btn" data-target="reveal-password" data-label="Password">📋</button></span>
      </div>
      <div class="reveal-row">
        <span class="reveal-label">URL</span>
        <span class="reveal-value"><span id="reveal-url"></span>
          <button class="copy-btn" data-target="reveal-url" data-label="URL">📋</button></span>
      </div>
      <div class="reveal-row">
        <span class="reveal-label">Notas</span>
        <span class="reveal-value" style="flex-direction:column;align-items:flex-start">
          <span id="reveal-notes"></span></span>
      </div>
    </div>
    <div style="margin-top:16px">
      <div class="alert alert-warn" style="font-size:.8rem">⚠️ Fecha esta janela quando terminares. Os dados não ficam guardados no ecrã.</div>
    </div>
  </div>
</div>

<!-- Modal: Editar Credencial -->
<div class="modal-overlay" id="edit-cred-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">✏️ Editar Credencial</div>
      <button class="modal-close">✕</button>
    </div>
    <form id="edit-cred-form">
      <input type="hidden" id="edit-label-raw">
      <div class="form-group">
        <label class="form-label">Etiqueta *</label>
        <input type="text" name="label" class="form-input" placeholder="Ex: FTP Servidor, cPanel, SSH..." required>
      </div>
      <div class="form-group">
        <label class="form-label">Username *</label>
        <input type="text" name="username" class="form-input" autocomplete="off" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password *</label>
        <div class="input-group">
          <input type="password" name="password" class="form-input" required autocomplete="new-password">
          <span class="input-suffix toggle-password">👁</span>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">URL / Endereço</label>
        <input type="text" name="url" class="form-input" placeholder="https://...">
      </div>
      <div class="form-group">
        <label class="form-label">Notas</label>
        <textarea name="notes" class="form-textarea" placeholder="Informações adicionais..."></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:4px">
        <button type="button" class="btn btn-ghost modal-close">Cancelar</button>
        <button type="submit" class="btn btn-primary">🔐 Guardar Alterações</button>
      </div>
    </form>
  </div>
</div>

<?php
ob_start();
?>
// Inicializa sem contexto de empresa (modo global)
(async () => {
    const privateKey = await VaultCrypto.loadPrivateKeyFromSession();
    if (!privateKey) {
        Toast.error('Sessão expirou. Faz login novamente.');
        setTimeout(() => location.href = '<?= APP_URL ?>/login.php', 2000);
        return;
    }
    CredentialsManager.privateKey = privateKey;
    CredentialsManager.bindEvents();

    // Carregar credenciais globais
    const list = document.getElementById('global-credentials-list');
    const r = await API.get('api/credentials.php?action=my_credentials');
    if (!r.success) {
        list.innerHTML = `<div class="alert alert-error">${r.error}</div>`;
        return;
    }
    const { credentials } = r.data;
    if (!credentials || !credentials.length) {
        list.innerHTML = `
          <div class="empty-state">
            <div class="empty-icon">🔑</div>
            <div class="empty-title">Sem credenciais</div>
            <div class="empty-text">Ainda não tens acesso a nenhuma credencial.</div>
          </div>`;
        return;
    }
    list.innerHTML = credentials.map(cr => CredentialsManager.renderGlobalCredentialItem(cr)).join('');

    // Copy buttons no reveal modal
    document.querySelectorAll('.copy-btn[data-target]').forEach(btn => {
        btn.addEventListener('click', () => {
            const val = document.getElementById(btn.dataset.target)?.textContent;
            if (val) copyToClipboard(val, btn.dataset.label || 'Valor');
        });
    });

    // Toggle password em modais
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.closest('.input-group')?.querySelector('input');
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            this.textContent = input.type === 'password' ? '👁' : '🙈';
        });
    });

    // Recarregar após fechar modal de reveal (Burn-on-Read)
    document.addEventListener('modalClosed', async e => {
        if (e.detail === 'reveal-modal') {
            ['reveal-added-by','reveal-username','reveal-password','reveal-url','reveal-notes']
                .forEach(id => { const el = document.getElementById(id); if(el) el.textContent = '—'; });
            // Recarregar lista para atualizar estado de acesso (burn-on-read)
            const r2 = await API.get('api/credentials.php?action=my_credentials');
            if (r2.success && r2.data.credentials) {
                list.innerHTML = r2.data.credentials.map(cr => CredentialsManager.renderGlobalCredentialItem(cr)).join('');
            }
        }
        if (e.detail === 'edit-cred-modal') {
            // Recarregar após edição
            const r3 = await API.get('api/credentials.php?action=my_credentials');
            if (r3.success && r3.data.credentials) {
                list.innerHTML = r3.data.credentials.map(cr => CredentialsManager.renderGlobalCredentialItem(cr)).join('');
            }
        }
    });
})();
<?php
$inlineScript = ob_get_clean();
include __DIR__ . '/../includes/footer.php';
?>
