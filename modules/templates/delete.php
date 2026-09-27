<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('settings.manage');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$t = db_one('SELECT * FROM notification_templates WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$t) { flash_error(__('Not found.')); redirect('modules/templates/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    db_query('DELETE FROM notification_templates WHERE id = :id', [':id' => $id]);
    log_audit('notification_templates','delete','notification_templates',$id,$t,null);
    flash_success(__('Template deleted.'));
    redirect('modules/templates/index.php');
}
$PAGE_TITLE = __('Delete Template');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Delete Template')) ?></h1>
  <p><?= e(__('Delete')) ?> <code><?= e($t['event_code']) ?></code> (<?= e($t['channel']) ?>)?</p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/templates/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><?= e(__('Delete')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>