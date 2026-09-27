<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('payments.view');

$today = db_one(
    'SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS amt
       FROM payments WHERE company_id = :c AND payment_date = CURDATE() AND status = "completed"',
    [':c' => (int)$USER['company_id']]
);
$month = db_one(
    'SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS amt
       FROM payments WHERE company_id = :c AND YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE()) AND status="completed"',
    [':c' => (int)$USER['company_id']]
);
$unalloc = db_one(
    'SELECT COALESCE(SUM(amount_unallocated),0) AS amt
       FROM payments WHERE company_id = :c AND status="completed" AND amount_unallocated > 0',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE   = __('Payments');
$PAGE_SCRIPTS = ['payments.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Payments')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('All receipts — auto-allocated to outstanding invoices by configurable rules.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/payments/create.php"><i data-lucide="plus"></i> <?= e(__('Record Payment')) ?></a>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__("Today's Collections")) ?></div>
    <div class="kpi-value"><?= e(money($today['amt'])) ?></div>
    <div class="text-muted small"><?= (int)$today['cnt'] ?> <?= e(__('payments')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="calendar"></i></div>
    <div class="kpi-label"><?= e(__('This Month')) ?></div>
    <div class="kpi-value"><?= e(money($month['amt'])) ?></div>
    <div class="text-muted small"><?= (int)$month['cnt'] ?> <?= e(__('payments')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="inbox"></i></div>
    <div class="kpi-label"><?= e(__('Unallocated')) ?></div>
    <div class="kpi-value <?= (float)$unalloc['amt'] > 0 ? 'text-warning' : '' ?>"><?= e(money($unalloc['amt'])) ?></div>
    <div class="text-muted small"><?= e(__('Needs manual allocation')) ?></div></div>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltMethod" class="form-select form-select-sm">
        <option value=""><?= e(__('All Methods')) ?></option>
        <?php foreach (['cash','bank_transfer','mobile_money','card','cheque','other'] as $m): ?>
          <option value="<?= e($m) ?>"><?= e(ucfirst(str_replace('_',' ',$m))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <option value="completed"><?= e(__('Completed')) ?></option>
        <option value="pending"><?= e(__('Pending')) ?></option>
        <option value="reversed"><?= e(__('Reversed')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <input type="date" id="fltFrom" class="form-control form-control-sm" placeholder="<?= e(__('From')) ?>">
    </div>
    <div class="col-md-3">
      <input type="date" id="fltTo" class="form-control form-control-sm" placeholder="<?= e(__('To')) ?>">
    </div>
  </div>

  <table id="srpPaymentsTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Date')) ?></th>
        <th><?= e(__('Tenant')) ?></th>
        <th><?= e(__('Unit')) ?></th>
        <th><?= e(__('Amount')) ?></th>
        <th><?= e(__('Unallocated')) ?></th>
        <th><?= e(__('Method')) ?></th>
        <th><?= e(__('Reference')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>