<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_export.php';

$USER = require_permission('reports.view');
$cid = (int)$USER['company_id'];
$f = report_filters();

$days = 90;
if (isset($_GET['days']) && in_array((int)$_GET['days'], [30, 60, 90], true)) {
    $days = (int)$_GET['days'];
}

$rows = db_all(
    "SELECT l.lease_number, l.start_date, l.end_date, l.monthly_rent, l.status,
            DATEDIFF(l.end_date, CURDATE()) AS days_left,
            CONCAT(t.first_name, ' ', t.last_name) AS tenant_name,
            t.phone AS tenant_phone,
            u.unit_number, p.name AS property_name
       FROM leases l
       JOIN tenants t ON t.id = l.tenant_id
       JOIN units   u ON u.id = l.unit_id
       JOIN properties p ON p.id = l.property_id
      WHERE l.company_id = :c
        AND l.status IN ('active', 'expiring')
        AND DATEDIFF(l.end_date, CURDATE()) BETWEEN 0 AND " . (int)$days . "
      ORDER BY l.end_date ASC",
    [':c' => $cid]
);

$headers = [__('Lease'), __('Tenant'), __('Phone'), __('Unit'), __('Property'), __('Start'), __('End'), __('Days Left'), __('Rent')];
$flat = [];
foreach ($rows as $r) {
    $flat[] = [
        $r['lease_number'], $r['tenant_name'], $r['tenant_phone'], $r['unit_number'], $r['property_name'],
        $r['start_date'], $r['end_date'], (int)$r['days_left'], number_format((float)$r['monthly_rent'], 2),
    ];
}

if ($f['format'] === 'csv')  report_csv('lease-expirations-' . $days . 'd.csv', $headers, $flat);
if ($f['format'] === 'xlsx') report_xlsx('lease-expirations-' . $days . 'd.xlsx', 'Lease Expirations', $headers, $flat);
if ($f['format'] === 'pdf')  report_pdf('lease-expirations.pdf', 'Lease Expirations (' . $days . ' days)', $f, $headers, $flat);

$PAGE_TITLE = __('Lease Expirations');
require __DIR__ . '/../../includes/header.php';
report_header(sprintf(__('Leases Expiring Within %d Days'), $days), $f);
?>
<div class="mb-3 d-flex gap-2">
  <?php foreach ([30, 60, 90] as $d): ?>
    <a class="btn btn-sm <?= $days === $d ? 'btn-primary' : 'btn-light' ?>" href="?<?= e(http_build_query(array_merge($_GET, ['days' => $d]))) ?>"><?= $d ?> <?= e(__('days')) ?></a>
  <?php endforeach; ?>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Lease')) ?></th><th><?= e(__('Tenant')) ?></th><th><?= e(__('Unit')) ?></th>
      <th><?= e(__('Property')) ?></th><th><?= e(__('End')) ?></th>
      <th class="text-end"><?= e(__('Days Left')) ?></th>
      <th class="text-end"><?= e(__('Rent')) ?></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4"><?= e(__('No leases expiring in this window.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><a href="<?= APP_URL ?>/modules/leases/view.php?id=<?= (int)$r['id'] ?>"><?= e($r['lease_number']) ?></a></td>
          <td><?= e($r['tenant_name']) ?></td>
          <td><?= e($r['unit_number']) ?></td>
          <td><?= e($r['property_name']) ?></td>
          <td><?= e($r['end_date']) ?></td>
          <td class="text-end <?= (int)$r['days_left'] <= 30 ? 'text-danger fw-semibold' : '' ?>"><?= (int)$r['days_left'] ?></td>
          <td class="text-end"><?= e(money($r['monthly_rent'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>