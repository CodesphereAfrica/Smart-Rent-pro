<?php
// modules/properties/view.php — detail page with tabs (Overview / Units / Financials / Documents / Maintenance / Activity).
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
    [':id' => $id, ':c' => (int)$USER['company_id']]
);
if (!$p) { flash_error(__('Property not found.')); redirect('modules/properties/index.php'); }

// KPIs
$unitStats = db_one(
    'SELECT COUNT(*) AS total,
            SUM(status="occupied") AS occupied,
            SUM(status="available") AS available,
            COALESCE(SUM(base_rent),0) AS potential
       FROM units WHERE property_id = :p AND company_id = :c',
    [':p' => $id, ':c' => (int)$USER['company_id']]
);
$financial = db_one(
    'SELECT
       COALESCE((SELECT SUM(amount) FROM invoices WHERE property_id IS NULL AND unit_id IN (SELECT id FROM units WHERE property_id = :p) AND status IN ("paid","partially_paid")),0) AS collected,
       COALESCE((SELECT SUM(amount) FROM expenses WHERE property_id = :p),0) AS expenses',
    [':p' => $id]
);

$photos = json_decode((string)($p['photos'] ?? '[]'), true) ?: [];
$units  = db_all('SELECT id, unit_number, unit_type, base_rent, status FROM units WHERE property_id = :p ORDER BY unit_number LIMIT 12', [':p' => $id]);
$recentMaint = db_all('SELECT id, ticket_number, priority, status, description, created_at FROM maintenance_requests WHERE property_id = :p ORDER BY id DESC LIMIT 5', [':p' => $id]);
$documents = db_all('SELECT id, document_name, file_path, uploaded_at FROM documents WHERE related_type = "property" AND related_id = :p ORDER BY id DESC LIMIT 6', [':p' => $id]);

$PAGE_TITLE = $p['name'];
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/properties/index.php"><?= e(__('Properties')) ?></a></li>
    <li class="breadcrumb-item active"><?= e($p['name']) ?></li>
  </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e($p['name']) ?></h1>
    <div class="text-muted small">
      <i data-lucide="map-pin" class="me-1" style="width:14px;height:14px"></i>
      <?= e(trim(($p['address'] ?? '') . ', ' . ($p['city'] ?? '') . ', ' . ($p['country'] ?? ''), ', ')) ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <?php if (can('properties.edit')): ?>
      <a href="<?= APP_URL ?>/modules/properties/edit.php?id=<?= (int)$id ?>" class="btn btn-light">
        <i data-lucide="pencil"></i> <?= e(__('Edit')) ?>
      </a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/modules/units/create.php?property_id=<?= (int)$id ?>" class="btn btn-primary">
      <i data-lucide="plus"></i> <?= e(__('Add Unit')) ?>
    </a>
  </div>
</div>

<!-- KPI cards -->
<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="door-open"></i></div>
    <div class="kpi-label"><?= e(__('Units')) ?></div>
    <div class="kpi-value"><?= (int)$unitStats['total'] ?></div>
    <div class="text-muted small"><?= (int)$unitStats['occupied'] ?> <?= e(__('occupied')) ?> · <?= (int)$unitStats['available'] ?> <?= e(__('available')) ?></div>
  </div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Potential Rent')) ?></div>
    <div class="kpi-value"><?= e(money($unitStats['potential'])) ?></div>
  </div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="trending-up"></i></div>
    <div class="kpi-label"><?= e(__('Collected')) ?></div>
    <div class="kpi-value"><?= e(money($financial['collected'])) ?></div>
  </div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="trending-down"></i></div>
    <div class="kpi-label"><?= e(__('Expenses')) ?></div>
    <div class="kpi-value"><?= e(money($financial['expenses'])) ?></div>
  </div>
</div>

