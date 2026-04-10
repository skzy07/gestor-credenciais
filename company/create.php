<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$pageTitle = 'Criar Empresa';
include __DIR__ . '/../includes/header.php';
?>
<meta name="app-url" content="<?= APP_URL ?>">
<div class="page-content">
  <div class="page-header">
    <a href="<?= APP_URL ?>/dashboard.php" style="font-size:.85rem;color:var(--t3);margin-bottom:12px;display:inline-block">← Voltar ao feed</a>
    <h1 class="page-title">Criar Empresa 🏢</h1>
    <p class="page-subtitle">Regista a tua empresa. O NIF será verificado e deve ser único.</p>
  </div>

  <div style="max-width:540px; margin: 0 auto;">
    <div class="card card-glow-blue">
      <div id="alert-box" class="alert alert-error" style="display:none"></div>

      <form id="create-company-form">
        <div class="form-group">
          <label class="form-label">Nome da Empresa *</label>
          <input type="text" name="name" id="name" class="form-input" placeholder="Ex: MegaStock Lda" required maxlength="255">
        </div>

        <div class="form-group">
          <label class="form-label">NIF da Empresa *</label>
          <input type="text" name="nif" id="nif" class="form-input" placeholder="123456789" required maxlength="9" pattern="\d{9}" inputmode="numeric">
          <span class="form-error" id="nif-error"></span>
          <span class="form-hint">9 dígitos — será validado pelo algoritmo português</span>
        </div>

        <div class="form-group">
          <label class="form-label">Descrição</label>
          <textarea name="description" id="description" class="form-textarea" placeholder="Breve descrição da empresa e serviços..."></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Logo da Empresa <span style="color:var(--t3);font-weight:400">(opcional)</span></label>
          <div class="upload-zone" id="logo-upload-zone" style="padding:20px;">
            <input type="file" id="logo-file-input" accept="image/jpeg,image/png,image/webp,image/gif">
            <div class="upload-zone-icon" style="font-size:1.8rem">🏢</div>
            <div class="upload-zone-label">Clica para selecionar um logo</div>
            <div class="upload-zone-hint">JPG, PNG ou WebP · Máx. 5 MB</div>
          </div>
          <div id="logo-preview-wrap" style="display:none;margin-top:12px;">
            <div style="display:flex;align-items:center;gap:12px;">
              <div style="width:60px;height:60px;border-radius:var(--r-md);overflow:hidden;border:2px solid var(--blue);">
                <img id="logo-preview-img" src="" alt="Preview" style="width:100%;height:100%;object-fit:cover;">
              </div>
              <div style="font-size:.85rem;color:var(--t2);">Logo selecionado — será carregado após criar a empresa</div>
              <button type="button" id="btn-cancel-logo" class="btn btn-ghost btn-sm">✕</button>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg" id="create-btn">Registar Empresa</button>
      </form>
    </div>

    <div class="alert alert-info" style="margin-top:20px">
      ℹ️ Cada técnico só pode ter <strong>uma empresa</strong> registada. O NIF é usado para verificar unicidade globalmente.
    </div>
  </div>
</div>

<?php
ob_start();
?>
// NIF validation on blur
document.getElementById('nif').addEventListener('blur', function() {
    const val    = this.value.trim();
    const errEl  = document.getElementById('nif-error');
    if (!val) return;
    const nifValid = typeof VaultCrypto !== 'undefined' ? VaultCrypto.validateNIF(val) : {valid:true};
    if (!nifValid.valid) {
        errEl.textContent = nifValid.error;
        errEl.classList.add('visible');
        this.style.borderColor = 'var(--red)';
    } else {
        errEl.classList.remove('visible');
        this.style.borderColor = 'var(--green)';
    }
});

document.getElementById('create-company-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn   = document.getElementById('create-btn');
    const alert = document.getElementById('alert-box');
    alert.style.display = 'none';

    const name        = document.getElementById('name').value.trim();
    const nif         = document.getElementById('nif').value.trim();
    const description = document.getElementById('description').value.trim();

    // Client-side NIF validation
    const nifCheck = typeof VaultCrypto !== 'undefined' ? VaultCrypto.validateNIF(nif) : {valid:true};
    if (!nifCheck.valid) {
        const errEl = document.getElementById('nif-error');
        errEl.textContent = nifCheck.error;
        errEl.classList.add('visible');
        return;
    }

    setLoading(btn, true, 'A registar...');
    const r = await API.post('api/companies.php?action=create', { name, nif, description });

    if (r.success) {
        // Se tem logo selecionado, faz upload agora
        const logoFile = document.getElementById('logo-file-input')?.files[0];
        if (logoFile && r.data.company_id) {
            const fd = new FormData();
            fd.append('photo', logoFile);
            await fetch(`../api/upload.php?type=company_logo&company_id=${r.data.company_id}`, { method: 'POST', body: fd });
        }
        Toast.success('Empresa registada com sucesso!');
        setTimeout(() => location.href = '../company/view.php?id=' + r.data.company_id, 1200);
    } else {
        alert.textContent = r.error;
        alert.style.display = 'flex';
        setLoading(btn, false);
    }
});

// Logo preview
const logoInput  = document.getElementById('logo-file-input');
const logoZone   = document.getElementById('logo-upload-zone');
const logoPrevWrap = document.getElementById('logo-preview-wrap');
const logoPrevImg  = document.getElementById('logo-preview-img');
const btnCancelLogo = document.getElementById('btn-cancel-logo');

logoInput?.addEventListener('change', () => {
    if (logoInput.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { logoPrevImg.src = e.target.result; logoPrevWrap.style.display = 'block'; };
        reader.readAsDataURL(logoInput.files[0]);
    }
});

btnCancelLogo?.addEventListener('click', () => {
    logoPrevWrap.style.display = 'none'; logoInput.value = '';
});
<?php
$inlineScript = ob_get_clean();
include __DIR__ . '/../includes/footer.php';
?>
