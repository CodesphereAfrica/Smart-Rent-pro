<?php
// modules/maintenance/index.php — DataTables list of maintenance requests.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('maintenance.view');

$stats = db_one(
    'SELECT
       COUNT(*) AS total,
       SUM(status = "new")                AS s_new,
       SUM(status = "assigned")           AS s_assigned,
       SUM(status = "in_progress")        AS s_in_progress,
       SUM(status = "waiting_for_parts")  AS s_waiting,
       SUM(status = "completed")          AS s_completed,
       SUM(status = "verified")           AS s_verified,
       SUM(status = "closed")             AS s_closed,
       SUM(priority = "emergency" AND status NOT IN ("closed","verified")) AS emergency_open,
       SUM(priority = "high"      AND status NOT IN ("closed","verified")) AS high_open,
       COALESCE(SUM(actual_cost),0) AS total_spend
     FROM maintenance_requests WHERE company_id = :c',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE   = __('Maintenance');
$PAGE_SCRIPTS = ['maintenance.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Maintenance')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Every request from report to closure, linked to unit, tenant, work order and expense.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/maintenance/create.php"><i data-lucide="plus"></i> <?= e(__('New Request')) ?></a>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="hammer"></i></div>
    <div class="kpi-label"><?= e(__('Open')) ?></div>
    <div class="kpi-value"><?= (int)$stats['s_new'] + (int)$stats['s_assigned'] + (int)$stats['s_in_progress'] + (int)$stats['s_waiting'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-octagon"></i></div>
    <div class="kpi-label"><?= e(__('Emergency Open')) ?></div>
    <div class="kpi-value text-danger"><?= (int)$stats['emergency_open'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-triangle"></i></div>
    <div class="kpi-label"><?= e(__('High Priority Open')) ?></div>
    <div class="kpi-value"><?= (int)$stats['high_open'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Total Spend')) ?></div>
    <div class="kpi-value"><?= e(money($stats['total_spend'])) ?></div></div>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-2">
      <select id="fltStatus" class="form-select form-select-sm">
        <option value=""><?= e(__('All Statuses')) ?></option>
        <?php foreach (['new','assigned','in_progress','waiting_for_parts','completed','verified','closed'] as $s): ?>
          <option value="<?= e($s) ?>"><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select id="fltPriority" class="form-select form-select-sm">
        <option value=""><?= e(__('All Priorities')) ?></option>
        <option value="emergency"><?= e(__('Emergency')) ?></option>
        <option value="high"><?= e(__('High')) ?></option>
        <option value="medium"><?= e(__('Medium')) ?></option>
        <option value="low"><?= e(__('Low')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltProperty" class="form-select form-select-sm">
        <option value=""><?= e(__('All Properties')) ?></option>
      </select>
    </div>
    <div class="col-md-3">
      <select id="fltOpenOnly" class="form-select form-select-sm">
        <option value=""><?= e(__('All (incl. closed)')) ?></option>
        <option value="1"><?= e(__('Open only')) ?></option>
      </select>
    </div>
  </div>

  <table id="srpMaintenanceTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Ticket')) ?></th>
        <th><?= e(__('Unit / Property')) ?></th>
        <th><?= e(__('Category')) ?></th>
        <th><?= e(__('Priority')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th><?= e(__('Assigned')) ?></th>
        <th><?= e(__('Reported')) ?></th>
        <th style="width:80px" data-orderable="false" class="text-end"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>