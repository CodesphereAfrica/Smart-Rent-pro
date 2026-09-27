<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_login();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $USER;

    $first = sanitize_string($_POST['first_name'] ?? '', 80);
    $last  = sanitize_string($_POST['last_name'] ?? '', 80);
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = sanitize_phone($_POST['phone'] ?? '');
    $lang  = sanitize_string($_POST['preferred_language'] ?? 'en', 5);

    if ($first === '' || $last === '') $errors['_'] = __('Name required.');
    if (!$email) $errors['_'] = __('Valid email required.');

    $photo = $USER['profile_photo'];
    if (!empty($_FILES['profile_photo']['tmp_name'])) {
        $path = store_upload($_FILES['profile_photo'], 'users', ['image/jpeg','image/png','image/webp']);
        if ($path) $photo = $path;
    }

    if (!$errors) {
        db_update('users', [
            'first_name' => $first,
            'last_name'  => $last,
            'email'      => $email,
            'phone'      => $phone,
            'preferred_language' => $lang,
            'profile_photo' => $photo,
        ], 'id = :id', [':id' => (int)$USER['id']]);

        log_audit('users', 'self_update', 'users', (int)$USER['id'], $before, ['first_name'=>$first,'last_name'=>$last,'email'=>$email]);
        flash_success(__('Profile updated.'));
        redirect('modules/profile/index.php');
    }
}

$PAGE_TITLE = __('My Profile');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('My Profile')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="srp-card p-4" style="max-width:720px">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('First Name')) ?></label>
      <input name="first_name" class="form-control" value="<?= e($USER['first_name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Last Name')) ?></label>
      <input name="last_name" class="form-control" value="<?= e($USER['last_name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="email" class="form-control" value="<?= e($USER['email']) ?>" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Phone')) ?></label>
      <input name="phone" class="form-control" value="<?= e((string)$USER['phone']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Language')) ?></label>
      <select name="preferred_language" class="form-select">
        <?php foreach (['en'=>'English','rw'=>'Kinyarwanda','fr'=>'Français'] as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= ($USER['preferred_language'] ?? 'en')===$code?'selected':'' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Profile Photo')) ?></label>
      <input type="file" name="profile_photo" class="form-control" accept="image/*"></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>

<div class="mt-3 d-flex gap-2">
  <a class="btn btn-light" href="<?= APP_URL ?>/modules/profile/change-password.php"><i data-lucide="key-round"></i> <?= e(__('Change password')) ?></a>
  <a class="btn btn-light" href="<?= APP_URL ?>/modules/auth/setup-2fa.php"><i data-lucide="shield-check"></i> <?= e(__('Two-factor authentication')) ?></a>
  <a class="btn btn-light" href="<?= APP_URL ?>/modules/profile/preferences.php"><i data-lucide="settings-2"></i> <?= e(__('Preferences')) ?></a>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>