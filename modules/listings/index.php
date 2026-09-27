<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.view');

$rows = db_all(
    'SELECT l.*, u.unit_number, p.name AS property_name
       FROM listings l
       JOIN units u ON u.id = l.unit_id
       JOIN properties p ON p.id = u.property_id
      WHERE l.company_id = :c ORDER BY l.id DESC',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Public Listings');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Public Listings')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Publicly published units that appear on the public site.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/listings/create.php"><i data-lucide="plus"></i> <?= e(__('New Listing')) ?></a>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Title')) ?></th><th><?= e(__('Property / Unit')) ?></th>
      <th><?= e(__('Rent')) ?></th><th><?= e(__('Published')) ?></th><th><?= e(__('Views')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['title']) ?></td>
          <td><?= e($r['property_name']) ?> · <?= e($r['unit_number']) ?></td>
          <td><?= e(money($r['rent'])) ?></td>
          <td><?= $r['is_published'] ? '<span class="badge bg-success">' . e(__('Yes')) . '</span>' : '<span class="badge bg-secondary">' . e(__('No')) . '</span>' ?></td>
          <td><?= (int)$r['views_count'] ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/listings/edit.php?id=<?= (int)$r['id'] ?>"><i data-lucide="pencil"></i></a>
            <a class="btn btn-sm btn-light" target="_blank" href="<?= APP_URL ?>/modules/public-listings/view.php?slug=<?= e($r['slug']) ?>"><i data-lucide="external-link"></i></a>
            <a class="btn btn-sm btn-light text-danger" href="<?= APP_URL ?>/modules/listings/delete.php?id=<?= (int)$r['id'] ?>"><i data-lucide="trash-2"></i></a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>