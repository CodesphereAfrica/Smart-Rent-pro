<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_export.php';

$USER = require_permission('reports.view');
$cid = (int)$USER['company_id'];
$f = report_filters();

$rows = db_all(
    'SELECT i.id, i.invoice_number, i.invoice_type, i.amount, i.amount_paid, i.balance, i.due_date,
            DATEDIFF(CURDATE(), i.due_date) AS days_overdue,
            CONCAT(t.first_name, " ", t.last_name) AS tenant_name,
            t.phone AS tenant_phone,
            u.unit_number, p.name AS property_name
       FROM invoices i
       JOIN tenants t ON t.id = i.tenant_id
       JOIN units   u ON u.id = i.unit_id
       JOIN properties p ON p.id = u.property_id
      WHERE i.company_id = :c
        AND i.balance > 0
        AND i.status IN ("due","partially_paid","overdue")
        AND i.due_date < CURDATE()
      ORDER BY i.due_date ASC',
    [':c' => $cid]
);

$headers = [__('Invoice'), __('Tenant'), __('Phone'), __('Unit'), __('Property'), __('Type'), __('Amount'), __('Paid'), __('Balance'), __('Due Date'), __('Days Overdue')];
$flat = [];
foreach ($rows as $r) {
    $flat[] = [
        $r['invoice_number'], $r['tenant_name'], $r['tenant_phone'], $r['unit_number'],
        $r['property_name'], ucfirst(str_replace('_', ' ', $r['invoice_type'])),
        number_format((float)$r['amount'], 2), number_format((float)$r['amount_paid'], 2),
        number_format((float)$r['balance'], 2), $r['due_date'], (int)$r['days_overdue'],
    ];
}

if ($f['format'] === 'csv')  report_csv('arrears-' . date('Ymd') . '.csv', $headers, $flat);
if ($f['format'] === 'xlsx') report_xlsx('arrears-' . date('Ymd') . '.xlsx', 'Arrears', $headers, $flat);
if ($f['format'] === 'pdf')  report_pdf('arrears.pdf', 'Arrears Aging', $f, $headers, $flat);

$PAGE_TITLE = __('Arrears Aging');
require __DIR__ . '/../../includes/header.php';
report_header(__('Arrears Aging'), $f);
?>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Invoice')) ?></th><th><?= e(__('Tenant')) ?></th><th><?= e(__('Unit')) ?></th>
      <th><?= e(__('Property')) ?></th><th><?= e(__('Type')) ?></th>
      <th class="text-end"><?= e(__('Balance')) ?></th>
      <th><?= e(__('Due')) ?></th><th class="text-end"><?= e(__('Days')) ?></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('No outstanding arrears.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/modules/invoices/view.php?id=<?= (int)$r['id'] ?>"><?= e($r['invoice_number']) ?></a></td>
          <td><?= e($r['tenant_name']) ?></td>
          <td><?= e($r['unit_number']) ?></td>
          <td><?= e($r['property_name']) ?></td>
          <td><?= e(ucfirst(str_replace('_', ' ', $r['invoice_type']))) ?></td>
          <td class="text-end text-danger fw-semibold"><?= e(money($r['balance'])) ?></td>
          <td><?= e($r['due_date']) ?></td>
          <td class="text-end"><?= (int)$r['days_overdue'] ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>