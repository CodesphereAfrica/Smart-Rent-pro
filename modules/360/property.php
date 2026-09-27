<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.view');

$id = (int)($_GET['id'] ?? 0);
$p = db_one(
    'SELECT p.*, o.full_name AS owner_name, CONCAT(m.first_name," ",m.last_name) AS manager_name
       FROM properties p
       LEFT JOIN owners o ON o.id = p.owner_id
       LEFT JOIN users  m ON m.id = p.assigned_manager_id
      WHERE p.id = :id AND p.company_id = :c AND p.deleted_at IS NULL LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$p) { flash_error(__('Property not found.')); redirect('modules/properties/index.php'); }

$units = db_one(
    'SELECT COUNT(*) AS total, SUM(status="occupied") AS occupied, SUM(status="available") AS available,
            COALESCE(SUM(base_rent),0) AS potential
       FROM units WHERE property_id = :p',
    [':p' => $id]
);

$PAGE_TITLE   = $p['name'] . ' · ' . __('Property 360');
$PAGE_SCRIPTS = ['360.js'];
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/properties/index.php"><?= e(__('Properties')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($p['name']) ?></li>
</ol></nav>

<h1 class="h4 mb-1"><?= e($p['name']) ?></h1>
<div class="text-muted small mb-3"><?= e(trim(($p['address'] ?? '') . ', ' . ($p['city'] ?? ''), ', ')) ?></div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="door-open"></i></div>
    <div class="kpi-label"><?= e(__('Units')) ?></div>
    <div class="kpi-value"><?= (int)$units['total'] ?></div>
    <div class="text-muted small"><?= (int)$units['occupied'] ?> <?= e(__('occupied')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Potential Rent')) ?></div>
    <div class="kpi-value"><?= e(money($units['potential'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="user-square"></i></div>
    <div class="kpi-label"><?= e(__('Owner')) ?></div>
    <div class="kpi-value" style="font-size:1rem"><?= e($p['owner_name'] ?? '—') ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="user-cog"></i></div>
    <div class="kpi-label"><?= e(__('Manager')) ?></div>
    <div class="kpi-value" style="font-size:1rem"><?= e($p['manager_name'] ?? '—') ?></div></div>
</div>

<ul class="nav nav-tabs mb-3" id="srp360Tabs" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-srp-tab="units"       data-bs-target="#tab-units"><?= e(__('Units')) ?></button></li>
  <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-srp-tab="maintenance" data-bs-target="#tab-maint"><?= e(__('Maintenance')) ?></button></li>
  <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-srp-tab="expenses"    data-bs-target="#tab-expenses"><?= e(__('Expenses')) ?></button></li>
  <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-srp-tab="documents"   data-bs-target="#tab-docs"><?= e(__('Documents')) ?></button></li>
</ul>

<div class="tab-content" id="srp360TabsContent" data-360-type="property" data-360-id="<?= (int)$id ?>">
  <div class="tab-pane fade show active" id="tab-units">      <div class="srp-card p-3" data-360-panel="units">      <div class="srp-skeleton-row" style="height:48px"></div></div></div>
  <div class="tab-pane fade"            id="tab-maint">        <div class="srp-card p-3" data-360-panel="maintenance"><div class="srp-skeleton-row" style="height:48px"></div></div></div>
  <div class="tab-pane fade"            id="tab-expenses">     <div class="srp-card p-3" data-360-panel="expenses">   <div class="srp-skeleton-row" style="height:48px"></div></div></div>
  <div class="tab-pane fade"            id="tab-docs">         <div class="srp-card p-3" data-360-panel="documents">  <div class="srp-skeleton-row" style="height:48px"></div></div></div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>