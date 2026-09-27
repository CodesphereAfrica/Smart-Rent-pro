<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('owners.create');
$errors = [];
$old = [
    'owner_type' => 'individual', 'full_name' => '', 'phone' => '', 'email' => '',
    'address' => '', 'tax_id' => '', 'bank_name' => '', 'bank_account' => '',
    'notes' => '', 'status' => 'active', 'create_login' => 0,
    'login_username' => '', 'login_email' => '', 'login_password' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    foreach (['full_name','address','tax_id','bank_name','bank_account','notes','login_username'] as $k) {
        $old[$k] = sanitize_string($_POST[$k] ?? '', 200);
    }
    $old['phone']       = sanitize_phone($_POST['phone'] ?? '');
    $old['email']       = sanitize_email($_POST['email'] ?? '');
    $old['login_email'] = sanitize_email($_POST['login_email'] ?? '');
    $old['owner_type']  = in_array($_POST['owner_type'] ?? '', ['individual','company'], true) ? $_POST['owner_type'] : 'individual';
    $old['status']      = in_array($_POST['status'] ?? '', ['active','inactive'], true) ? $_POST['status'] : 'active';
    $old['create_login']= !empty($_POST['create_login']) ? 1 : 0;
    $plainPassword      = (string)($_POST['login_password'] ?? '');

    if ($old['full_name'] === '') $errors['full_name'] = __('Name is required.');

    $linkedUserId = null;
    if ($old['create_login']) {
        if ($old['login_username'] === '') $errors['login_username'] = __('Username is required when creating a login.');
        if (strlen($plainPassword) < 8)   $errors['login_password'] = __('Password must be at least 8 characters.');
        if (!empty($old['login_email']) === false) $errors['login_email'] = __('Email is required for portal logins.');

        if (!$errors) {
            $dup = db_one('SELECT id FROM users WHERE company_id = :c AND (username = :u OR email = :e)',
                          [':c' => (int)$USER['company_id'], ':u' => $old['login_username'], ':e' => $old['login_email']]);
            if ($dup) $errors['login_username'] = __('Username or email already in use.');
        }

        if (!$errors) {
            $role = db_one('SELECT id FROM roles WHERE company_id IS NULL AND name = "Property Owner" LIMIT 1');
            if ($role) {
                $linkedUserId = db_insert('users', [
                    'company_id'     => (int)$USER['company_id'],
                    'role_id'        => (int)$role['id'],
                    'first_name'     => mb_substr($old['full_name'], 0, 80),
                    'last_name'      => '—',
                    'username'       => $old['login_username'],
                    'email'          => $old['login_email'],
                    'phone'          => $old['phone'],
                    'password_hash'  => password_hash($plainPassword, PASSWORD_BCRYPT),
                    'preferred_language' => DEFAULT_LANGUAGE,
                    'status'         => 'active',
                    'must_change_password' => 1,
                ]);
            }
        }
    }

    if (!$errors) {
        $id = db_insert('owners', [
            'company_id'  => (int)$USER['company_id'],
            'user_id'     => $linkedUserId,
            'owner_type'  => $old['owner_type'],
            'full_name'   => $old['full_name'],
            'phone'       => $old['phone'],
            'email'       => $old['email'],
            'address'     => $old['address'] ?: null,
            'tax_id'      => $old['tax_id'] ?: null,
            'bank_name'   => $old['bank_name'] ?: null,
            'bank_account'=> $old['bank_account'] ?: null,
            'notes'       => $old['notes'] ?: null,
            'status'      => $old['status'],
        ]);
        log_audit('owners', 'create', 'owners', $id, null, $old);
        flash_success(__('Owner created.'));
        redirect('modules/owners/view.php?id=' . $id);
    }
}

$PAGE_TITLE = __('Add Owner');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Add Property Owner')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Full Name')) ?> *</label>
      <input name="full_name" class="form-control<?= isset($errors['full_name'])?' is-invalid':'' ?>" value="<?= e($old['full_name']) ?>" required>
      <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= e($errors['full_name']) ?></div><?php endif; ?></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Type')) ?></label>
      <select name="owner_type" class="form-select">
        <option value="individual" <?= $old['owner_type']==='individual'?'selected':'' ?>><?= e(__('Individual')) ?></option>
        <option value="company"    <?= $old['owner_type']==='company'?'selected':'' ?>><?= e(__('Company')) ?></option>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <option value="active"   <?= $old['status']==='active'?'selected':'' ?>><?= e(__('Active')) ?></option>
        <option value="inactive" <?= $old['status']==='inactive'?'selected':'' ?>><?= e(__('Inactive')) ?></option>
      </select></div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Phone')) ?></label>
      <input name="phone" class="form-control" value="<?= e((string)$old['phone']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="email" class="form-control" value="<?= e((string)$old['email']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Tax ID')) ?></label>
      <input name="tax_id" class="form-control" value="<?= e($old['tax_id']) ?>"></div>

    <div class="col-md-6"><label class="form-label"><?= e(__('Address')) ?></label>
      <input name="address" class="form-control" value="<?= e($old['address']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Bank Name')) ?></label>
      <input name="bank_name" class="form-control" value="<?= e($old['bank_name']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Bank Account')) ?></label>
      <input name="bank_account" class="form-control" value="<?= e($old['bank_account']) ?>"></div>

    <div class="col-12"><label class="form-label"><?= e(__('Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="2"><?= e($old['notes']) ?></textarea></div>

    <div class="col-12"><hr class="my-2"></div>
    <div class="col-12">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="create_login" id="createLogin" value="1"
               <?= $old['create_login'] ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="createLogin"><?= e(__('Create an owner portal login')) ?></label>
        <div class="text-muted small"><?= e(__('Linked users see only their own portfolio data.')) ?></div>
      </div>
    </div>
    <div class="col-md-4 loginFields"><label class="form-label"><?= e(__('Username')) ?></label>
      <input name="login_username" class="form-control<?= isset($errors['login_username'])?' is-invalid':'' ?>" value="<?= e($old['login_username']) ?>">
      <?php if (isset($errors['login_username'])): ?><div class="invalid-feedback"><?= e($errors['login_username']) ?></div><?php endif; ?></div>
    <div class="col-md-4 loginFields"><label class="form-label"><?= e(__('Login Email')) ?></label>
      <input type="email" name="login_email" class="form-control<?= isset($errors['login_email'])?' is-invalid':'' ?>" value="<?= e((string)$old['login_email']) ?>">
      <?php if (isset($errors['login_email'])): ?><div class="invalid-feedback"><?= e($errors['login_email']) ?></div><?php endif; ?></div>
    <div class="col-md-4 loginFields"><label class="form-label"><?= e(__('Temp Password')) ?></label>
      <input type="text" name="login_password" class="form-control<?= isset($errors['login_password'])?' is-invalid':'' ?>" value="">
      <?php if (isset($errors['login_password'])): ?><div class="invalid-feedback"><?= e($errors['login_password']) ?></div><?php endif; ?></div>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/owners/index.php" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Owner')) ?></button>
  </div>
</form>

<script>
(function () {
  const cb = document.getElementById('createLogin');
  const toggle = () => document.querySelectorAll('.loginFields').forEach(el => el.style.display = cb.checked ? '' : 'none');
  cb.addEventListener('change', toggle);
  toggle();
})();
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>