<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.create');
$errors = []; $old = ['property_id'=>0,'space_number'=>'','space_type'=>'','monthly_fee'=>0,'status'=>'available'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['property_id']  = (int)($_POST['property_id'] ?? 0);
    $old['space_number'] = sanitize_string($_POST['space_number'] ?? '', 30);
    $old['space_type']   = sanitize_string($_POST['space_type'] ?? '', 40) ?: null;
    $old['monthly_fee']  = (float)($_POST['monthly_fee'] ?? 0);
    $old['status']       = in_array($_POST['status'] ?? '', ['available','assigned','unavailable'], true) ? $_POST['status'] : 'available';
    if (!$old['property_id']) $errors['property_id'] = __('Property required.');
    if ($old['space_number'] === '') $errors['space_number'] = __('Space number required.');
    $p = db_one('SELECT id FROM properties WHERE id = :p AND company_id = :c', [':p'=>$old['property_id'], ':c'=>(int)$USER['company_id']]);
    if (!$p) $errors['property_id'] = __('Invalid property.');
    $dup = $old['property_id'] && $old['space_number']
        ? db_one('SELECT id FROM parking_spaces WHERE property_id = :p AND space_number = :n', [':p'=>$old['property_id'], ':n'=>$old['space_number']])
        : null;
    if ($dup) $errors['space_number'] = __('This space number already exists for this property.');
    if (!$errors) {
        $id = db_insert('parking_spaces', $old);
        log_audit('parking_spaces','create','parking_spaces',$id,null,$old);
        flash_success(__('Parking space added.'));
        redirect('modules/parking/index.php');
    }
}
$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('Add Parking Space');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Add Parking Space')) ?></h1>
<form method="post" class="srp-card p-4" style="max-width:640px">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Property')) ?> *</label>
      <select name="property_id" class="form-select<?= isset($errors['property_id'])?' is-invalid':'' ?>">
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Space #')) ?> *</label>
      <input name="space_number" class="form-control<?= isset($errors['space_number'])?' is-invalid':'' ?>" value="<?= e($old['space_number']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Type')) ?></label>
      <input name="space_type" class="form-control" value="<?= e($old['space_type']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Monthly Fee')) ?></label>
      <input type="number" step="0.01" name="monthly_fee" class="form-control" value="<?= e((string)$old['monthly_fee']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['available','assigned','unavailable'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $old['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/parking/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>