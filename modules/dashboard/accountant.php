<?php
// modules/dashboard/accountant.php — accountant's financial overview.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_login();
$cid  = (int)$USER['company_id'];

$kpi = db_one(
    'SELECT
        (SELECT COALESCE(SUM(amount),0) FROM payments WHERE company_id = :c AND status = "completed" AND YEAR(payment_date)=YEAR(CURDATE()) AND MONTH(payment_date)=MONTH(CURDATE())) AS month_collected,
        (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE company_id = :c AND YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())) AS month_expenses,
        (SELECT COALESCE(SUM(balance),0) FROM invoices WHERE company_id = :c AND balance > 0) AS ar_total,
        (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE company_id = :c AND payment_status <> "paid") AS ap_total,
        (SELECT COUNT(*) FROM approval_requests WHERE company_id = :c AND status = "pending") AS pending_approvals
    ',
    [':c'=>$cid]
);

$aging = db_one(
    'SELECT
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 0 AND 30 THEN balance ELSE 0 END),0)  AS b1,
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 31 AND 60 THEN balance ELSE 0 END),0) AS b2,
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 61 AND 90 THEN balance ELSE 0 END),0) AS b3,
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) > 90 THEN balance ELSE 0 END),0) AS b4
       FROM invoices WHERE company_id = :c AND balance > 0 AND status IN ("due","partially_paid","overdue")',
    [':c'=>$cid]
);

$expenseBreakdown = db_all(
    'SELECT cat.name AS category, COALESCE(SUM(e.amount),0) AS total
       FROM expenses e LEFT JOIN expense_categories cat ON cat.id = e.category_id
      WHERE e.company_id = :c AND YEAR(e.expense_date)=YEAR(CURDATE())
      GROUP BY cat.id ORDER BY total DESC LIMIT 8',
    [':c'=>$cid]
);

$PAGE_TITLE   = __('Finance Dashboard');
$PAGE_SCRIPTS = ['accountant.js'];
require __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3"><?= e(__('Finance Dashboard')) ?></h1>

<div class="kpi-grid mb-4">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="trending-up"></i></div>
    <div class="kpi-label"><?= e(__('This Month Collected')) ?></div>
    <div class="kpi-value text-success"><?= e(money($kpi['month_collected'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="trending-down"></i></div>
    <div class="kpi-label"><?= e(__('This Month Expenses')) ?></div>
    <div class="kpi-value text-danger"><?= e(money($kpi['month_expenses'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="receipt"></i></div>
    <div class="kpi-label"><?= e(__('Accounts Receivable')) ?></div>
    <div class="kpi-value"><?= e(money($kpi['ar_total'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="credit-card"></i></div>
    <div class="kpi-label"><?= e(__('Accounts Payable')) ?></div>
    <div class="kpi-value"><?= e(money($kpi['ap_total'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="check-square"></i></div>
    <div class="kpi-label"><?= e(__('Pending Approvals')) ?></div>
    <div class="kpi-value"><?= (int)$kpi['pending_approvals'] ?></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Arrears Aging')) ?></h2>
      <canvas id="chartAging" height="90"></canvas>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Year-to-Date Expense Categories')) ?></h2>
      <canvas id="chartExpenses" height="90"></canvas>
    </div>
  </div>
</div>

<div class="d-flex flex-wrap gap-2 mt-3">
  <a class="btn btn-light" href="<?= APP_URL ?>/modules/accounting/trial-balance.php"><i data-lucide="scale"></i> <?= e(__('Trial Balance')) ?></a>
  <a class="btn btn-light" href="<?= APP_URL ?>/modules/accounting/income-statement.php"><i data-lucide="file-text"></i> <?= e(__('Income Statement')) ?></a>
  <a class="btn btn-light" href="<?= APP_URL ?>/modules/accounting/balance-sheet.php"><i data-lucide="book-open"></i> <?= e(__('Balance Sheet')) ?></a>
  <a class="btn btn-light" href="<?= APP_URL ?>/modules/accounting/ar.php"><i data-lucide="receipt"></i> <?= e(__('AR Aging')) ?></a>
  <a class="btn btn-light" href="<?= APP_URL ?>/modules/accounting/ap.php"><i data-lucide="credit-card"></i> <?= e(__('AP')) ?></a>
</div>

<script id="srpAccountantData" type="application/json">
<?= json_encode([
    'aging'   => $aging,
    'expense' => array_map(fn($e) => ['label'=>$e['category'] ?? 'Uncategorized','value'=>(float)$e['total']], $expenseBreakdown),
], JSON_UNESCAPED_UNICODE) ?>
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>