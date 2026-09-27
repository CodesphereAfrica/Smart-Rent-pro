<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.view');

$rows = db_all(
    'SELECT t.*, p.name AS property_name FROM tax_records t
       JOIN properties p ON p.id = t.property_id
      WHERE t.company_id = :c ORDER BY t.due_date DESC',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Tax & Compliance');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Tax & Compliance Records')) ?></h1>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/tax/create.php"><i data-lucide="plus"></i> <?= e(__('Add Record')) ?></a>
</div>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Property')) ?></th><th><?= e(__('Type')) ?></th><th><?= e(__('Period')) ?></th>
      <th><?= e(__('Amount')) ?></th><th><?= e(__('Due')) ?></th><th><?= e(__('Paid')) ?></th><th><?= e(__('Status')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('No records.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['property_name']) ?></td>
          <td><?= e($r['tax_type']) ?></td>
          <td><?= e($r['period_label']) ?></td>
          <td><?= e(money($r['amount'])) ?></td>
          <td><?= e($r['due_date']) ?></td>
          <td><?= e($r['paid_date'] ?: '—') ?></td>
          <td><?= e(ucfirst($r['status'])) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/tax/edit.php?id=<?= (int)$r['id'] ?>"><i data-lucide="pencil"></i></a>
            <a class="btn btn-sm btn-light text-danger" href="<?= APP_URL ?>/modules/tax/delete.php?id=<?= (int)$r['id'] ?>"><i data-lucide="trash-2"></i></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>