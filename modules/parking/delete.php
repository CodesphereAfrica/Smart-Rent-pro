<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.delete');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$s = db_one('SELECT s.* FROM parking_spaces s JOIN properties p ON p.id = s.property_id WHERE s.id = :id AND p.company_id = :c',
            [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$s) { flash_error(__('Not found.')); redirect('modules/parking/index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $active = db_one('SELECT id FROM parking_assignments WHERE parking_space_id = :s AND status = "active" LIMIT 1', [':s'=>$id]);
    if ($active) { flash_error(__('Cannot delete — space has an active assignment.')); redirect('modules/parking/index.php'); }
    db_query('DELETE FROM parking_spaces WHERE id = :id', [':id'=>$id]);
    log_audit('parking_spaces','delete','parking_spaces',$id,$s,null);
    flash_success(__('Space deleted.'));
    redirect('modules/parking/index.php');
}
$PAGE_TITLE = __('Delete Parking Space');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Delete Parking Space')) ?></h1>
  <p><?= e(__('Delete space')) ?> <strong><?= e($s['space_number']) ?></strong>?</p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/parking/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><?= e(__('Delete')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>