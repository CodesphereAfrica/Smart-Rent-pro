<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('maintenance.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$wo = db_one('SELECT * FROM work_orders WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$wo) { flash_error(__('Not found.')); redirect('modules/work-orders/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    db_update('work_orders', ['status'=>'cancelled'], 'id = :id', [':id' => $id]);
    db_update('maintenance_requests', ['status'=>'new'], 'id = :id', [':id'=>(int)$wo['maintenance_request_id']]);
    log_audit('work_orders','cancel','work_orders',$id,$wo,['status'=>'cancelled']);
    flash_success(__('Work order cancelled.'));
    redirect('modules/work-orders/index.php');
}
$PAGE_TITLE = __('Cancel Work Order');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Cancel Work Order')) ?> <?= e($wo['work_order_number']) ?></h1>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/work-orders/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><?= e(__('Confirm Cancel')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>