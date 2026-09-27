<?php
// modules/leases/index.php — DataTables list of leases.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('leases.view');

$stats = db_one(
    'SELECT
       COUNT(*)                                     AS total,
       SUM(status = "active")                       AS active,
       SUM(status = "expiring")                     AS expiring,
       SUM(status = "expired")                      AS expired,
       SUM(status = "draft")                        AS draft,
       SUM(status = "pending_signature")            AS pending,
       SUM(status = "terminated") AS `terminated`,
SUM(status = "renewed")    AS `renewed`,
       COALESCE(SUM(CASE WHEN status IN ("active","expiring") THEN monthly_rent ELSE 0 END),0) AS mrr
     FROM leases WHERE company_id = :c',
    [':c' => (int)$USER['company_id']]
);

// 90/60/30-day lease expirations
$expiringSoon = db_one(
    'SELECT
       SUM(DATEDIFF(end_date, CURDATE()) BETWEEN 0 AND 30)  AS d30,
       SUM(DATEDIFF(end_date, CURDATE()) BETWEEN 31 AND 60) AS d60,
       SUM(DATEDIFF(end_date, CURDATE()) BETWEEN 61 AND 90) AS d90
     FROM leases WHERE company_id = :c AND status IN ("active","expiring")',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE   = __('Leases');
$PAGE_SCRIPTS = ['leases.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Leases')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Every tenancy agreement, linked to its tenant, unit and rent schedule.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/leases/create.php"><i data-lucide="plus"></i> <?= e(__('New Lease')) ?></a>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="file-signature"></i></div>
    <div class="kpi-label"><?= e(__('Active Leases')) ?></div>
    <div class="kpi-value"><?= (int)$stats['active'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Monthly Rent Roll')) ?></div>
    <div class="kpi-value"><?= e(money($stats['mrr'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alarm-clock"></i></div>
    <div class="kpi-label"><?= e(__('Expiring ≤ 30 days')) ?></div>
    <div class="kpi-value"><?= (int)$expiringSoon['d30'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-triangle"></i></div>
    <div class="kpi-label"><?= e(__('Expired')) ?></div>
    <div class="kpi-value"><?= (int)$stats['expired'] ?></div></div>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <?php foreach (['draft','pending_signature','active','expiring','expired','terminated','renewed'] as $s): ?>
          <option value="<?= e($s) ?>"><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltProperty" class="form-select form-select-sm">
        <option value=""><?= e(__('All Properties')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltExpiring" class="form-select form-select-sm">
        <option value=""><?= e(__('Any expiry')) ?></option>
        <option value="30"><?= e(__('Expiring ≤ 30 days')) ?></option>
        <option value="60"><?= e(__('Expiring ≤ 60 days')) ?></option>
        <option value="90"><?= e(__('Expiring ≤ 90 days')) ?></option>
        <option value="expired"><?= e(__('Already expired')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpLeasesTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Lease #')) ?></th>
        <th><?= e(__('Tenant')) ?></th>
        <th><?= e(__('Unit')) ?></th>
        <th><?= e(__('Period')) ?></th>
        <th><?= e(__('Rent')) ?></th>
        <th><?= e(__('Days Left')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>