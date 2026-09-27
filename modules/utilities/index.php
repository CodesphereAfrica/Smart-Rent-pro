<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.view');

$util = db_all(
    'SELECT ut.*, p.name AS property_name, u.unit_number
       FROM utilities ut
       JOIN properties p ON p.id = ut.property_id
       LEFT JOIN units u ON u.id = ut.unit_id
      WHERE ut.company_id = :c ORDER BY p.name, ut.utility_type',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Utilities');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Utilities')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Billing models per property/unit: landlord-paid, tenant-direct, shared, included, or meter-based.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/utilities/create.php"><i data-lucide="plus"></i> <?= e(__('Add Utility')) ?></a>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Property')) ?></th><th><?= e(__('Unit')) ?></th><th><?= e(__('Type')) ?></th>
      <th><?= e(__('Billing Model')) ?></th><th><?= e(__('Rate')) ?></th>
      <th><?= e(__('Meters')) ?></th><th><?= e(__('Status')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$util): ?><tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('No utilities configured.')) ?></td></tr>
      <?php else: foreach ($util as $u): ?>
        <tr>
          <td><?= e($u['property_name']) ?></td>
          <td><?= e($u['unit_number'] ?? '—') ?></td>
          <td><?= e(ucfirst($u['utility_type'])) ?></td>
          <td><?= e(ucfirst(str_replace('_',' ',$u['billing_model']))) ?></td>
          <td><?= e((string)$u['rate_per_unit'] ?: (string)$u['fixed_amount']) ?></td>
          <td><?= (int)db_one('SELECT COUNT(*) AS c FROM utility_meters WHERE utility_id = :u', [':u'=>(int)$u['id']])['c'] ?></td>
          <td><?= $u['is_active'] ? '<span class="badge bg-success">' . e(__('Active')) . '</span>' : '<span class="badge bg-secondary">' . e(__('Inactive')) . '</span>' ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/utilities/edit.php?id=<?= (int)$u['id'] ?>"><i data-lucide="pencil"></i></a>
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/meters/index.php?utility_id=<?= (int)$u['id'] ?>"><i data-lucide="gauge"></i></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>