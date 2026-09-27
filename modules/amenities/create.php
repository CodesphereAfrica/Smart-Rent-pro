<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.create');

$errors = []; $old = [
    'property_id' => (int)($_GET['property_id'] ?? 0),
    'name' => '', 'amenity_type' => '', 'description' => '', 'fee' => '0', 'rules' => '', 'status' => 'active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['property_id']  = (int)($_POST['property_id'] ?? 0);
    $old['name']         = sanitize_string($_POST['name'] ?? '', 120);
    $old['amenity_type'] = sanitize_string($_POST['amenity_type'] ?? '', 60) ?: null;
    $old['description']  = sanitize_string($_POST['description'] ?? '', 2000) ?: null;
    $old['fee']          = (float)($_POST['fee'] ?? 0);
    $old['rules']        = sanitize_string($_POST['rules'] ?? '', 2000) ?: null;
    $old['status']       = in_array($_POST['status'] ?? '', ['active','under_maintenance','inactive'], true) ? $_POST['status'] : 'active';

    if (!$old['property_id'])     $errors['property_id'] = __('Select a property.');
    if ($old['name'] === '')      $errors['name'] = __('Name is required.');

    $p = db_one('SELECT id FROM properties WHERE id = :p AND company_id = :c', [':p'=>$old['property_id'], ':c'=>(int)$USER['company_id']]);
    if (!$p) $errors['property_id'] = __('Invalid property.');

    if (!$errors) {
        $id = db_insert('amenities', [
            'property_id' => $old['property_id'], 'name' => $old['name'],
            'amenity_type' => $old['amenity_type'], 'description' => $old['description'],
            'fee' => $old['fee'], 'rules' => $old['rules'], 'status' => $old['status'],
        ]);
        log_audit('amenities', 'create', 'amenities', $id, null, $old);
        flash_success(__('Amenity created.'));
        redirect('modules/amenities/index.php');
    }
}
$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('Add Amenity');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Add Amenity')) ?></h1>
<form method="post" class="srp-card p-4" style="max-width:720px">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Property')) ?> *</label>
      <select name="property_id" class="form-select<?= isset($errors['property_id'])?' is-invalid':'' ?>">
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Name')) ?> *</label>
      <input name="name" class="form-control<?= isset($errors['name'])?' is-invalid':'' ?>" value="<?= e($old['name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Type')) ?></label>
      <input name="amenity_type" class="form-control" value="<?= e($old['amenity_type']) ?>" placeholder="gym, pool, conference_room…"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Fee')) ?></label>
      <input type="number" step="0.01" name="fee" class="form-control" value="<?= e((string)$old['fee']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['active','under_maintenance','inactive'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $old['status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-12"><label class="form-label"><?= e(__('Description')) ?></label>
      <textarea name="description" class="form-control" rows="2"><?= e($old['description']) ?></textarea></div>
    <div class="col-12"><label class="form-label"><?= e(__('Rules')) ?></label>
      <textarea name="rules" class="form-control" rows="2"><?= e($old['rules']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/amenities/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>