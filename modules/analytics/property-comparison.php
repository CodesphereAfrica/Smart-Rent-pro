<?php
// modules/analytics/property-comparison.php — side-by-side property KPI comparison.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('analytics.view');
$cid  = (int)$USER['company_id'];

$selectedIds = (array)($_GET['property_ids'] ?? []);
$selectedIds = array_values(array_filter(array_map('intval', $selectedIds)));

$allProperties = db_all(
    'SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name',
    [':c' => $cid]
);

// Default: if nothing selected, compare the first 3.
if (!$selectedIds) {
    $selectedIds = array_slice(array_column($allProperties, 'id'), 0, 3);
    $selectedIds = array_map('intval', $selectedIds);
}

$rows = [];
$chartData = ['labels' => [], 'revenue' => [], 'expenses' => [], 'occupancy' => [], 'arrears' => []];
if ($selectedIds) {
    $in  = implode(',', array_fill(0, count($selectedIds), '?'));
    $sql = "SELECT p.id, p.name,
                   (SELECT COUNT(*) FROM units u WHERE u.property_id = p.id) AS units_total,
                   (SELECT COUNT(*) FROM units u WHERE u.property_id = p.id AND u.status = 'occupied') AS units_occupied,
                   COALESCE((SELECT SUM(i.amount_paid) FROM invoices i
                                JOIN units u ON u.id = i.unit_id
                               WHERE u.property_id = p.id AND i.status IN ('paid','partially_paid')
                                 AND i.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)), 0) AS revenue_12m,
                   COALESCE((SELECT SUM(e.amount) FROM expenses e
                               WHERE e.property_id = p.id
                                 AND e.expense_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)), 0) AS expenses_12m,
                   COALESCE((SELECT SUM(i.balance) FROM invoices i
                                JOIN units u ON u.id = i.unit_id
                               WHERE u.property_id = p.id AND i.balance > 0), 0) AS arrears,
                   COALESCE((SELECT AVG(DATEDIFF(mr.completed_at, mr.reported_at))
                               FROM maintenance_requests mr
                              WHERE mr.property_id = p.id AND mr.completed_at IS NOT NULL), 0) AS avg_maint_days
              FROM properties p
             WHERE p.id IN ($in) AND p.company_id = ?
             ORDER BY p.name";
    $params = array_merge($selectedIds, [$cid]);
    $rows = db_all($sql, $params);

    foreach ($rows as $r) {
        $unitsT = (int)$r['units_total'];
        $unitsO = (int)$r['units_occupied'];
        $occ = $unitsT > 0 ? round(($unitsO / $unitsT) * 100, 1) : 0.0;
        $net = (float)$r['revenue_12m'] - (float)$r['expenses_12m'];

        $chartData['labels'][]     = $r['name'];
        $chartData['revenue'][]    = (float)$r['revenue_12m'];
        $chartData['expenses'][]   = (float)$r['expenses_12m'];
        $chartData['occupancy'][]  = $occ;
        $chartData['arrears'][]    = (float)$r['arrears'];
    }
}

$PAGE_TITLE   = __('Property Comparison');
$PAGE_SCRIPTS = ['analytics-comparison.js'];
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/analytics/index.php"><?= e(__('Analytics')) ?></a></li>
  <li class="breadcrumb-item active"><?= e(__('Compare Properties')) ?></li>
</ol></nav>

<h1 class="h4 mb-3"><?= e(__('Compare Properties')) ?></h1>

<form method="get" class="srp-card p-3 mb-3">
  <label class="form-label small"><?= e(__('Select two or more properties')) ?></label>
  <div class="row g-2 mb-2">
    <?php foreach ($allProperties as $p): ?>
      <div class="col-md-4 col-lg-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="property_ids[]"
                 value="<?= (int)$p['id'] ?>" id="p_<?= (int)$p['id'] ?>"
                 <?= in_array((int)$p['id'], $selectedIds, true) ? 'checked' : '' ?>>
          <label class="form-check-label small" for="p_<?= (int)$p['id'] ?>"><?= e($p['name']) ?></label>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-primary btn-sm"><?= e(__('Compare')) ?></button>
</form>

<?php if (!$rows): ?>
  <div class="alert alert-info"><?= e(__('Select at least one property to compare.')) ?></div>
<?php else: ?>

  <div class="srp-card p-3 mb-4">
    <h2 class="h6 mb-3"><?= e(__('Side-by-Side Metrics — Last 12 Months')) ?></h2>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th><?= e(__('Property')) ?></th>
            <th class="text-end"><?= e(__('Units')) ?></th>
            <th class="text-end"><?= e(__('Occupancy')) ?></th>
            <th class="text-end"><?= e(__('Revenue 12m')) ?></th>
            <th class="text-end"><?= e(__('Expenses 12m')) ?></th>
            <th class="text-end"><?= e(__('Net 12m')) ?></th>
            <th class="text-end"><?= e(__('Arrears')) ?></th>
            <th class="text-end"><?= e(__('Avg Repair Time')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $unitsT = (int)$r['units_total'];
            $unitsO = (int)$r['units_occupied'];
            $occ = $unitsT > 0 ? round(($unitsO / $unitsT) * 100, 1) : 0.0;
            $net = (float)$r['revenue_12m'] - (float)$r['expenses_12m'];
          ?>
            <tr>
              <td><a href="<?= APP_URL ?>/modules/properties/view.php?id=<?= (int)$r['id'] ?>"><?= e($r['name']) ?></a></td>
              <td class="text-end"><?= $unitsT ?></td>
              <td class="text-end"><?= e((string)$occ) ?>%</td>
              <td class="text-end"><?= e(money($r['revenue_12m'])) ?></td>
              <td class="text-end"><?= e(money($r['expenses_12m'])) ?></td>
              <td class="text-end fw-semibold <?= $net >= 0 ? 'text-success' : 'text-danger' ?>"><?= e(money($net)) ?></td>
              <td class="text-end text-danger"><?= e(money($r['arrears'])) ?></td>
              <td class="text-end"><?= number_format((float)$r['avg_maint_days'], 1) ?>d</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="srp-card p-4">
        <h2 class="h6 mb-3"><?= e(__('Revenue vs Expenses (12m)')) ?></h2>
        <div style="position: relative; height: 300px;">
          <canvas id="chartCompareRevenue"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="srp-card p-4">
        <h2 class="h6 mb-3"><?= e(__('Occupancy Rate')) ?></h2>
        <div style="position: relative; height: 300px;">
          <canvas id="chartCompareOccupancy"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="srp-card p-4">
        <h2 class="h6 mb-3"><?= e(__('Arrears')) ?></h2>
        <div style="position: relative; height: 300px;">
          <canvas id="chartCompareArrears"></canvas>
        </div>
      </div>
    </div>
  </div>

  <script id="srpComparisonData" type="application/json">
  <?= json_encode($chartData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
  </script>

<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>