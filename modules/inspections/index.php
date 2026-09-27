<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('inspections.view');

$PAGE_TITLE   = __('Inspections');
$PAGE_SCRIPTS = ['inspections.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Inspections')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Move-in, move-out, routine, maintenance and safety inspections with checklist results.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/inspections/create.php"><i data-lucide="plus"></i> <?= e(__('Schedule Inspection')) ?></a>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltType" class="form-select form-select-sm">
        <option value=""><?= e(__('All Types')) ?></option>
        <option value="move_in"><?= e(__('Move In')) ?></option>
        <option value="move_out"><?= e(__('Move Out')) ?></option>
        <option value="routine"><?= e(__('Routine')) ?></option>
        <option value="maintenance"><?= e(__('Maintenance')) ?></option>
        <option value="safety"><?= e(__('Safety')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <option value="scheduled"><?= e(__('Scheduled')) ?></option>
        <option value="completed"><?= e(__('Completed')) ?></option>
        <option value="cancelled"><?= e(__('Cancelled')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpInspectionsTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Date')) ?></th>
        <th><?= e(__('Type')) ?></th>
        <th><?= e(__('Unit / Property')) ?></th>
        <th><?= e(__('Inspector')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>