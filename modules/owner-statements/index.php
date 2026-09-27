<?php
// modules/owner-statements/index.php — list of generated owner statements.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.view');

$rows = db_all(
    'SELECT os.*, o.full_name AS owner_name, p.name AS property_name,
            CONCAT(u.first_name, " ", u.last_name) AS generator
       FROM owner_statements os
       JOIN owners     o ON o.id = os.owner_id
       JOIN properties p ON p.id = os.property_id
       LEFT JOIN users u ON u.id = os.generated_by
      WHERE os.company_id = :c
      ORDER BY os.generated_at DESC
      LIMIT 300',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Owner Statements');
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Owner Statements')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Period summaries of income, expenses and management fees for each property owner.')) ?></p>
  </div>
  <?php if (can('properties.edit', $USER)): ?>
    <a class="btn btn-primary" href="<?= APP_URL ?>/modules/owner-statements/generate.php">
      <i data-lucide="plus"></i> <?= e(__('Generate Statement')) ?>
    </a>
  <?php endif; ?>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle mb-0">
    <thead>
      <tr>
        <th><?= e(__('Owner')) ?></th>
        <th><?= e(__('Property')) ?></th>
        <th><?= e(__('Period')) ?></th>
        <th class="text-end"><?= e(__('Income')) ?></th>
        <th class="text-end"><?= e(__('Expenses')) ?></th>
        <th class="text-end"><?= e(__('Mgmt Fee')) ?></th>
        <th class="text-end"><?= e(__('Net')) ?></th>
        <th><?= e(__('Generated')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="9" class="text-center text-muted py-4"><?= e(__('No statements yet. Click "Generate Statement" to create one.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['owner_name']) ?></td>
          <td><?= e($r['property_name']) ?></td>
          <td><?= e($r['period_start']) ?> → <?= e($r['period_end']) ?></td>
          <td class="text-end"><?= e(money($r['total_income'])) ?></td>
          <td class="text-end"><?= e(money($r['total_expenses'])) ?></td>
          <td class="text-end"><?= e(money($r['management_fee_amount'])) ?></td>
          <td class="text-end fw-semibold"><?= e(money($r['net_owner_income'])) ?></td>
          <td><?= e(date('Y-m-d H:i', strtotime($r['generated_at']))) ?>
            <?php if ($r['generator']): ?><div class="text-muted small"><?= e($r['generator']) ?></div><?php endif; ?>
          </td>
          <td class="text-end">
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/owner-statements/view.php?id=<?= (int)$r['id'] ?>"><i data-lucide="eye"></i></a>
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/owner-statements/view.php?id=<?= (int)$r['id'] ?>&pdf=1" target="_blank"><i data-lucide="file-text"></i></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>