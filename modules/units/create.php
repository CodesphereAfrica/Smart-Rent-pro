<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('units.create');

$presetProperty = (int)($_GET['property_id'] ?? 0);
$presetBuilding = (int)($_GET['building_id'] ?? 0);

$errors = [];
$old = [
    'property_id' => $presetProperty, 'building_id' => $presetBuilding, 'floor_id' => 0,
    'unit_number' => '', 'unit_type' => '', 'bedrooms' => 0, 'bathrooms' => 0,
    'size_sqm' => '', 'furnished_status' => 'unfurnished',
    'base_rent' => '', 'security_deposit' => '', 'status' => 'available',
    'description' => '', 'availability_date' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['property_id']      = (int)($_POST['property_id'] ?? 0);
    $old['building_id']      = (int)($_POST['building_id'] ?? 0) ?: null;
    $old['floor_id']         = (int)($_POST['floor_id'] ?? 0) ?: null;
    $old['unit_number']      = sanitize_string($_POST['unit_number'] ?? '', 40);
    $old['unit_type']        = sanitize_string($_POST['unit_type'] ?? '', 60) ?: null;
    $old['bedrooms']         = max(0, (int)($_POST['bedrooms'] ?? 0));
    $old['bathrooms']        = max(0, (int)($_POST['bathrooms'] ?? 0));
    $old['size_sqm']         = $_POST['size_sqm'] !== '' ? (float)$_POST['size_sqm'] : null;
    $old['furnished_status'] = in_array($_POST['furnished_status'] ?? '', ['furnished','unfurnished','semi_furnished'], true) ? $_POST['furnished_status'] : 'unfurnished';
    $old['base_rent']        = (float)($_POST['base_rent'] ?? 0);
    $old['security_deposit'] = (float)($_POST['security_deposit'] ?? 0);
    $old['status']           = in_array($_POST['status'] ?? '', ['available','occupied','reserved','under_maintenance','under_renovation','unavailable','notice_given'], true) ? $_POST['status'] : 'available';
    $old['description']      = sanitize_string($_POST['description'] ?? '', 2000) ?: null;
    $old['availability_date']= sanitize_string($_POST['availability_date'] ?? '', 20) ?: null;

    if (!$old['property_id'])         $errors['property_id'] = __('Select a property.');
    if ($old['unit_number'] === '')   $errors['unit_number'] = __('Unit number is required.');
    if ($old['base_rent'] < 0)        $errors['base_rent']   = __('Rent cannot be negative.');

    // Property belongs to company?
    $p = db_one('SELECT id FROM properties WHERE id = :p AND company_id = :c', [':p'=>$old['property_id'], ':c'=>(int)$USER['company_id']]);
    if (!$p) $errors['property_id'] = __('Invalid property.');

    // Unit number uniqueness within property
    if (!$errors) {
        $dup = db_one('SELECT id FROM units WHERE property_id = :p AND unit_number = :u LIMIT 1',
                      [':p'=>$old['property_id'], ':u'=>$old['unit_number']]);
        if ($dup) $errors['unit_number'] = __('This unit number already exists in this property.');
    }

    if (!$errors) {
        $id = db_insert('units', [
            'company_id' => (int)$USER['company_id'],
            'property_id' => $old['property_id'],
            'building_id' => $old['building_id'],
            'floor_id' => $old['floor_id'],
            'unit_number' => $old['unit_number'],
            'unit_type' => $old['unit_type'],
            'bedrooms' => $old['bedrooms'],
            'bathrooms' => $old['bathrooms'],
            'size_sqm' => $old['size_sqm'],
            'furnished_status' => $old['furnished_status'],
            'base_rent' => $old['base_rent'],
            'security_deposit' => $old['security_deposit'],
            'status' => $old['status'],
            'description' => $old['description'],
            'availability_date' => $old['availability_date'],
        ]);
        // Update cached counts on properties
        db_query('UPDATE properties SET num_units = (SELECT COUNT(*) FROM units WHERE property_id = :p) WHERE id = :p',
                 [':p'=>$old['property_id']]);

        log_audit('units', 'create', 'units', $id, null, $old);
        flash_success(__('Unit created.'));
        redirect('modules/units/view.php?id=' . $id);
    }
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name',
                     [':c' => (int)$USER['company_id']]);
$buildings = $old['property_id']
    ? db_all('SELECT id, name FROM buildings WHERE property_id = :p ORDER BY name', [':p'=>$old['property_id']])
    : [];
$floors = $old['building_id']
    ? db_all('SELECT id, floor_number, floor_name FROM floors WHERE building_id = :b ORDER BY floor_number', [':b'=>$old['building_id']])
    : [];

$PAGE_TITLE = __('Add Unit');
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/units/index.php"><?= e(__('Units')) ?></a></li>
  <li class="breadcrumb-item active"><?= e(__('New')) ?></li>
