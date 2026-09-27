<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.view');

$PAGE_TITLE   = __('Assets');
$PAGE_SCRIPTS = ['assets.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Assets & Inventory')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Equipment and furniture tracked by property, unit and warranty.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/assets/create.php"><i data-lucide="plus"></i> <?= e(__('Add Asset')) ?></a>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <option value="in_use"><?= e(__('In Use')) ?></option>
        <option value="under_repair"><?= e(__('Under Repair')) ?></option>
        <option value="retired"><?= e(__('Retired')) ?></option>
        <option value="disposed"><?= e(__('Disposed')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltProperty" class="form-select form-select-sm">
        <option value=""><?= e(__('All Properties')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpAssetsTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Asset')) ?></th>
        <th><?= e(__('Property / Unit')) ?></th>
        <th><?= e(__('Serial')) ?></th>
        <th><?= e(__('Purchase Cost')) ?></th>
        <th><?= e(__('Warranty Expiry')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>