<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.create');

$errors = []; $old = ['amenity_id'=>0,'tenant_id'=>0,'booking_date'=>date('Y-m-d'),'start_time'=>'','end_time'=>'','fee_paid'=>0,'status'=>'pending'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['amenity_id']   = (int)($_POST['amenity_id'] ?? 0);
    $old['tenant_id']    = (int)($_POST['tenant_id'] ?? 0);
    $old['booking_date'] = sanitize_string($_POST['booking_date'] ?? '', 20);
    $old['start_time']   = sanitize_string($_POST['start_time'] ?? '', 8) ?: null;
    $old['end_time']     = sanitize_string($_POST['end_time'] ?? '', 8) ?: null;
    $old['fee_paid']     = (float)($_POST['fee_paid'] ?? 0);
    $old['status']       = in_array($_POST['status'] ?? '', ['pending','confirmed','cancelled','completed'], true) ? $_POST['status'] : 'pending';

    if (!$old['amenity_id']) $errors['amenity_id'] = __('Select an amenity.');
    if (!$old['tenant_id'])  $errors['tenant_id']  = __('Select a tenant.');
    if (!$old['booking_date']) $errors['booking_date'] = __('Pick a date.');

    if (!$errors) {
        $id = db_insert('amenity_bookings', $old);
        log_audit('amenity_bookings','create','amenity_bookings',$id,null,$old);
        flash_success(__('Booking recorded.'));
        redirect('modules/amenity-bookings/index.php');
    }
}
$amenities = db_all('SELECT a.id, a.name, p.name AS property_name FROM amenities a JOIN properties p ON p.id = a.property_id WHERE p.company_id = :c AND a.status = "active" ORDER BY a.name', [':c'=>(int)$USER['company_id']]);
$tenants   = db_all('SELECT id, first_name, last_name FROM tenants WHERE company_id = :c AND status = "active" ORDER BY first_name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('New Booking');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('New Amenity Booking')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors.')) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4" style="max-width:720px">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Amenity')) ?></label>
      <select name="amenity_id" class="form-select<?= isset($errors['amenity_id'])?' is-invalid':'' ?>" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($amenities as $a): ?>
          <option value="<?= (int)$a['id'] ?>" <?= (int)$old['amenity_id']===(int)$a['id']?'selected':'' ?>><?= e($a['name'] . ' — ' . $a['property_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Tenant')) ?></label>
      <select name="tenant_id" class="form-select<?= isset($errors['tenant_id'])?' is-invalid':'' ?>" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($tenants as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= (int)$old['tenant_id']===(int)$t['id']?'selected':'' ?>><?= e($t['first_name'].' '.$t['last_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Date')) ?></label>
      <input type="date" name="booking_date" class="form-control" value="<?= e($old['booking_date']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Start Time')) ?></label>
      <input type="time" name="start_time" class="form-control" value="<?= e((string)$old['start_time']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('End Time')) ?></label>
      <input type="time" name="end_time" class="form-control" value="<?= e((string)$old['end_time']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Fee Paid')) ?></label>
      <input type="number" step="0.01" name="fee_paid" class="form-control" value="<?= e((string)$old['fee_paid']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['pending','confirmed','cancelled','completed'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $old['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/amenity-bookings/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><?= e(__('Save Booking')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>