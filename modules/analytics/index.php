<?php
// modules/analytics/index.php — analytics dashboard with KPI cards and Chart.js visualisations.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('analytics.view');
$cid  = (int)$USER['company_id'];

// ---------------------------------------------------------------------------
// Portfolio-level KPIs — one round-trip.
// ---------------------------------------------------------------------------
$kpi = db_one(
    'SELECT
        (SELECT COUNT(*) FROM units WHERE company_id = :c)                                       AS units_total,
        (SELECT COUNT(*) FROM units WHERE company_id = :c AND status = "occupied")                AS units_occupied,
        (SELECT COUNT(*) FROM units WHERE company_id = :c AND status = "available")               AS units_vacant,
        (SELECT COUNT(*) FROM tenants WHERE company_id = :c AND status = "active")                AS tenants_active,
        (SELECT COUNT(*) FROM leases  WHERE company_id = :c AND status IN ("active","expiring"))  AS leases_active,
        (SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE company_id = :c AND status IN ("paid","partially_paid") AND period_start >= DATE_FORMAT(CURDATE(), "%Y-%m-01")) AS expected_this_month,
        (SELECT COALESCE(SUM(amount_paid), 0) FROM invoices WHERE company_id = :c AND period_start >= DATE_FORMAT(CURDATE(), "%Y-%m-01")) AS collected_this_month,
        (SELECT COALESCE(SUM(balance), 0) FROM invoices WHERE company_id = :c AND balance > 0) AS outstanding,
        (SELECT COALESCE(AVG(DATEDIFF(completed_at, reported_at)), 0) FROM maintenance_requests WHERE company_id = :c AND completed_at IS NOT NULL) AS avg_maint_days,
        (SELECT COALESCE(SUM(actual_cost), 0) FROM maintenance_requests WHERE company_id = :c AND completed_at IS NOT NULL) AS maint_cost,
        (SELECT COALESCE(AVG(DATEDIFF(COALESCE(end_date, CURDATE()), start_date)), 0) FROM leases WHERE company_id = :c) AS avg_tenant_days,
        (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE company_id = :c AND status = "completed" AND payment_date >= DATE_FORMAT(CURDATE(), "%Y-%m-01")) AS cash_this_month,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE company_id = :c AND expense_date >= DATE_FORMAT(CURDATE(), "%Y-%m-01")) AS expense_this_month
    ',
    [':c' => $cid]
);

$unitsTotal = (int)$kpi['units_total'];
$occRate = $unitsTotal > 0 ? round(((int)$kpi['units_occupied'] / $unitsTotal) * 100, 1) : 0.0;
$vacRate = $unitsTotal > 0 ? round(((int)$kpi['units_vacant']   / $unitsTotal) * 100, 1) : 0.0;
$expected  = (float)$kpi['expected_this_month'];
$collected = (float)$kpi['collected_this_month'];
$collRate  = $expected > 0 ? round(($collected / $expected) * 100, 1) : 0.0;

$revenuePerUnit = $unitsTotal > 0 ? round((float)$kpi['cash_this_month'] / $unitsTotal, 0) : 0;
$maintPerUnit   = $unitsTotal > 0 ? round((float)$kpi['maint_cost'] / $unitsTotal, 0) : 0;
$avgTenantMonths = round(((float)$kpi['avg_tenant_days']) / 30.4, 1);

// ---------------------------------------------------------------------------
// Snapshot series — used by the trend charts. Falls back to live data if empty.
// ---------------------------------------------------------------------------
$snapshots = db_all(
    'SELECT snapshot_date, occupancy_rate, collection_rate, net_operating_income,
            expected_rent, collected_rent, total_expenses
       FROM analytics_daily_snapshots
      WHERE company_id = :c AND property_id IS NULL
        AND snapshot_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
      ORDER BY snapshot_date ASC',
    [':c' => $cid]
);

