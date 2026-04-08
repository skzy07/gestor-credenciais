<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$companyId = (int)($_GET['id'] ?? 0);
if (!$companyId) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$db   = getDB();
$stmt = $db->prepare(
    'SELECT c.*, u.username AS owner_username, u.avatar_color AS owner_avatar, u.id AS owner_user_id
     FROM companies c JOIN users u ON u.id = c.owner_id WHERE c.id = ? LIMIT 1'
);
$stmt->execute([$companyId]);
$company = $stmt->fetch();
if (!$company) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$isOwner   = (int)$company['owner_user_id'] === currentUserId();

$addReqStatus = null;
if (!$isOwner) {
    $stmt = $db->prepare('SELECT status FROM access_requests WHERE type = ? AND company_id = ? AND requester_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute(['add_to_company', $companyId, currentUserId()]);
    $addReqStatus = $stmt->fetchColumn(); // 'pending', 'approved', 'denied' or false
}

$pageTitle = sanitize($company['name']);
include __DIR__ . '/../includes/header.php';
?>
<meta name="app-url" content="<?= APP_URL ?>">
<div class="page-content">
  <a href="<?= APP_URL ?>/dashboard.php" style="font-size:.85rem;color:var(--t3);margin-bottom:20px;display:inline-block">← Voltar ao feed</a>

  <div class="view-layout">
    <!-- Sidebar: info da empresa -->
    <div>
      <div class="card card-glow-blue" style="margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
          <div class="company-icon" style="width:52px;height:52px;font-size:1.4rem">🏢</div>
          <div>
            <div style="font-size:1.2rem;font-weight:800"><?= sanitize($company['name']) ?></div>
            <div style="font-size:.78rem;color:var(--t3);font-family:monospace">NIF: <?= sanitize($company['nif']) ?></div>
          </div>
        </div>
        <p style="font-size:.875rem;color:var(--t2);margin-bottom:16px"><?= sanitize($company['description'] ?: 'Sem descrição.') ?></p>
        <div style="border-top:1px solid var(--glass-b);padding-top:14px;display:flex;align-items:center;gap:8px">
          <div class="owner-dot" style="background:<?= sanitize($company['owner_avatar']) ?>"><?= sanitize(initials($company['owner_username'])) ?></div>
          <div>
            <div style="font-size:.8rem;color:var(--t3)">Dono</div>
            <div style="font-size:.875rem;font-weight:600"><?= sanitize($company['owner_username']) ?></div>
          </div>
        </div>
      </div>

      <?php if ($isOwner): ?>
      <div class="card" style="margin-bottom:16px">
        <div class="panel-title">Gestão</div>
        <button class="btn btn-ghost btn-full" id="btn-invite-technician" style="margin-bottom:8px">
          👨‍💻 Convidar Técnico
        </button>
      </div>
      <?php elseif (!$isOwner && $addReqStatus !== 'approved'): ?>
      <div class="card" style="margin-bottom:16px">
        <div class="panel-title">Ações</div>
        <button class="btn btn-ghost btn-full" id="btn-request-add" style="margin-bottom:8px" <?= $addReqStatus === 'pending' ? 'disabled' : '' ?>>
          <?= $addReqStatus === 'pending' ? '⏳ Pedido Pendente' : '➕ Pedir para adicionar' ?>
        </button>
      </div>
      <?php endif; ?>

      <div class="panel">
        <div class="panel-title">Criada em</div>
        <div style="font-size:.875rem;color:var(--t2)"><?= date('d/m/Y', strtotime($company['created_at'])) ?></div>
      </div>
    </div>

    <!-- Main: credenciais -->
    <div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <h2 style="font-size:1.2rem;font-weight:700">Credenciais</h2>
        <?php if ($isOwner || $addReqStatus === 'approved'): ?>
        <button class="btn btn-primary" id="btn-add-cred">+ Adicionar Credencial</button>
        <?php endif; ?>
      </div>

      <div id="credentials-list">
        <div class="loading-overlay"><span class="spinner"></span></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Adicionar Credencial -->
<div class="modal-overlay" id="add-cred-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">🔑 Nova Credencial</div>
      <button class="modal-close">✕</button>
    </div>
    <form id="add-cred-form">
      <div class="form-group">
        <label class="form-label">Etiqueta *</label>
        <input type="text" name="label" class="form-input" placeholder="Ex: FTP Servidor, cPanel, SSH..." required>
      </div>
      <div class="form-group">
        <label class="form-label">Username *</label>
        <input type="text" name="username" class="form-input" placeholder="admin" required autocomplete="off">
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
      <button type="submit" class="btn btn-primary btn-full">🔐 Encriptar e Guardar</button>
    </form>
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

<!-- Modal: Convidar Técnico -->
<div class="modal-overlay" id="invite-tech-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">👨‍💻 Convidar Técnico</div>
      <button class="modal-close">✕</button>
    </div>
    <form id="invite-tech-form">
      <div class="form-group">
        <label class="form-label">E-mail do Técnico *</label>
        <input type="email" name="technician_email" class="form-input" placeholder="ex: mario@empresa.pt" required>
        <div style="font-size: 0.75rem; color: var(--t3); margin-top: 6px;">
          * O técnico já deve ter conta no VaultKeeper. Este processo pode demorar alguns segundos, pois a sua Chave Pública será usada para desencriptar e re-encriptar um Mega-Payload de permissões automaticamente.
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
        <button type="button" class="btn btn-ghost modal-close">Cancelar</button>
        <button type="submit" class="btn btn-primary" id="btn-submit-invite">Convidar</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraScripts = ['credentials.js'];
ob_start();
?>
const COMPANY_ID = <?= $companyId ?>;
const IS_OWNER   = <?= $isOwner ? 'true' : 'false' ?>;
const OWNER_ID   = <?= (int)$company['owner_user_id'] ?>;

CredentialsManager.init(COMPANY_ID, IS_OWNER, OWNER_ID);

// Convidar Técnico
const inviteModal = document.getElementById('invite-tech-modal');
document.getElementById('btn-invite-technician')?.addEventListener('click', () => {
    inviteModal.classList.add('active');
});
inviteModal?.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', () => inviteModal.classList.remove('active'));
});
document.getElementById('invite-tech-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-invite');
    const email = this.technician_email.value.trim();
    if (!email) return;

    setLoading(btn, true, 'A encriptar payload...');
    await CredentialsManager.inviteTechnician(email);
    setLoading(btn, false);
    inviteModal.classList.remove('active');
    this.reset();
});

// Pedir para adicionar credenciais (técnico externo)
document.getElementById('btn-request-add')?.addEventListener('click', async function() {
    setLoading(this, true, 'A enviar...');
    const r = await API.post('api/access_requests.php?action=request_add', { company_id: COMPANY_ID });
    if (r.success) Toast.success('Pedido enviado! Aguarda aprovação do dono.');
    else Toast.error(r.error);
    setLoading(this, false);
});

// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', function() {
        const input = this.closest('.input-group')?.querySelector('input');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        this.textContent = input.type === 'password' ? '👁' : '🙈';
    });
});
<?php
$inlineScript = ob_get_clean();
include __DIR__ . '/../includes/footer.php';
?>
