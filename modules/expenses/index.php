<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('expenses.view');

$stats = db_one(
    'SELECT
       COUNT(*) AS total,
       COALESCE(SUM(amount),0) AS total_amount,
       COALESCE(SUM(CASE WHEN payment_status="unpaid" THEN amount ELSE 0 END),0) AS unpaid
     FROM expenses WHERE company_id = :c',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE   = __('Expenses');
$PAGE_SCRIPTS = ['expenses.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Expenses')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Every operating cost, linked to property, unit, vendor and work order.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/expenses/create.php"><i data-lucide="plus"></i> <?= e(__('Add Expense')) ?></a>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="trending-down"></i></div>
    <div class="kpi-label"><?= e(__('Total Expenses')) ?></div>
    <div class="kpi-value"><?= e(money($stats['total_amount'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-circle"></i></div>
    <div class="kpi-label"><?= e(__('Unpaid')) ?></div>
    <div class="kpi-value text-danger"><?= e(money($stats['unpaid'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="hash"></i></div>
    <div class="kpi-label"><?= e(__('Records')) ?></div>
    <div class="kpi-value"><?= (int)$stats['total'] ?></div></div>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <option value="unpaid"><?= e(__('Unpaid')) ?></option>
        <option value="partially_paid"><?= e(__('Partially Paid')) ?></option>
        <option value="paid"><?= e(__('Paid')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltCategory" class="form-select form-select-sm">
        <option value=""><?= e(__('All Categories')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltProperty" class="form-select form-select-sm">
        <option value=""><?= e(__('All Properties')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpExpensesTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Date')) ?></th>
        <th><?= e(__('Description')) ?></th>
        <th><?= e(__('Category')) ?></th>
        <th><?= e(__('Property / Unit')) ?></th>
        <th><?= e(__('Vendor')) ?></th>
        <th><?= e(__('Amount')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>