// If snapshots are empty (cron not yet run), synthesize the last 12 months
// from live tables so the charts still have something meaningful to show.
if (!$snapshots) {
    $snapshots = db_all(
        'SELECT DATE_FORMAT(payment_date, "%Y-%m-01") AS snapshot_date,
                NULL AS occupancy_rate,
                NULL AS collection_rate,
                SUM(amount) AS collected_rent,
                NULL AS expected_rent,
                SUM(amount) AS net_operating_income,
                0 AS total_expenses
           FROM payments
          WHERE company_id = :c AND status = "completed"
            AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
          GROUP BY DATE_FORMAT(payment_date, "%Y-%m-01")
          ORDER BY snapshot_date ASC',
        [':c' => $cid]
    );
}

// ---------------------------------------------------------------------------
// Occupancy split for the doughnut.
// ---------------------------------------------------------------------------
$occBreakdown = db_one(
    'SELECT
        SUM(status = "occupied")          AS occupied,
        SUM(status = "available")         AS available,
        SUM(status = "reserved")          AS reserved,
        SUM(status IN ("under_maintenance","under_renovation")) AS maintenance,
        SUM(status IN ("notice_given","unavailable"))           AS other
       FROM units WHERE company_id = :c',
    [':c' => $cid]
);

// ---------------------------------------------------------------------------
// Arrears aging — the 4 buckets.
// ---------------------------------------------------------------------------
$aging = db_one(
    'SELECT
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 0 AND 30 THEN balance ELSE 0 END), 0)  AS b1,
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 31 AND 60 THEN balance ELSE 0 END), 0) AS b2,
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 61 AND 90 THEN balance ELSE 0 END), 0) AS b3,
       COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) > 90 THEN balance ELSE 0 END), 0) AS b4
       FROM invoices WHERE company_id = :c AND balance > 0 AND status IN ("due","partially_paid","overdue")',
    [':c' => $cid]
);

// ---------------------------------------------------------------------------
// Maintenance status funnel.
// ---------------------------------------------------------------------------
$maintenanceFunnel = db_all(
    'SELECT status, COUNT(*) AS cnt
       FROM maintenance_requests
      WHERE company_id = :c
      GROUP BY status',
    [':c' => $cid]
);
$funnelMap = [];
foreach ($maintenanceFunnel as $r) $funnelMap[$r['status']] = (int)$r['cnt'];

// ---------------------------------------------------------------------------
// Lease expirations — next 30/60/90 days.
// ---------------------------------------------------------------------------
$expiries = db_one(
    'SELECT
       SUM(DATEDIFF(end_date, CURDATE()) BETWEEN 0 AND 30)  AS d30,
       SUM(DATEDIFF(end_date, CURDATE()) BETWEEN 31 AND 60) AS d60,
       SUM(DATEDIFF(end_date, CURDATE()) BETWEEN 61 AND 90) AS d90
       FROM leases WHERE company_id = :c AND status IN ("active","expiring")',
    [':c' => $cid]
);

$PAGE_TITLE   = __('Analytics');
$PAGE_SCRIPTS = ['analytics.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Analytics')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Live portfolio KPIs and trends across every property.')) ?></p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/analytics/property-comparison.php">
      <i data-lucide="git-compare"></i> <?= e(__('Compare Properties')) ?>
    </a>
  </div>
</div>

