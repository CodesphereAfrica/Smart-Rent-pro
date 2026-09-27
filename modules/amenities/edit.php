<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$a = db_one('SELECT a.* FROM amenities a JOIN properties p ON p.id = a.property_id
             WHERE a.id = :id AND p.company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$a) { flash_error(__('Amenity not found.')); redirect('modules/amenities/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf(); $before = $a;
    $a['name']         = sanitize_string($_POST['name'] ?? '', 120);
    $a['amenity_type'] = sanitize_string($_POST['amenity_type'] ?? '', 60) ?: null;
    $a['description']  = sanitize_string($_POST['description'] ?? '', 2000) ?: null;
    $a['fee']          = (float)($_POST['fee'] ?? 0);
    $a['rules']        = sanitize_string($_POST['rules'] ?? '', 2000) ?: null;
    $a['status']       = in_array($_POST['status'] ?? '', ['active','under_maintenance','inactive'], true) ? $_POST['status'] : 'active';
    if ($a['name'] === '') $errors['name'] = __('Name required.');
    if (!$errors) {
        db_update('amenities', [
            'name' => $a['name'], 'amenity_type' => $a['amenity_type'],
            'description' => $a['description'], 'fee' => $a['fee'],
            'rules' => $a['rules'], 'status' => $a['status'],
        ], 'id = :id', [':id'=>$id]);
        log_audit('amenities','update','amenities',$id,$before,$a);
        flash_success(__('Amenity updated.'));
        redirect('modules/amenities/index.php');
    }
}
$PAGE_TITLE = __('Edit Amenity');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Amenity')) ?></h1>
<form method="post" class="srp-card p-4" style="max-width:720px">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Name')) ?></label>
      <input name="name" class="form-control<?= isset($errors['name'])?' is-invalid':'' ?>" value="<?= e($a['name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Type')) ?></label>
      <input name="amenity_type" class="form-control" value="<?= e($a['amenity_type']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Fee')) ?></label>
      <input type="number" step="0.01" name="fee" class="form-control" value="<?= e((string)$a['fee']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['active','under_maintenance','inactive'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $a['status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-12"><label class="form-label"><?= e(__('Description')) ?></label>
      <textarea name="description" class="form-control" rows="2"><?= e($a['description']) ?></textarea></div>
    <div class="col-12"><label class="form-label"><?= e(__('Rules')) ?></label>
      <textarea name="rules" class="form-control" rows="2"><?= e($a['rules']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/amenities/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>