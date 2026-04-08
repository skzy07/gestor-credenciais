<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();
$pageTitle = 'Notificações';
include __DIR__ . '/../includes/header.php';
?>
<meta name="app-url" content="<?= APP_URL ?>">
<div class="page-content">
  <div class="page-header-row" style="margin-bottom:28px">
    <div>
      <h1 class="page-title">Notificações 🔔</h1>
      <p class="page-subtitle">Pedidos de acesso e respostas</p>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-ghost btn-sm" id="mark-all-read-btn">✓ Marcar todas como lidas</button>
      <button class="btn btn-danger btn-sm" id="clear-all-btn">🗑 Limpar Histórico</button>
    </div>
  </div>

  <div id="notif-wrap" style="max-width: 800px; margin: 0 auto;">
    <div class="loading-overlay"><span class="spinner spinner-lg"></span></div>
  </div>
</div>

<?php
$extraScripts = ['notifications.js'];
ob_start();
?>
NotificationsManager.init();

document.getElementById('mark-all-read-btn').addEventListener('click', async function() {
    await API.post('api/notifications.php?action=mark_read', {});
    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
    document.querySelectorAll('.unread-dot').forEach(el => el.remove());
    document.getElementById('notif-badge')?.remove();
    Toast.info('Todas marcadas como lidas.');
});

document.getElementById('clear-all-btn').addEventListener('click', async function() {
    if (!confirm('Tens a certeza que queres apagar TODO o teu histórico de notificações? Isto não afetará credenciais.')) return;
    const r = await API.post('api/notifications.php?action=clear_all', {});
    if (r.success) {
        Toast.success('Histórico limpo com sucesso.');
        document.getElementById('notif-badge')?.remove();
        NotificationsManager.load();
    } else Toast.error(r.error || 'Erro ao limpar histórico.');
});
<?php
$inlineScript = ob_get_clean();
include __DIR__ . '/../includes/footer.php';
?>
