<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('owners.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$o = db_one('SELECT * FROM owners WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$o) { flash_error(__('Owner not found.')); redirect('modules/owners/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $o;

    foreach (['full_name','address','tax_id','bank_name','bank_account','notes'] as $k) {
        $o[$k] = sanitize_string($_POST[$k] ?? '', 200);
    }
    $o['phone']      = sanitize_phone($_POST['phone'] ?? '');
    $o['email']      = sanitize_email($_POST['email'] ?? '');
    $o['owner_type'] = in_array($_POST['owner_type'] ?? '', ['individual','company'], true) ? $_POST['owner_type'] : 'individual';
    $o['status']     = in_array($_POST['status'] ?? '', ['active','inactive'], true) ? $_POST['status'] : 'active';

    if ($o['full_name'] === '') $errors['full_name'] = __('Name is required.');

    if (!$errors) {
        db_update('owners', [
            'full_name' => $o['full_name'],
            'owner_type'=> $o['owner_type'],
            'phone'     => $o['phone'],
            'email'     => $o['email'],
            'address'   => $o['address'] ?: null,
            'tax_id'    => $o['tax_id'] ?: null,
            'bank_name' => $o['bank_name'] ?: null,
            'bank_account' => $o['bank_account'] ?: null,
            'notes'     => $o['notes'] ?: null,
            'status'    => $o['status'],
        ], 'id = :id', [':id' => $id]);
        log_audit('owners','update','owners',$id,$before,$o);
        flash_success(__('Owner updated.'));
        redirect('modules/owners/view.php?id=' . $id);
    }
}

$PAGE_TITLE = __('Edit Owner');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Owner')) ?> · <?= e($o['full_name']) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Full Name')) ?> *</label>
      <input name="full_name" class="form-control<?= isset($errors['full_name'])?' is-invalid':'' ?>" value="<?= e($o['full_name']) ?>" required>
      <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= e($errors['full_name']) ?></div><?php endif; ?></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Type')) ?></label>
      <select name="owner_type" class="form-select">
        <option value="individual" <?= $o['owner_type']==='individual'?'selected':'' ?>><?= e(__('Individual')) ?></option>
        <option value="company"    <?= $o['owner_type']==='company'?'selected':'' ?>><?= e(__('Company')) ?></option>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <option value="active"   <?= $o['status']==='active'?'selected':'' ?>><?= e(__('Active')) ?></option>
        <option value="inactive" <?= $o['status']==='inactive'?'selected':'' ?>><?= e(__('Inactive')) ?></option>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Phone')) ?></label>
      <input name="phone" class="form-control" value="<?= e((string)$o['phone']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="email" class="form-control" value="<?= e((string)$o['email']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Tax ID')) ?></label>
      <input name="tax_id" class="form-control" value="<?= e((string)$o['tax_id']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Address')) ?></label>
      <input name="address" class="form-control" value="<?= e((string)$o['address']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Bank Name')) ?></label>
      <input name="bank_name" class="form-control" value="<?= e((string)$o['bank_name']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Bank Account')) ?></label>
      <input name="bank_account" class="form-control" value="<?= e((string)$o['bank_account']) ?>"></div>
    <div class="col-12"><label class="form-label"><?= e(__('Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="2"><?= e((string)$o['notes']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/owners/view.php?id=<?= (int)$id ?>" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>