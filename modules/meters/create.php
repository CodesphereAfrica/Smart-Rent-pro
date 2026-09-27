<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.create');
$errors = []; $old = ['utility_id'=>(int)($_GET['utility_id'] ?? 0),'unit_id'=>0,'meter_number'=>'','meter_type'=>'manual','is_smart_meter'=>0,'installation_date'=>'','status'=>'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['utility_id']       = (int)($_POST['utility_id'] ?? 0);
    $old['unit_id']          = (int)($_POST['unit_id'] ?? 0) ?: null;
    $old['meter_number']     = sanitize_string($_POST['meter_number'] ?? '', 60);
    $old['meter_type']       = sanitize_string($_POST['meter_type'] ?? '', 40) ?: null;
    $old['is_smart_meter']   = !empty($_POST['is_smart_meter']) ? 1 : 0;
    $old['installation_date']= sanitize_string($_POST['installation_date'] ?? '', 20) ?: null;
    $old['status']           = in_array($_POST['status'] ?? '', ['active','inactive','faulty'], true) ? $_POST['status'] : 'active';

    if (!$old['utility_id']) $errors['utility_id'] = __('Utility required.');
    if ($old['meter_number'] === '') $errors['meter_number'] = __('Meter number required.');

    if (!$errors) {
        $id = db_insert('utility_meters', $old);
        log_audit('utility_meters','create','utility_meters',$id,null,$old);
        flash_success(__('Meter created.'));
        redirect('modules/meters/index.php');
    }
}
$utilities = db_all('SELECT ut.id, ut.utility_type, p.name AS property_name FROM utilities ut JOIN properties p ON p.id = ut.property_id WHERE ut.company_id = :c ORDER BY p.name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('Add Meter');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Add Meter')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Utility')) ?> *</label>
      <select name="utility_id" class="form-select" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($utilities as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)$old['utility_id']===(int)$u['id']?'selected':'' ?>><?= e($u['property_name']) ?> · <?= e(ucfirst($u['utility_type'])) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Meter Number')) ?> *</label>
      <input name="meter_number" class="form-control" value="<?= e($old['meter_number']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Meter Type')) ?></label>
      <input name="meter_type" class="form-control" value="<?= e((string)$old['meter_type']) ?>" placeholder="manual, smart…"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Installation Date')) ?></label>
      <input type="date" name="installation_date" class="form-control" value="<?= e((string)$old['installation_date']) ?>"></div>
    <div class="col-md-3"><div class="form-check mt-4">
      <input class="form-check-input" type="checkbox" name="is_smart_meter" id="smart" value="1" <?= $old['is_smart_meter']?'checked':'' ?>>
      <label class="form-check-label" for="smart"><?= e(__('Smart Meter (IoT-ready)')) ?></label>
    </div></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['active','inactive','faulty'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $old['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/meters/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>