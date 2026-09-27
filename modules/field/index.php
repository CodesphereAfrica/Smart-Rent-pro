<?php
// modules/field/index.php — Inspector/Technician offline-friendly mobile view.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_login();

// Assigned jobs: maintenance assigned to me + inspections scheduled for today
$maintenance = db_all(
    'SELECT mr.id, mr.ticket_number, mr.status, mr.priority, mr.description,
            u.unit_number, p.name AS property_name, p.latitude, p.longitude
       FROM maintenance_requests mr
       JOIN units u ON u.id = mr.unit_id
       JOIN properties p ON p.id = mr.property_id
      WHERE mr.assigned_to_user_id = :me AND mr.status NOT IN ("closed","verified")
      ORDER BY FIELD(mr.priority,"emergency","high","medium","low"), mr.created_at ASC',
    [':me' => (int)$USER['id']]
);

$inspections = db_all(
    'SELECT i.id, i.inspection_type, i.inspection_date, i.status,
            u.unit_number, p.name AS property_name
       FROM inspections i
       JOIN units u ON u.id = i.unit_id
       JOIN properties p ON p.id = i.property_id
      WHERE i.inspector_id = :me AND i.status = "scheduled"
      ORDER BY i.inspection_date ASC',
    [':me' => (int)$USER['id']]
);

$PAGE_TITLE   = __('My Jobs');
$PAGE_SCRIPTS = ['field-sync.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="alert alert-info small" id="offlineBanner" style="display:none">
  <?= e(__('You are offline. Your edits will sync when connectivity returns.')) ?>
</div>

<h1 class="h4 mb-3"><?= e(__('My Jobs')) ?></h1>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><i data-lucide="hammer"></i> <?= e(__('Assigned Maintenance')) ?></h2>
      <?php if (!$maintenance): ?><div class="text-muted small"><?= e(__('No maintenance assigned to you.')) ?></div>
      <?php else: foreach ($maintenance as $m): ?>
        <div class="border-bottom py-3" data-job="maintenance:<?= (int)$m['id'] ?>">
          <div class="d-flex justify-content-between">
            <div>
              <div class="fw-semibold"><?= e($m['ticket_number']) ?></div>
              <div class="text-muted small"><?= e($m['unit_number']) ?> · <?= e($m['property_name']) ?></div>
            </div>
            <span class="srp-badge-pill <?= $m['priority']==='emergency'?'badge-status-under_maintenance':($m['priority']==='high'?'badge-status-reserved':'badge-status-notice_given') ?>">
              <span class="dot"></span><?= e(ucfirst($m['priority'])) ?>
            </span>
          </div>
          <div class="text-muted small mt-1"><?= e($m['description']) ?></div>
          <div class="d-flex gap-2 mt-2">
            <a class="btn btn-sm btn-primary" href="<?= APP_URL ?>/modules/maintenance/edit.php?id=<?= (int)$m['id'] ?>"><?= e(__('Update')) ?></a>
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/maintenance/view.php?id=<?= (int)$m['id'] ?>"><?= e(__('View')) ?></a>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><i data-lucide="clipboard-check"></i> <?= e(__('My Inspections')) ?></h2>
      <?php if (!$inspections): ?><div class="text-muted small"><?= e(__('No inspections assigned.')) ?></div>
      <?php else: foreach ($inspections as $i): ?>
        <div class="border-bottom py-3">
          <div class="fw-semibold"><?= e(ucfirst(str_replace('_',' ',$i['inspection_type']))) ?> · <?= e($i['unit_number']) ?></div>
          <div class="text-muted small"><?= e($i['property_name']) ?> · <?= e($i['inspection_date']) ?></div>
          <a class="btn btn-sm btn-primary mt-2" href="<?= APP_URL ?>/modules/inspections/view.php?id=<?= (int)$i['id'] ?>"><?= e(__('Open')) ?></a>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<script>
window.SRP_FIELD = {
    userId: <?= (int)$USER['id'] ?>,
    syncUrl: <?= json_encode(APP_URL . '/modules/field/sync.php') ?>,
    cacheKey: <?= json_encode('srp_field_jobs_' . $USER['id']) ?>,
    maintenance: <?= json_encode($maintenance) ?>,
    inspections: <?= json_encode($inspections) ?>
};
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>