</ol></nav>
<h1 class="h4 mb-3"><?= e(__('Add Unit')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>

<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label"><?= e(__('Property')) ?> *</label>
      <select name="property_id" id="selProperty" class="form-select<?= isset($errors['property_id'])?' is-invalid':'' ?>" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['property_id'])): ?><div class="invalid-feedback"><?= e($errors['property_id']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Building')) ?></label>
      <select name="building_id" id="selBuilding" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($buildings as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= (int)$old['building_id']===(int)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Floor')) ?></label>
      <select name="floor_id" id="selFloor" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($floors as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= (int)$old['floor_id']===(int)$f['id']?'selected':'' ?>><?= e((string)$f['floor_number'] . ' ' . ($f['floor_name'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label"><?= e(__('Unit Number')) ?> *</label>
      <input type="text" name="unit_number" class="form-control<?= isset($errors['unit_number'])?' is-invalid':'' ?>" value="<?= e($old['unit_number']) ?>" required>
      <?php if (isset($errors['unit_number'])): ?><div class="invalid-feedback"><?= e($errors['unit_number']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Type')) ?></label>
      <input type="text" name="unit_type" class="form-control" value="<?= e((string)$old['unit_type']) ?>" placeholder="Studio, 2BR, Office Suite…">
    </div>
    <div class="col-md-2">
      <label class="form-label"><?= e(__('Beds')) ?></label>
      <input type="number" name="bedrooms" min="0" class="form-control" value="<?= (int)$old['bedrooms'] ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label"><?= e(__('Baths')) ?></label>
      <input type="number" name="bathrooms" min="0" class="form-control" value="<?= (int)$old['bathrooms'] ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label"><?= e(__('Size (sqm)')) ?></label>
      <input type="number" step="0.01" name="size_sqm" class="form-control" value="<?= e((string)$old['size_sqm']) ?>">
    </div>

    <div class="col-md-3">
      <label class="form-label"><?= e(__('Furnished')) ?></label>
      <select name="furnished_status" class="form-select">
        <?php foreach (['unfurnished','semi_furnished','furnished'] as $f): ?>
          <option value="<?= e($f) ?>" <?= $old['furnished_status']===$f?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$f))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Base Rent')) ?></label>
      <input type="number" step="0.01" name="base_rent" class="form-control" value="<?= e((string)$old['base_rent']) ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Security Deposit')) ?></label>
      <input type="number" step="0.01" name="security_deposit" class="form-control" value="<?= e((string)$old['security_deposit']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['available','occupied','reserved','under_maintenance','under_renovation','unavailable','notice_given'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $old['status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Availability Date')) ?></label>
      <input type="date" name="availability_date" class="form-control" value="<?= e((string)$old['availability_date']) ?>">
    </div>
    <div class="col-12">
      <label class="form-label"><?= e(__('Description')) ?></label>
      <textarea name="description" class="form-control" rows="3"><?= e((string)$old['description']) ?></textarea>
    </div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/units/index.php" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Unit')) ?></button>
  </div>
</form>

<script>
/* Cascade: property → buildings/floors */
document.getElementById('selProperty').addEventListener('change', function () {
  const pid = this.value;
  const bSel = document.getElementById('selBuilding'); const fSel = document.getElementById('selFloor');
  bSel.innerHTML = '<option value="">— none —</option>'; fSel.innerHTML = '<option value="">— none —</option>';
  if (!pid) return;
  srpFetch(window.SRP.url + '/modules/units/create.php?cascade=buildings&property_id=' + pid)
    .then(r => r.json()).then(d => {
      (d.buildings || []).forEach(b => bSel.insertAdjacentHTML('beforeend', '<option value="'+b.id+'">'+b.name+'</option>'));
    });
});
document.getElementById('selBuilding').addEventListener('change', function () {
  const bid = this.value;
  const fSel = document.getElementById('selFloor');
  fSel.innerHTML = '<option value="">— none —</option>';
  if (!bid) return;
  srpFetch(window.SRP.url + '/modules/units/create.php?cascade=floors&building_id=' + bid)
    .then(r => r.json()).then(d => {
      (d.floors || []).forEach(f => fSel.insertAdjacentHTML('beforeend', '<option value="'+f.id+'">'+f.floor_number+' '+(f.floor_name||'')+'</option>'));
    });
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

<?php
// Cascade endpoints — self-contained inside create.php, no separate file.
if (($_GET['cascade'] ?? '') === 'buildings') {
    header('Content-Type: application/json');
    $rows = db_all('SELECT id, name FROM buildings WHERE property_id = :p AND property_id IN (SELECT id FROM properties WHERE company_id = :c) ORDER BY name',
                   [':p'=>(int)$_GET['property_id'], ':c'=>(int)$USER['company_id']]);
    echo json_encode(['buildings'=>$rows]); exit;
}
if (($_GET['cascade'] ?? '') === 'floors') {
    header('Content-Type: application/json');
    $rows = db_all('SELECT id, floor_number, floor_name FROM floors WHERE building_id = :b ORDER BY floor_number',
                   [':b'=>(int)$_GET['building_id']]);
    echo json_encode(['floors'=>$rows]); exit;
}
?>