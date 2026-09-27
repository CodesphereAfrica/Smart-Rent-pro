<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('documents.delete');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$d = db_one('SELECT * FROM documents WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$d) { flash_error(__('Not found.')); redirect('modules/documents/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    // Soft delete not supported on documents table per schema — do a DB delete but keep physical file for audit
    db_query('DELETE FROM documents WHERE id = :id', [':id' => $id]);
    log_audit('documents','delete','documents',$id,$d,null);
    flash_success(__('Document record deleted (physical file retained).'));
    redirect('modules/documents/index.php');
}
$PAGE_TITLE = __('Delete Document');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Delete Document')) ?></h1>
  <p><?= e(__('Delete')) ?> <strong><?= e($d['document_name']) ?></strong>?</p>
  <p class="text-muted small"><?= e(__('The physical file remains on disk for audit purposes.')) ?></p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/documents/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><?= e(__('Delete')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>