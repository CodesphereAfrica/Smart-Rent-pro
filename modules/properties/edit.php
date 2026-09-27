<?php
// modules/properties/edit.php — self-contained: form + validation + update.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.edit');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { flash_error(__('Invalid property.')); redirect('modules/properties/index.php'); }

$p = db_one('SELECT * FROM properties WHERE id = :id AND company_id = :c AND deleted_at IS NULL LIMIT 1',
            [':id' => $id, ':c' => (int)$USER['company_id']]);
if (!$p) { flash_error(__('Property not found.')); redirect('modules/properties/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $p;

    $p['name']             = sanitize_string($_POST['name'] ?? '', 150);
    $p['property_type']    = sanitize_string($_POST['property_type'] ?? '', 40);
    $p['address']          = sanitize_string($_POST['address'] ?? '', 255);
    $p['city']             = sanitize_string($_POST['city'] ?? '', 100);
    $p['country']          = sanitize_string($_POST['country'] ?? 'Rwanda', 100);
    $p['latitude']         = sanitize_string($_POST['latitude'] ?? '', 20) ?: null;
    $p['longitude']        = sanitize_string($_POST['longitude'] ?? '', 20) ?: null;
    $p['owner_id']         = ($_POST['owner_id'] ?? '') !== '' ? (int)$_POST['owner_id'] : null;
    $p['assigned_manager_id'] = ($_POST['assigned_manager_id'] ?? '') !== '' ? (int)$_POST['assigned_manager_id'] : null;
    $p['description']      = sanitize_string($_POST['description'] ?? '', 2000) ?: null;
    $p['status']           = sanitize_string($_POST['status'] ?? 'active', 30);
    $p['notes']            = sanitize_string($_POST['notes'] ?? '', 2000) ?: null;

    if ($p['name'] === '')    $errors['name'] = __('Name is required.');
    if ($p['address'] === '') $errors['address'] = __('Address is required.');

    // Append uploaded photos
    $photos = json_decode((string)($p['photos'] ?? '[]'), true) ?: [];
    if (!empty($_FILES['photos']['name'][0])) {
        $count = count($_FILES['photos']['name']);
        for ($i = 0; $i < $count; $i++) {
            $f = [
                'name' => $_FILES['photos']['name'][$i],
                'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                'error' => $_FILES['photos']['error'][$i],
                'size' => $_FILES['photos']['size'][$i],
            ];
            $path = store_upload($f, 'properties', ['image/jpeg','image/png','image/webp']);
            if ($path) $photos[] = $path;
        }
    }

    if (!$errors) {
        db_update('properties', [
            'owner_id' => $p['owner_id'],
            'name' => $p['name'],
            'property_type' => $p['property_type'],
            'address' => $p['address'],
            'city' => $p['city'] ?: null,
            'country' => $p['country'] ?: 'Rwanda',
            'latitude' => $p['latitude'],
            'longitude' => $p['longitude'],
            'assigned_manager_id' => $p['assigned_manager_id'],
            'description' => $p['description'],
            'photos' => $photos ? json_encode($photos) : null,
            'status' => $p['status'],
            'notes' => $p['notes'],
        ], 'id = :id', [':id' => $id]);

        log_audit('properties', 'update', 'properties', $id, $before, $p);
        flash_success(__('Property updated.'));
        redirect('modules/properties/view.php?id=' . $id);
    }
}

$owners   = db_all('SELECT id, full_name FROM owners WHERE company_id = :c AND status = "active" ORDER BY full_name',
                   [':c' => (int)$USER['company_id']]);
$managers = db_all(
    'SELECT u.id, CONCAT(u.first_name," ",u.last_name) AS name
       FROM users u JOIN roles r ON r.id = u.role_id
      WHERE u.company_id = :c AND u.status = "active" AND r.name IN ("Property Manager","Super Admin")',
    [':c' => (int)$USER['company_id']]
);
$photos = json_decode((string)($p['photos'] ?? '[]'), true) ?: [];

$PAGE_TITLE = __('Edit Property');
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/properties/index.php"><?= e(__('Properties')) ?></a></li>
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/properties/view.php?id=<?= (int)$id ?>"><?= e($p['name']) ?></a></li>
    <li class="breadcrumb-item active"><?= e(__('Edit')) ?></li>
  </ol>
</nav>

<h1 class="h4 mb-3"><?= e(__('Edit Property')) ?> · <?= e($p['name']) ?></h1>

<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="srp-card p-4 needs-validation" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">

  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label"><?= e(__('Property Name')) ?> *</label>
      <input type="text" name="name" class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" value="<?= e($p['name']) ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Type')) ?></label>
      <select name="property_type" class="form-select">
        <?php foreach (['residential','commercial','office','retail','warehouse','mixed_use','apartment','villa','house','student_housing','land','other'] as $t): ?>
          <option value="<?= e($t) ?>" <?= $p['property_type'] === $t ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_',' ',$t))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['active','inactive','under_construction','sold'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $p['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-8"><label class="form-label"><?= e(__('Address')) ?> *</label>
      <input type="text" name="address" class="form-control<?= isset($errors['address']) ? ' is-invalid' : '' ?>" value="<?= e($p['address']) ?>" required>
      <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?= e($errors['address']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-2"><label class="form-label"><?= e(__('City')) ?></label>
      <input type="text" name="city" class="form-control" value="<?= e($p['city']) ?>"></div>
    <div class="col-md-2"><label class="form-label"><?= e(__('Country')) ?></label>
      <input type="text" name="country" class="form-control" value="<?= e($p['country']) ?>"></div>

    <div class="col-md-3"><label class="form-label"><?= e(__('Latitude')) ?></label>
      <input type="text" name="latitude" class="form-control" value="<?= e($p['latitude']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Longitude')) ?></label>
      <input type="text" name="longitude" class="form-control" value="<?= e($p['longitude']) ?>"></div>

    <div class="col-md-3"><label class="form-label"><?= e(__('Owner')) ?></label>
      <select name="owner_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($owners as $o): ?>
          <option value="<?= (int)$o['id'] ?>" <?= (string)$p['owner_id'] === (string)$o['id'] ? 'selected' : '' ?>><?= e($o['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Assigned Manager')) ?></label>
      <select name="assigned_manager_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($managers as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= (string)$p['assigned_manager_id'] === (string)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12"><label class="form-label"><?= e(__('Description')) ?></label>
      <textarea name="description" class="form-control" rows="3"><?= e($p['description']) ?></textarea></div>
    <div class="col-12"><label class="form-label"><?= e(__('Add More Photos')) ?></label>
      <input type="file" name="photos[]" class="form-control" accept="image/*" multiple></div>

    <?php if ($photos): ?>
    <div class="col-12">
      <label class="form-label"><?= e(__('Current Photos')) ?></label>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($photos as $ph): ?>
          <img src="<?= e(UPLOAD_URL . '/' . $ph) ?>" style="width:96px;height:72px;object-fit:cover;border-radius:10px;border:1px solid var(--srp-border)">
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="col-12"><label class="form-label"><?= e(__('Internal Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="2"><?= e($p['notes']) ?></textarea></div>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/properties/view.php?id=<?= (int)$id ?>" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>