<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
$USER = require_role('Tenant');

$tenant = db_one('SELECT * FROM tenants WHERE user_id = :u LIMIT 1', [':u'=>(int)$USER['id']]);
if (!$tenant) { redirect('modules/dashboard/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $tenant;
    $tenant['phone'] = sanitize_phone($_POST['phone'] ?? '');
    $tenant['email'] = sanitize_email($_POST['email'] ?? '');
    $tenant['address'] = sanitize_string($_POST['address'] ?? '', 255) ?: null;
    $tenant['emergency_contact_name']  = sanitize_string($_POST['emergency_contact_name'] ?? '', 120) ?: null;
    $tenant['emergency_contact_phone'] = sanitize_phone($_POST['emergency_contact_phone'] ?? '');
    $tenant['occupation'] = sanitize_string($_POST['occupation'] ?? '', 100) ?: null;
    $tenant['employer']   = sanitize_string($_POST['employer'] ?? '', 150) ?: null;

    if (empty($tenant['phone'])) $errors['phone'] = __('Phone required.');

    if (!$errors) {
        db_update('tenants', [
            'phone'=>$tenant['phone'],'email'=>$tenant['email'],'address'=>$tenant['address'],
            'emergency_contact_name'=>$tenant['emergency_contact_name'],
            'emergency_contact_phone'=>$tenant['emergency_contact_phone'],
            'occupation'=>$tenant['occupation'],'employer'=>$tenant['employer'],
        ], 'id = :id', [':id' => (int)$tenant['id']]);

        log_audit('tenants', 'self_update', 'tenants', (int)$tenant['id'], $before, $tenant);
        flash_success(__('Profile updated.'));
        redirect('modules/portal/tenant/profile.php');
    }
}

$PAGE_TITLE = __('My Profile');
require __DIR__ . '/../../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('My Profile')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Phone')) ?></label>
      <input name="phone" class="form-control" value="<?= e((string)$tenant['phone']) ?>" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="email" class="form-control" value="<?= e((string)$tenant['email']) ?>"></div>
    <div class="col-12"><label class="form-label"><?= e(__('Address')) ?></label>
      <input name="address" class="form-control" value="<?= e((string)$tenant['address']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Emergency Contact Name')) ?></label>
      <input name="emergency_contact_name" class="form-control" value="<?= e((string)$tenant['emergency_contact_name']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Emergency Contact Phone')) ?></label>
      <input name="emergency_contact_phone" class="form-control" value="<?= e((string)$tenant['emergency_contact_phone']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Occupation')) ?></label>
      <input name="occupation" class="form-control" value="<?= e((string)$tenant['occupation']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Employer')) ?></label>
      <input name="employer" class="form-control" value="<?= e((string)$tenant['employer']) ?>"></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>