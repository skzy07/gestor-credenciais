<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$userId = currentUserId();
$db = getDB();

$stmt = $db->prepare(
    "SELECT DISTINCT c.*, u.username AS owner_username, u.avatar_color AS owner_avatar,
            (c.owner_id = ?) AS is_mine
     FROM companies c 
     JOIN users u ON u.id = c.owner_id
     LEFT JOIN access_requests ar ON ar.company_id = c.id
     WHERE c.owner_id = ? 
        OR (ar.status = 'approved' AND (
              (ar.type = 'add_to_company' AND ar.requester_id = ?) OR 
              (ar.type = 'invite_technician' AND ar.owner_id = ?)
           ))
     ORDER BY c.created_at DESC"
);
$stmt->execute([$userId, $userId, $userId, $userId]);
$companies = $stmt->fetchAll();

if (count($companies) === 0) {
    header('Location: ' . APP_URL . '/company/create.php');
    exit;
}

$pageTitle = 'As Minhas Empresas';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-content">
  <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
        <h1 class="page-title">As Minhas Empresas 🏢</h1>
        <p class="page-subtitle">Empresas que geres ou às quais tens acesso como técnico.</p>
    </div>
    <?php
    $hasOwned = false;
    foreach ($companies as $c) {
        if ($c['is_mine']) {
            $hasOwned = true;
            break;
        }
    }
    if (!$hasOwned):
    ?>
    <a href="<?= APP_URL ?>/company/create.php" class="btn btn-primary" style="text-decoration:none">+ Nova Empresa</a>
    <?php endif; ?>
  </div>
  
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;">
    <?php foreach ($companies as $company): ?>
      <a href="<?= APP_URL ?>/company/view.php?id=<?= $company['id'] ?>" class="card card-glow-blue" style="text-decoration:none; color:inherit; display:block; transition:transform 0.2s; cursor:pointer;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='none'">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
          <div style="width:52px;height:52px;font-size:1.8rem;display:flex;align-items:center;justify-content:center;background:var(--bg3);border-radius:12px;overflow:hidden;flex-shrink:0;">
            <?php if (!empty($company['logo_url'])): ?>
              <img src="<?= sanitize($company['logo_url']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:12px;" alt="Logo">
            <?php else: ?>🏢<?php endif; ?>
          </div>
          <div style="overflow:hidden">
            <div style="font-size:1.15rem;font-weight:800;color:var(--t1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= sanitize($company['name']) ?>"><?= sanitize($company['name']) ?></div>
            <div style="font-size:.78rem;color:var(--t3);font-family:monospace">NIF: <?= sanitize($company['nif']) ?></div>
          </div>
        </div>
        <div style="border-top:1px solid var(--glass-b);padding-top:14px;display:flex;align-items:center;justify-content:space-between;">
           <div style="display:flex;align-items:center;gap:8px">
              <div style="background:<?= sanitize($company['owner_avatar']) ?>;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:bold"><?= sanitize(initials($company['owner_username'])) ?></div>
              <div style="font-size:.875rem;font-weight:600;color:var(--t2)"><?= sanitize($company['owner_username']) ?></div>
           </div>
           <div>
               <?php if ($company['is_mine']): ?>
                 <span style="background:rgba(79,142,247,0.1);color:var(--primary);padding:4px 8px;border-radius:6px;font-size:0.75rem;font-weight:600;">Dono</span>
               <?php else: ?>
                 <span style="background:rgba(40,199,111,0.1);color:var(--green);padding:4px 8px;border-radius:6px;font-size:0.75rem;font-weight:600;">Técnico</span>
               <?php endif; ?>
           </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php
include __DIR__ . '/../includes/footer.php';
?>
