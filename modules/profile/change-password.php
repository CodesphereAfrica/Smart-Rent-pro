<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $cur  = (string)($_POST['current_password'] ?? '');
    $new  = (string)($_POST['new_password'] ?? '');
    $conf = (string)($_POST['confirm_password'] ?? '');

    $dbUser = db_one('SELECT password_hash FROM users WHERE id = :id', [':id' => (int)$USER['id']]);

    if (!$dbUser || !password_verify($cur, $dbUser['password_hash'])) $errors['_'] = __('Current password is incorrect.');
    if (strlen($new) < 8) $errors['_'] = __('New password must be at least 8 characters.');
    if ($new !== $conf) $errors['_'] = __('New passwords do not match.');

    if (!$errors) {
        db_update('users', [
            'password_hash' => password_hash($new, PASSWORD_BCRYPT),
            'must_change_password' => 0,
        ], 'id = :id', [':id' => (int)$USER['id']]);

        log_audit('users', 'change_password', 'users', (int)$USER['id']);
        flash_success(__('Password changed.'));
        redirect('modules/profile/index.php');
    }
}
$PAGE_TITLE = __('Change Password');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Change Password')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4" style="max-width:560px">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label"><?= e(__('Current Password')) ?></label>
    <input type="password" name="current_password" class="form-control" required></div>
  <div class="mb-3"><label class="form-label"><?= e(__('New Password')) ?></label>
    <input type="password" name="new_password" class="form-control" minlength="8" required></div>
  <div class="mb-3"><label class="form-label"><?= e(__('Confirm New Password')) ?></label>
    <input type="password" name="confirm_password" class="form-control" minlength="8" required></div>
  <div class="d-flex justify-content-end">
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Change Password')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>