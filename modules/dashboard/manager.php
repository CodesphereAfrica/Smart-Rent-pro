<?php
// modules/dashboard/manager.php — the operational "Today" screen for Property Manager.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_login();
$cid  = (int)$USER['company_id'];

// --- Overview KPIs ---
$kpi = db_one(
    'SELECT
        (SELECT COUNT(*) FROM properties WHERE company_id = :c AND deleted_at IS NULL)                                AS properties,
        (SELECT COUNT(*) FROM units WHERE company_id = :c)                                                            AS units_total,
        (SELECT COUNT(*) FROM units WHERE company_id = :c AND status = "occupied")                                     AS units_occupied,
        (SELECT COUNT(*) FROM units WHERE company_id = :c AND status = "available")                                    AS units_vacant,
        (SELECT COUNT(*) FROM leases WHERE company_id = :c AND status IN ("active","expiring"))                        AS leases_active,
        (SELECT COUNT(*) FROM leases WHERE company_id = :c AND status IN ("active","expiring") AND end_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)) AS leases_expiring_60,
        (SELECT COALESCE(SUM(balance),0) FROM invoices WHERE company_id = :c AND status IN ("due","partially_paid","overdue")) AS outstanding,
        (SELECT COALESCE(SUM(balance),0) FROM invoices WHERE company_id = :c AND status = "overdue")                   AS overdue_amount,
        (SELECT COUNT(*) FROM maintenance_requests WHERE company_id = :c AND status NOT IN ("closed","verified"))       AS open_maint,
        (SELECT COUNT(*) FROM approval_requests WHERE company_id = :c AND status = "pending")                          AS pending_approvals,
        (SELECT COUNT(*) FROM inspections WHERE company_id = :c AND inspection_date = CURDATE() AND status = "scheduled") AS inspections_today
    ',
    [':c'=>$cid]
);

$unitsOccupancy = ((int)$kpi['units_total']) > 0
    ? round(((int)$kpi['units_occupied'] / (int)$kpi['units_total']) * 100, 1) : 0.0;

$collections = db_one(
    'SELECT COALESCE(SUM(amount),0) AS today_total, COUNT(*) AS today_count
       FROM payments WHERE company_id = :c AND payment_date = CURDATE() AND status = "completed"',
    [':c'=>$cid]
);

// --- Attention panels ---
$overdueList = db_all(
    'SELECT i.id, i.invoice_number, i.balance, i.due_date,
            DATEDIFF(CURDATE(), i.due_date) AS days_late,
            CONCAT(t.first_name," ",t.last_name) AS tenant_name,
            u.unit_number, t.id AS tenant_id
       FROM invoices i
       JOIN tenants t ON t.id = i.tenant_id
       JOIN units   u ON u.id = i.unit_id
      WHERE i.company_id = :c AND i.status IN ("overdue","partially_paid") AND i.balance > 0
      ORDER BY i.due_date ASC LIMIT 6',
    [':c'=>$cid]
);

$expiringList = db_all(
    'SELECT l.id, l.lease_number, l.end_date, DATEDIFF(l.end_date, CURDATE()) AS days_left,
            CONCAT(t.first_name," ",t.last_name) AS tenant_name,
            u.unit_number
       FROM leases l
       JOIN tenants t ON t.id = l.tenant_id
       JOIN units   u ON u.id = l.unit_id
      WHERE l.company_id = :c AND l.status IN ("active","expiring") AND l.end_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
      ORDER BY l.end_date ASC LIMIT 6',
    [':c'=>$cid]
);

$maintenanceToday = db_all(
    'SELECT mr.id, mr.ticket_number, mr.priority, mr.status, mr.description,
            u.unit_number, p.name AS property_name
       FROM maintenance_requests mr
       JOIN units u ON u.id = mr.unit_id
       JOIN properties p ON p.id = mr.property_id
      WHERE mr.company_id = :c AND mr.status NOT IN ("closed","verified")
      ORDER BY FIELD(mr.priority,"emergency","high","medium","low"), mr.created_at ASC
      LIMIT 6',
    [':c'=>$cid]
);

$recentPayments = db_all(
    'SELECT p.id, p.amount, p.payment_date, p.payment_method,
            CONCAT(t.first_name," ",t.last_name) AS tenant_name, u.unit_number
       FROM payments p
       JOIN tenants t ON t.id = p.tenant_id
       LEFT JOIN units u ON u.id = p.unit_id
      WHERE p.company_id = :c AND p.payment_date = CURDATE() AND p.status = "completed"
      ORDER BY p.id DESC LIMIT 6',
    [':c'=>$cid]
);

