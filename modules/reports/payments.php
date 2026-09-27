<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_export.php';

$USER = require_permission('reports.view');
$cid = (int)$USER['company_id'];
$f = report_filters();

$rows = db_all(
    'SELECT p.payment_method,
            COUNT(*) AS cnt,
            COALESCE(SUM(p.amount), 0) AS total
       FROM payments p
      WHERE p.company_id = :c
        AND p.status = "completed"
        AND p.payment_date BETWEEN :from AND :to
      GROUP BY p.payment_method
      ORDER BY total DESC',
    [':c' => $cid, ':from' => $f['from'], ':to' => $f['to']]
);

$headers = [__('Method'), __('Count'), __('Total')];
$flat = [];
$grandTotal = 0.0; $grandCount = 0;
foreach ($rows as $r) {
    $flat[] = [ucfirst(str_replace('_', ' ', $r['payment_method'])), (int)$r['cnt'], number_format((float)$r['total'], 2)];
    $grandTotal += (float)$r['total']; $grandCount += (int)$r['cnt'];
}
$flat[] = [__('TOTAL'), $grandCount, number_format($grandTotal, 2)];

if ($f['format'] === 'csv')  report_csv('payments-' . $f['from'] . '-' . $f['to'] . '.csv', $headers, $flat);
if ($f['format'] === 'xlsx') report_xlsx('payments-' . $f['from'] . '-' . $f['to'] . '.xlsx', 'Payments', $headers, $flat);
if ($f['format'] === 'pdf')  report_pdf('payments.pdf', 'Payments Summary', $f, $headers, $flat);

$PAGE_TITLE = __('Payments Summary');
require __DIR__ . '/../../includes/header.php';
report_header(__('Payments Summary'), $f);
report_filter_bar($f, report_properties($cid));
?>
<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Total Collected')) ?></div>
    <div class="kpi-value"><?= e(money($grandTotal)) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="hash"></i></div>
    <div class="kpi-label"><?= e(__('Payments')) ?></div>
    <div class="kpi-value"><?= (int)$grandCount ?></div></div>
</div>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr><th><?= e(__('Method')) ?></th><th class="text-end"><?= e(__('Count')) ?></th><th class="text-end"><?= e(__('Total')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e(ucfirst(str_replace('_', ' ', $r['payment_method']))) ?></td>
          <td class="text-end"><?= (int)$r['cnt'] ?></td>
          <td class="text-end"><?= e(money($r['total'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="table-active fw-bold">
        <td><?= e(__('TOTAL')) ?></td>
        <td class="text-end"><?= (int)$grandCount ?></td>
        <td class="text-end"><?= e(money($grandTotal)) ?></td>
      </tr>
    </tfoot>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>