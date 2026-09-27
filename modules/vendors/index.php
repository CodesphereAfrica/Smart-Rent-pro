<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('vendors.view');

$PAGE_TITLE   = __('Vendors');
$PAGE_SCRIPTS = ['vendors.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Vendors')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Contractors, suppliers and service providers with performance metrics.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/vendors/create.php"><i data-lucide="plus"></i> <?= e(__('Add Vendor')) ?></a>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <option value="active"><?= e(__('Active')) ?></option>
        <option value="inactive"><?= e(__('Inactive')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <input id="fltCategory" class="form-control form-control-sm" placeholder="<?= e(__('Category (e.g. plumber)')) ?>">
    </div>
  </div>

  <table id="srpVendorsTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Vendor')) ?></th>
        <th><?= e(__('Category')) ?></th>
        <th><?= e(__('Contact')) ?></th>
        <th><?= e(__('Rating')) ?></th>
        <th><?= e(__('Jobs')) ?></th>
        <th><?= e(__('Avg Response')) ?></th>
        <th><?= e(__('Total Paid')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>