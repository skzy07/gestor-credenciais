<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$db   = getDB();
$stmt = $db->prepare('SELECT * FROM companies WHERE owner_id = ? LIMIT 1');
$stmt->execute([currentUserId()]);
$company = $stmt->fetch();

$pageTitle = 'Gerir Empresa';
include __DIR__ . '/../includes/header.php';
?>
<meta name="app-url" content="<?= APP_URL ?>">
<div class="page-content">
  <div class="page-header">
    <a href="<?= APP_URL ?>/dashboard.php" style="font-size:.85rem;color:var(--t3);margin-bottom:12px;display:inline-block">← Voltar ao feed</a>
    <h1 class="page-title">Gerir Empresa ⚙️</h1>
  </div>

  <?php if (!$company): ?>
  <div class="empty-state" style="max-width:500px">
    <div class="empty-icon">🏢</div>
    <div class="empty-title">Ainda não tens empresa</div>
    <div class="empty-text">Cria a tua empresa para começares a gerir credenciais.</div>
    <a href="<?= APP_URL ?>/company/create.php" class="btn btn-primary">+ Criar Empresa</a>
  </div>
  <?php else: ?>

  <div style="max-width:600px; margin: 0 auto;">
    <!-- Info card -->
    <div class="card card-glow-green" style="margin-bottom:24px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
        <div style="font-size:1.1rem;font-weight:700"><?= sanitize($company['name']) ?></div>
        <span class="tag tag-green">Minha empresa</span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
        <div>
          <div style="font-size:.75rem;color:var(--t3);font-weight:600;text-transform:uppercase;margin-bottom:4px">NIF</div>
          <div style="font-family:monospace;font-size:.95rem"><?= sanitize($company['nif']) ?></div>
        </div>
        <div>
          <div style="font-size:.75rem;color:var(--t3);font-weight:600;text-transform:uppercase;margin-bottom:4px">Criada em</div>
          <div style="font-size:.875rem"><?= date('d/m/Y', strtotime($company['created_at'])) ?></div>
        </div>
      </div>
      <a href="<?= APP_URL ?>/company/view.php?id=<?= $company['id'] ?>" class="btn btn-ghost btn-sm">👁 Ver página pública</a>
    </div>

    <!-- Logo da Empresa -->
    <div class="card" style="margin-bottom:24px;">
      <div class="panel-title">Logo da Empresa</div>
      
      <?php if (!empty($company['logo_url'])): ?>
      <div class="logo-preview-wrap" id="logo-current-wrap">
        <img src="<?= sanitize($company['logo_url']) ?>" alt="Logo atual" class="logo-preview-img" id="logo-current-img">
        <div>
          <div style="font-size:.875rem;font-weight:600;color:var(--t1);margin-bottom:6px;">Logo atual</div>
          <button id="btn-remove-logo" class="btn btn-ghost btn-sm" style="color:var(--red);border-color:rgba(255,71,87,.3);">🗑 Remover logo</button>
        </div>
      </div>
      <?php endif; ?>

      <div class="upload-zone" id="logo-upload-zone">
        <input type="file" id="logo-file-input" accept="image/jpeg,image/png,image/webp,image/gif">
        <div class="upload-zone-icon">🏢</div>
        <div class="upload-zone-label"><strong>Clica ou arrasta</strong> para carregar o logo</div>
        <div class="upload-zone-hint">JPG, PNG ou WebP · Máx. 5 MB · Será redimensionado para 512×512px</div>
      </div>

      <div id="logo-preview-wrap" style="display:none;margin-top:16px;">
        <div style="font-size:.8rem;color:var(--t3);margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Pré-visualização</div>
        <div style="display:flex;align-items:center;gap:16px;">
          <div style="width:80px;height:80px;border-radius:var(--r-lg);overflow:hidden;border:2px solid var(--blue);box-shadow:var(--sh-blue);">
            <img id="logo-preview-img" src="" alt="Preview" style="width:100%;height:100%;object-fit:cover;">
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;">
            <button id="btn-upload-logo" class="btn btn-primary">💾 Guardar Logo</button>
            <button id="btn-cancel-logo" class="btn btn-ghost btn-sm">Cancelar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Editar -->
    <div class="card" style="margin-bottom:24px">
      <div class="panel-title">Editar Informação</div>
      <div id="edit-alert" class="alert alert-error" style="display:none"></div>
      <form id="edit-form">
        <div class="form-group">
          <label class="form-label">Nome</label>
          <input type="text" name="name" class="form-input" value="<?= sanitize($company['name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Descrição</label>
          <textarea name="description" class="form-textarea"><?= sanitize($company['description'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary" id="save-btn">Guardar Alterações</button>
      </form>
    </div>
  </div>

  <?php
  ob_start();
  ?>
  document.getElementById('edit-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      const btn = document.getElementById('save-btn');
      setLoading(btn, true, 'A guardar...');
      const r = await API.post('api/companies.php?action=update', {
          company_id:  <?= $company['id'] ?>,
          name:        this.querySelector('[name=name]').value.trim(),
          description: this.querySelector('[name=description]').value.trim()
      });
      if (r.success) Toast.success('Empresa atualizada!');
      else { document.getElementById('edit-alert').textContent = r.error; document.getElementById('edit-alert').style.display='flex'; }
      setLoading(btn, false);
  });

  // ── Logo upload ──────────────────────────────────────────
  const COMPANY_ID = <?= $company['id'] ?>;
  const appUrl     = '<?= APP_URL ?>';
  const logoInput  = document.getElementById('logo-file-input');
  const logoZone   = document.getElementById('logo-upload-zone');
  const logoPrevWrap = document.getElementById('logo-preview-wrap');
  const logoPrevImg  = document.getElementById('logo-preview-img');
  const btnUploadLogo = document.getElementById('btn-upload-logo');
  const btnCancelLogo = document.getElementById('btn-cancel-logo');
  const btnRemoveLogo = document.getElementById('btn-remove-logo');
  let selectedLogoFile = null;

  if (logoZone) {
    logoZone.addEventListener('dragover',  e => { e.preventDefault(); logoZone.classList.add('drag-over'); });
    logoZone.addEventListener('dragleave', () => logoZone.classList.remove('drag-over'));
    logoZone.addEventListener('drop', e => {
      e.preventDefault(); logoZone.classList.remove('drag-over');
      if (e.dataTransfer.files[0]) handleLogo(e.dataTransfer.files[0]);
    });
  }
  logoInput?.addEventListener('change', () => { if (logoInput.files[0]) handleLogo(logoInput.files[0]); });

  function handleLogo(file) {
    if (!file.type.startsWith('image/')) { Toast.error('Só são aceites imagens.'); return; }
    if (file.size > 5 * 1024 * 1024)    { Toast.error('Máx. 5 MB.'); return; }
    selectedLogoFile = file;
    const reader = new FileReader();
    reader.onload = e => { logoPrevImg.src = e.target.result; logoPrevWrap.style.display = 'block'; };
    reader.readAsDataURL(file);
  }

  btnCancelLogo?.addEventListener('click', () => {
    logoPrevWrap.style.display = 'none'; selectedLogoFile = null; logoInput.value = '';
  });

  btnUploadLogo?.addEventListener('click', async () => {
    if (!selectedLogoFile) return;
    setLoading(btnUploadLogo, true, 'A enviar...');
    const fd = new FormData();
    fd.append('photo', selectedLogoFile);
    try {
      const res  = await fetch(`${appUrl}/api/upload.php?type=company_logo&company_id=${COMPANY_ID}`, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        Toast.success('Logo atualizado!');
        // Atualizar preview no ecrã
        const wrap = document.getElementById('logo-current-wrap');
        const url  = data.data.url;
        if (wrap) {
          document.getElementById('logo-current-img').src = url;
        } else {
          // Inserir o wrap se não existia antes
          const newWrap = document.createElement('div');
          newWrap.className = 'logo-preview-wrap';
          newWrap.id = 'logo-current-wrap';
          newWrap.innerHTML = `<img src="${url}" alt="Logo" class="logo-preview-img" id="logo-current-img"><div><div style="font-size:.875rem;font-weight:600;color:var(--t1);margin-bottom:6px;">Logo atual</div><button id="btn-remove-logo" class="btn btn-ghost btn-sm" style="color:var(--red);border-color:rgba(255,71,87,.3);">🗑 Remover logo</button></div>`;
          logoZone.parentNode.insertBefore(newWrap, logoZone);
          bindRemoveLogo();
        }
        logoPrevWrap.style.display = 'none'; selectedLogoFile = null;
      } else { Toast.error(data.error); }
    } catch(e) { Toast.error('Erro de rede.'); }
    setLoading(btnUploadLogo, false);
  });

  function bindRemoveLogo() {
    document.getElementById('btn-remove-logo')?.addEventListener('click', async () => {
      if (!confirm('Remover o logo da empresa?')) return;
      const res  = await fetch(`${appUrl}/api/upload.php?type=remove_company_logo&company_id=${COMPANY_ID}`, { method: 'POST' });
      const data = await res.json();
      if (data.success) { Toast.success('Logo removido.'); location.reload(); }
      else Toast.error(data.error);
    });
  }
  bindRemoveLogo();
  <?php
  $inlineScript = ob_get_clean();
  ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
