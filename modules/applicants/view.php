<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('tenants.view');
$id = (int)($_GET['id'] ?? 0);
$a = db_one(
    'SELECT a.*, u.unit_number, u.id AS unit_id, p.name AS property_name
       FROM applicants a
       LEFT JOIN units u ON u.id = a.unit_id
       LEFT JOIN properties p ON p.id = u.property_id
      WHERE a.id = :id AND a.company_id = :c LIMIT 1',
    [':id' => $id, ':c' => (int)$USER['company_id']]
);
if (!$a) { flash_error(__('Applicant not found.')); redirect('modules/applicants/index.php'); }

$docs = db_all('SELECT id, document_type, file_path, uploaded_at FROM applicant_documents WHERE applicant_id = :a ORDER BY id DESC', [':a'=>$id]);
$acts = db_all('SELECT id, activity_type, description, created_at FROM applicant_activities WHERE applicant_id = :a ORDER BY id DESC LIMIT 30', [':a'=>$id]);

$PAGE_TITLE = __('Applicant') . ' · ' . $a['first_name'] . ' ' . $a['last_name'];
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/applicants/index.php"><?= e(__('Applicants')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($a['first_name'].' '.$a['last_name']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e($a['first_name'].' '.$a['last_name']) ?>
      <span class="srp-badge-pill badge-status-reserved ms-2"><span class="dot"></span><?= e(ucfirst($a['stage'])) ?></span>
    </h1>
    <div class="text-muted small">
      <?= e($a['phone'] ?: '—') ?>
      <?php if ($a['email']): ?> · <?= e($a['email']) ?><?php endif; ?>
      <?php if ($a['unit_number']): ?> · <?= e(__('Interested in')) ?> <?= e($a['unit_number']) ?> (<?= e($a['property_name']) ?>)<?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <?php if (can('tenants.edit')): ?>
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/applicants/edit.php?id=<?= (int)$id ?>"><i data-lucide="pencil"></i> <?= e(__('Edit')) ?></a>
    <?php endif; ?>
    <?php if ($a['stage'] === 'approved' && can('tenants.create')): ?>
      <a class="btn btn-success" href="<?= APP_URL ?>/modules/applicants/convert.php?id=<?= (int)$id ?>"><i data-lucide="user-check"></i> <?= e(__('Convert to Tenant')) ?></a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6"><div class="srp-card p-4">
    <h2 class="h6 mb-3"><?= e(__('Applicant Details')) ?></h2>
    <div class="row small g-2">
      <div class="col-6"><span class="text-muted"><?= e(__('Monthly Income')) ?>:</span> <?= e(money($a['monthly_income'] ?? 0)) ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Employer')) ?>:</span> <?= e($a['employer'] ?: '—') ?></div>
      <div class="col-12"><span class="text-muted"><?= e(__('Unit of Interest')) ?>:</span> <?= e($a['unit_number'] ? $a['unit_number'].' ('.$a['property_name'].')' : '—') ?></div>
      <?php if ($a['screening_notes']): ?>
        <div class="col-12"><span class="text-muted"><?= e(__('Screening Notes')) ?>:</span><br><?= nl2br(e($a['screening_notes'])) ?></div>
      <?php endif; ?>
    </div>
  </div></div>

  <div class="col-lg-6"><div class="srp-card p-4 h-100">
    <h2 class="h6 mb-3"><?= e(__('Activity Log')) ?></h2>
    <?php if (!$acts): ?><div class="text-muted small"><?= e(__('No activity yet.')) ?></div>
    <?php else: ?>
      <ul class="list-unstyled small">
        <?php foreach ($acts as $act): ?>
          <li class="border-bottom pb-2 mb-2">
            <span class="text-muted"><?= e(date('Y-m-d H:i', strtotime($act['created_at']))) ?></span>
            — <span class="fw-semibold"><?= e($act['activity_type']) ?></span>
            <div><?= e($act['description']) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div></div>

  <div class="col-12"><div class="srp-card p-4">
    <h2 class="h6 mb-3"><?= e(__('Documents')) ?></h2>
    <?php if (!$docs): ?><div class="text-muted small"><?= e(__('No documents uploaded yet.')) ?></div>
    <?php else: ?>
      <ul class="list-group list-group-flush">
        <?php foreach ($docs as $d): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <a href="<?= e(UPLOAD_URL . '/' . $d['file_path']) ?>" target="_blank" class="text-decoration-none"><i data-lucide="file"></i> <?= e($d['document_type']) ?></a>
            <span class="text-muted small"><?= e(date('Y-m-d', strtotime($d['uploaded_at']))) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>