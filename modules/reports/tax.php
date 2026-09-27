<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_export.php';

$USER = require_permission('reports.view');
$cid = (int)$USER['company_id'];
$f = report_filters();

$rows = db_all(
    'SELECT t.tax_type, t.period_label, t.amount, t.due_date, t.paid_date, t.status,
            p.name AS property_name
       FROM tax_records t
       JOIN properties p ON p.id = t.property_id
      WHERE t.company_id = :c
      ORDER BY t.due_date DESC',
    [':c' => $cid]
);

$headers = [__('Property'), __('Tax Type'), __('Period'), __('Amount'), __('Due'), __('Paid'), __('Status')];
$flat = [];
foreach ($rows as $r) {
    $flat[] = [
        $r['property_name'], $r['tax_type'], $r['period_label'],
        number_format((float)$r['amount'], 2), $r['due_date'], $r['paid_date'] ?: '—',
        ucfirst($r['status']),
    ];
}

if ($f['format'] === 'csv')  report_csv('tax.csv', $headers, $flat);
if ($f['format'] === 'xlsx') report_xlsx('tax.xlsx', 'Tax', $headers, $flat);
if ($f['format'] === 'pdf')  report_pdf('tax.pdf', 'Tax & Compliance', $f, $headers, $flat);

$PAGE_TITLE = __('Tax & Compliance');
require __DIR__ . '/../../includes/header.php';
report_header(__('Tax & Compliance'), $f);
?>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Property')) ?></th><th><?= e(__('Tax Type')) ?></th><th><?= e(__('Period')) ?></th>
      <th class="text-end"><?= e(__('Amount')) ?></th><th><?= e(__('Due')) ?></th>
      <th><?= e(__('Paid')) ?></th><th><?= e(__('Status')) ?></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('No tax records.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['property_name']) ?></td>
          <td><?= e($r['tax_type']) ?></td>
          <td><?= e($r['period_label']) ?></td>
          <td class="text-end"><?= e(money($r['amount'])) ?></td>
          <td><?= e($r['due_date']) ?></td>
          <td><?= e($r['paid_date'] ?: '—') ?></td>
          <td><?= e(ucfirst($r['status'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>