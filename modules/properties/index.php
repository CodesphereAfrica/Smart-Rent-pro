<?php
// modules/properties/index.php — DataTable list of properties (server-side via ajax_list.php).
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.view');

$PAGE_TITLE = __('Properties');
$PAGE_SCRIPTS = ['properties.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Properties')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Manage your portfolio of buildings, units and owners.')) ?></p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/modules/properties/create.php" class="btn btn-primary">
      <i data-lucide="plus"></i> <?= e(__('Add Property')) ?>
    </a>
  </div>
</div>

<div class="row g-3 mb-3" id="srpPropKpis">
  <?php
  $kpi = db_one(
      'SELECT
         COUNT(*) AS total,
         SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) AS active,
         COALESCE(SUM(num_units), 0) AS units
       FROM properties WHERE company_id = :c AND deleted_at IS NULL',
      [':c' => (int)$USER['company_id']]
  );
  $units = db_one(
      'SELECT COUNT(*) AS total,
              SUM(CASE WHEN status = "occupied" THEN 1 ELSE 0 END) AS occupied
         FROM units WHERE company_id = :c',
      [':c' => (int)$USER['company_id']]
  );
  $occ = ((int)($units['total'] ?? 0)) > 0 ? round(((int)$units['occupied'] / (int)$units['total']) * 100) : 0;
  ?>
  <div class="col-md-3">
    <div class="kpi"><div class="kpi-icon"><i data-lucide="building"></i></div>
      <div class="kpi-label"><?= e(__('Total Properties')) ?></div>
      <div class="kpi-value"><?= (int)$kpi['total'] ?></div></div>
  </div>
  <div class="col-md-3">
    <div class="kpi"><div class="kpi-icon"><i data-lucide="check-circle-2"></i></div>
      <div class="kpi-label"><?= e(__('Active')) ?></div>
      <div class="kpi-value"><?= (int)$kpi['active'] ?></div></div>
  </div>
  <div class="col-md-3">
    <div class="kpi"><div class="kpi-icon"><i data-lucide="door-open"></i></div>
      <div class="kpi-label"><?= e(__('Total Units')) ?></div>
      <div class="kpi-value"><?= (int)$units['total'] ?></div></div>
  </div>
  <div class="col-md-3">
    <div class="kpi"><div class="kpi-icon"><i data-lucide="pie-chart"></i></div>
      <div class="kpi-label"><?= e(__('Occupancy')) ?></div>
      <div class="kpi-value"><?= (int)$occ ?>%</div></div>
  </div>
</div>

<div class="srp-card p-3">
  <table id="srpPropertiesTable" class="table table-hover align-middle w-100">
    <thead>
      <tr>
        <th><?= e(__('Name')) ?></th>
        <th><?= e(__('Type')) ?></th>
        <th><?= e(__('Location')) ?></th>
        <th><?= e(__('Owner')) ?></th>
        <th><?= e(__('Manager')) ?></th>
        <th><?= e(__('Units')) ?></th>
        <th><?= e(__('Status')) ?></th>
        <th style="width:120px" data-orderable="false"><?= e(__('Actions')) ?></th>
      </tr>
    </thead>
  </table>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>