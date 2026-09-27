<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.view');
$id = (int)($_GET['id'] ?? 0);
$c = db_one(
    'SELECT ct.*, p.name AS property_name, v.name AS vendor_name, d.file_path AS document_path, d.document_name
       FROM contracts ct
       LEFT JOIN properties p ON p.id = ct.property_id
       LEFT JOIN vendors v ON v.id = ct.vendor_id
       LEFT JOIN documents d ON d.id = ct.document_id
      WHERE ct.id = :id AND ct.company_id = :co LIMIT 1',
    [':id'=>$id, ':co'=>(int)$USER['company_id']]
);
if (!$c) { flash_error(__('Not found.')); redirect('modules/contracts/index.php'); }

$daysLeft = $c['end_date'] ? (int)((strtotime($c['end_date']) - time()) / 86400) : null;

$PAGE_TITLE = $c['title'];
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/contracts/index.php"><?= e(__('Contracts')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($c['title']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e($c['title']) ?></h1>
    <div class="text-muted small"><?= e($c['contract_type']) ?> · <?= e($c['property_name'] ?? '—') ?> · <?= e($c['vendor_name'] ?? '—') ?></div>
  </div>
  <?php if (can('properties.edit')): ?>
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/contracts/edit.php?id=<?= (int)$id ?>"><i data-lucide="pencil"></i> <?= e(__('Edit')) ?></a>
  <?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-lg-8"><div class="srp-card p-4">
    <div class="row small g-2">
      <div class="col-6"><span class="text-muted"><?= e(__('Start')) ?>:</span> <?= e($c['start_date']) ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('End')) ?>:</span> <?= e($c['end_date'] ?: '—') ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Amount')) ?>:</span> <?= e(money($c['amount'] ?? 0)) ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Responsible')) ?>:</span> <?= e($c['responsible_party'] ?? '—') ?></div>
      <div class="col-12"><span class="text-muted"><?= e(__('Renewal Terms')) ?>:</span> <?= e($c['renewal_terms'] ?? '—') ?></div>
    </div>

    <?php if ($c['document_id']): ?>
      <div class="mt-3"><a class="btn btn-light" href="<?= e(UPLOAD_URL . '/' . $c['document_path']) ?>" target="_blank"><i data-lucide="file-text"></i> <?= e($c['document_name']) ?></a></div>
    <?php endif; ?>
  </div></div>

  <?php if ($daysLeft !== null): ?>
  <div class="col-lg-4"><div class="srp-card p-4 h-100">
    <h2 class="h6 mb-3"><?= e(__('Renewal')) ?></h2>
    <p class="mb-0"><?= e(__('Days remaining:')) ?> <strong class="<?= $daysLeft < 30 ? 'text-danger' : 'text-success' ?>"><?= $daysLeft ?></strong></p>
    <?php if ($daysLeft < 60): ?>
      <div class="alert alert-warning small mt-2 mb-0"><?= e(__('Renewal due — contact the vendor.')) ?></div>
    <?php endif; ?>
  </div></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>