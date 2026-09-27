<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.delete');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$a = db_one('SELECT a.* FROM amenities a JOIN properties p ON p.id = a.property_id WHERE a.id = :id AND p.company_id = :c',
            [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$a) { flash_error(__('Not found.')); redirect('modules/amenities/index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    db_query('DELETE FROM amenities WHERE id = :id', [':id'=>$id]);
    log_audit('amenities','delete','amenities',$id,$a,null);
    flash_success(__('Amenity deleted.'));
    redirect('modules/amenities/index.php');
}
$PAGE_TITLE = __('Delete Amenity');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Delete Amenity')) ?></h1>
  <p><?= e(__('Are you sure you want to delete')) ?> <strong><?= e($a['name']) ?></strong>?</p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/amenities/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><?= e(__('Delete')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>