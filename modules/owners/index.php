<?php
// modules/owners/index.php — DataTables list of owners.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('owners.view');

$PAGE_TITLE   = __('Owners');
$PAGE_SCRIPTS = ['owners.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Property Owners')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Landlords and their portfolios. Data isolation is enforced at query level.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/owners/create.php">
    <i data-lucide="plus"></i> <?= e(__('Add Owner')) ?>
  </a>
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
      <select id="fltType" class="form-select form-select-sm">
        <option value=""><?= e(__('All Types')) ?></option>
        <option value="individual"><?= e(__('Individual')) ?></option>
        <option value="company"><?= e(__('Company')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpOwnersTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Owner')) ?></th>
        <th><?= e(__('Contact')) ?></th>
        <th><?= e(__('Properties')) ?></th>
        <th><?= e(__('Ownership %')) ?></th>
        <th><?= e(__('Portal Access')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>