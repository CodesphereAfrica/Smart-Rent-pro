<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('users.manage');
$errors = []; $old = ['first_name'=>'','last_name'=>'','username'=>'','email'=>'','phone'=>'','role_id'=>0,'status'=>'active','password'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['first_name'] = sanitize_string($_POST['first_name'] ?? '', 80);
    $old['last_name']  = sanitize_string($_POST['last_name'] ?? '', 80);
    $old['username']   = sanitize_string($_POST['username'] ?? '', 60);
    $old['email']      = sanitize_email($_POST['email'] ?? '');
    $old['phone']      = sanitize_phone($_POST['phone'] ?? '');
    $old['role_id']    = (int)($_POST['role_id'] ?? 0);
    $old['status']     = in_array($_POST['status'] ?? '', ['active','inactive','suspended'], true) ? $_POST['status'] : 'active';
    $plain             = (string)($_POST['password'] ?? '');

    if ($old['first_name'] === '') $errors['first_name'] = __('Required.');
    if ($old['last_name']  === '') $errors['last_name']  = __('Required.');
    if ($old['username'] === '') $errors['username'] = __('Required.');
    if (!$old['email']) $errors['email'] = __('A valid email is required.');
    if (!$old['role_id']) $errors['role_id'] = __('Pick a role.');
    if (strlen($plain) < 8) $errors['password'] = __('Password must be at least 8 characters.');

    $dup = $old['username'] ? db_one('SELECT id FROM users WHERE company_id = :c AND (username = :u OR email = :e)',
                                       [':c'=>(int)$USER['company_id'], ':u'=>$old['username'], ':e'=>$old['email']]) : null;
    if ($dup) $errors['username'] = __('Username or email already in use.');

    if (!$errors) {
        $id = db_insert('users', [
            'company_id' => (int)$USER['company_id'],
            'role_id' => $old['role_id'],
            'first_name' => $old['first_name'],
            'last_name' => $old['last_name'],
            'username' => $old['username'],
            'email' => $old['email'],
            'phone' => $old['phone'],
            'password_hash' => password_hash($plain, PASSWORD_BCRYPT),
            'status' => $old['status'],
            'preferred_language' => DEFAULT_LANGUAGE,
            'must_change_password' => 1,
        ]);
        log_audit('users','create','users',$id,null,$old);
        flash_success(__('User created.'));
        redirect('modules/users/index.php');
    }
}
$roles = db_all('SELECT id, name FROM roles WHERE company_id IS NULL OR company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('New User');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('New User')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('First Name')) ?></label>
      <input name="first_name" class="form-control" value="<?= e($old['first_name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Last Name')) ?></label>
      <input name="last_name" class="form-control" value="<?= e($old['last_name']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Username')) ?></label>
      <input name="username" class="form-control" value="<?= e($old['username']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="email" class="form-control" value="<?= e((string)$old['email']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Phone')) ?></label>
      <input name="phone" class="form-control" value="<?= e((string)$old['phone']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Role')) ?></label>
      <select name="role_id" class="form-select" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($roles as $r): ?>
          <option value="<?= (int)$r['id'] ?>" <?= (int)$old['role_id']===(int)$r['id']?'selected':'' ?>><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Temp Password')) ?></label>
      <input name="password" class="form-control" required minlength="8"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['active','inactive','suspended'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $old['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/users/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Create User')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>