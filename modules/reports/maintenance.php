<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_export.php';

$USER = require_permission('reports.view');
$cid = (int)$USER['company_id'];
$f = report_filters();

$rows = db_all(
    'SELECT mr.ticket_number, mr.priority, mr.status, mr.description,
            mr.estimated_cost, mr.actual_cost, mr.reported_at, mr.completed_at,
            u.unit_number, p.name AS property_name,
            v.name AS vendor_name
       FROM maintenance_requests mr
       JOIN units u ON u.id = mr.unit_id
       JOIN properties p ON p.id = mr.property_id
       LEFT JOIN vendors v ON v.id = mr.assigned_vendor_id
      WHERE mr.company_id = :c
        AND mr.reported_at BETWEEN :from AND :to
      ORDER BY mr.reported_at DESC',
    [':c' => $cid, ':from' => $f['from'] . ' 00:00:00', ':to' => $f['to'] . ' 23:59:59']
);

$headers = [__('Ticket'), __('Property'), __('Unit'), __('Priority'), __('Status'), __('Vendor'),
            __('Reported'), __('Completed'), __('Estimated'), __('Actual')];
$flat = [];
foreach ($rows as $r) {
    $flat[] = [
        $r['ticket_number'], $r['property_name'], $r['unit_number'],
        ucfirst($r['priority']), ucfirst(str_replace('_', ' ', $r['status'])),
        $r['vendor_name'] ?: '—',
        substr((string)$r['reported_at'], 0, 10),
        $r['completed_at'] ? substr((string)$r['completed_at'], 0, 10) : '—',
        number_format((float)($r['estimated_cost'] ?? 0), 2),
        number_format((float)($r['actual_cost'] ?? 0), 2),
    ];
}

if ($f['format'] === 'csv')  report_csv('maintenance-' . $f['from'] . '-' . $f['to'] . '.csv', $headers, $flat);
if ($f['format'] === 'xlsx') report_xlsx('maintenance-' . $f['from'] . '-' . $f['to'] . '.xlsx', 'Maintenance', $headers, $flat);
if ($f['format'] === 'pdf')  report_pdf('maintenance.pdf', 'Maintenance Report', $f, $headers, $flat);

$PAGE_TITLE = __('Maintenance Report');
require __DIR__ . '/../../includes/header.php';
report_header(__('Maintenance Report'), $f);
report_filter_bar($f, report_properties($cid));
?>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Ticket')) ?></th><th><?= e(__('Unit')) ?></th><th><?= e(__('Property')) ?></th>
      <th><?= e(__('Priority')) ?></th><th><?= e(__('Status')) ?></th><th><?= e(__('Vendor')) ?></th>
      <th><?= e(__('Reported')) ?></th><th class="text-end"><?= e(__('Actual')) ?></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('No maintenance in this period.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['ticket_number']) ?></td>
          <td><?= e($r['unit_number']) ?></td>
          <td><?= e($r['property_name']) ?></td>
          <td><?= e(ucfirst($r['priority'])) ?></td>
          <td><?= e(ucfirst(str_replace('_', ' ', $r['status']))) ?></td>
          <td><?= e($r['vendor_name'] ?? '—') ?></td>
          <td><?= e(substr((string)$r['reported_at'], 0, 10)) ?></td>
          <td class="text-end"><?= e(money($r['actual_cost'] ?? 0)) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>