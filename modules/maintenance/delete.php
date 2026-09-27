<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('maintenance.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$mr = db_one('SELECT * FROM maintenance_requests WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$mr) { flash_error(__('Not found.')); redirect('modules/maintenance/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $hasWO = db_one('SELECT id FROM work_orders WHERE maintenance_request_id = :id LIMIT 1', [':id'=>$id]);
    if ($hasWO) { flash_error(__('Cannot delete — a work order is linked. Cancel the WO first.')); redirect('modules/maintenance/view.php?id=' . $id); }
    db_query('DELETE FROM maintenance_status_history WHERE maintenance_request_id = :id', [':id'=>$id]);
    db_query('DELETE FROM maintenance_requests WHERE id = :id', [':id'=>$id]);
    log_audit('maintenance','delete','maintenance_requests',$id,$mr,null);
    flash_success(__('Request deleted.'));
    redirect('modules/maintenance/index.php');
}
$PAGE_TITLE = __('Delete Maintenance');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Delete Maintenance Request')) ?></h1>
  <p><?= e(__('Delete')) ?> <strong><?= e($mr['ticket_number']) ?></strong>?</p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/maintenance/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><?= e(__('Delete')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>