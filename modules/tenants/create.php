<?php
// modules/tenants/create.php — self-contained: form + validation + insert.
// Includes live client-side preview of the uploaded profile photo.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('tenants.create');

$errors = [];
$old = [
    'first_name' => '', 'last_name' => '', 'phone' => '', 'email' => '',
    'address' => '', 'id_type' => '', 'id_number' => '',
    'emergency_contact_name' => '', 'emergency_contact_phone' => '',
    'occupation' => '', 'employer' => '', 'tenant_type' => 'individual',
    'notes' => '', 'status' => 'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    foreach (['first_name','last_name','id_type','id_number','emergency_contact_name',
              'emergency_contact_phone','occupation','employer','address','notes'] as $k) {
        $old[$k] = sanitize_string($_POST[$k] ?? '', 150);
    }
    $old['phone']       = sanitize_phone($_POST['phone'] ?? '');
    $old['email']       = sanitize_email($_POST['email'] ?? '');
    $old['tenant_type'] = in_array($_POST['tenant_type'] ?? '', ['individual','corporate'], true) ? $_POST['tenant_type'] : 'individual';
    $old['status']      = in_array($_POST['status'] ?? '', ['active','former','blacklisted'], true) ? $_POST['status'] : 'active';

    if ($old['first_name'] === '') $errors['first_name'] = __('First name is required.');
    if ($old['last_name']  === '') $errors['last_name']  = __('Last name is required.');
    if (empty($old['phone']))      $errors['phone']      = __('Phone is required.');
    if (!empty($_POST['email']) && !$old['email']) $errors['email'] = __('Email is not valid.');

    // Profile photo (optional) — must be a valid image, safely stored under /storage/uploads/tenants/.
    $photo = null;
    if (!empty($_FILES['profile_photo']['tmp_name'])) {
        $photo = store_upload(
            $_FILES['profile_photo'],
            'tenants',
            ['image/jpeg','image/png','image/webp']
        );
        if ($photo === null) {
            $errors['profile_photo'] = __('Upload failed — please use a JPG, PNG or WebP image.');
        }
    }

    if (!$errors) {
        $id = db_insert('tenants', [
            'company_id' => (int)$USER['company_id'],
            'first_name' => $old['first_name'],
            'last_name'  => $old['last_name'],
            'phone'      => $old['phone'],
            'email'      => $old['email'],
            'address'    => $old['address'] ?: null,
            'id_type'    => $old['id_type'] ?: null,
            'id_number'  => $old['id_number'] ?: null,
            'emergency_contact_name'  => $old['emergency_contact_name']  ?: null,
            'emergency_contact_phone' => $old['emergency_contact_phone'] ?: null,
            'occupation' => $old['occupation'] ?: null,
            'employer'   => $old['employer']   ?: null,
            'tenant_type'=> $old['tenant_type'],
            'profile_photo' => $photo,
            'notes'      => $old['notes'] ?: null,
            'status'     => $old['status'],
        ]);

        log_audit('tenants', 'create', 'tenants', $id, null, $old);
        flash_success(__('Tenant created.'));
        redirect('modules/tenants/view.php?id=' . $id);
    }
}

$PAGE_TITLE = __('Add Tenant');
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/tenants/index.php"><?= e(__('Tenants')) ?></a></li>
  <li class="breadcrumb-item active"><?= e(__('New')) ?></li>
