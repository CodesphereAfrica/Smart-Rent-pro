<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('tenants.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$t = db_one('SELECT * FROM tenants WHERE id = :id AND company_id = :c LIMIT 1',
            [':id' => $id, ':c' => (int)$USER['company_id']]);
if (!$t) { flash_error(__('Tenant not found.')); redirect('modules/tenants/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $t;

    foreach (['first_name','last_name','id_type','id_number','emergency_contact_name',
              'emergency_contact_phone','occupation','employer','address','notes'] as $k) {
        $t[$k] = sanitize_string($_POST[$k] ?? '', 150);
    }
    $t['phone']       = sanitize_phone($_POST['phone'] ?? '');
    $t['email']       = sanitize_email($_POST['email'] ?? '');
    $t['tenant_type'] = in_array($_POST['tenant_type'] ?? '', ['individual','corporate'], true) ? $_POST['tenant_type'] : 'individual';
    $t['status']      = in_array($_POST['status'] ?? '', ['active','former','blacklisted'], true) ? $_POST['status'] : 'active';

    if ($t['first_name'] === '') $errors['first_name'] = __('First name is required.');
    if ($t['last_name']  === '') $errors['last_name']  = __('Last name is required.');
    if (empty($t['phone']))      $errors['phone']      = __('Phone is required.');

    if (!empty($_FILES['profile_photo']['tmp_name'])) {
        $photo = store_upload($_FILES['profile_photo'], 'tenants', ['image/jpeg','image/png','image/webp']);
        if ($photo) $t['profile_photo'] = $photo;
    }

    if (!$errors) {
        db_update('tenants', [
            'first_name' => $t['first_name'],
            'last_name'  => $t['last_name'],
            'phone'      => $t['phone'],
            'email'      => $t['email'],
            'address'    => $t['address'] ?: null,
            'id_type'    => $t['id_type'] ?: null,
            'id_number'  => $t['id_number'] ?: null,
            'emergency_contact_name'  => $t['emergency_contact_name'] ?: null,
            'emergency_contact_phone' => $t['emergency_contact_phone'] ?: null,
            'occupation' => $t['occupation'] ?: null,
            'employer'   => $t['employer']   ?: null,
            'tenant_type'=> $t['tenant_type'],
            'profile_photo' => $t['profile_photo'],
            'notes'      => $t['notes'] ?: null,
            'status'     => $t['status'],
        ], 'id = :id', [':id' => $id]);

        log_audit('tenants', 'update', 'tenants', $id, $before, $t);
        flash_success(__('Tenant updated.'));
        redirect('modules/tenants/view.php?id=' . $id);
    }
}

$PAGE_TITLE = __('Edit Tenant');
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/tenants/index.php"><?= e(__('Tenants')) ?></a></li>
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/tenants/view.php?id=<?= (int)$id ?>"><?= e($t['first_name'].' '.$t['last_name']) ?></a></li>
  <li class="breadcrumb-item active"><?= e(__('Edit')) ?></li>
</ol></nav>
<h1 class="h4 mb-3"><?= e(__('Edit Tenant')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('First Name')) ?> *</label>
      <input name="first_name" class="form-control<?= isset($errors['first_name'])?' is-invalid':'' ?>" value="<?= e($t['first_name']) ?>" required>
      <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= e($errors['first_name']) ?></div><?php endif; ?></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Last Name')) ?> *</label>
      <input name="last_name" class="form-control<?= isset($errors['last_name'])?' is-invalid':'' ?>" value="<?= e($t['last_name']) ?>" required>
      <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= e($errors['last_name']) ?></div><?php endif; ?></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Type')) ?></label>
      <select name="tenant_type" class="form-select">
        <option value="individual" <?= $t['tenant_type']==='individual'?'selected':'' ?>><?= e(__('Individual')) ?></option>
        <option value="corporate"  <?= $t['tenant_type']==='corporate' ?'selected':'' ?>><?= e(__('Corporate')) ?></option>
      </select></div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Phone')) ?> *</label>
      <input name="phone" class="form-control<?= isset($errors['phone'])?' is-invalid':'' ?>" value="<?= e((string)$t['phone']) ?>" required>
      <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="email" class="form-control" value="<?= e((string)$t['email']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <option value="active"      <?= $t['status']==='active'?'selected':'' ?>><?= e(__('Active')) ?></option>
        <option value="former"      <?= $t['status']==='former'?'selected':'' ?>><?= e(__('Former')) ?></option>
        <option value="blacklisted" <?= $t['status']==='blacklisted'?'selected':'' ?>><?= e(__('Blacklisted')) ?></option>
      </select></div>

    <div class="col-md-6"><label class="form-label"><?= e(__('Address')) ?></label>
      <input name="address" class="form-control" value="<?= e((string)$t['address']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('ID Type')) ?></label>
      <input name="id_type" class="form-control" value="<?= e((string)$t['id_type']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('ID Number')) ?></label>
      <input name="id_number" class="form-control" value="<?= e((string)$t['id_number']) ?>"></div>

    <div class="col-md-6"><label class="form-label"><?= e(__('Emergency Contact Name')) ?></label>
      <input name="emergency_contact_name" class="form-control" value="<?= e((string)$t['emergency_contact_name']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Emergency Contact Phone')) ?></label>
      <input name="emergency_contact_phone" class="form-control" value="<?= e((string)$t['emergency_contact_phone']) ?>"></div>

    <div class="col-md-6"><label class="form-label"><?= e(__('Occupation')) ?></label>
      <input name="occupation" class="form-control" value="<?= e((string)$t['occupation']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Employer')) ?></label>
      <input name="employer" class="form-control" value="<?= e((string)$t['employer']) ?>"></div>

    <div class="col-md-6">
      <label class="form-label"><?= e(__('Replace Profile Photo')) ?></label>
      <input type="file" name="profile_photo" class="form-control" accept="image/*">
      <?php if (!empty($t['profile_photo'])): ?>
        <img src="<?= e(UPLOAD_URL . '/' . $t['profile_photo']) ?>" style="width:64px;height:64px;object-fit:cover;border-radius:50%;margin-top:8px">
      <?php endif; ?>
    </div>
    <div class="col-12"><label class="form-label"><?= e(__('Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="3"><?= e((string)$t['notes']) ?></textarea></div>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/tenants/view.php?id=<?= (int)$id ?>" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>