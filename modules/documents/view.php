<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('documents.view');
$id = (int)($_GET['id'] ?? 0);
$d = db_one(
    'SELECT d.*, dc.name AS category_name, CONCAT(u.first_name," ",u.last_name) AS uploader
       FROM documents d
       LEFT JOIN document_categories dc ON dc.id = d.category_id
       LEFT JOIN users u ON u.id = d.uploaded_by
      WHERE d.id = :id AND d.company_id = :c LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$d) { flash_error(__('Document not found.')); redirect('modules/documents/index.php'); }

$versions = db_all(
    'SELECT id, version, file_path, uploaded_at FROM documents
      WHERE company_id = :c AND (id = :root OR parent_document_id = :root OR id = (SELECT COALESCE(parent_document_id,id) FROM documents WHERE id = :root))
      ORDER BY version ASC',
    [':c'=>(int)$USER['company_id'], ':root'=>$id]
);

$PAGE_TITLE = $d['document_name'];
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/documents/index.php"><?= e(__('Documents')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($d['document_name']) ?></li>
</ol></nav>

<div class="row g-3">
  <div class="col-lg-8"><div class="srp-card p-4">
    <h1 class="h5 mb-1"><?= e($d['document_name']) ?></h1>
    <div class="text-muted small mb-3">
      <?= e(ucfirst(str_replace('_',' ',$d['related_type']))) ?> #<?= (int)$d['related_id'] ?>
      · <?= e($d['category_name'] ?? '—') ?>
      · v<?= (int)$d['version'] ?>
      · <?= (int)($d['file_size_kb'] ?? 0) ?> KB
    </div>
    <a class="btn btn-primary" href="<?= e(UPLOAD_URL . '/' . $d['file_path']) ?>" target="_blank"><i data-lucide="download"></i> <?= e(__('Download')) ?></a>
    <?php if (can('documents.upload')): ?>
      <a class="btn btn-outline-primary" href="<?= APP_URL ?>/modules/documents/upload.php?parent_document_id=<?= (int)$id ?>"><i data-lucide="git-branch"></i> <?= e(__('Upload New Version')) ?></a>
    <?php endif; ?>

    <?php if ($d['expiry_date']): ?>
      <div class="alert alert-warning mt-3 mb-0 small">
        <?= e(__('Expires on')) ?>: <strong><?= e($d['expiry_date']) ?></strong>
      </div>
    <?php endif; ?>

    <?php if ($d['notes']): ?>
      <div class="mt-3 small text-muted"><?= nl2br(e($d['notes'])) ?></div>
    <?php endif; ?>
  </div></div>

  <div class="col-lg-4"><div class="srp-card p-4 h-100">
    <h2 class="h6 mb-3"><?= e(__('Versions')) ?></h2>
    <ul class="list-unstyled small">
      <?php foreach ($versions as $v): ?>
        <li class="border-bottom pb-2 mb-2">
          <a href="<?= e(UPLOAD_URL . '/' . $v['file_path']) ?>" target="_blank">v<?= (int)$v['version'] ?></a>
          <span class="text-muted ms-2"><?= e(date('Y-m-d', strtotime($v['uploaded_at']))) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="text-muted small mt-3">
      <?= e(__('Uploaded by')) ?> <?= e($d['uploader'] ?? '—') ?>
    </div>
  </div></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>