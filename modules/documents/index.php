<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('documents.view');

$PAGE_TITLE = __('Documents');
$PAGE_SCRIPTS = ['documents.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Documents')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Central document store with versioning, expiry tracking and category filtering.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/documents/upload.php"><i data-lucide="upload"></i> <?= e(__('Upload Document')) ?></a>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltCategory" class="form-select form-select-sm">
        <option value=""><?= e(__('All Categories')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltRelated" class="form-select form-select-sm">
        <option value=""><?= e(__('All Related Types')) ?></option>
        <option value="property"><?= e(__('Property')) ?></option>
        <option value="unit"><?= e(__('Unit')) ?></option>
        <option value="tenant"><?= e(__('Tenant')) ?></option>
        <option value="lease"><?= e(__('Lease')) ?></option>
        <option value="vendor"><?= e(__('Vendor')) ?></option>
        <option value="owner"><?= e(__('Owner')) ?></option>
        <option value="maintenance"><?= e(__('Maintenance')) ?></option>
        <option value="inspection"><?= e(__('Inspection')) ?></option>
        <option value="contract"><?= e(__('Contract')) ?></option>
        <option value="insurance"><?= e(__('Insurance')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltExpiry" class="form-select form-select-sm">
        <option value=""><?= e(__('Any expiry')) ?></option>
        <option value="30"><?= e(__('Expiring within 30 days')) ?></option>
        <option value="60"><?= e(__('Expiring within 60 days')) ?></option>
        <option value="expired"><?= e(__('Already expired')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpDocsTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Document')) ?></th>
        <th><?= e(__('Category')) ?></th>
        <th><?= e(__('Related To')) ?></th>
        <th><?= e(__('Size')) ?></th>
        <th><?= e(__('Version')) ?></th>
        <th><?= e(__('Expiry')) ?></th>
        <th><?= e(__('Uploaded')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>