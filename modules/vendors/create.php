<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('vendors.create');
$errors = [];
$old = [
    'name'=>'', 'vendor_type'=>'company', 'category'=>'', 'phone'=>'', 'email'=>'',
    'address'=>'', 'tax_id'=>'', 'bank_name'=>'', 'bank_account'=>'', 'notes'=>'', 'status'=>'active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach (['name','category','address','tax_id','bank_name','bank_account','notes'] as $k) {
        $old[$k] = sanitize_string($_POST[$k] ?? '', 200);
    }
    $old['phone'] = sanitize_phone($_POST['phone'] ?? '');
    $old['email'] = sanitize_email($_POST['email'] ?? '');
    $old['vendor_type'] = in_array($_POST['vendor_type'] ?? '', ['individual','company'], true) ? $_POST['vendor_type'] : 'company';
    $old['status'] = in_array($_POST['status'] ?? '', ['active','inactive'], true) ? $_POST['status'] : 'active';

    if ($old['name'] === '')     $errors['name'] = __('Name is required.');
    if ($old['category'] === '') $errors['category'] = __('Category is required.');

    if (!$errors) {
        $id = db_insert('vendors', [
            'company_id' => (int)$USER['company_id'],
            'name'       => $old['name'],
            'vendor_type'=> $old['vendor_type'],
            'category'   => $old['category'],
            'phone'      => $old['phone'],
            'email'      => $old['email'],
            'address'    => $old['address'] ?: null,
            'tax_id'     => $old['tax_id'] ?: null,
            'bank_name'  => $old['bank_name'] ?: null,
            'bank_account' => $old['bank_account'] ?: null,
            'notes'      => $old['notes'] ?: null,
            'status'     => $old['status'],
        ]);
        log_audit('vendors','create','vendors',$id,null,$old);
        flash_success(__('Vendor created.'));
        redirect('modules/vendors/view.php?id=' . $id);
    }
}

$PAGE_TITLE = __('Add Vendor');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Add Vendor')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Vendor Name')) ?> *</label>
      <input name="name" class="form-control<?= isset($errors['name'])?' is-invalid':'' ?>" value="<?= e($old['name']) ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Type')) ?></label>
      <select name="vendor_type" class="form-select">
        <option value="company"    <?= $old['vendor_type']==='company'?'selected':'' ?>><?= e(__('Company')) ?></option>
        <option value="individual" <?= $old['vendor_type']==='individual'?'selected':'' ?>><?= e(__('Individual')) ?></option>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Category')) ?> *</label>
      <input name="category" class="form-control<?= isset($errors['category'])?' is-invalid':'' ?>" value="<?= e($old['category']) ?>" placeholder="plumber, electrician, cleaner…" required>
      <?php if (isset($errors['category'])): ?><div class="invalid-feedback"><?= e($errors['category']) ?></div><?php endif; ?></div>

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
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <option value="active"   <?= $old['status']==='active'?'selected':'' ?>><?= e(__('Active')) ?></option>
        <option value="inactive" <?= $old['status']==='inactive'?'selected':'' ?>><?= e(__('Inactive')) ?></option>
      </select></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/vendors/index.php" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Vendor')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>