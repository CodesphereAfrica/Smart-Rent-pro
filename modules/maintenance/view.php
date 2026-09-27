<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('maintenance.view');
$id = (int)($_GET['id'] ?? 0);
$mr = db_one(
    'SELECT mr.*, u.unit_number, u.id AS unit_id, p.name AS property_name, p.id AS property_id,
            c.name AS category_name,
            CONCAT(t.first_name," ",t.last_name) AS tenant_name,
            us.first_name AS assigned_first, us.last_name AS assigned_last,
            v.name AS vendor_name, v.phone AS vendor_phone,
            wo.id AS work_order_id, wo.work_order_number, wo.status AS wo_status,
            wo.actual_cost AS wo_cost
       FROM maintenance_requests mr
       JOIN units u ON u.id = mr.unit_id
       JOIN properties p ON p.id = mr.property_id
       LEFT JOIN maintenance_categories c ON c.id = mr.category_id
       LEFT JOIN tenants t ON t.id = mr.tenant_id
       LEFT JOIN users us ON us.id = mr.assigned_to_user_id
       LEFT JOIN vendors v ON v.id = mr.assigned_vendor_id
       LEFT JOIN work_orders wo ON wo.maintenance_request_id = mr.id
      WHERE mr.id = :id AND mr.company_id = :c LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$mr) { flash_error(__('Maintenance request not found.')); redirect('modules/maintenance/index.php'); }

$history = db_all(
    'SELECT mh.id, mh.from_status, mh.to_status, mh.notes, mh.changed_at,
            CONCAT(us.first_name," ",us.last_name) AS changer
       FROM maintenance_status_history mh
       LEFT JOIN users us ON us.id = mh.changed_by
      WHERE mh.maintenance_request_id = :id ORDER BY mh.id ASC',
    [':id' => $id]
);
$photos = json_decode((string)$mr['photos'], true) ?: [];

