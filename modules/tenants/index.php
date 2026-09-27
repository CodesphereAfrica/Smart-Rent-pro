<?php
// modules/tenants/index.php — DataTables list of tenants (shell page).
// Row rendering — including profile photo vs. initials fallback — is produced by ajax_list.php.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('tenants.view');
$cid  = (int)$USER['company_id'];

// --- KPI header: tenant counts + arrears ---
// One round-trip: tenant status breakdown + arrears totals from invoices, in a single row.
$stats = db_one(
    'SELECT
        (SELECT COUNT(*) FROM tenants WHERE company_id = :c) AS total,
        (SELECT COUNT(*) FROM tenants WHERE company_id = :c AND status = "active")      AS active,
        (SELECT COUNT(*) FROM tenants WHERE company_id = :c AND status = "former")      AS former,
        (SELECT COUNT(*) FROM tenants WHERE company_id = :c AND status = "blacklisted") AS blacklisted,
        COALESCE((
            SELECT SUM(balance) FROM invoices
             WHERE company_id = :c AND balance > 0
               AND status IN ("due","partially_paid","overdue")
        ), 0) AS arrears_total,
        (
            SELECT COUNT(DISTINCT tenant_id) FROM invoices
             WHERE company_id = :c AND balance > 0
               AND status IN ("due","partially_paid","overdue")
        ) AS tenants_in_arrears',
    [':c' => $cid]
);

// Arrears % of total for the KPI subtitle.
$arrearsPct = ((int)$stats['total']) > 0
    ? round(((int)$stats['tenants_in_arrears'] / (int)$stats['total']) * 100, 1)
    : 0.0;

$PAGE_TITLE   = __('Tenants');
$PAGE_SCRIPTS = ['tenants.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Tenants')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Every tenant, their active unit and their balance.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/tenants/create.php">
    <i data-lucide="plus"></i> <?= e(__('Add Tenant')) ?>
  </a>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="users"></i></div>
    <div class="kpi-label"><?= e(__('Total')) ?></div>
    <div class="kpi-value"><?= (int)$stats['total'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="user-check"></i></div>
    <div class="kpi-label"><?= e(__('Active')) ?></div>
    <div class="kpi-value"><?= (int)$stats['active'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="user-minus"></i></div>
    <div class="kpi-label"><?= e(__('Former')) ?></div>
    <div class="kpi-value"><?= (int)$stats['former'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-triangle"></i></div>
    <div class="kpi-label"><?= e(__('In Arrears')) ?></div>
    <div class="kpi-value"><?= (int)$stats['tenants_in_arrears'] ?></div>
    <div class="text-muted small"><?= e(money($stats['arrears_total'])) ?> · <?= e((string)$arrearsPct) ?>%</div></div>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <option value="active"><?= e(__('Active')) ?></option>
        <option value="former"><?= e(__('Former')) ?></option>
        <option value="blacklisted"><?= e(__('Blacklisted')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltType" class="form-select form-select-sm">
        <option value=""><?= e(__('All Types')) ?></option>
        <option value="individual"><?= e(__('Individual')) ?></option>
        <option value="corporate"><?= e(__('Corporate')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltArrears" class="form-select form-select-sm">
        <option value=""><?= e(__('Any balance')) ?></option>
        <option value="yes"><?= e(__('Has arrears')) ?></option>
        <option value="no"><?= e(__('No arrears')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpTenantsTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Tenant')) ?></th>
        <th><?= e(__('Contact')) ?></th>
        <th><?= e(__('Unit')) ?></th>
        <th><?= e(__('Balance')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>