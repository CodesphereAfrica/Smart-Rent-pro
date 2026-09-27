<?php
// modules/parking/index.php — DataTables list of parking spaces.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.view');

$PAGE_TITLE   = __('Parking');
$PAGE_SCRIPTS = ['parking.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Parking Spaces')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Spaces, fees and active assignments.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/parking/create.php">
    <i data-lucide="plus"></i> <?= e(__('Add Space')) ?>
  </a>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-4">
      <select id="fltProperty" class="form-select form-select-sm">
        <option value=""><?= e(__('All Properties')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <option value="available"><?= e(__('Available')) ?></option>
        <option value="assigned"><?= e(__('Assigned')) ?></option>
        <option value="unavailable"><?= e(__('Unavailable')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpParkingTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Space #')) ?></th>
        <th><?= e(__('Property')) ?></th>
        <th><?= e(__('Type')) ?></th>
        <th><?= e(__('Monthly Fee')) ?></th>
        <th><?= e(__('Active Assignments')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>