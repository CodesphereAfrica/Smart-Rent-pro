<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
$USER = require_role('Property Owner');
$ownerId = (int)(db_one('SELECT id FROM owners WHERE user_id = :u LIMIT 1', [':u'=>(int)$USER['id']])['id'] ?? 0);
if (!$ownerId) { flash_error(__('No owner record linked.')); redirect('modules/dashboard/index.php'); }

$properties = db_all(
    'SELECT p.id, p.name, p.property_type, p.address, p.city, p.status, op.ownership_percentage
       FROM owner_properties op JOIN properties p ON p.id = op.property_id
      WHERE op.owner_id = :o AND p.deleted_at IS NULL
      ORDER BY p.name',
    [':o' => $ownerId]
);

$PAGE_TITLE = __('My Properties');
require __DIR__ . '/../../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('My Properties')) ?></h1>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr><th><?= e(__('Property')) ?></th><th><?= e(__('Type')) ?></th><th><?= e(__('Location')) ?></th><th><?= e(__('Own %')) ?></th><th><?= e(__('Status')) ?></th><th></th></tr></thead>
    <tbody>
      <?php if (!$properties): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('No properties.')) ?></td></tr>
      <?php else: foreach ($properties as $p): ?>
        <tr>
          <td><?= e($p['name']) ?></td>
          <td><?= e(ucfirst(str_replace('_',' ',$p['property_type']))) ?></td>
          <td><?= e(trim(($p['city'] ?? '') . ', ' . ($p['address'] ?? ''), ', ')) ?></td>
          <td><?= number_format((float)$p['ownership_percentage'], 1) ?>%</td>
          <td><?= e(ucfirst($p['status'])) ?></td>
          <td class="text-end"><a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/properties/view.php?id=<?= (int)$p['id'] ?>"><i data-lucide="arrow-right"></i></a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>