<!-- KPI strip -->
<div class="kpi-grid mb-4">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="pie-chart"></i></div>
    <div class="kpi-label"><?= e(__('Occupancy Rate')) ?></div>
    <div class="kpi-value"><?= e((string)$occRate) ?>%</div>
    <div class="text-muted small"><?= (int)$kpi['units_occupied'] ?> / <?= $unitsTotal ?> <?= e(__('units')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="percent"></i></div>
    <div class="kpi-label"><?= e(__('Collection Rate')) ?></div>
    <div class="kpi-value"><?= e((string)$collRate) ?>%</div>
    <div class="text-muted small"><?= e(__('This month')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="key-round"></i></div>
    <div class="kpi-label"><?= e(__('Vacancy Rate')) ?></div>
    <div class="kpi-value"><?= e((string)$vacRate) ?>%</div>
    <div class="text-muted small"><?= (int)$kpi['units_vacant'] ?> <?= e(__('vacant')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Revenue / Unit')) ?></div>
    <div class="kpi-value"><?= e(money($revenuePerUnit)) ?></div>
    <div class="text-muted small"><?= e(__('This month')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="hammer"></i></div>
    <div class="kpi-label"><?= e(__('Avg. Repair Time')) ?></div>
    <div class="kpi-value"><?= number_format((float)$kpi['avg_maint_days'], 1) ?>d</div>
    <div class="text-muted small"><?= e(__('Across all tickets')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="wrench"></i></div>
    <div class="kpi-label"><?= e(__('Maint. / Unit')) ?></div>
    <div class="kpi-value"><?= e(money($maintPerUnit)) ?></div>
    <div class="text-muted small"><?= e(__('Lifetime')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="user-clock"></i></div>
    <div class="kpi-label"><?= e(__('Avg. Tenant Lifetime')) ?></div>
    <div class="kpi-value"><?= e((string)$avgTenantMonths) ?> <?= e(__('mo')) ?></div>
    <div class="text-muted small"><?= (int)$kpi['leases_active'] ?> <?= e(__('active leases')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-triangle"></i></div>
    <div class="kpi-label"><?= e(__('Outstanding')) ?></div>
    <div class="kpi-value <?= (float)$kpi['outstanding'] > 0 ? 'text-danger' : '' ?>"><?= e(money($kpi['outstanding'])) ?></div>
    <div class="text-muted small"><?= e(__('Total AR')) ?></div></div>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Occupancy & Collection Trend')) ?></h2>
      <div style="position: relative; height: 300px;">
        <canvas id="chartTrend"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Unit Status')) ?></h2>
      <div style="position: relative; height: 300px;">
        <canvas id="chartOccupancy"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Arrears Aging')) ?></h2>
      <div style="position: relative; height: 260px;">
        <canvas id="chartAging"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Maintenance Funnel')) ?></h2>
      <div style="position: relative; height: 260px;">
        <canvas id="chartFunnel"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Lease Expirations — Next 90 Days')) ?></h2>
      <div style="position: relative; height: 260px;">
        <canvas id="chartExpiry"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Revenue vs Expenses — Last 6 Months')) ?></h2>
      <div style="position: relative; height: 260px;">
        <canvas id="chartRevenue"></canvas>
      </div>
    </div>
  </div>
</div>

<script id="srpAnalyticsData" type="application/json">
<?= json_encode([
    'snapshots' => array_map(fn($s) => [
        'date'      => $s['snapshot_date'],
        'occ'       => $s['occupancy_rate'],
        'coll'      => $s['collection_rate'],
        'collected' => (float)$s['collected_rent'],
        'expenses'  => (float)$s['total_expenses'],
    ], $snapshots),
    'occupancy' => [
        'occupied'    => (int)($occBreakdown['occupied']    ?? 0),
        'available'   => (int)($occBreakdown['available']   ?? 0),
        'reserved'    => (int)($occBreakdown['reserved']    ?? 0),
        'maintenance' => (int)($occBreakdown['maintenance'] ?? 0),
        'other'       => (int)($occBreakdown['other']       ?? 0),
    ],
    'aging' => [
        'b1' => (float)$aging['b1'], 'b2' => (float)$aging['b2'],
        'b3' => (float)$aging['b3'], 'b4' => (float)$aging['b4'],
    ],
    'funnel' => [
        'new'               => $funnelMap['new']               ?? 0,
        'assigned'          => $funnelMap['assigned']          ?? 0,
        'in_progress'       => $funnelMap['in_progress']       ?? 0,
        'waiting_for_parts' => $funnelMap['waiting_for_parts'] ?? 0,
        'completed'         => $funnelMap['completed']         ?? 0,
        'verified'          => $funnelMap['verified']          ?? 0,
        'closed'            => $funnelMap['closed']            ?? 0,
    ],
    'expiry' => [
        'd30' => (int)($expiries['d30'] ?? 0),
        'd60' => (int)($expiries['d60'] ?? 0),
        'd90' => (int)($expiries['d90'] ?? 0),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>