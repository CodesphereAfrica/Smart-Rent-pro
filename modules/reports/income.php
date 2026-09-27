<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_export.php';

$USER = require_permission('reports.view');
$cid = (int)$USER['company_id'];
$f = report_filters();

// Income = sum of amount_paid on rent/utility/other/deposit invoices
$income = (float)(db_one(
    'SELECT COALESCE(SUM(i.amount_paid), 0) AS total
       FROM invoices i
       JOIN units u ON u.id = i.unit_id
      WHERE i.company_id = :c
        AND i.status IN ("paid", "partially_paid")
        AND i.invoice_type IN ("rent","utility","other","deposit")
        AND i.created_at BETWEEN :from AND :to',
    [':c' => $cid, ':from' => $f['from'] . ' 00:00:00', ':to' => $f['to'] . ' 23:59:59']
)['total'] ?? 0);

$expenses = (float)(db_one(
    'SELECT COALESCE(SUM(amount), 0) AS total
       FROM expenses
      WHERE company_id = :c AND expense_date BETWEEN :from AND :to',
    [':c' => $cid, ':from' => $f['from'], ':to' => $f['to']]
)['total'] ?? 0);

$incomeDetail = db_all(
    'SELECT i.invoice_type, COUNT(*) AS cnt, COALESCE(SUM(i.amount_paid), 0) AS total
       FROM invoices i
      WHERE i.company_id = :c
        AND i.status IN ("paid", "partially_paid")
        AND i.invoice_type IN ("rent","utility","other","deposit")
        AND i.created_at BETWEEN :from AND :to
      GROUP BY i.invoice_type
      ORDER BY total DESC',
    [':c' => $cid, ':from' => $f['from'] . ' 00:00:00', ':to' => $f['to'] . ' 23:59:59']
);

$expenseDetail = db_all(
    'SELECT COALESCE(ec.name, "Uncategorized") AS category, COUNT(*) AS cnt, COALESCE(SUM(e.amount), 0) AS total
       FROM expenses e
       LEFT JOIN expense_categories ec ON ec.id = e.category_id
      WHERE e.company_id = :c AND e.expense_date BETWEEN :from AND :to
      GROUP BY e.category_id
      ORDER BY total DESC',
    [':c' => $cid, ':from' => $f['from'], ':to' => $f['to']]
);

$net = $income - $expenses;

$headers = [__('Section'), __('Line'), __('Count'), __('Amount')];
$flat = [];
foreach ($incomeDetail as $r) {
    $flat[] = [__('Income'), ucfirst(str_replace('_', ' ', $r['invoice_type'])), (int)$r['cnt'], number_format((float)$r['total'], 2)];
}
$flat[] = [__('Income'), __('TOTAL'), '', number_format($income, 2)];
foreach ($expenseDetail as $r) {
    $flat[] = [__('Expense'), $r['category'], (int)$r['cnt'], number_format((float)$r['total'], 2)];
}
$flat[] = [__('Expense'), __('TOTAL'), '', number_format($expenses, 2)];
$flat[] = [__('Net Income'), '', '', number_format($net, 2)];

if ($f['format'] === 'csv')  report_csv('income-' . $f['from'] . '-' . $f['to'] . '.csv', $headers, $flat);
if ($f['format'] === 'xlsx') report_xlsx('income-' . $f['from'] . '-' . $f['to'] . '.xlsx', 'Income', $headers, $flat);
if ($f['format'] === 'pdf')  report_pdf('income.pdf', 'Income Statement', $f, $headers, $flat);

$PAGE_TITLE = __('Income Statement');
require __DIR__ . '/../../includes/header.php';
report_header(__('Income Statement'), $f);
report_filter_bar($f, report_properties($cid));
?>
<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="trending-up"></i></div>
    <div class="kpi-label"><?= e(__('Income')) ?></div>
    <div class="kpi-value text-success"><?= e(money($income)) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="trending-down"></i></div>
    <div class="kpi-label"><?= e(__('Expenses')) ?></div>
    <div class="kpi-value text-danger"><?= e(money($expenses)) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="wallet"></i></div>
    <div class="kpi-label"><?= e(__('Net Income')) ?></div>
    <div class="kpi-value <?= $net >= 0 ? 'text-success' : 'text-danger' ?>"><?= e(money($net)) ?></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="srp-card p-4 h-100">
      <h2 class="h6 mb-3"><?= e(__('Income Detail')) ?></h2>
      <table class="table table-sm mb-0">
        <thead><tr><th><?= e(__('Type')) ?></th><th class="text-end"><?= e(__('Count')) ?></th><th class="text-end"><?= e(__('Amount')) ?></th></tr></thead>
        <tbody>
          <?php if (!$incomeDetail): ?><tr><td colspan="3" class="text-muted text-center py-3">—</td></tr>
          <?php else: foreach ($incomeDetail as $r): ?>
            <tr><td><?= e(ucfirst(str_replace('_', ' ', $r['invoice_type']))) ?></td><td class="text-end"><?= (int)$r['cnt'] ?></td><td class="text-end"><?= e(money($r['total'])) ?></td></tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot><tr class="table-active fw-bold"><th colspan="2"><?= e(__('Total')) ?></th><th class="text-end"><?= e(money($income)) ?></th></tr></tfoot>
      </table>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="srp-card p-4 h-100">
      <h2 class="h6 mb-3"><?= e(__('Expense Detail')) ?></h2>
      <table class="table table-sm mb-0">
        <thead><tr><th><?= e(__('Category')) ?></th><th class="text-end"><?= e(__('Count')) ?></th><th class="text-end"><?= e(__('Amount')) ?></th></tr></thead>
        <tbody>
          <?php if (!$expenseDetail): ?><tr><td colspan="3" class="text-muted text-center py-3">—</td></tr>
          <?php else: foreach ($expenseDetail as $r): ?>
            <tr><td><?= e($r['category']) ?></td><td class="text-end"><?= (int)$r['cnt'] ?></td><td class="text-end"><?= e(money($r['total'])) ?></td></tr>
          <?php endforeach; endif; ?>
        </tbody>
        <tfoot><tr class="table-active fw-bold"><th colspan="2"><?= e(__('Total')) ?></th><th class="text-end"><?= e(money($expenses)) ?></th></tr></tfoot>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>