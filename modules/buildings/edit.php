<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('buildings.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$b = db_one('SELECT b.* FROM buildings b JOIN properties p ON p.id = b.property_id
             WHERE b.id = :id AND p.company_id = :c LIMIT 1',
            [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$b) { flash_error(__('Building not found.')); redirect('modules/buildings/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $b;
    $b['name']         = sanitize_string($_POST['name'] ?? '', 120);
    $b['floors_count'] = max(1, (int)($_POST['floors_count'] ?? 1));
    $b['description']  = sanitize_string($_POST['description'] ?? '', 2000) ?: null;
    $b['status']       = in_array($_POST['status'] ?? '', ['active','inactive'], true) ? $_POST['status'] : 'active';
    if ($b['name'] === '') $errors['name'] = __('Name is required.');

    if (!$errors) {
        db_update('buildings', [
            'name' => $b['name'], 'floors_count' => $b['floors_count'],
            'description' => $b['description'], 'status' => $b['status'],
        ], 'id = :id', [':id'=>$id]);
        log_audit('buildings', 'update', 'buildings', $id, $before, $b);
        flash_success(__('Building updated.'));
        redirect('modules/buildings/view.php?id=' . $id);
    }
}
$PAGE_TITLE = __('Edit Building');
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/buildings/index.php"><?= e(__('Buildings')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($b['name']) ?></li>
</ol></nav>
<h1 class="h4 mb-3"><?= e(__('Edit Building')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Building Name')) ?></label>
      <input type="text" name="name" class="form-control<?= isset($errors['name'])?' is-invalid':'' ?>" value="<?= e($b['name']) ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Floors')) ?></label>
      <input type="number" name="floors_count" min="1" class="form-control" value="<?= (int)$b['floors_count'] ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <option value="active" <?= $b['status']==='active'?'selected':'' ?>><?= e(__('Active')) ?></option>
        <option value="inactive" <?= $b['status']==='inactive'?'selected':'' ?>><?= e(__('Inactive')) ?></option>
      </select></div>
    <div class="col-12"><label class="form-label"><?= e(__('Description')) ?></label>
      <textarea name="description" class="form-control" rows="3"><?= e($b['description']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/buildings/view.php?id=<?= (int)$id ?>" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>