<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_export.php';

$USER = require_permission('reports.view');
$cid = (int)$USER['company_id'];
$f = report_filters();

$rows = db_all(
    'SELECT os.period_start, os.period_end,
            o.full_name AS owner_name,
            p.name AS property_name,
            os.total_income, os.total_expenses, os.management_fee_pct,
            os.management_fee_amount, os.net_owner_income, os.generated_at
       FROM owner_statements os
       JOIN owners o ON o.id = os.owner_id
       JOIN properties p ON p.id = os.property_id
      WHERE os.company_id = :c
        AND os.period_start >= :from
        AND os.period_end   <= :to
      ORDER BY os.generated_at DESC',
    [':c' => $cid, ':from' => $f['from'], ':to' => $f['to']]
);

$headers = [__('Owner'), __('Property'), __('Period Start'), __('Period End'),
            __('Income'), __('Expenses'), __('Mgmt Fee %'), __('Mgmt Fee'), __('Net'), __('Generated')];
$flat = [];
foreach ($rows as $r) {
    $flat[] = [
        $r['owner_name'], $r['property_name'], $r['period_start'], $r['period_end'],
        number_format((float)$r['total_income'], 2),
        number_format((float)$r['total_expenses'], 2),
        number_format((float)$r['management_fee_pct'], 2),
        number_format((float)$r['management_fee_amount'], 2),
        number_format((float)$r['net_owner_income'], 2),
        $r['generated_at'],
    ];
}

if ($f['format'] === 'csv')  report_csv('owner-statements-' . $f['from'] . '-' . $f['to'] . '.csv', $headers, $flat);
if ($f['format'] === 'xlsx') report_xlsx('owner-statements-' . $f['from'] . '-' . $f['to'] . '.xlsx', 'Owner Statements', $headers, $flat);
if ($f['format'] === 'pdf')  report_pdf('owner-statements.pdf', 'Owner Statements Report', $f, $headers, $flat);

$PAGE_TITLE = __('Owner Statements Report');
require __DIR__ . '/../../includes/header.php';
report_header(__('Owner Statements Report'), $f);
?>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Owner')) ?></th><th><?= e(__('Property')) ?></th><th><?= e(__('Period')) ?></th>
      <th class="text-end"><?= e(__('Income')) ?></th><th class="text-end"><?= e(__('Expenses')) ?></th>
      <th class="text-end"><?= e(__('Mgmt Fee')) ?></th><th class="text-end"><?= e(__('Net')) ?></th>
      <th></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4"><?= e(__('No statements in this period.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['owner_name']) ?></td>
          <td><?= e($r['property_name']) ?></td>
          <td><?= e($r['period_start']) ?> → <?= e($r['period_end']) ?></td>
          <td class="text-end"><?= e(money($r['total_income'])) ?></td>
          <td class="text-end"><?= e(money($r['total_expenses'])) ?></td>
          <td class="text-end"><?= e(money($r['management_fee_amount'])) ?> (<?= e(number_format((float)$r['management_fee_pct'], 2)) ?>%)</td>
          <td class="text-end fw-semibold"><?= e(money($r['net_owner_income'])) ?></td>
          <td class="text-end"><a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/owner-statements/view.php?id=<?= (int)$r['id'] ?>" target="_blank"><i data-lucide="external-link"></i></a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>