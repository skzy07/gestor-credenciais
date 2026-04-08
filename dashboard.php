<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/csrf.php';
requireLogin();
$pageTitle   = 'Feed de Empresas';
$extraScripts = [];
include __DIR__ . '/includes/header.php';
?>
<meta name="app-url" content="<?= APP_URL ?>">
<div class="page-content">
  <div class="page-header-row">
    <div class="page-header" style="margin-bottom:0">
      <h1 class="page-title">Feed de Empresas 🏢</h1>
      <p class="page-subtitle">Descobre as empresas dos outros técnicos</p>
    </div>
    <div style="display:flex;gap:12px;align-items:center">
      <input type="text" id="search-input" class="form-input" placeholder="🔍  Pesquisar empresa ou NIF..." style="width:280px">
      <a href="<?= APP_URL ?>/company/create.php" class="btn btn-primary">+ Criar Empresa</a>
    </div>
  </div>

  <div style="margin:28px 0 16px;display:flex;align-items:center;justify-content:space-between">
    <span style="font-size:.85rem;color:var(--t3)" id="results-count"></span>
  </div>

  <div class="feed-grid" id="company-feed">
    <div class="loading-overlay" style="grid-column:1/-1"><span class="spinner spinner-lg"></span></div>
  </div>

  <div id="pagination" style="display:flex;justify-content:center;gap:8px;margin-top:32px"></div>
</div>

<?php
ob_start();
?>
let currentPage = 1;
let searchTimer;

async function loadFeed(page = 1, query = '') {
    const feed = document.getElementById('company-feed');
    feed.innerHTML = '<div class="loading-overlay" style="grid-column:1/-1"><span class="spinner spinner-lg"></span></div>';
    currentPage = page;

    const q = query ? `&q=${encodeURIComponent(query)}` : '';
    const r = await API.get(`api/companies.php?action=list&page=${page}${q}`);
    if (!r.success) { feed.innerHTML = `<div class="alert alert-error">${r.error}</div>`; return; }

    const { companies, total, pages } = r.data;
    document.getElementById('results-count').textContent = `${total} empresa${total !== 1 ? 's' : ''} encontrada${total !== 1 ? 's' : ''}`;

    if (!companies.length) {
        feed.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
          <div class="empty-icon">🏢</div>
          <div class="empty-title">Nenhuma empresa encontrada</div>
          <div class="empty-text">${query ? 'Tenta outra pesquisa.' : 'Sê o primeiro a criar uma empresa!'}</div>
          <a href="create.php" class="btn btn-primary">+ Criar Empresa</a>
        </div>`;
        document.getElementById('pagination').innerHTML = ''; return;
    }

    feed.innerHTML = companies.map(co => `
      <div class="company-card" onclick="location.href='company/view.php?id=${co.id}'">
        <div class="company-card-header">
          <div class="company-icon">🏢</div>
          <div style="flex:1;min-width:0">
            <div class="company-name">${escHtml(co.name)}</div>
            <div class="company-nif">NIF: ${escHtml(co.nif)}</div>
          </div>
          ${co.is_mine ? '<span class="tag tag-green">Minha</span>' : ''}
        </div>
        <div class="company-description">${escHtml(co.description || 'Sem descrição.')}</div>
        <div class="company-meta">
          <div class="company-owner">
            <div class="owner-dot" style="background:${escHtml(co.owner_avatar)}">${escHtml(co.owner_username?.substring(0,2).toUpperCase())}</div>
            ${escHtml(co.owner_username)}
          </div>
          <span class="tag tag-blue">🔑 ${co.cred_count} credencial${co.cred_count !== '1' ? 'is' : ''}</span>
        </div>
      </div>`).join('');

    // Pagination
    const pag = document.getElementById('pagination');
    pag.innerHTML = '';
    for (let i = 1; i <= pages; i++) {
        const btn = document.createElement('button');
        btn.className = `btn ${i === page ? 'btn-primary' : 'btn-ghost'} btn-sm`;
        btn.textContent = i;
        btn.addEventListener('click', () => loadFeed(i, document.getElementById('search-input').value));
        pag.appendChild(btn);
    }
}

document.getElementById('search-input').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadFeed(1, this.value.trim()), 400);
});

function escHtml(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadFeed();
<?php
$inlineScript = ob_get_clean();
include __DIR__ . '/includes/footer.php';
?>
