<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('inspections.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$i = db_one('SELECT * FROM inspections WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$i) { flash_error(__('Not found.')); redirect('modules/inspections/index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    db_query('DELETE FROM inspection_results WHERE inspection_id = :id', [':id'=>$id]);
    db_query('DELETE FROM inspections WHERE id = :id', [':id'=>$id]);
    log_audit('inspections','delete','inspections',$id,$i,null);
    flash_success(__('Inspection deleted.'));
    redirect('modules/inspections/index.php');
}
$PAGE_TITLE = __('Delete Inspection');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Delete Inspection')) ?></h1>
  <p><?= e(__('Delete inspection from')) ?> <?= e($i['inspection_date']) ?>?</p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/inspections/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><?= e(__('Delete')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>