$PAGE_TITLE = $mr['ticket_number'];
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/maintenance/index.php"><?= e(__('Maintenance')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($mr['ticket_number']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e($mr['ticket_number']) ?>
      <span class="srp-badge-pill badge-status-<?= $mr['status']==='closed'?'unavailable':($mr['status']==='completed'||$mr['status']==='verified'?'available':'occupied') ?> ms-2">
        <span class="dot"></span><?= e(ucfirst(str_replace('_',' ',$mr['status']))) ?>
      </span>
    </h1>
    <div class="text-muted small">
      <a href="<?= APP_URL ?>/modules/units/view.php?id=<?= (int)$mr['unit_id'] ?>"><?= e($mr['unit_number']) ?></a> ·
      <a href="<?= APP_URL ?>/modules/properties/view.php?id=<?= (int)$mr['property_id'] ?>"><?= e($mr['property_name']) ?></a>
    </div>
  </div>
  <div class="d-flex gap-2">
    <?php if (can('maintenance.assign')): ?>
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/maintenance/assign.php?id=<?= (int)$id ?>"><i data-lucide="user-check"></i> <?= e(__('Assign')) ?></a>
    <?php endif; ?>
    <?php if (can('maintenance.edit')): ?>
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/maintenance/edit.php?id=<?= (int)$id ?>"><i data-lucide="pencil"></i> <?= e(__('Update Status')) ?></a>
    <?php endif; ?>
    <?php if (!$mr['work_order_id'] && can('maintenance.create')): ?>
      <a class="btn btn-primary" href="<?= APP_URL ?>/modules/work-orders/create.php?maintenance_id=<?= (int)$id ?>"><i data-lucide="clipboard-list"></i> <?= e(__('Create Work Order')) ?></a>
    <?php elseif ($mr['work_order_id']): ?>
      <a class="btn btn-outline-primary" href="<?= APP_URL ?>/modules/work-orders/view.php?id=<?= (int)$mr['work_order_id'] ?>"><i data-lucide="arrow-up-right"></i> <?= e(__('View WO')) ?> <?= e($mr['work_order_number']) ?></a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="srp-card p-4 mb-3">
      <h2 class="h6 mb-3"><?= e(__('Description')) ?></h2>
      <p class="mb-3"><?= nl2br(e($mr['description'])) ?></p>

      <div class="row small g-2">
        <div class="col-4"><span class="text-muted"><?= e(__('Priority')) ?>:</span> <?= e(ucfirst($mr['priority'])) ?></div>
        <div class="col-4"><span class="text-muted"><?= e(__('Category')) ?>:</span> <?= e($mr['category_name'] ?? '—') ?></div>
        <div class="col-4"><span class="text-muted"><?= e(__('Reported')) ?>:</span> <?= e(date('Y-m-d H:i', strtotime($mr['reported_at']))) ?></div>
        <?php if ($mr['tenant_name']): ?>
          <div class="col-4"><span class="text-muted"><?= e(__('Tenant')) ?>:</span> <?= e($mr['tenant_name']) ?></div>
        <?php endif; ?>
        <div class="col-4"><span class="text-muted"><?= e(__('Estimated Cost')) ?>:</span> <?= e(money($mr['estimated_cost'] ?? 0)) ?></div>
        <div class="col-4"><span class="text-muted"><?= e(__('Actual Cost')) ?>:</span> <?= e(money($mr['actual_cost'] ?? 0)) ?></div>
        <div class="col-6"><span class="text-muted"><?= e(__('Assigned To')) ?>:</span>
          <?= e(trim(($mr['assigned_first'] ?? '') . ' ' . ($mr['assigned_last'] ?? '')) ?: ($mr['vendor_name'] ?? '—')) ?></div>
        <?php if (!empty($mr['vendor_phone'])): ?>
          <div class="col-6"><span class="text-muted"><?= e(__('Vendor Phone')) ?>:</span> <?= e($mr['vendor_phone']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($photos): ?>
    <div class="srp-card p-4 mb-3">
      <h2 class="h6 mb-3"><?= e(__('Photos')) ?></h2>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($photos as $ph): ?>
          <a href="<?= e(UPLOAD_URL . '/' . $ph) ?>" target="_blank">
            <img src="<?= e(UPLOAD_URL . '/' . $ph) ?>" style="width:120px;height:90px;object-fit:cover;border-radius:8px">
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Status History')) ?></h2>
      <ul class="timeline list-unstyled mb-0">
        <?php foreach ($history as $h): ?>
          <li class="border-bottom pb-2 mb-2 small">
            <div class="fw-semibold">
              <?= $h['from_status'] ? e(ucfirst(str_replace('_',' ',$h['from_status']))) . ' → ' : '' ?>
              <?= e(ucfirst(str_replace('_',' ',$h['to_status']))) ?>
            </div>
            <div class="text-muted"><?= e(date('Y-m-d H:i', strtotime($h['changed_at']))) ?> · <?= e($h['changer'] ?? '—') ?></div>
            <?php if ($h['notes']): ?><div><?= e($h['notes']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Quick Stats')) ?></h2>
      <div class="small">
        <div class="d-flex justify-content-between mb-2"><span class="text-muted"><?= e(__('Age')) ?>:</span>
          <span><?= (int)((time() - strtotime($mr['reported_at'])) / 86400) ?> <?= e(__('days')) ?></span></div>
        <?php if ($mr['completed_at']): ?>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted"><?= e(__('Completed')) ?>:</span>
            <span><?= e(date('Y-m-d', strtotime($mr['completed_at']))) ?></span></div>
        <?php endif; ?>
        <?php if ($mr['verified_at']): ?>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted"><?= e(__('Verified')) ?>:</span>
            <span><?= e(date('Y-m-d', strtotime($mr['verified_at']))) ?></span></div>
        <?php endif; ?>
        <?php if ($mr['closed_at']): ?>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted"><?= e(__('Closed')) ?>:</span>
            <span><?= e(date('Y-m-d', strtotime($mr['closed_at']))) ?></span></div>
        <?php endif; ?>
        <?php if ($mr['work_order_id']): ?>
          <hr>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted"><?= e(__('Work Order')) ?>:</span>
            <a href="<?= APP_URL ?>/modules/work-orders/view.php?id=<?= (int)$mr['work_order_id'] ?>"><?= e($mr['work_order_number']) ?></a></div>
          <div class="d-flex justify-content-between"><span class="text-muted"><?= e(__('WO Status')) ?>:</span>
            <span><?= e(ucfirst(str_replace('_',' ',$mr['wo_status']))) ?></span></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>