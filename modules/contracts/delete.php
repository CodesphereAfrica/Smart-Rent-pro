<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.delete');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$c = db_one('SELECT * FROM contracts WHERE id = :id AND company_id = :co LIMIT 1', [':id'=>$id, ':co'=>(int)$USER['company_id']]);
if (!$c) { flash_error(__('Not found.')); redirect('modules/contracts/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    db_query('DELETE FROM contracts WHERE id = :id', [':id' => $id]);
    log_audit('contracts','delete','contracts',$id,$c,null);
    flash_success(__('Contract deleted.'));
    redirect('modules/contracts/index.php');
}
$PAGE_TITLE = __('Delete Contract');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Delete Contract')) ?></h1>
  <p><?= e(__('Delete')) ?> <strong><?= e($c['title']) ?></strong>?</p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/contracts/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><?= e(__('Delete')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>