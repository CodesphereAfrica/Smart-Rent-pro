<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_export.php';

$USER = require_permission('reports.view');
$cid = (int)$USER['company_id'];
$f = report_filters();

$properties = report_properties($cid);
$propFilter = $f['property_id'] ? 'AND u.property_id = :pid' : '';
$params = [':c' => $cid];
if ($f['property_id']) $params[':pid'] = $f['property_id'];

$rows = db_all(
    "SELECT p.name AS property_name,
            SUM(u.status = 'occupied')             AS occupied,
            SUM(u.status = 'available')            AS vacant,
            SUM(u.status = 'reserved')             AS reserved,
            SUM(u.status = 'under_maintenance')    AS maintenance,
            SUM(u.status = 'under_renovation')     AS renovation,
            SUM(u.status = 'notice_given')         AS notice,
            SUM(u.status = 'unavailable')          AS unavailable,
            COUNT(*)                               AS total,
            COALESCE(SUM(u.base_rent), 0)          AS potential_rent
       FROM units u
       JOIN properties p ON p.id = u.property_id
      WHERE u.company_id = :c AND p.deleted_at IS NULL $propFilter
      GROUP BY p.id
      ORDER BY p.name",
    $params
);

$headers = [__('Property'), __('Occupied'), __('Vacant'), __('Reserved'), __('Maintenance'), __('Renovation'), __('Notice Given'), __('Unavailable'), __('Total'), __('Potential Rent')];
$flat = [];
$tOcc = $tVac = $tRes = $tMnt = $tRen = $tNtc = $tUn = $tTot = 0; $tRent = 0.0;
foreach ($rows as $r) {
    $flat[] = [
        $r['property_name'], (int)$r['occupied'], (int)$r['vacant'], (int)$r['reserved'],
        (int)$r['maintenance'], (int)$r['renovation'], (int)$r['notice'], (int)$r['unavailable'],
        (int)$r['total'], number_format((float)$r['potential_rent'], 2),
    ];
    $tOcc += (int)$r['occupied']; $tVac += (int)$r['vacant']; $tRes += (int)$r['reserved'];
    $tMnt += (int)$r['maintenance']; $tRen += (int)$r['renovation']; $tNtc += (int)$r['notice'];
    $tUn  += (int)$r['unavailable']; $tTot += (int)$r['total']; $tRent += (float)$r['potential_rent'];
}
$flat[] = [__('TOTAL'), $tOcc, $tVac, $tRes, $tMnt, $tRen, $tNtc, $tUn, $tTot, number_format($tRent, 2)];

if ($f['format'] === 'csv')  report_csv('occupancy-' . $f['from'] . '-' . $f['to'] . '.csv', $headers, $flat);
if ($f['format'] === 'xlsx') report_xlsx('occupancy-' . $f['from'] . '-' . $f['to'] . '.xlsx', 'Occupancy', $headers, $flat);
if ($f['format'] === 'pdf')  report_pdf('occupancy.pdf', 'Occupancy Report', $f, $headers, $flat);

$PAGE_TITLE = __('Occupancy Report');
require __DIR__ . '/../../includes/header.php';
report_header(__('Occupancy Report'), $f);
report_filter_bar($f, $properties);
?>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?>
    </tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['property_name']) ?></td>
          <td><?= (int)$r['occupied'] ?></td>
          <td><?= (int)$r['vacant'] ?></td>
          <td><?= (int)$r['reserved'] ?></td>
          <td><?= (int)$r['maintenance'] ?></td>
          <td><?= (int)$r['renovation'] ?></td>
          <td><?= (int)$r['notice'] ?></td>
          <td><?= (int)$r['unavailable'] ?></td>
          <td class="fw-semibold"><?= (int)$r['total'] ?></td>
          <td><?= e(money($r['potential_rent'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="table-active fw-bold">
        <td><?= e(__('TOTAL')) ?></td>
        <td><?= $tOcc ?></td><td><?= $tVac ?></td><td><?= $tRes ?></td><td><?= $tMnt ?></td>
        <td><?= $tRen ?></td><td><?= $tNtc ?></td><td><?= $tUn ?></td><td><?= $tTot ?></td>
        <td><?= e(money($tRent)) ?></td>
      </tr>
    </tfoot>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>