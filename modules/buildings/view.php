<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('buildings.view');
$id = (int)($_GET['id'] ?? 0);
$b = db_one(
    'SELECT b.*, p.name AS property_name, p.id AS property_id
       FROM buildings b JOIN properties p ON p.id = b.property_id
      WHERE b.id = :id AND p.company_id = :c LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$b) { flash_error(__('Building not found.')); redirect('modules/buildings/index.php'); }

$units  = db_all('SELECT id, unit_number, base_rent, status FROM units WHERE building_id = :b ORDER BY unit_number', [':b'=>$id]);
$floors = db_all('SELECT id, floor_number, floor_name FROM floors WHERE building_id = :b ORDER BY floor_number', [':b'=>$id]);

$PAGE_TITLE = $b['name'];
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/buildings/index.php"><?= e(__('Buildings')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($b['name']) ?></li>
</ol></nav>
<div class="d-flex justify-content-between align-items-start mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e($b['name']) ?></h1>
    <div class="text-muted small">
      <?= e(__('in')) ?> <a href="<?= APP_URL ?>/modules/properties/view.php?id=<?= (int)$b['property_id'] ?>"><?= e($b['property_name']) ?></a>
    </div>
  </div>
  <div class="d-flex gap-2">
    <?php if (can('buildings.edit')): ?>
      <a href="<?= APP_URL ?>/modules/buildings/edit.php?id=<?= (int)$id ?>" class="btn btn-light"><i data-lucide="pencil"></i> <?= e(__('Edit')) ?></a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/modules/units/create.php?building_id=<?= (int)$id ?>&property_id=<?= (int)$b['property_id'] ?>" class="btn btn-primary"><i data-lucide="plus"></i> <?= e(__('Add Unit')) ?></a>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="kpi"><div class="kpi-icon"><i data-lucide="layers"></i></div>
    <div class="kpi-label"><?= e(__('Floors')) ?></div>
    <div class="kpi-value"><?= (int)$b['floors_count'] ?></div></div></div>
  <div class="col-md-3"><div class="kpi"><div class="kpi-icon"><i data-lucide="door-open"></i></div>
    <div class="kpi-label"><?= e(__('Units')) ?></div>
    <div class="kpi-value"><?= count($units) ?></div></div></div>
  <div class="col-md-3"><div class="kpi"><div class="kpi-icon"><i data-lucide="check-circle-2"></i></div>
    <div class="kpi-label"><?= e(__('Occupied')) ?></div>
    <div class="kpi-value"><?= count(array_filter($units, fn($u)=>$u['status']==='occupied')) ?></div></div></div>
  <div class="col-md-3"><div class="kpi"><div class="kpi-icon"><i data-lucide="info"></i></div>
    <div class="kpi-label"><?= e(__('Status')) ?></div>
    <div class="kpi-value text-capitalize"><?= e($b['status']) ?></div></div></div>
</div>

<?php if ($b['description']): ?>
<div class="srp-card p-4 mb-3">
  <h2 class="h6 mb-2"><?= e(__('About this building')) ?></h2>
  <div><?= nl2br(e($b['description'])) ?></div>
</div>
<?php endif; ?>

<div class="srp-card p-3">
  <h2 class="h6 mb-3"><?= e(__('Units in this building')) ?></h2>
  <table class="table table-hover align-middle">
    <thead><tr><th><?= e(__('Unit')) ?></th><th><?= e(__('Floor')) ?></th><th><?= e(__('Rent')) ?></th><th><?= e(__('Status')) ?></th><th></th></tr></thead>
    <tbody>
    <?php if (!$units): ?>
      <tr><td colspan="5" class="text-center text-muted py-4"><?= e(__('No units in this building yet.')) ?></td></tr>
    <?php else: foreach ($units as $u):
      $floor = null;
      foreach ($floors as $f) { /* look up by unit if needed — simplified */ }
    ?>
      <tr>
        <td><a href="<?= APP_URL ?>/modules/units/view.php?id=<?= (int)$u['id'] ?>"><?= e($u['unit_number']) ?></a></td>
        <td>—</td>
        <td><?= e(money($u['base_rent'])) ?></td>
        <td><span class="srp-badge-pill badge-status-<?= e($u['status']) ?>"><span class="dot"></span><?= e(ucfirst(str_replace('_',' ',$u['status']))) ?></span></td>
        <td class="text-end"><a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/units/view.php?id=<?= (int)$u['id'] ?>"><i data-lucide="arrow-right"></i></a></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>