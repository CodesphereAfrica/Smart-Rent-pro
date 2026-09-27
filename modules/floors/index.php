<?php
// modules/floors/index.php — DataTables list of floors.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('buildings.view');

$buildingId = (int)($_GET['building_id'] ?? 0);
$building = $buildingId
    ? db_one(
        'SELECT b.*, p.name AS property_name
           FROM buildings b
           JOIN properties p ON p.id = b.property_id
          WHERE b.id = :b AND p.company_id = :c AND p.deleted_at IS NULL
          LIMIT 1',
        [':b' => $buildingId, ':c' => (int)$USER['company_id']]
    )
    : null;

$PAGE_TITLE   = __('Floors');
$PAGE_SCRIPTS = ['floors.js'];
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/buildings/index.php"><?= e(__('Buildings')) ?></a></li>
    <?php if ($building): ?>
      <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/buildings/view.php?id=<?= (int)$buildingId ?>"><?= e($building['name']) ?></a></li>
    <?php endif; ?>
    <li class="breadcrumb-item active"><?= e(__('Floors')) ?></li>
  </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Floors')) ?><?= $building ? ' · ' . e($building['name']) : '' ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Levels defined within buildings.')) ?></p>
  </div>
  <a class="btn btn-primary"
     href="<?= APP_URL ?>/modules/floors/create.php<?= $buildingId ? '?building_id=' . (int)$buildingId : '' ?>">
    <i data-lucide="plus"></i> <?= e(__('Add Floor')) ?>
  </a>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-4">
      <select id="fltBuilding" class="form-select form-select-sm"<?= $buildingId ? ' data-preset="' . (int)$buildingId . '"' : '' ?>>
        <option value=""><?= e(__('All Buildings')) ?></option>
        <?php if ($building): ?>
          <option value="<?= (int)$buildingId ?>" selected><?= e($building['property_name'] . ' · ' . $building['name']) ?></option>
        <?php endif; ?>
      </select>
    </div>
  </div>

  <table id="srpFloorsTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Building')) ?></th>
        <th><?= e(__('Property')) ?></th>
        <th><?= e(__('Floor')) ?></th>
        <th><?= e(__('Units')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>