// --- 12-month revenue vs collected trend (from analytics snapshots if available, else on-the-fly) ---
$trend = db_all(
    'SELECT DATE_FORMAT(payment_date, "%Y-%m") AS period,
            SUM(amount) AS collected
       FROM payments
      WHERE company_id = :c AND status = "completed" AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
      GROUP BY period ORDER BY period ASC',
    [':c'=>$cid]
);
$trendLabels = array_column($trend, 'period');
$trendValues = array_map('floatval', array_column($trend, 'collected'));

// --- Occupancy doughnut ---
$occSplit = ['occupied' => (int)$kpi['units_occupied'], 'vacant' => (int)$kpi['units_vacant']];

$PAGE_TITLE   = __('Today');
$PAGE_SCRIPTS = ['today.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Today')) ?></h1>
    <p class="text-muted small mb-0"><?= e(date('l, F j, Y')) ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('payments.create')): ?>
      <a class="btn btn-primary" href="<?= APP_URL ?>/modules/payments/create.php"><i data-lucide="banknote"></i> <?= e(__('Record Payment')) ?></a>
    <?php endif; ?>
    <?php if (can('tenants.create')): ?>
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/tenants/create.php"><i data-lucide="user-plus"></i> <?= e(__('Add Tenant')) ?></a>
    <?php endif; ?>
    <?php if (can('properties.create')): ?>
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/properties/create.php"><i data-lucide="building"></i> <?= e(__('Add Property')) ?></a>
    <?php endif; ?>
    <?php if (can('maintenance.create')): ?>
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/maintenance/create.php"><i data-lucide="hammer"></i> <?= e(__('New Request')) ?></a>
    <?php endif; ?>
    <?php if (can('inspections.create')): ?>
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/inspections/create.php"><i data-lucide="clipboard-check"></i> <?= e(__('Schedule Inspection')) ?></a>
    <?php endif; ?>
  </div>
</div>

