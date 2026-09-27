<?php
// modules/users/edit.php — self-contained: form + validation + update (with optional password reset).
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('users.manage');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$u = db_one('SELECT u.* FROM users u WHERE u.id = :id AND u.company_id = :c LIMIT 1',
            [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$u) { flash_error(__('User not found.')); redirect('modules/users/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $u;

    $u['first_name'] = sanitize_string($_POST['first_name'] ?? '', 80);
    $u['last_name']  = sanitize_string($_POST['last_name'] ?? '', 80);
    $u['username']   = sanitize_string($_POST['username'] ?? '', 60);
    $u['email']      = sanitize_email($_POST['email'] ?? '');
    $u['phone']      = sanitize_phone($_POST['phone'] ?? '');
    $u['role_id']    = (int)($_POST['role_id'] ?? 0);
    $u['status']     = in_array($_POST['status'] ?? '', ['active','inactive','suspended'], true) ? $_POST['status'] : $u['status'];
    $plain           = (string)($_POST['password'] ?? '');
    $twoFactorToggle = !empty($_POST['two_factor_enabled']) ? 1 : 0;

    if ($u['first_name'] === '') $errors['first_name'] = __('Required.');
    if ($u['last_name']  === '') $errors['last_name']  = __('Required.');
    if ($u['username']   === '') $errors['username']   = __('Required.');
    if (!$u['email'])            $errors['email']      = __('A valid email is required.');
    if (!$u['role_id'])          $errors['role_id']    = __('Pick a role.');
    if ($plain !== '' && strlen($plain) < 8) $errors['password'] = __('Password must be at least 8 characters.');

    // Uniqueness within company (excluding this user)
    if (!$errors) {
        $dup = db_one(
            'SELECT id FROM users WHERE company_id = :c AND (username = :u OR email = :e) AND id <> :id',
            [':c'=>(int)$USER['company_id'], ':u'=>$u['username'], ':e'=>$u['email'], ':id'=>$id]
        );
        if ($dup) $errors['username'] = __('Username or email already in use.');
    }

    // Guard: prevent disabling the last active Super Admin in the company
    if (!$errors && $u['status'] !== 'active' && $before['status'] === 'active') {
        $role = db_one('SELECT name FROM roles WHERE id = :r', [':r'=>(int)$before['role_id']]);
        if ($role && $role['name'] === 'Super Admin') {
            $others = (int)(db_one(
                'SELECT COUNT(*) AS c FROM users u JOIN roles r ON r.id = u.role_id
                  WHERE u.company_id = :c AND r.name = "Super Admin" AND u.status = "active" AND u.id <> :id',
                [':c'=>(int)$USER['company_id'], ':id'=>$id]
            )['c'] ?? 0);
            if ($others === 0) $errors['status'] = __('Cannot deactivate the only active Super Admin.');
        }
    }

    if (!$errors) {
        $data = [
            'first_name' => $u['first_name'],
            'last_name'  => $u['last_name'],
            'username'   => $u['username'],
            'email'      => $u['email'],
            'phone'      => $u['phone'],
            'role_id'    => $u['role_id'],
            'status'     => $u['status'],
            'two_factor_enabled' => $twoFactorToggle,
        ];
        if ($plain !== '') {
            $data['password_hash'] = password_hash($plain, PASSWORD_BCRYPT);
            $data['must_change_password'] = 1;
        }
        db_update('users', $data, 'id = :id', [':id' => $id]);

        log_audit('users', 'update', 'users', $id, $before, array_merge($u, ['password_changed' => $plain !== '']));
        flash_success(__('User updated.'));
        redirect('modules/users/index.php');
    }
}

$roles = db_all('SELECT id, name FROM roles WHERE company_id IS NULL OR company_id = :c ORDER BY name',
                [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('Edit User');
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/users/index.php"><?= e(__('Users')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($u['first_name'].' '.$u['last_name']) ?></li>
</ol></nav>

<h1 class="h4 mb-3"><?= e(__('Edit User')) ?> · <?= e($u['first_name'].' '.$u['last_name']) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label"><?= e(__('First Name')) ?> *</label>
      <input name="first_name" class="form-control<?= isset($errors['first_name'])?' is-invalid':'' ?>" value="<?= e($u['first_name']) ?>" required>
      <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= e($errors['first_name']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-6">
      <label class="form-label"><?= e(__('Last Name')) ?> *</label>
      <input name="last_name" class="form-control<?= isset($errors['last_name'])?' is-invalid':'' ?>" value="<?= e($u['last_name']) ?>" required>
      <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= e($errors['last_name']) ?></div><?php endif; ?>
    </div>

    <div class="col-md-4">
      <label class="form-label"><?= e(__('Username')) ?> *</label>
      <input name="username" class="form-control<?= isset($errors['username'])?' is-invalid':'' ?>" value="<?= e($u['username']) ?>" required>
      <?php if (isset($errors['username'])): ?><div class="invalid-feedback"><?= e($errors['username']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Email')) ?> *</label>
      <input type="email" name="email" class="form-control<?= isset($errors['email'])?' is-invalid':'' ?>" value="<?= e((string)$u['email']) ?>" required>
      <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Phone')) ?></label>
      <input name="phone" class="form-control" value="<?= e((string)$u['phone']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label"><?= e(__('Role')) ?> *</label>
      <select name="role_id" class="form-select<?= isset($errors['role_id'])?' is-invalid':'' ?>" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($roles as $r): ?>
          <option value="<?= (int)$r['id'] ?>" <?= (int)$u['role_id']===(int)$r['id']?'selected':'' ?>><?= e($r['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['role_id'])): ?><div class="invalid-feedback"><?= e($errors['role_id']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select<?= isset($errors['status'])?' is-invalid':'' ?>">
        <?php foreach (['active','inactive','suspended'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $u['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['status'])): ?><div class="invalid-feedback"><?= e($errors['status']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Reset Password (leave blank to keep)')) ?></label>
      <input name="password" class="form-control<?= isset($errors['password'])?' is-invalid':'' ?>" type="password" minlength="8">
      <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div><?php endif; ?>
    </div>

    <div class="col-12">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="two_factor_enabled" id="tfa" value="1" <?= !empty($u['two_factor_enabled']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="tfa"><?= e(__('Two-factor authentication enabled')) ?></label>
      </div>
      <div class="text-muted small"><?= e(__('The user must complete 2FA setup before they can enable it.')) ?></div>
    </div>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/users/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>