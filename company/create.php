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
        Toast.success('Empresa registada com sucesso!');
        setTimeout(() => location.href = '../company/view.php?id=' + r.data.company_id, 1200);
    } else {
        alert.textContent = r.error;
        alert.style.display = 'flex';
        setLoading(btn, false);
    }
});
<?php
$inlineScript = ob_get_clean();
include __DIR__ . '/../includes/footer.php';
?>
