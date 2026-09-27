<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('vendors.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$v = db_one('SELECT * FROM vendors WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$v) { flash_error(__('Vendor not found.')); redirect('modules/vendors/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $v;

    foreach (['name','category','address','tax_id','bank_name','bank_account','notes'] as $k) {
        $v[$k] = sanitize_string($_POST[$k] ?? '', 200);
    }
    $v['phone'] = sanitize_phone($_POST['phone'] ?? '');
    $v['email'] = sanitize_email($_POST['email'] ?? '');
    $v['vendor_type'] = in_array($_POST['vendor_type'] ?? '', ['individual','company'], true) ? $_POST['vendor_type'] : 'company';
    $v['status'] = in_array($_POST['status'] ?? '', ['active','inactive'], true) ? $_POST['status'] : 'active';

    if ($v['name'] === '')     $errors['name'] = __('Name is required.');
    if ($v['category'] === '') $errors['category'] = __('Category is required.');

    if (!$errors) {
        db_update('vendors', [
            'name'=>$v['name'], 'vendor_type'=>$v['vendor_type'], 'category'=>$v['category'],
            'phone'=>$v['phone'], 'email'=>$v['email'], 'address'=>$v['address'] ?: null,
            'tax_id'=>$v['tax_id'] ?: null, 'bank_name'=>$v['bank_name'] ?: null,
            'bank_account'=>$v['bank_account'] ?: null, 'notes'=>$v['notes'] ?: null, 'status'=>$v['status'],
        ], 'id = :id', [':id' => $id]);
        log_audit('vendors','update','vendors',$id,$before,$v);
        flash_success(__('Vendor updated.'));
        redirect('modules/vendors/view.php?id=' . $id);
    }
}

$PAGE_TITLE = __('Edit Vendor');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Vendor')) ?> · <?= e($v['name']) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Vendor Name')) ?> *</label>
      <input name="name" class="form-control<?= isset($errors['name'])?' is-invalid':'' ?>" value="<?= e($v['name']) ?>" required>
      <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Type')) ?></label>
      <select name="vendor_type" class="form-select">
        <option value="company"    <?= $v['vendor_type']==='company'?'selected':'' ?>><?= e(__('Company')) ?></option>
        <option value="individual" <?= $v['vendor_type']==='individual'?'selected':'' ?>><?= e(__('Individual')) ?></option>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Category')) ?> *</label>
      <input name="category" class="form-control<?= isset($errors['category'])?' is-invalid':'' ?>" value="<?= e($v['category']) ?>" required>
      <?php if (isset($errors['category'])): ?><div class="invalid-feedback"><?= e($errors['category']) ?></div><?php endif; ?></div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Phone')) ?></label>
      <input name="phone" class="form-control" value="<?= e((string)$v['phone']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="email" class="form-control" value="<?= e((string)$v['email']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Tax ID')) ?></label>
      <input name="tax_id" class="form-control" value="<?= e((string)$v['tax_id']) ?>"></div>

    <div class="col-md-6"><label class="form-label"><?= e(__('Address')) ?></label>
      <input name="address" class="form-control" value="<?= e((string)$v['address']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Bank Name')) ?></label>
      <input name="bank_name" class="form-control" value="<?= e((string)$v['bank_name']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Bank Account')) ?></label>
      <input name="bank_account" class="form-control" value="<?= e((string)$v['bank_account']) ?>"></div>

    <div class="col-12"><label class="form-label"><?= e(__('Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="2"><?= e((string)$v['notes']) ?></textarea></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <option value="active"   <?= $v['status']==='active'?'selected':'' ?>><?= e(__('Active')) ?></option>
        <option value="inactive" <?= $v['status']==='inactive'?'selected':'' ?>><?= e(__('Inactive')) ?></option>
      </select></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/vendors/view.php?id=<?= (int)$id ?>" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>