</ol></nav>
<h1 class="h4 mb-3"><?= e(__('Add Tenant')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('First Name')) ?> *</label>
      <input name="first_name" class="form-control<?= isset($errors['first_name'])?' is-invalid':'' ?>" value="<?= e($old['first_name']) ?>" required>
      <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= e($errors['first_name']) ?></div><?php endif; ?></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Last Name')) ?> *</label>
      <input name="last_name" class="form-control<?= isset($errors['last_name'])?' is-invalid':'' ?>" value="<?= e($old['last_name']) ?>" required>
      <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= e($errors['last_name']) ?></div><?php endif; ?></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Type')) ?></label>
      <select name="tenant_type" class="form-select">
        <option value="individual" <?= $old['tenant_type']==='individual'?'selected':'' ?>><?= e(__('Individual')) ?></option>
        <option value="corporate"  <?= $old['tenant_type']==='corporate' ?'selected':'' ?>><?= e(__('Corporate')) ?></option>
      </select></div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Phone')) ?> *</label>
      <input name="phone" class="form-control<?= isset($errors['phone'])?' is-invalid':'' ?>" value="<?= e((string)$old['phone']) ?>" required>
      <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="email" class="form-control<?= isset($errors['email'])?' is-invalid':'' ?>" value="<?= e((string)$old['email']) ?>">
      <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <option value="active" <?= $old['status']==='active'?'selected':'' ?>><?= e(__('Active')) ?></option>
        <option value="former" <?= $old['status']==='former'?'selected':'' ?>><?= e(__('Former')) ?></option>
        <option value="blacklisted" <?= $old['status']==='blacklisted'?'selected':'' ?>><?= e(__('Blacklisted')) ?></option>
      </select></div>

    <div class="col-md-6"><label class="form-label"><?= e(__('Physical Address')) ?></label>
      <input name="address" class="form-control" value="<?= e($old['address']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('ID Type')) ?></label>
      <input name="id_type" class="form-control" value="<?= e($old['id_type']) ?>" placeholder="National ID, Passport…"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('ID Number')) ?></label>
      <input name="id_number" class="form-control" value="<?= e($old['id_number']) ?>"></div>

    <div class="col-md-6"><label class="form-label"><?= e(__('Emergency Contact Name')) ?></label>
      <input name="emergency_contact_name" class="form-control" value="<?= e($old['emergency_contact_name']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Emergency Contact Phone')) ?></label>
      <input name="emergency_contact_phone" class="form-control" value="<?= e($old['emergency_contact_phone']) ?>"></div>

    <div class="col-md-6"><label class="form-label"><?= e(__('Occupation')) ?></label>
      <input name="occupation" class="form-control" value="<?= e($old['occupation']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Employer')) ?></label>
      <input name="employer" class="form-control" value="<?= e($old['employer']) ?>"></div>

    <div class="col-12">
      <label class="form-label"><?= e(__('Profile Photo')) ?></label>
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <div id="srpPhotoPreview" class="srp-avatar-img srp-avatar-lg" style="background: var(--srp-surface-2);">
          <i data-lucide="user" style="width:22px;height:22px;color:var(--srp-muted)"></i>
        </div>
        <div class="flex-grow-1" style="min-width: 220px;">
          <input type="file" id="srpPhotoInput" name="profile_photo"
                 class="form-control<?= isset($errors['profile_photo'])?' is-invalid':'' ?>"
                 accept="image/jpeg,image/png,image/webp">
          <div class="form-text">
            <?= e(__('Optional. JPG, PNG or WebP, up to 5 MB. Shown wherever this tenant appears.')) ?>
          </div>
          <?php if (isset($errors['profile_photo'])): ?>
            <div class="invalid-feedback d-block"><?= e($errors['profile_photo']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12"><label class="form-label"><?= e(__('Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="3"><?= e($old['notes']) ?></textarea></div>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/tenants/index.php" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Tenant')) ?></button>
  </div>
</form>

<script>
(function () {
  'use strict';
  const input   = document.getElementById('srpPhotoInput');
  const preview = document.getElementById('srpPhotoPreview');
  if (!input || !preview) return;

  // Replace the placeholder icon with a live preview when the user picks a file.
  input.addEventListener('change', function () {
    const file = this.files && this.files[0];
    if (!file) return;

    // Validate quickly on the client (server still re-validates).
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
      srpToast('error', 'Please choose a JPG, PNG or WebP image.');
      this.value = '';
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      srpToast('error', 'Image must be 5 MB or smaller.');
      this.value = '';
      return;
    }

    const url = URL.createObjectURL(file);
    preview.innerHTML = '<img src="' + url + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
    // Revoke the object URL once the image has loaded to avoid a memory leak.
    preview.querySelector('img').addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
  });
})();
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>