<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.view');

$rows = db_all(
    'SELECT i.*, p.name AS property_name,
            DATEDIFF(i.expiry_date, CURDATE()) AS days_left
       FROM insurance_policies i
       JOIN properties p ON p.id = i.property_id
      WHERE i.company_id = :c ORDER BY i.expiry_date ASC',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Insurance');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Insurance Policies')) ?></h1>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/insurance/create.php"><i data-lucide="plus"></i> <?= e(__('Add Policy')) ?></a>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Provider')) ?></th><th><?= e(__('Policy #')) ?></th><th><?= e(__('Property')) ?></th>
      <th><?= e(__('Premium')) ?></th><th><?= e(__('Expiry')) ?></th><th><?= e(__('Status')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('No policies.')) ?></td></tr>
      <?php else: foreach ($rows as $i): ?>
        <tr>
          <td><?= e($i['provider']) ?></td>
          <td><?= e($i['policy_number']) ?></td>
          <td><?= e($i['property_name']) ?></td>
          <td><?= e(money($i['premium'] ?? 0)) ?></td>
          <td>
            <?= e($i['expiry_date']) ?>
            <?php if ((int)$i['days_left'] < 30 && (int)$i['days_left'] >= 0): ?>
              <span class="text-danger small">(<?= (int)$i['days_left'] ?>d)</span>
            <?php elseif ((int)$i['days_left'] < 0): ?>
              <span class="text-danger small"><?= e(__('expired')) ?></span>
            <?php endif; ?>
          </td>
          <td><?= e(ucfirst($i['status'])) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/insurance/edit.php?id=<?= (int)$i['id'] ?>"><i data-lucide="pencil"></i></a>
            <a class="btn btn-sm btn-light text-danger" href="<?= APP_URL ?>/modules/insurance/delete.php?id=<?= (int)$i['id'] ?>"><i data-lucide="trash-2"></i></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>