<ul class="nav nav-tabs mb-3" id="srpPropTabs" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button"><?= e(__('Overview')) ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-units" type="button"><?= e(__('Units')) ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-maintenance" type="button"><?= e(__('Maintenance')) ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-documents" type="button"><?= e(__('Documents')) ?></button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tab-overview">
    <div class="row g-3">
      <div class="col-lg-8">
        <div class="srp-card p-4">
          <h2 class="h6 mb-2"><?= e(__('Property Details')) ?></h2>
          <div class="row small">
            <div class="col-sm-6 mb-2"><span class="text-muted"><?= e(__('Type')) ?>:</span> <?= e(ucfirst(str_replace('_',' ',$p['property_type']))) ?></div>
            <div class="col-sm-6 mb-2"><span class="text-muted"><?= e(__('Status')) ?>:</span> <?= e(ucfirst(str_replace('_',' ',$p['status']))) ?></div>
            <div class="col-sm-6 mb-2"><span class="text-muted"><?= e(__('Owner')) ?>:</span> <?= e($p['owner_name'] ?? '—') ?></div>
            <div class="col-sm-6 mb-2"><span class="text-muted"><?= e(__('Manager')) ?>:</span> <?= e($p['manager_name'] ?? '—') ?></div>
            <?php if ($p['description']): ?>
              <div class="col-12 mt-2"><?= nl2br(e($p['description'])) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="srp-card p-4 h-100">
          <h2 class="h6 mb-2"><?= e(__('Photos')) ?></h2>
          <?php if ($photos): ?>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach (array_slice($photos, 0, 6) as $ph): ?>
                <img src="<?= e(UPLOAD_URL . '/' . $ph) ?>" style="width:80px;height:60px;object-fit:cover;border-radius:10px">
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-muted small"><?= e(__('No photos uploaded yet.')) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-units">
    <div class="srp-card p-3">
      <table class="table table-hover align-middle">
        <thead><tr>
          <th><?= e(__('Unit')) ?></th><th><?= e(__('Type')) ?></th>
          <th><?= e(__('Rent')) ?></th><th><?= e(__('Status')) ?></th><th></th>
        </tr></thead>
        <tbody>
        <?php if (!$units): ?>
          <tr><td colspan="5" class="text-center text-muted py-4"><?= e(__('No units yet.')) ?></td></tr>
        <?php else: foreach ($units as $u): ?>
          <tr>
            <td><a href="<?= APP_URL ?>/modules/units/view.php?id=<?= (int)$u['id'] ?>" class="fw-semibold text-decoration-none"><?= e($u['unit_number']) ?></a></td>
            <td><?= e($u['unit_type'] ?? '—') ?></td>
            <td><?= e(money($u['base_rent'])) ?></td>
            <td><span class="srp-badge-pill badge-status-<?= e($u['status']) ?>"><span class="dot"></span><?= e(ucfirst(str_replace('_',' ',$u['status']))) ?></span></td>
            <td class="text-end"><a href="<?= APP_URL ?>/modules/units/view.php?id=<?= (int)$u['id'] ?>" class="btn btn-sm btn-light"><i data-lucide="arrow-right"></i></a></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-maintenance">
    <div class="srp-card p-3">
      <table class="table table-hover align-middle">
        <thead><tr><th><?= e(__('Ticket')) ?></th><th><?= e(__('Priority')) ?></th><th><?= e(__('Status')) ?></th><th><?= e(__('Description')) ?></th><th><?= e(__('Reported')) ?></th></tr></thead>
        <tbody>
        <?php if (!$recentMaint): ?>
          <tr><td colspan="5" class="text-center text-muted py-4"><?= e(__('No maintenance requests.')) ?></td></tr>
        <?php else: foreach ($recentMaint as $m): ?>
          <tr>
            <td><a href="<?= APP_URL ?>/modules/maintenance/view.php?id=<?= (int)$m['id'] ?>"><?= e($m['ticket_number']) ?></a></td>
            <td><?= e(ucfirst($m['priority'])) ?></td>
            <td><?= e(ucfirst(str_replace('_',' ',$m['status']))) ?></td>
            <td class="text-truncate" style="max-width:320px"><?= e($m['description']) ?></td>
            <td><?= e(date('Y-m-d', strtotime($m['created_at']))) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-documents">
    <div class="srp-card p-3">
      <?php if (!$documents): ?>
        <div class="text-muted small p-3"><?= e(__('No documents attached to this property.')) ?></div>
      <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($documents as $d): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <a href="<?= e(UPLOAD_URL . '/' . $d['file_path']) ?>" target="_blank" class="text-decoration-none"><i data-lucide="file"></i> <?= e($d['document_name']) ?></a>
            <span class="text-muted small"><?= e(date('Y-m-d', strtotime($d['uploaded_at']))) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>