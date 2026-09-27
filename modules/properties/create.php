<?php
// modules/properties/create.php — self-contained: form + validation + insert.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.create');

$errors = [];
$old = [
    'name' => '', 'property_type' => 'residential', 'address' => '',
    'city' => '', 'country' => 'Rwanda', 'latitude' => '', 'longitude' => '',
    'owner_id' => '', 'assigned_manager_id' => '', 'description' => '',
    'status' => 'active', 'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $old['name']             = sanitize_string($_POST['name'] ?? '', 150);
    $old['property_type']    = sanitize_string($_POST['property_type'] ?? 'residential', 40);
    $old['address']          = sanitize_string($_POST['address'] ?? '', 255);
    $old['city']             = sanitize_string($_POST['city'] ?? '', 100);
    $old['country']          = sanitize_string($_POST['country'] ?? 'Rwanda', 100);
    $old['latitude']         = sanitize_string($_POST['latitude'] ?? '', 20);
    $old['longitude']        = sanitize_string($_POST['longitude'] ?? '', 20);
    $old['owner_id']         = $_POST['owner_id'] !== '' ? (int)$_POST['owner_id'] : null;
    $old['assigned_manager_id'] = $_POST['assigned_manager_id'] !== '' ? (int)$_POST['assigned_manager_id'] : null;
    $old['description']      = sanitize_string($_POST['description'] ?? '', 2000);
    $old['status']           = sanitize_string($_POST['status'] ?? 'active', 30);
    $old['notes']            = sanitize_string($_POST['notes'] ?? '', 2000);

    if ($old['name'] === '')      $errors['name'] = __('Name is required.');
    if ($old['address'] === '')   $errors['address'] = __('Address is required.');

    $allowedTypes = ['residential','commercial','office','retail','warehouse','mixed_use','apartment','villa','house','student_housing','land','other'];
    if (!in_array($old['property_type'], $allowedTypes, true)) $errors['property_type'] = __('Invalid property type.');

    $allowedStatus = ['active','inactive','under_construction','sold'];
    if (!in_array($old['status'], $allowedStatus, true)) $errors['status'] = __('Invalid status.');

    // Photos (multi-upload optional)
    $photos = [];
    if (!empty($_FILES['photos']['name'][0])) {
        $count = count($_FILES['photos']['name']);
        for ($i = 0; $i < $count; $i++) {
            $f = [
                'name' => $_FILES['photos']['name'][$i],
                'type' => $_FILES['photos']['type'][$i],
                'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                'error' => $_FILES['photos']['error'][$i],
                'size' => $_FILES['photos']['size'][$i],
            ];
            $path = store_upload($f, 'properties', ['image/jpeg','image/png','image/webp']);
            if ($path) $photos[] = $path;
        }
    }

    if (!$errors) {
        $id = db_insert('properties', [
            'company_id' => (int)$USER['company_id'],
            'owner_id' => $old['owner_id'],
            'name' => $old['name'],
            'property_type' => $old['property_type'],
            'address' => $old['address'],
            'city' => $old['city'] ?: null,
            'country' => $old['country'] ?: 'Rwanda',
            'latitude' => $old['latitude'] !== '' ? $old['latitude'] : null,
            'longitude' => $old['longitude'] !== '' ? $old['longitude'] : null,
            'assigned_manager_id' => $old['assigned_manager_id'],
            'description' => $old['description'] ?: null,
            'photos' => $photos ? json_encode($photos) : null,
            'status' => $old['status'],
            'notes' => $old['notes'] ?: null,
        ]);

        log_audit('properties', 'create', 'properties', $id, null, $old);
        flash_success(__('Property created successfully.'));
        redirect('modules/properties/view.php?id=' . $id);
    }
}

$owners = db_all('SELECT id, full_name FROM owners WHERE company_id = :c AND status = "active" ORDER BY full_name',
                 [':c' => (int)$USER['company_id']]);
$managers = db_all(
    'SELECT u.id, CONCAT(u.first_name," ",u.last_name) AS name
       FROM users u JOIN roles r ON r.id = u.role_id
      WHERE u.company_id = :c AND u.status = "active" AND r.name IN ("Property Manager","Super Admin")
      ORDER BY u.first_name',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Add Property');
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/properties/index.php"><?= e(__('Properties')) ?></a></li>
    <li class="breadcrumb-item active"><?= e(__('New')) ?></li>
  </ol>
</nav>

<h1 class="h4 mb-3"><?= e(__('Add Property')) ?></h1>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="srp-card p-4 needs-validation" novalidate>
  <?= csrf_field() ?>

  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label"><?= e(__('Property Name')) ?> *</label>
      <input type="text" name="name" class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>"
             value="<?= e($old['name']) ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Type')) ?> *</label>
      <select name="property_type" class="form-select">
        <?php foreach (['residential','commercial','office','retail','warehouse','mixed_use','apartment','villa','house','student_housing','land','other'] as $t): ?>
          <option value="<?= e($t) ?>" <?= $old['property_type'] === $t ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_',' ',$t))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['active','inactive','under_construction','sold'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $old['status'] === $s ? 'selected' : '' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-8">
      <label class="form-label"><?= e(__('Address')) ?> *</label>
      <input type="text" name="address" class="form-control<?= isset($errors['address']) ? ' is-invalid' : '' ?>"
             value="<?= e($old['address']) ?>" required>
      <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?= e($errors['address']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-2">
      <label class="form-label"><?= e(__('City')) ?></label>
      <input type="text" name="city" class="form-control" value="<?= e($old['city']) ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label"><?= e(__('Country')) ?></label>
      <input type="text" name="country" class="form-control" value="<?= e($old['country']) ?>">
    </div>

    <div class="col-md-3">
      <label class="form-label"><?= e(__('Latitude')) ?></label>
      <input type="text" name="latitude" class="form-control" value="<?= e($old['latitude']) ?>" placeholder="-1.9441">
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Longitude')) ?></label>
      <input type="text" name="longitude" class="form-control" value="<?= e($old['longitude']) ?>" placeholder="30.0619">
    </div>

    <div class="col-md-3">
      <label class="form-label"><?= e(__('Owner')) ?></label>
      <select name="owner_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($owners as $o): ?>
          <option value="<?= (int)$o['id'] ?>" <?= (string)$old['owner_id'] === (string)$o['id'] ? 'selected' : '' ?>><?= e($o['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Assigned Manager')) ?></label>
      <select name="assigned_manager_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($managers as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= (string)$old['assigned_manager_id'] === (string)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <label class="form-label"><?= e(__('Description')) ?></label>
      <textarea name="description" class="form-control" rows="3"><?= e($old['description']) ?></textarea>
    </div>
    <div class="col-12">
      <label class="form-label"><?= e(__('Photos')) ?></label>
      <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
    </div>
    <div class="col-12">
      <label class="form-label"><?= e(__('Internal Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="2"><?= e($old['notes']) ?></textarea>
    </div>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/properties/index.php" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Property')) ?></button>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>