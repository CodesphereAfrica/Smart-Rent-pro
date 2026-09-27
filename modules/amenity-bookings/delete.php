<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.delete');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$b = db_one('SELECT b.* FROM amenity_bookings b JOIN amenities a ON a.id = b.amenity_id JOIN properties p ON p.id = a.property_id
             WHERE b.id = :id AND p.company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$b) { flash_error(__('Not found.')); redirect('modules/amenity-bookings/index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    db_query('DELETE FROM amenity_bookings WHERE id = :id', [':id'=>$id]);
    log_audit('amenity_bookings','delete','amenity_bookings',$id,$b,null);
    flash_success(__('Booking removed.'));
    redirect('modules/amenity-bookings/index.php');
}
$PAGE_TITLE = __('Cancel Booking');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Delete Booking')) ?></h1>
  <p><?= e(__('Remove this booking permanently?')) ?></p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/amenity-bookings/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><?= e(__('Delete')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>