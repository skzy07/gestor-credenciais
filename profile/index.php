<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$user      = currentUser();
$avatarUrl = $user['avatar_url'] ?? null;

$pageTitle = 'Meu Perfil';
include __DIR__ . '/../includes/header.php';
?>
<meta name="app-url" content="<?= APP_URL ?>">
<div class="page-content">
  <div class="page-header">
    <a href="<?= APP_URL ?>/dashboard.php" style="font-size:.85rem;color:var(--t3);margin-bottom:12px;display:inline-block">← Voltar ao feed</a>
    <h1 class="page-title">Meu Perfil 👤</h1>
    <p class="page-subtitle">Personaliza o teu avatar de perfil.</p>
  </div>

  <div style="max-width:480px;margin:0 auto;">
    <!-- Avatar atual + upload -->
    <div class="card card-glow-blue" style="margin-bottom:24px;">
      <div class="panel-title">Foto de Perfil</div>
      
      <!-- Preview atual -->
      <div style="display:flex;align-items:center;gap:20px;margin-bottom:24px;">
        <div class="avatar-preview-wrap">
          <div id="avatar-display" style="width:80px;height:80px;border-radius:50%;background:<?= sanitize($user['avatar_color'] ?? '#4F8EF7') ?>;display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:700;color:#fff;overflow:hidden;border:3px solid var(--glass-b);">
            <?php if ($avatarUrl): ?>
              <img id="avatar-current-img" src="<?= sanitize($avatarUrl) ?>" class="avatar-img" alt="Avatar atual">
            <?php else: ?>
              <span id="avatar-initials"><?= sanitize(initials($user['username'])) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div>
          <div style="font-size:1rem;font-weight:700;color:var(--t1)"><?= sanitize($user['username']) ?></div>
          <div style="font-size:.85rem;color:var(--t3)"><?= sanitize($user['email']) ?></div>
          <?php if ($avatarUrl): ?>
            <button id="btn-remove-avatar" class="btn btn-ghost btn-sm" style="margin-top:8px;color:var(--red);border-color:rgba(255,71,87,.3)">🗑 Remover foto</button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Upload zone -->
      <div class="upload-zone" id="avatar-upload-zone">
        <input type="file" id="avatar-file-input" accept="image/jpeg,image/png,image/webp,image/gif">
        <div class="upload-zone-icon">📷</div>
        <div class="upload-zone-label"><strong>Clica ou arrasta</strong> para selecionar uma foto</div>
        <div class="upload-zone-hint">JPG, PNG ou WebP · Máx. 5 MB · Será redimensionada para 256×256px</div>
      </div>

      <!-- Preview antes do upload -->
      <div id="preview-wrap" style="display:none;margin-top:16px;">
        <div style="font-size:.8rem;color:var(--t3);margin-bottom:8px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Pré-visualização</div>
        <div style="display:flex;align-items:center;gap:16px;">
          <div style="width:80px;height:80px;border-radius:50%;overflow:hidden;border:3px solid var(--blue);box-shadow:var(--sh-blue);">
            <img id="avatar-preview-img" src="" alt="Preview" style="width:100%;height:100%;object-fit:cover;">
          </div>
          <div style="display:flex;flex-direction:column;gap:8px;">
            <button id="btn-upload-avatar" class="btn btn-primary">💾 Guardar Foto</button>
            <button id="btn-cancel-preview" class="btn btn-ghost btn-sm">Cancelar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Info da conta (read-only) -->
    <div class="card" style="margin-bottom:24px;">
      <div class="panel-title">Informação da Conta</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div>
          <div style="font-size:.75rem;color:var(--t3);font-weight:600;text-transform:uppercase;margin-bottom:4px">Username</div>
          <div style="font-size:.95rem;font-weight:600"><?= sanitize($user['username']) ?></div>
        </div>
        <div>
          <div style="font-size:.75rem;color:var(--t3);font-weight:600;text-transform:uppercase;margin-bottom:4px">Email</div>
          <div style="font-size:.95rem"><?= sanitize($user['email']) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php ob_start(); ?>
const appUrl = document.querySelector('meta[name="app-url"]').content;

const fileInput    = document.getElementById('avatar-file-input');
const uploadZone   = document.getElementById('avatar-upload-zone');
const previewWrap  = document.getElementById('preview-wrap');
const previewImg   = document.getElementById('avatar-preview-img');
const btnUpload    = document.getElementById('btn-upload-avatar');
const btnCancel    = document.getElementById('btn-cancel-preview');
const btnRemove    = document.getElementById('btn-remove-avatar');
const avatarDisplay = document.getElementById('avatar-display');

let selectedFile = null;

// Drag & Drop
uploadZone.addEventListener('dragover',  e => { e.preventDefault(); uploadZone.classList.add('drag-over'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('drag-over'));
uploadZone.addEventListener('drop', e => {
  e.preventDefault(); uploadZone.classList.remove('drag-over');
  const f = e.dataTransfer.files[0];
  if (f) handleFile(f);
});

fileInput.addEventListener('change', () => {
  if (fileInput.files[0]) handleFile(fileInput.files[0]);
});

function handleFile(file) {
  if (!file.type.startsWith('image/')) { Toast.error('Só são aceites imagens.'); return; }
  if (file.size > 5 * 1024 * 1024)    { Toast.error('O ficheiro não pode ter mais de 5 MB.'); return; }
  selectedFile = file;
  const reader = new FileReader();
  reader.onload = e => { previewImg.src = e.target.result; previewWrap.style.display = 'block'; };
  reader.readAsDataURL(file);
}

btnCancel?.addEventListener('click', () => {
  previewWrap.style.display = 'none';
  selectedFile = null;
  fileInput.value = '';
});

btnUpload?.addEventListener('click', async () => {
  if (!selectedFile) return;
  setLoading(btnUpload, true, 'A enviar...');

  const fd = new FormData();
  fd.append('photo', selectedFile);

  try {
    const res  = await fetch(`${appUrl}/api/upload.php?type=avatar`, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      Toast.success('Foto de perfil atualizada!');
      // Atualizar o avatar no ecrã sem reload
      const url = data.data.url;
      avatarDisplay.innerHTML = `<img src="${url}" class="avatar-img" alt="Avatar" style="width:100%;height:100%;object-fit:cover;">`;
      previewWrap.style.display = 'none';
      selectedFile = null;
      fileInput.value = '';
      // Atualizar o avatar na navbar
      const navAvatar = document.querySelector('.nav-avatar');
      if (navAvatar) navAvatar.innerHTML = `<img src="${url}" class="avatar-img" alt="Avatar">`;
    } else {
      Toast.error(data.error || 'Erro ao carregar a foto.');
    }
  } catch(e) {
    Toast.error('Erro de rede ao enviar a foto.');
  }

  setLoading(btnUpload, false);
});

btnRemove?.addEventListener('click', async () => {
  if (!confirm('Tens a certeza que queres remover a tua foto de perfil?')) return;
  setLoading(btnRemove, true, '');
  try {
    const res  = await fetch(`${appUrl}/api/upload.php?type=remove_avatar`, { method: 'POST' });
    const data = await res.json();
    if (data.success) {
      Toast.success('Foto removida.');
      location.reload();
    } else {
      Toast.error(data.error);
    }
  } catch(e) { Toast.error('Erro.'); }
  setLoading(btnRemove, false);
});
<?php
$inlineScript = ob_get_clean();
include __DIR__ . '/../includes/footer.php';
?>
