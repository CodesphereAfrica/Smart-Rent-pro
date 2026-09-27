<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.create');
$errors = [];
$old = ['property_id'=>0,'vendor_id'=>0,'contract_type'=>'','title'=>'','start_date'=>date('Y-m-d'),
        'end_date'=>'','amount'=>'','renewal_terms'=>'','responsible_party'=>'','status'=>'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['property_id']      = (int)($_POST['property_id'] ?? 0) ?: null;
    $old['vendor_id']        = (int)($_POST['vendor_id'] ?? 0) ?: null;
    $old['contract_type']    = sanitize_string($_POST['contract_type'] ?? '', 80);
    $old['title']            = sanitize_string($_POST['title'] ?? '', 200);
    $old['start_date']       = sanitize_string($_POST['start_date'] ?? '', 20);
    $old['end_date']         = sanitize_string($_POST['end_date'] ?? '', 20) ?: null;
    $old['amount']           = $_POST['amount'] !== '' ? (float)$_POST['amount'] : null;
    $old['renewal_terms']    = sanitize_string($_POST['renewal_terms'] ?? '', 255) ?: null;
    $old['responsible_party']= sanitize_string($_POST['responsible_party'] ?? '', 150) ?: null;
    $old['status']           = in_array($_POST['status'] ?? '', ['active','expired','terminated'], true) ? $_POST['status'] : 'active';

    if ($old['title'] === '')         $errors['title'] = __('Title required.');
    if ($old['contract_type'] === '') $errors['contract_type'] = __('Type required.');
    if ($old['start_date'] === '')    $errors['start_date'] = __('Start date required.');

    if (!$errors) {
        $id = db_insert('contracts', array_merge($old, ['company_id' => (int)$USER['company_id']]));
        log_audit('contracts','create','contracts',$id,null,$old);
        flash_success(__('Contract created.'));
        redirect('modules/contracts/index.php');
    }
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$vendors    = db_all('SELECT id, name FROM vendors WHERE company_id = :c AND status = "active" ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('New Contract');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('New Contract')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-8"><label class="form-label"><?= e(__('Title')) ?> *</label>
      <input name="title" class="form-control" value="<?= e($old['title']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Type')) ?> *</label>
      <input name="contract_type" class="form-control" value="<?= e($old['contract_type']) ?>" placeholder="security, cleaning, internet…" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Property')) ?></label>
      <select name="property_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Vendor')) ?></label>
      <select name="vendor_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($vendors as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= (int)$old['vendor_id']===(int)$v['id']?'selected':'' ?>><?= e($v['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Start Date')) ?> *</label>
      <input type="date" name="start_date" class="form-control" value="<?= e($old['start_date']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('End Date')) ?></label>
      <input type="date" name="end_date" class="form-control" value="<?= e((string)$old['end_date']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Amount')) ?></label>
      <input type="number" step="0.01" name="amount" class="form-control" value="<?= e((string)$old['amount']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['active','expired','terminated'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $old['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Responsible Party')) ?></label>
      <input name="responsible_party" class="form-control" value="<?= e((string)$old['responsible_party']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Renewal Terms')) ?></label>
      <input name="renewal_terms" class="form-control" value="<?= e((string)$old['renewal_terms']) ?>"></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/contracts/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Contract')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>