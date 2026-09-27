<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('vendors.delete');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$v = db_one('SELECT * FROM vendors WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$v) { flash_error(__('Vendor not found.')); redirect('modules/vendors/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $hasWO = db_one('SELECT id FROM work_orders WHERE vendor_id = :v LIMIT 1', [':v' => $id]);
    if ($hasWO) {
        db_update('vendors', ['status' => 'inactive'], 'id = :id', [':id' => $id]);
        log_audit('vendors', 'deactivate', 'vendors', $id, $v, ['status' => 'inactive']);
        flash_success(__('Vendor deactivated (work-order history preserved).'));
    } else {
        db_query('DELETE FROM vendors WHERE id = :id', [':id' => $id]);
        log_audit('vendors','delete','vendors',$id,$v,null);
        flash_success(__('Vendor removed.'));
    }
    redirect('modules/vendors/index.php');
}
$PAGE_TITLE = __('Remove Vendor');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Remove Vendor')) ?></h1>
  <p><?= e(__('Remove')) ?> <strong><?= e($v['name']) ?></strong>?</p>
  <p class="text-muted small"><?= e(__('Vendors with work-order history will be deactivated rather than deleted.')) ?></p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/vendors/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><i data-lucide="trash-2"></i> <?= e(__('Remove')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>