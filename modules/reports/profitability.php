<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_export.php';

$USER = require_permission('reports.view');
$cid = (int)$USER['company_id'];
$f = report_filters();

$rows = db_all(
    'SELECT p.id, p.name AS property_name,
            COALESCE((SELECT SUM(i.amount_paid) FROM invoices i
                        JOIN units u ON u.id = i.unit_id
                       WHERE u.property_id = p.id AND i.company_id = :c
                         AND i.status IN ("paid","partially_paid")
                         AND i.invoice_type IN ("rent","utility","other","deposit")
                         AND i.created_at BETWEEN :from AND :to), 0) AS income,
            COALESCE((SELECT SUM(e.amount) FROM expenses e
                       WHERE e.property_id = p.id AND e.company_id = :c
                         AND e.expense_date BETWEEN :from AND :to), 0) AS expenses,
            (SELECT COUNT(*) FROM units u WHERE u.property_id = p.id) AS units
       FROM properties p
      WHERE p.company_id = :c AND p.deleted_at IS NULL
      ORDER BY income DESC',
    [':c' => $cid, ':from' => $f['from'] . ' 00:00:00', ':to' => $f['to'] . ' 23:59:59']
);

$headers = [__('Property'), __('Units'), __('Income'), __('Expenses'), __('Net'), __('Margin %')];
$flat = [];
foreach ($rows as $r) {
    $income = (float)$r['income']; $expenses = (float)$r['expenses']; $net = $income - $expenses;
    $margin = $income > 0 ? round(($net / $income) * 100, 1) : 0;
    $flat[] = [$r['property_name'], (int)$r['units'], number_format($income, 2), number_format($expenses, 2), number_format($net, 2), $margin];
}

if ($f['format'] === 'csv')  report_csv('profitability-' . $f['from'] . '-' . $f['to'] . '.csv', $headers, $flat);
if ($f['format'] === 'xlsx') report_xlsx('profitability-' . $f['from'] . '-' . $f['to'] . '.xlsx', 'Profitability', $headers, $flat);
if ($f['format'] === 'pdf')  report_pdf('profitability.pdf', 'Property Profitability', $f, $headers, $flat);

$PAGE_TITLE = __('Property Profitability');
require __DIR__ . '/../../includes/header.php';
report_header(__('Property Profitability'), $f);
report_filter_bar($f, report_properties($cid));
?>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Property')) ?></th><th class="text-end"><?= e(__('Units')) ?></th>
      <th class="text-end"><?= e(__('Income')) ?></th><th class="text-end"><?= e(__('Expenses')) ?></th>
      <th class="text-end"><?= e(__('Net')) ?></th><th class="text-end"><?= e(__('Margin %')) ?></th>
    </tr></thead>
    <tbody>
      <?php foreach ($rows as $r):
        $income = (float)$r['income']; $expenses = (float)$r['expenses']; $net = $income - $expenses;
        $margin = $income > 0 ? round(($net / $income) * 100, 1) : 0;
      ?>
        <tr>
          <td><a href="<?= APP_URL ?>/modules/properties/view.php?id=<?= (int)$r['id'] ?>"><?= e($r['property_name']) ?></a></td>
          <td class="text-end"><?= (int)$r['units'] ?></td>
          <td class="text-end"><?= e(money($income)) ?></td>
          <td class="text-end"><?= e(money($expenses)) ?></td>
          <td class="text-end fw-semibold <?= $net >= 0 ? 'text-success' : 'text-danger' ?>"><?= e(money($net)) ?></td>
          <td class="text-end"><?= $margin ?>%</td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>