<!-- KPI strip -->
<div class="kpi-grid mb-4">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="building-2"></i></div>
    <div class="kpi-label"><?= e(__('Properties')) ?></div>
    <div class="kpi-value"><?= (int)$kpi['properties'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="pie-chart"></i></div>
    <div class="kpi-label"><?= e(__('Occupancy')) ?></div>
    <div class="kpi-value"><?= e((string)$unitsOccupancy) ?>%</div>
    <div class="text-muted small"><?= (int)$kpi['units_occupied'] ?> / <?= (int)$kpi['units_total'] ?> <?= e(__('occupied')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__("Today's Collections")) ?></div>
    <div class="kpi-value"><?= e(money($collections['today_total'])) ?></div>
    <div class="text-muted small"><?= (int)$collections['today_count'] ?> <?= e(__('payments')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-triangle"></i></div>
    <div class="kpi-label"><?= e(__('Overdue')) ?></div>
    <div class="kpi-value text-danger"><?= e(money($kpi['overdue_amount'])) ?></div>
    <div class="text-muted small"><?= e(__('Total outstanding')) ?> <?= e(money($kpi['outstanding'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="hammer"></i></div>
    <div class="kpi-label"><?= e(__('Open Maintenance')) ?></div>
    <div class="kpi-value"><?= (int)$kpi['open_maint'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="check-square"></i></div>
    <div class="kpi-label"><?= e(__('Pending Approvals')) ?></div>
    <div class="kpi-value"><?= (int)$kpi['pending_approvals'] ?></div></div>
</div>

<!-- Charts -->
<div class="row g-3 mb-4">
<div class="col-lg-8">
    <div class="srp-card p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 mb-0"><?= e(__('Collections — Last 12 Months')) ?></h2>
        <a class="small text-decoration-none" href="<?= APP_URL ?>/modules/analytics/index.php"><?= e(__('Full analytics')) ?> →</a>
      </div>
      <div style="position: relative; height: 280px;">
        <canvas id="chartRevenueTrend"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="srp-card p-4 h-100">
      <h2 class="h6 mb-3"><?= e(__('Occupancy')) ?></h2>
      <div style="position: relative; height: 280px;">
        <canvas id="chartOccupancy"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="srp-card p-4 h-100">
      <h2 class="h6 mb-3"><?= e(__('Occupancy')) ?></h2>
      <canvas id="chartOccupancy" height="180"></canvas>
    </div>
  </div>
</div>

<!-- Attention panels -->
<div class="row g-3">
  <div class="col-lg-6">
    <div class="srp-card p-4 h-100">
      <h2 class="h6 mb-3 text-danger"><i data-lucide="alert-octagon" style="width:16px;height:16px"></i> <?= e(__('Overdue Invoices')) ?></h2>
      <?php if (!$overdueList): ?>
        <div class="text-muted small"><?= e(__('No overdue invoices — nice.')) ?></div>
      <?php else: ?>
        <ul class="list-unstyled mb-0">
          <?php foreach ($overdueList as $o): ?>
            <li class="border-bottom py-2 d-flex justify-content-between">
              <div>
                <a class="text-decoration-none fw-semibold" href="<?= APP_URL ?>/modules/tenants/view.php?id=<?= (int)$o['tenant_id'] ?>"><?= e($o['tenant_name']) ?></a>
                <div class="text-muted small"><?= e($o['unit_number']) ?> · <?= e($o['invoice_number']) ?> · <span class="text-danger"><?= (int)$o['days_late'] ?>d <?= e(__('late')) ?></span></div>
              </div>
              <div class="text-end">
                <div class="text-danger fw-semibold"><?= e(money($o['balance'])) ?></div>
                <?php if (can('payments.create')): ?>
                  <a class="small" href="<?= APP_URL ?>/modules/payments/create.php?invoice_id=<?= (int)$o['id'] ?>"><?= e(__('Record')) ?></a>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="srp-card p-4 h-100">
      <h2 class="h6 mb-3 text-warning"><i data-lucide="calendar-clock" style="width:16px;height:16px"></i> <?= e(__('Leases Expiring Within 90 Days')) ?></h2>
      <?php if (!$expiringList): ?>
        <div class="text-muted small"><?= e(__('No leases expiring soon.')) ?></div>
      <?php else: ?>
        <ul class="list-unstyled mb-0">
          <?php foreach ($expiringList as $ex): ?>
            <li class="border-bottom py-2 d-flex justify-content-between">
              <div>
                <a class="text-decoration-none fw-semibold" href="<?= APP_URL ?>/modules/leases/view.php?id=<?= (int)$ex['id'] ?>"><?= e($ex['lease_number']) ?></a>
                <div class="text-muted small"><?= e($ex['tenant_name']) ?> · <?= e($ex['unit_number']) ?></div>
              </div>
              <div class="text-end">
                <div class="<?= (int)$ex['days_left'] <= 30 ? 'text-danger' : 'text-warning' ?> fw-semibold"><?= (int)$ex['days_left'] ?>d</div>
                <?php if (can('leases.create')): ?>
                  <a class="small" href="<?= APP_URL ?>/modules/leases/renew.php?id=<?= (int)$ex['id'] ?>"><?= e(__('Renew')) ?></a>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="srp-card p-4 h-100">
      <h2 class="h6 mb-3"><i data-lucide="hammer" style="width:16px;height:16px"></i> <?= e(__('Open Maintenance')) ?></h2>
      <?php if (!$maintenanceToday): ?>
        <div class="text-muted small"><?= e(__('No open maintenance requests.')) ?></div>
      <?php else: ?>
        <ul class="list-unstyled mb-0">
          <?php foreach ($maintenanceToday as $m): ?>
            <li class="border-bottom py-2">
              <div class="d-flex justify-content-between">
                <div>
                  <a class="text-decoration-none fw-semibold" href="<?= APP_URL ?>/modules/maintenance/view.php?id=<?= (int)$m['id'] ?>"><?= e($m['ticket_number']) ?></a>
                  <span class="srp-badge-pill badge-status-<?= $m['priority']==='emergency'?'under_maintenance':($m['priority']==='high'?'reserved':'notice_given') ?> ms-1">
                    <span class="dot"></span><?= e(ucfirst($m['priority'])) ?>
                  </span>
                </div>
                <div class="text-muted small"><?= e($m['unit_number']) ?></div>
              </div>
              <div class="text-muted small text-truncate" style="max-width:420px"><?= e($m['description']) ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="srp-card p-4 h-100">
      <h2 class="h6 mb-3 text-success"><i data-lucide="check-circle-2" style="width:16px;height:16px"></i> <?= e(__("Today's Payments")) ?></h2>
      <?php if (!$recentPayments): ?>
        <div class="text-muted small"><?= e(__('No payments recorded today yet.')) ?></div>
      <?php else: ?>
        <ul class="list-unstyled mb-0">
          <?php foreach ($recentPayments as $p): ?>
            <li class="border-bottom py-2 d-flex justify-content-between">
              <div>
                <a class="text-decoration-none fw-semibold" href="<?= APP_URL ?>/modules/payments/view.php?id=<?= (int)$p['id'] ?>"><?= e($p['tenant_name']) ?></a>
                <div class="text-muted small"><?= e($p['unit_number'] ?? '') ?> · <?= e(ucfirst(str_replace('_',' ',$p['payment_method']))) ?></div>
              </div>
              <div class="text-success fw-semibold"><?= e(money($p['amount'])) ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Chart data for today.js -->
<script id="srpDashboardData" type="application/json">
<?= json_encode([
    'trendLabels' => $trendLabels,
    'trendValues' => $trendValues,
    'occupancy'   => $occSplit,
], JSON_UNESCAPED_UNICODE) ?>
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>