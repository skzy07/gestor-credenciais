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
  <?php
  $inlineScript = ob_get_clean();
  ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
