<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

$rules = db_all(
    'SELECT lf.*, p.name AS property_name, l.lease_number
       FROM late_fee_rules lf
       LEFT JOIN properties p ON p.id = lf.property_id
       LEFT JOIN leases l ON l.id = lf.lease_id
      WHERE lf.company_id = :c ORDER BY lf.id DESC',
    [':c'=>(int)$USER['company_id']]
);

$PAGE_TITLE = __('Late Fee Rules');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Late Fee Rules')) ?></h1>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/late-fees/create.php"><i data-lucide="plus"></i> <?= e(__('Add Rule')) ?></a>
</div>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Scope')) ?></th><th><?= e(__('Type')) ?></th><th><?= e(__('Amount')) ?></th>
      <th><?= e(__('Grace (days)')) ?></th><th><?= e(__('Max Penalty')) ?></th><th><?= e(__('Active')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$rules): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('No rules configured — a company-wide default may still apply.')) ?></td></tr>
      <?php else: foreach ($rules as $r): ?>
        <tr>
          <td><?= $r['lease_id'] ? e(__('Lease')) . ' ' . e($r['lease_number']) : ($r['property_id'] ? e($r['property_name']) : e(__('Company-wide'))) ?></td>
          <td><?= e(ucfirst($r['fee_type'])) ?></td>
          <td><?= $r['fee_type'] === 'percentage' ? e((string)$r['amount'] . '%') : e(money($r['amount'])) ?></td>
          <td><?= (int)$r['grace_period_days'] ?></td>
          <td><?= $r['max_penalty'] ? e(money($r['max_penalty'])) : '—' ?></td>
          <td><?= $r['is_active'] ? '<span class="badge bg-success">' . e(__('Yes')) . '</span>' : '<span class="badge bg-secondary">' . e(__('No')) . '</span>' ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/late-fees/edit.php?id=<?= (int)$r['id'] ?>"><i data-lucide="pencil"></i></a>
            <a class="btn btn-sm btn-light text-danger" href="<?= APP_URL ?>/modules/late-fees/delete.php?id=<?= (int)$r['id'] ?>"><i data-lucide="trash-2"></i></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>    