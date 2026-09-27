<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('maintenance.view');

$PAGE_TITLE   = __('Work Orders');
$PAGE_SCRIPTS = ['work-orders.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Work Orders')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Vendor and technician assignments tied to maintenance requests and expenses.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/work-orders/create.php"><i data-lucide="plus"></i> <?= e(__('New Work Order')) ?></a>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <option value="scheduled"><?= e(__('Scheduled')) ?></option>
        <option value="in_progress"><?= e(__('In Progress')) ?></option>
        <option value="completed"><?= e(__('Completed')) ?></option>
        <option value="cancelled"><?= e(__('Cancelled')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltVendor" class="form-select form-select-sm">
        <option value=""><?= e(__('All Vendors')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpWorkOrdersTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('WO #')) ?></th>
        <th><?= e(__('Ticket')) ?></th>
        <th><?= e(__('Vendor / Technician')) ?></th>
        <th><?= e(__('Scheduled')) ?></th>
        <th><?= e(__('Cost')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>