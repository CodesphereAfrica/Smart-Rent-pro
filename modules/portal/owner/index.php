<?php
// modules/portal/owner/index.php — read-focused dashboard for an owner.
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';

$USER = require_role('Property Owner');
$ownerId = (int)(db_one('SELECT id FROM owners WHERE user_id = :u LIMIT 1', [':u'=>(int)$USER['id']])['id'] ?? 0);

if (!$ownerId) { flash_error(__('Your account is not linked to an owner record yet.')); redirect('modules/dashboard/index.php'); }

// Properties owned by this owner, isolated at query level
$properties = db_all(
    'SELECT p.id, p.name, p.property_type, p.address, p.city, p.status, op.ownership_percentage,
            (SELECT COUNT(*) FROM units u WHERE u.property_id = p.id) AS units_total,
            (SELECT COUNT(*) FROM units u WHERE u.property_id = p.id AND u.status="occupied") AS units_occupied,
            COALESCE((SELECT SUM(i.amount_paid) FROM invoices i
                        JOIN units u ON u.id = i.unit_id
                       WHERE u.property_id = p.id AND i.status IN ("paid","partially_paid")
                         AND YEAR(i.created_at)=YEAR(CURDATE())), 0) AS collected_ytd
       FROM owner_properties op
       JOIN properties p ON p.id = op.property_id
      WHERE op.owner_id = :o AND p.deleted_at IS NULL
      ORDER BY p.name',
    [':o'=>$ownerId]
);

$totals = db_one(
    'SELECT
        COUNT(*) AS props,
        COALESCE(SUM(op.ownership_percentage),0) AS ownership_sum
       FROM owner_properties op JOIN properties p ON p.id = op.property_id
      WHERE op.owner_id = :o AND p.deleted_at IS NULL',
    [':o'=>$ownerId]
);

$PAGE_TITLE = __('Owner Portal');
require __DIR__ . '/../../../includes/header.php';
?>

<h1 class="h4 mb-1"><?= e(__('Welcome')) ?>, <?= e($USER['first_name']) ?></h1>
<p class="text-muted small mb-3"><?= e(__('Your portfolio at a glance.')) ?></p>

<div class="kpi-grid mb-4">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="building"></i></div>
    <div class="kpi-label"><?= e(__('Properties')) ?></div>
    <div class="kpi-value"><?= (int)$totals['props'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Collected YTD')) ?></div>
    <div class="kpi-value"><?= e(money(array_sum(array_column($properties, 'collected_ytd')))) ?></div></div>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Property')) ?></th><th><?= e(__('Location')) ?></th>
      <th><?= e(__('Units')) ?></th><th><?= e(__('Ownership')) ?></th><th><?= e(__('Collected YTD')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$properties): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('No properties linked yet.')) ?></td></tr>
      <?php else: foreach ($properties as $p): ?>
        <tr>
          <td><?= e($p['name']) ?></td>
          <td><?= e(trim(($p['city'] ?? '') . ', ' . ($p['address'] ?? ''), ', ')) ?></td>
          <td><?= (int)$p['units_occupied'] ?> / <?= (int)$p['units_total'] ?></td>
          <td><?= number_format((float)$p['ownership_percentage'], 1) ?>%</td>
          <td><?= e(money($p['collected_ytd'])) ?></td>
          <td class="text-end"><a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/properties/view.php?id=<?= (int)$p['id'] ?>"><i data-lucide="arrow-right"></i></a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="mt-3">
  <a class="btn btn-light" href="<?= APP_URL ?>/modules/portal/owner/statements.php"><i data-lucide="scroll-text"></i> <?= e(__('Owner Statements')) ?></a>
  <a class="btn btn-light" href="<?= APP_URL ?>/modules/portal/owner/documents.php"><i data-lucide="folder"></i> <?= e(__('Documents')) ?></a>
</div>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>