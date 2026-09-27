<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('invoices.view');

$stats = db_one(
    'SELECT
       COUNT(*) AS total,
       COALESCE(SUM(amount), 0) AS invoiced,
       COALESCE(SUM(amount_paid), 0) AS collected,
       COALESCE(SUM(balance), 0) AS outstanding,
       SUM(CASE WHEN status="overdue" THEN 1 ELSE 0 END) AS overdue_count
     FROM invoices WHERE company_id = :c',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE   = __('Invoices');
$PAGE_SCRIPTS = ['invoices.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Invoices')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('All rent, utility, late-fee and deposit charges. Void only — never deleted.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/invoices/create.php"><i data-lucide="plus"></i> <?= e(__('New Invoice')) ?></a>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="receipt"></i></div>
    <div class="kpi-label"><?= e(__('Total Invoiced')) ?></div>
    <div class="kpi-value"><?= e(money($stats['invoiced'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="check-circle-2"></i></div>
    <div class="kpi-label"><?= e(__('Collected')) ?></div>
    <div class="kpi-value"><?= e(money($stats['collected'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-triangle"></i></div>
    <div class="kpi-label"><?= e(__('Outstanding')) ?></div>
    <div class="kpi-value text-danger"><?= e(money($stats['outstanding'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alarm-clock"></i></div>
    <div class="kpi-label"><?= e(__('Overdue Count')) ?></div>
    <div class="kpi-value"><?= (int)$stats['overdue_count'] ?></div></div>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <?php foreach (['upcoming','due','partially_paid','paid','overdue','written_off'] as $s): ?>
          <option value="<?= e($s) ?>"><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltType" class="form-select form-select-sm">
        <option value=""><?= e(__('All Types')) ?></option>
        <?php foreach (['rent','late_fee','utility','deposit','other'] as $t): ?>
          <option value="<?= e($t) ?>"><?= e(ucfirst(str_replace('_',' ',$t))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <input id="fltPeriod" type="month" class="form-control form-control-sm" title="<?= e(__('Period')) ?>">
    </div>
  </div>

  <table id="srpInvoicesTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Invoice #')) ?></th>
        <th><?= e(__('Tenant')) ?></th>
        <th><?= e(__('Unit')) ?></th>
        <th><?= e(__('Type')) ?></th>
        <th><?= e(__('Amount')) ?></th>
        <th><?= e(__('Balance')) ?></th>
        <th><?= e(__('Due')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>