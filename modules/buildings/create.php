<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('buildings.create');

$errors = [];
$old = ['property_id' => (int)($_GET['property_id'] ?? 0), 'name'=>'', 'floors_count'=>1, 'description'=>'', 'status'=>'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['property_id']   = (int)($_POST['property_id'] ?? 0);
    $old['name']          = sanitize_string($_POST['name'] ?? '', 120);
    $old['floors_count']  = max(1, (int)($_POST['floors_count'] ?? 1));
    $old['description']   = sanitize_string($_POST['description'] ?? '', 2000) ?: null;
    $old['status']        = in_array($_POST['status'] ?? '', ['active','inactive'], true) ? $_POST['status'] : 'active';

    if (!$old['property_id']) $errors['property_id'] = __('Select a property.');
    if ($old['name'] === '')  $errors['name'] = __('Name is required.');

    // Verify property belongs to company
    $propOk = db_one('SELECT id FROM properties WHERE id = :p AND company_id = :c LIMIT 1',
                     [':p' => $old['property_id'], ':c' => (int)$USER['company_id']]);
    if (!$propOk) $errors['property_id'] = __('Invalid property.');

    if (!$errors) {
        $id = db_insert('buildings', [
            'property_id' => $old['property_id'],
            'name' => $old['name'],
            'floors_count' => $old['floors_count'],
            'description' => $old['description'],
            'status' => $old['status'],
        ]);
        log_audit('buildings', 'create', 'buildings', $id, null, $old);
        flash_success(__('Building created.'));
        redirect('modules/buildings/view.php?id=' . $id);
    }
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name',
                     [':c' => (int)$USER['company_id']]);

$PAGE_TITLE = __('Add Building');
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/buildings/index.php"><?= e(__('Buildings')) ?></a></li>
  <li class="breadcrumb-item active"><?= e(__('New')) ?></li>
</ol></nav>
<h1 class="h4 mb-3"><?= e(__('Add Building')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label"><?= e(__('Property')) ?> *</label>
      <select name="property_id" class="form-select<?= isset($errors['property_id']) ? ' is-invalid' : '' ?>">
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['property_id'])): ?><div class="invalid-feedback"><?= e($errors['property_id']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-6">
      <label class="form-label"><?= e(__('Building Name')) ?> *</label>
      <input type="text" name="name" class="form-control<?= isset($errors['name']) ? ' is-invalid' : '' ?>" value="<?= e($old['name']) ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Floors')) ?></label>
      <input type="number" name="floors_count" min="1" class="form-control" value="<?= (int)$old['floors_count'] ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <option value="active" <?= $old['status']==='active'?'selected':'' ?>><?= e(__('Active')) ?></option>
        <option value="inactive" <?= $old['status']==='inactive'?'selected':'' ?>><?= e(__('Inactive')) ?></option>
      </select>
    </div>
    <div class="col-12">
      <label class="form-label"><?= e(__('Description')) ?></label>
      <textarea name="description" class="form-control" rows="3"><?= e($old['description']) ?></textarea>
    </div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/buildings/index.php" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>