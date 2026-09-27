<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('units.view');

// KPI header numbers
$stats = db_one(
    'SELECT COUNT(*) AS total,
            SUM(status="occupied") AS occupied,
            SUM(status="available") AS available,
            SUM(status="reserved") AS reserved,
            SUM(status IN ("under_maintenance","under_renovation")) AS maintenance,
            SUM(status="notice_given") AS notice,
            COALESCE(SUM(base_rent),0) AS potential
       FROM units WHERE company_id = :c',
    [':c' => (int)$USER['company_id']]
);
$occ = ((int)$stats['total']) ? round(((int)$stats['occupied'] / (int)$stats['total']) * 100) : 0;

$PAGE_TITLE = __('Units');
$PAGE_SCRIPTS = ['units.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div><h1 class="h4 mb-0"><?= e(__('Units')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Every unit and its current state — the pivot of the whole system.')) ?></p></div>
  <a href="<?= APP_URL ?>/modules/units/create.php" class="btn btn-primary"><i data-lucide="plus"></i> <?= e(__('Add Unit')) ?></a>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="door-open"></i></div><div class="kpi-label"><?= e(__('Total')) ?></div><div class="kpi-value"><?= (int)$stats['total'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="check-circle-2"></i></div><div class="kpi-label"><?= e(__('Occupied')) ?></div><div class="kpi-value"><?= (int)$stats['occupied'] ?></div><div class="text-muted small"><?= $occ ?>% <?= e(__('occupancy')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="key-round"></i></div><div class="kpi-label"><?= e(__('Vacant')) ?></div><div class="kpi-value"><?= (int)$stats['available'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-triangle"></i></div><div class="kpi-label"><?= e(__('In Maintenance')) ?></div><div class="kpi-value"><?= (int)$stats['maintenance'] ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div><div class="kpi-label"><?= e(__('Potential Rent')) ?></div><div class="kpi-value"><?= e(money($stats['potential'])) ?></div></div>
</div>

<div class="srp-card p-3">
  <div class="row g-2 mb-3">
    <div class="col-md-3"><select id="fltProperty" class="form-select form-select-sm"><option value=""><?= e(__('All Properties')) ?></option></select></div>
    <div class="col-md-3"><select id="fltStatus" class="form-select form-select-sm">
      <option value=""><?= e(__('All Statuses')) ?></option>
      <?php foreach (['available','occupied','reserved','under_maintenance','under_renovation','unavailable','notice_given'] as $s): ?>
        <option value="<?= e($s) ?>"><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
      <?php endforeach; ?>
    </select></div>
  </div>
  <table id="srpUnitsTable" class="table table-hover align-middle w-100">
    <thead><tr>
      <th><?= e(__('Unit')) ?></th><th><?= e(__('Property')) ?></th><th><?= e(__('Type')) ?></th>
      <th><?= e(__('Bed / Bath')) ?></th><th><?= e(__('Rent')) ?></th><th><?= e(__('Tenant')) ?></th>
      <th><?= e(__('Status')) ?></th><th style="width:110px" data-orderable="false"><?= e(__('Actions')) ?></th>
    </tr></thead>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>