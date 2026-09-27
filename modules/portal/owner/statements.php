<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
$USER = require_role('Property Owner');
$ownerId = (int)(db_one('SELECT id FROM owners WHERE user_id = :u LIMIT 1', [':u'=>(int)$USER['id']])['id'] ?? 0);
if (!$ownerId) { flash_error(__('No owner record linked.')); redirect('modules/dashboard/index.php'); }

// Owner-isolated query — enforce ownership at query level
$rows = db_all(
    'SELECT os.*, p.name AS property_name
       FROM owner_statements os
       JOIN properties p ON p.id = os.property_id
      WHERE os.owner_id = :o
      ORDER BY os.period_end DESC LIMIT 200',
    [':o' => $ownerId]
);

$PAGE_TITLE = __('Owner Statements');
require __DIR__ . '/../../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Owner Statements')) ?></h1>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Period')) ?></th><th><?= e(__('Property')) ?></th>
      <th class="text-end"><?= e(__('Income')) ?></th><th class="text-end"><?= e(__('Expenses')) ?></th>
      <th class="text-end"><?= e(__('Mgmt Fee')) ?></th><th class="text-end"><?= e(__('Net')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('No statements issued yet.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['period_start']) ?> → <?= e($r['period_end']) ?></td>
          <td><?= e($r['property_name']) ?></td>
          <td class="text-end"><?= e(money($r['total_income'])) ?></td>
          <td class="text-end"><?= e(money($r['total_expenses'])) ?></td>
          <td class="text-end"><?= e(money($r['management_fee_amount'])) ?></td>
          <td class="text-end fw-semibold"><?= e(money($r['net_owner_income'])) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/owner-statements/view.php?id=<?= (int)$r['id'] ?>"><i data-lucide="eye"></i></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>