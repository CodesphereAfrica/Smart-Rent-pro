<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('units.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$u = db_one('SELECT * FROM units WHERE id = :id AND company_id = :c LIMIT 1',
            [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$u) { flash_error(__('Unit not found.')); redirect('modules/units/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $u;
    $u['unit_number']      = sanitize_string($_POST['unit_number'] ?? '', 40);
    $u['unit_type']        = sanitize_string($_POST['unit_type'] ?? '', 60) ?: null;
    $u['building_id']      = (int)($_POST['building_id'] ?? 0) ?: null;
    $u['floor_id']         = (int)($_POST['floor_id'] ?? 0) ?: null;
    $u['bedrooms']         = max(0, (int)($_POST['bedrooms'] ?? 0));
    $u['bathrooms']        = max(0, (int)($_POST['bathrooms'] ?? 0));
    $u['size_sqm']         = $_POST['size_sqm'] !== '' ? (float)$_POST['size_sqm'] : null;
    $u['furnished_status'] = in_array($_POST['furnished_status'] ?? '', ['furnished','unfurnished','semi_furnished'], true) ? $_POST['furnished_status'] : 'unfurnished';
    $u['base_rent']        = (float)($_POST['base_rent'] ?? 0);
    $u['security_deposit'] = (float)($_POST['security_deposit'] ?? 0);
    $u['status']           = in_array($_POST['status'] ?? '', ['available','occupied','reserved','under_maintenance','under_renovation','unavailable','notice_given'], true) ? $_POST['status'] : 'available';
    $u['description']      = sanitize_string($_POST['description'] ?? '', 2000) ?: null;
    $u['availability_date']= sanitize_string($_POST['availability_date'] ?? '', 20) ?: null;

    if ($u['unit_number'] === '') $errors['unit_number'] = __('Unit number is required.');

    $dup = db_one('SELECT id FROM units WHERE property_id = :p AND unit_number = :u AND id <> :id',
                  [':p'=>(int)$u['property_id'], ':u'=>$u['unit_number'], ':id'=>$id]);
    if ($dup) $errors['unit_number'] = __('Another unit already uses this number in the same property.');

    if (!$errors) {
        db_update('units', [
            'unit_number' => $u['unit_number'], 'unit_type' => $u['unit_type'],
            'building_id' => $u['building_id'], 'floor_id' => $u['floor_id'],
            'bedrooms' => $u['bedrooms'], 'bathrooms' => $u['bathrooms'],
            'size_sqm' => $u['size_sqm'], 'furnished_status' => $u['furnished_status'],
            'base_rent' => $u['base_rent'], 'security_deposit' => $u['security_deposit'],
            'status' => $u['status'], 'description' => $u['description'],
            'availability_date' => $u['availability_date'],
        ], 'id = :id', [':id'=>$id]);
        log_audit('units','update','units',$id,$before,$u);
        flash_success(__('Unit updated.'));
        redirect('modules/units/view.php?id=' . $id);
    }
}
$buildings = db_all('SELECT id, name FROM buildings WHERE property_id = :p ORDER BY name', [':p'=>(int)$u['property_id']]);
$floors    = $u['building_id']
    ? db_all('SELECT id, floor_number, floor_name FROM floors WHERE building_id = :b ORDER BY floor_number', [':b'=>(int)$u['building_id']])
    : [];

$PAGE_TITLE = __('Edit Unit');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Unit')) ?> · <?= e($u['unit_number']) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-3"><label class="form-label"><?= e(__('Unit Number')) ?> *</label>
      <input type="text" name="unit_number" class="form-control<?= isset($errors['unit_number'])?' is-invalid':'' ?>" value="<?= e($u['unit_number']) ?>" required>
      <?php if (isset($errors['unit_number'])): ?><div class="invalid-feedback"><?= e($errors['unit_number']) ?></div><?php endif; ?></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Type')) ?></label>
      <input type="text" name="unit_type" class="form-control" value="<?= e($u['unit_type']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Building')) ?></label>
      <select name="building_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($buildings as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= (int)$u['building_id']===(int)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Floor')) ?></label>
      <select name="floor_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($floors as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= (int)$u['floor_id']===(int)$f['id']?'selected':'' ?>><?= e((string)$f['floor_number'] . ' ' . ($f['floor_name'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-md-2"><label class="form-label"><?= e(__('Beds')) ?></label>
      <input type="number" name="bedrooms" class="form-control" value="<?= (int)$u['bedrooms'] ?>"></div>
    <div class="col-md-2"><label class="form-label"><?= e(__('Baths')) ?></label>
      <input type="number" name="bathrooms" class="form-control" value="<?= (int)$u['bathrooms'] ?>"></div>
    <div class="col-md-2"><label class="form-label"><?= e(__('Size (sqm)')) ?></label>
      <input type="number" step="0.01" name="size_sqm" class="form-control" value="<?= e((string)$u['size_sqm']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Furnished')) ?></label>
      <select name="furnished_status" class="form-select">
        <?php foreach (['unfurnished','semi_furnished','furnished'] as $f): ?>
          <option value="<?= e($f) ?>" <?= $u['furnished_status']===$f?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$f))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['available','occupied','reserved','under_maintenance','under_renovation','unavailable','notice_given'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $u['status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-md-3"><label class="form-label"><?= e(__('Base Rent')) ?></label>
      <input type="number" step="0.01" name="base_rent" class="form-control" value="<?= e((string)$u['base_rent']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Security Deposit')) ?></label>
      <input type="number" step="0.01" name="security_deposit" class="form-control" value="<?= e((string)$u['security_deposit']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Availability Date')) ?></label>
      <input type="date" name="availability_date" class="form-control" value="<?= e((string)$u['availability_date']) ?>"></div>

    <div class="col-12"><label class="form-label"><?= e(__('Description')) ?></label>
      <textarea name="description" class="form-control" rows="3"><?= e($u['description']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/units/view.php?id=<?= (int)$id ?>" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>