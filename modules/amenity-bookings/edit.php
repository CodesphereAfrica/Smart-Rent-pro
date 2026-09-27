<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$b = db_one('SELECT b.* FROM amenity_bookings b JOIN amenities a ON a.id = b.amenity_id JOIN properties p ON p.id = a.property_id
             WHERE b.id = :id AND p.company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$b) { flash_error(__('Booking not found.')); redirect('modules/amenity-bookings/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf(); $before = $b;
    $b['amenity_id']   = (int)($_POST['amenity_id'] ?? 0);
    $b['tenant_id']    = (int)($_POST['tenant_id'] ?? 0);
    $b['booking_date'] = sanitize_string($_POST['booking_date'] ?? '', 20);
    $b['start_time']   = sanitize_string($_POST['start_time'] ?? '', 8) ?: null;
    $b['end_time']     = sanitize_string($_POST['end_time'] ?? '', 8) ?: null;
    $b['fee_paid']     = (float)($_POST['fee_paid'] ?? 0);
    $b['status']       = in_array($_POST['status'] ?? '', ['pending','confirmed','cancelled','completed'], true) ? $_POST['status'] : 'pending';
    if (!$errors) {
        db_update('amenity_bookings', [
            'amenity_id'=>$b['amenity_id'],'tenant_id'=>$b['tenant_id'],
            'booking_date'=>$b['booking_date'],'start_time'=>$b['start_time'],
            'end_time'=>$b['end_time'],'fee_paid'=>$b['fee_paid'],'status'=>$b['status'],
        ], 'id = :id', [':id'=>$id]);
        log_audit('amenity_bookings','update','amenity_bookings',$id,$before,$b);
        flash_success(__('Booking updated.'));
        redirect('modules/amenity-bookings/index.php');
    }
}
$amenities = db_all('SELECT a.id, a.name, p.name AS property_name FROM amenities a JOIN properties p ON p.id = a.property_id WHERE p.company_id = :c ORDER BY a.name', [':c'=>(int)$USER['company_id']]);
$tenants   = db_all('SELECT id, first_name, last_name FROM tenants WHERE company_id = :c ORDER BY first_name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('Edit Booking');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Booking')) ?></h1>
<form method="post" class="srp-card p-4" style="max-width:720px">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Amenity')) ?></label>
      <select name="amenity_id" class="form-select">
        <?php foreach ($amenities as $a): ?>
          <option value="<?= (int)$a['id'] ?>" <?= (int)$b['amenity_id']===(int)$a['id']?'selected':'' ?>><?= e($a['name'] . ' — ' . $a['property_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Tenant')) ?></label>
      <select name="tenant_id" class="form-select">
        <?php foreach ($tenants as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= (int)$b['tenant_id']===(int)$t['id']?'selected':'' ?>><?= e($t['first_name'].' '.$t['last_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Date')) ?></label>
      <input type="date" name="booking_date" class="form-control" value="<?= e($b['booking_date']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Start')) ?></label>
      <input type="time" name="start_time" class="form-control" value="<?= e($b['start_time']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('End')) ?></label>
      <input type="time" name="end_time" class="form-control" value="<?= e($b['end_time']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Fee Paid')) ?></label>
      <input type="number" step="0.01" name="fee_paid" class="form-control" value="<?= e((string)$b['fee_paid']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['pending','confirmed','cancelled','completed'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $b['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/amenity-bookings/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>