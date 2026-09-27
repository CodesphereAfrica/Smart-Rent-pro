<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_export.php';

$USER = require_permission('reports.view');
$cid = (int)$USER['company_id'];
$f = report_filters();

$where = ['e.company_id = :c', 'e.expense_date BETWEEN :from AND :to'];
$params = [':c' => $cid, ':from' => $f['from'], ':to' => $f['to']];
if ($f['property_id']) { $where[] = 'e.property_id = :pid'; $params[':pid'] = $f['property_id']; }
$w = implode(' AND ', $where);

$rows = db_all(
    "SELECT COALESCE(ec.name, 'Uncategorized') AS category,
            COALESCE(v.name, '—') AS vendor,
            p.name AS property_name,
            e.description, e.amount, e.expense_date, e.payment_status
       FROM expenses e
       LEFT JOIN expense_categories ec ON ec.id = e.category_id
       LEFT JOIN vendors v ON v.id = e.vendor_id
       LEFT JOIN properties p ON p.id = e.property_id
      WHERE $w
      ORDER BY e.expense_date DESC",
    $params
);

$headers = [__('Date'), __('Description'), __('Category'), __('Vendor'), __('Property'), __('Amount'), __('Status')];
$flat = [];
$total = 0.0;
foreach ($rows as $r) {
    $flat[] = [
        $r['expense_date'], $r['description'], $r['category'], $r['vendor'],
        $r['property_name'] ?: '—', number_format((float)$r['amount'], 2),
        ucfirst(str_replace('_', ' ', $r['payment_status'])),
    ];
    $total += (float)$r['amount'];
}

if ($f['format'] === 'csv')  report_csv('expenses-' . $f['from'] . '-' . $f['to'] . '.csv', $headers, $flat);
if ($f['format'] === 'xlsx') report_xlsx('expenses-' . $f['from'] . '-' . $f['to'] . '.xlsx', 'Expenses', $headers, $flat);
if ($f['format'] === 'pdf')  report_pdf('expenses.pdf', 'Expense Breakdown', $f, $headers, $flat);

$PAGE_TITLE = __('Expense Breakdown');
require __DIR__ . '/../../includes/header.php';
report_header(__('Expense Breakdown'), $f);
report_filter_bar($f, report_properties($cid));
?>
<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="trending-down"></i></div>
    <div class="kpi-label"><?= e(__('Total Expenses')) ?></div>
    <div class="kpi-value text-danger"><?= e(money($total)) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="hash"></i></div>
    <div class="kpi-label"><?= e(__('Records')) ?></div>
    <div class="kpi-value"><?= count($rows) ?></div></div>
</div>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Date')) ?></th><th><?= e(__('Description')) ?></th><th><?= e(__('Category')) ?></th>
      <th><?= e(__('Vendor')) ?></th><th><?= e(__('Property')) ?></th>
      <th class="text-end"><?= e(__('Amount')) ?></th><th><?= e(__('Status')) ?></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('No expenses in this period.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['expense_date']) ?></td>
          <td><?= e($r['description']) ?></td>
          <td><?= e($r['category']) ?></td>
          <td><?= e($r['vendor']) ?></td>
          <td><?= e($r['property_name'] ?? '—') ?></td>
          <td class="text-end"><?= e(money($r['amount'])) ?></td>
          <td><?= e(ucfirst(str_replace('_', ' ', $r['payment_status']))) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>