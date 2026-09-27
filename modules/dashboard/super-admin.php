<?php
// modules/dashboard/super-admin.php — platform owner overview.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_login();

$kpi = db_one(
    'SELECT
        (SELECT COUNT(*) FROM companies) AS companies,
        (SELECT COUNT(*) FROM companies WHERE is_active = 1) AS active_companies,
        (SELECT COUNT(*) FROM users WHERE status = "active") AS users,
        (SELECT COUNT(*) FROM properties WHERE deleted_at IS NULL) AS properties,
        (SELECT COUNT(*) FROM units) AS units,
        (SELECT COUNT(*) FROM tenants WHERE status = "active") AS tenants,
        (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = "completed" AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS collected_30d
    '
);

$companies = db_all(
    'SELECT c.id, c.name, c.slug, c.is_active, c.base_currency_code, c.created_at,
            (SELECT COUNT(*) FROM properties p WHERE p.company_id = c.id AND p.deleted_at IS NULL) AS props,
            (SELECT COUNT(*) FROM units u WHERE u.company_id = c.id) AS units,
            (SELECT COUNT(*) FROM users  u WHERE u.company_id = c.id AND u.status = "active") AS users
       FROM companies c ORDER BY c.created_at DESC LIMIT 10'
);

$PAGE_TITLE = __('Platform Dashboard');
require __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3"><?= e(__('Platform Dashboard')) ?></h1>

<div class="kpi-grid mb-4">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="building-2"></i></div>
    <div class="kpi-label"><?= e(__('Companies')) ?></div>
    <div class="kpi-value"><?= (int)$kpi['companies'] ?></div>
    <div class="text-muted small"><?= (int)$kpi['active_companies'] ?> <?= e(__('active')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="users"></i></div>
    <div class="kpi-label"><?= e(__('Users')) ?></div>
    <div class="kpi-value"><?= (int)$kpi['users'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="building"></i></div>
    <div class="kpi-label"><?= e(__('Properties')) ?></div>
    <div class="kpi-value"><?= (int)$kpi['properties'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="door-open"></i></div>
    <div class="kpi-label"><?= e(__('Units')) ?></div>
    <div class="kpi-value"><?= (int)$kpi['units'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Collected (30d)')) ?></div>
    <div class="kpi-value"><?= e(money($kpi['collected_30d'])) ?></div></div>
</div>

<div class="srp-card p-3">
  <h2 class="h6 mb-3"><?= e(__('Latest Companies')) ?></h2>
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Company')) ?></th><th><?= e(__('Slug')) ?></th><th><?= e(__('Currency')) ?></th>
      <th><?= e(__('Properties')) ?></th><th><?= e(__('Units')) ?></th><th><?= e(__('Users')) ?></th>
      <th><?= e(__('Created')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($companies as $c): ?>
        <tr>
          <td><?= e($c['name']) ?></td>
          <td><code><?= e($c['slug']) ?></code></td>
          <td><?= e($c['base_currency_code']) ?></td>
          <td><?= (int)$c['props'] ?></td>
          <td><?= (int)$c['units'] ?></td>
          <td><?= (int)$c['users'] ?></td>
          <td><?= e(date('Y-m-d', strtotime($c['created_at']))) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/companies/view.php?id=<?= (int)$c['id'] ?>"><i data-lucide="eye"></i></a>
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/companies/edit.php?id=<?= (int)$c['id'] ?>"><i data-lucide="pencil"></i></a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/companies/create.php"><i data-lucide="plus"></i> <?= e(__('Add Company')) ?></a>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>