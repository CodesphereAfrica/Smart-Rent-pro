<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('documents.upload');

$presetRel  = sanitize_string($_GET['related_type'] ?? '', 60);
$presetId   = (int)($_GET['related_id'] ?? 0);
$parentId   = (int)($_GET['parent_document_id'] ?? 0);

$errors = [];
$old = [
    'related_type' => $presetRel, 'related_id' => $presetId,
    'category_id' => 0,
    'document_name' => '', 'expiry_date' => '', 'notes' => '',
    'parent_document_id' => $parentId,
];

// If versioning, lookup previous document name
if ($parentId) {
    $parent = db_one('SELECT * FROM documents WHERE id = :i AND company_id = :c', [':i'=>$parentId, ':c'=>(int)$USER['company_id']]);
    if ($parent) {
        $old['related_type'] = $parent['related_type'];
        $old['related_id'] = (int)$parent['related_id'];
        $old['category_id'] = (int)$parent['category_id'];
        $old['document_name'] = $parent['document_name'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['related_type'] = sanitize_string($_POST['related_type'] ?? '', 60);
    $old['related_id']   = (int)($_POST['related_id'] ?? 0);
    $old['category_id']  = (int)($_POST['category_id'] ?? 0) ?: null;
    $old['document_name'] = sanitize_string($_POST['document_name'] ?? '', 200);
    $old['expiry_date']  = sanitize_string($_POST['expiry_date'] ?? '', 20) ?: null;
    $old['notes']        = sanitize_string($_POST['notes'] ?? '', 500) ?: null;
    $old['parent_document_id'] = (int)($_POST['parent_document_id'] ?? 0) ?: null;

    if ($old['related_type'] === '') $errors['related_type'] = __('Related type required.');
    if ($old['related_id'] <= 0)    $errors['related_id'] = __('Related ID required.');
    if ($old['document_name'] === '') $errors['document_name'] = __('Document name required.');
    if (empty($_FILES['file']['tmp_name'])) $errors['file'] = __('Please pick a file.');

    if (!$errors) {
        $path = store_upload($_FILES['file'], 'documents');
        if (!$path) {
            $errors['file'] = __('Upload failed or file type not allowed.');
        } else {
            // Determine version
            $version = 1;
            if ($old['parent_document_id']) {
                $prev = db_one('SELECT version FROM documents WHERE id = :i', [':i' => $old['parent_document_id']]);
                $version = ((int)($prev['version'] ?? 0)) + 1;
            }

            $id = db_insert('documents', [
                'company_id' => (int)$USER['company_id'],
                'category_id' => $old['category_id'],
                'related_type' => $old['related_type'],
                'related_id' => $old['related_id'],
                'document_name' => $old['document_name'],
                'file_path' => $path,
                'file_type' => mime_content_type($_FILES['file']['tmp_name']) ?: null,
                'file_size_kb' => (int)round($_FILES['file']['size'] / 1024),
                'expiry_date' => $old['expiry_date'],
                'version' => $version,
                'parent_document_id' => $old['parent_document_id'],
                'uploaded_by' => (int)$USER['id'],
                'notes' => $old['notes'],
            ]);

            log_audit('documents', 'upload', 'documents', $id, null, $old);
            flash_success(__('Document uploaded.'));
            redirect('modules/documents/view.php?id=' . $id);
        }
    }
}

$categories = db_all('SELECT id, name FROM document_categories WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('Upload Document');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Upload Document')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="srp-card p-4">
  <?= csrf_field() ?>
  <input type="hidden" name="parent_document_id" value="<?= (int)$old['parent_document_id'] ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Document Name')) ?> *</label>
      <input name="document_name" class="form-control" value="<?= e($old['document_name']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Related Type')) ?> *</label>
      <select name="related_type" class="form-select" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach (['tenant','owner','property','unit','lease','vendor','maintenance','inspection','contract','insurance','payment','invoice'] as $rt): ?>
          <option value="<?= e($rt) ?>" <?= $old['related_type']===$rt?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$rt))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Related ID')) ?> *</label>
      <input type="number" name="related_id" class="form-control" value="<?= (int)$old['related_id'] ?>" required></div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Category')) ?></label>
      <select name="category_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$old['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Expiry Date (optional)')) ?></label>
      <input type="date" name="expiry_date" class="form-control" value="<?= e((string)$old['expiry_date']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('File')) ?> *</label>
      <input type="file" name="file" class="form-control" required></div>

    <div class="col-12"><label class="form-label"><?= e(__('Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="2"><?= e((string)$old['notes']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/documents/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="upload"></i> <?= e(__('Upload')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>