<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.create');
$errors = [];
$old = ['property_id'=>0,'provider'=>'','policy_number'=>'','coverage_details'=>'','premium'=>'','start_date'=>date('Y-m-d'),'expiry_date'=>'','status'=>'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['property_id']      = (int)($_POST['property_id'] ?? 0);
    $old['provider']         = sanitize_string($_POST['provider'] ?? '', 150);
    $old['policy_number']    = sanitize_string($_POST['policy_number'] ?? '', 80);
    $old['coverage_details'] = sanitize_string($_POST['coverage_details'] ?? '', 2000) ?: null;
    $old['premium']          = $_POST['premium'] !== '' ? (float)$_POST['premium'] : null;
    $old['start_date']       = sanitize_string($_POST['start_date'] ?? '', 20);
    $old['expiry_date']      = sanitize_string($_POST['expiry_date'] ?? '', 20);
    $old['status']           = in_array($_POST['status'] ?? '', ['active','expired','cancelled'], true) ? $_POST['status'] : 'active';

    if (!$old['property_id'])         $errors['property_id'] = __('Property required.');
    if ($old['provider'] === '')      $errors['provider'] = __('Provider required.');
    if ($old['policy_number'] === '') $errors['policy_number'] = __('Policy number required.');
    if (!$old['expiry_date'])         $errors['expiry_date'] = __('Expiry required.');

    if (!$errors) {
        $id = db_insert('insurance_policies', array_merge($old, ['company_id' => (int)$USER['company_id']]));
        log_audit('insurance_policies','create','insurance_policies',$id,null,$old);
        flash_success(__('Policy created.'));
        redirect('modules/insurance/index.php');
    }
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('Add Insurance Policy');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Add Insurance Policy')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Property')) ?> *</label>
      <select name="property_id" class="form-select" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Provider')) ?> *</label>
      <input name="provider" class="form-control" value="<?= e($old['provider']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Policy Number')) ?> *</label>
      <input name="policy_number" class="form-control" value="<?= e($old['policy_number']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Premium')) ?></label>
      <input type="number" step="0.01" name="premium" class="form-control" value="<?= e((string)$old['premium']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['active','expired','cancelled'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $old['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Start Date')) ?></label>
      <input type="date" name="start_date" class="form-control" value="<?= e($old['start_date']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Expiry Date')) ?> *</label>
      <input type="date" name="expiry_date" class="form-control" value="<?= e($old['expiry_date']) ?>" required></div>
    <div class="col-12"><label class="form-label"><?= e(__('Coverage Details')) ?></label>
      <textarea name="coverage_details" class="form-control" rows="3"><?= e((string)$old['coverage_details']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/insurance/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Policy')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>