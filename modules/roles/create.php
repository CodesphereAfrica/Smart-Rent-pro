<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('users.manage');
$errors = [];
$old = ['name'=>'','description'=>'','is_active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['name'] = sanitize_string($_POST['name'] ?? '', 80);
    $old['description'] = sanitize_string($_POST['description'] ?? '', 255) ?: null;
    $old['is_active'] = !empty($_POST['is_active']) ? 1 : 0;

    if ($old['name'] === '') $errors['name'] = __('Role name required.');

    if (!$errors) {
        $id = db_insert('roles', [
            'company_id' => (int)$USER['company_id'],
            'name' => $old['name'],
            'description' => $old['description'],
            'is_system_role' => 0,
            'is_active' => $old['is_active'],
        ]);
        log_audit('roles','create','roles',$id,null,$old);
        flash_success(__('Role created. Now assign permissions.'));
        redirect('modules/roles/edit.php?id=' . $id);
    }
}
$PAGE_TITLE = __('New Role');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('New Custom Role')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4" style="max-width:640px">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label"><?= e(__('Role Name')) ?> *</label>
    <input name="name" class="form-control" value="<?= e($old['name']) ?>" required></div>
  <div class="mb-3"><label class="form-label"><?= e(__('Description')) ?></label>
    <input name="description" class="form-control" value="<?= e((string)$old['description']) ?>"></div>
  <div class="mb-3"><div class="form-check">
    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" <?= $old['is_active']?'checked':'' ?>>
    <label class="form-check-label" for="active"><?= e(__('Active')) ?></label>
  </div></div>
  <div class="d-flex justify-content-end gap-2">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/roles/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Create & Assign Permissions')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>