<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.view');

$PAGE_TITLE   = __('Contracts');
$PAGE_SCRIPTS = ['contracts.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Contracts')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Service contracts (security, cleaning, maintenance, internet) with renewal reminders.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/contracts/create.php"><i data-lucide="plus"></i> <?= e(__('New Contract')) ?></a>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <option value="active"><?= e(__('Active')) ?></option>
        <option value="expired"><?= e(__('Expired')) ?></option>
        <option value="terminated"><?= e(__('Terminated')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltExpiry" class="form-select form-select-sm">
        <option value=""><?= e(__('Any expiry')) ?></option>
        <option value="30"><?= e(__('Expiring within 30 days')) ?></option>
        <option value="60"><?= e(__('Expiring within 60 days')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpContractsTable" class="table table-hover align-middle w-100">
    <thead><tr>
      <th><?= e(__('Title')) ?></th>
      <th><?= e(__('Type')) ?></th>
      <th><?= e(__('Vendor')) ?></th>
      <th><?= e(__('Period')) ?></th>
      <th><?= e(__('Amount')) ?></th>
      <th><?= e(__('Status')) ?></th>
      <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
    </tr></thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>