<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_login();
if (!is_super_admin($USER)) { http_response_code(403); exit('Forbidden'); }

$id = (int)($_GET['id'] ?? 0);
$c = db_one('SELECT * FROM companies WHERE id = :id LIMIT 1', [':id'=>$id]);
if (!$c) { flash_error(__('Not found.')); redirect('modules/companies/index.php'); }

$stats = db_one(
    'SELECT
        (SELECT COUNT(*) FROM properties WHERE company_id = :c) AS properties,
        (SELECT COUNT(*) FROM units WHERE company_id = :c) AS units,
        (SELECT COUNT(*) FROM tenants WHERE company_id = :c) AS tenants,
        (SELECT COUNT(*) FROM users WHERE company_id = :c AND status="active") AS users,
        (SELECT COUNT(*) FROM payments WHERE company_id = :c AND status="completed") AS payments
    ',
    [':c'=>$id]
);

$users = db_all(
    'SELECT u.id, u.first_name, u.last_name, u.email, u.status, u.last_login_at, r.name AS role_name
       FROM users u JOIN roles r ON r.id = u.role_id
      WHERE u.company_id = :c ORDER BY r.name, u.first_name LIMIT 50',
    [':c'=>$id]
);

$PAGE_TITLE = $c['name'];
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/companies/index.php"><?= e(__('Companies')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($c['name']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e($c['name']) ?>
      <span class="srp-badge-pill <?= $c['is_active']?'badge-status-available':'badge-status-unavailable' ?> ms-2">
        <span class="dot"></span><?= e($c['is_active'] ? __('Active') : __('Inactive')) ?>
      </span>
    </h1>
    <div class="text-muted small"><code><?= e($c['slug']) ?></code></div>
  </div>
  <a class="btn btn-light" href="<?= APP_URL ?>/modules/companies/edit.php?id=<?= (int)$id ?>"><i data-lucide="pencil"></i> <?= e(__('Edit')) ?></a>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="building"></i></div>
    <div class="kpi-label"><?= e(__('Properties')) ?></div>
    <div class="kpi-value"><?= (int)$stats['properties'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="door-open"></i></div>
    <div class="kpi-label"><?= e(__('Units')) ?></div>
    <div class="kpi-value"><?= (int)$stats['units'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="users"></i></div>
    <div class="kpi-label"><?= e(__('Tenants')) ?></div>
    <div class="kpi-value"><?= (int)$stats['tenants'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="user-cog"></i></div>
    <div class="kpi-label"><?= e(__('Active Users')) ?></div>
    <div class="kpi-value"><?= (int)$stats['users'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Payments Recorded')) ?></div>
    <div class="kpi-value"><?= (int)$stats['payments'] ?></div></div>
</div>

<div class="srp-card p-3">
  <h2 class="h6 mb-3"><?= e(__('Users')) ?></h2>
  <table class="table table-hover align-middle">
    <thead><tr><th><?= e(__('Name')) ?></th><th><?= e(__('Email')) ?></th><th><?= e(__('Role')) ?></th>
      <th><?= e(__('Status')) ?></th><th><?= e(__('Last Login')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['first_name'].' '.$u['last_name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e($u['role_name']) ?></td>
          <td><?= e(ucfirst($u['status'])) ?></td>
          <td><?= e($u['last_login_at'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>