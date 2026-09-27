<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$c = db_one('SELECT * FROM contracts WHERE id = :id AND company_id = :co LIMIT 1', [':id'=>$id, ':co'=>(int)$USER['company_id']]);
if (!$c) { flash_error(__('Not found.')); redirect('modules/contracts/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $c;
    $c['property_id']       = (int)($_POST['property_id'] ?? 0) ?: null;
    $c['vendor_id']         = (int)($_POST['vendor_id'] ?? 0) ?: null;
    $c['contract_type']     = sanitize_string($_POST['contract_type'] ?? '', 80);
    $c['title']             = sanitize_string($_POST['title'] ?? '', 200);
    $c['start_date']        = sanitize_string($_POST['start_date'] ?? '', 20);
    $c['end_date']          = sanitize_string($_POST['end_date'] ?? '', 20) ?: null;
    $c['amount']            = $_POST['amount'] !== '' ? (float)$_POST['amount'] : null;
    $c['renewal_terms']     = sanitize_string($_POST['renewal_terms'] ?? '', 255) ?: null;
    $c['responsible_party'] = sanitize_string($_POST['responsible_party'] ?? '', 150) ?: null;
    $c['status']            = in_array($_POST['status'] ?? '', ['active','expired','terminated'], true) ? $_POST['status'] : $c['status'];

    if ($c['title'] === '') $errors['title'] = __('Title required.');

    if (!$errors) {
        db_update('contracts', [
            'property_id'=>$c['property_id'],'vendor_id'=>$c['vendor_id'],'contract_type'=>$c['contract_type'],
            'title'=>$c['title'],'start_date'=>$c['start_date'],'end_date'=>$c['end_date'],
            'amount'=>$c['amount'],'renewal_terms'=>$c['renewal_terms'],
            'responsible_party'=>$c['responsible_party'],'status'=>$c['status'],
        ], 'id = :id', [':id' => $id]);

        log_audit('contracts','update','contracts',$id,$before,$c);
        flash_success(__('Contract updated.'));
        redirect('modules/contracts/index.php');
    }
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$vendors    = db_all('SELECT id, name FROM vendors WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('Edit Contract');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Contract')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-8"><label class="form-label"><?= e(__('Title')) ?></label>
      <input name="title" class="form-control" value="<?= e($c['title']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Type')) ?></label>
      <input name="contract_type" class="form-control" value="<?= e($c['contract_type']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Property')) ?></label>
      <select name="property_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$c['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Vendor')) ?></label>
      <select name="vendor_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($vendors as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= (int)$c['vendor_id']===(int)$v['id']?'selected':'' ?>><?= e($v['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Start')) ?></label>
      <input type="date" name="start_date" class="form-control" value="<?= e($c['start_date']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('End')) ?></label>
      <input type="date" name="end_date" class="form-control" value="<?= e((string)$c['end_date']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Amount')) ?></label>
      <input type="number" step="0.01" name="amount" class="form-control" value="<?= e((string)$c['amount']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['active','expired','terminated'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $c['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Responsible Party')) ?></label>
      <input name="responsible_party" class="form-control" value="<?= e((string)$c['responsible_party']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Renewal Terms')) ?></label>
      <input name="renewal_terms" class="form-control" value="<?= e((string)$c['renewal_terms']) ?>"></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/contracts/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>