<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$u = db_one('SELECT ut.* FROM utilities ut WHERE ut.id = :id AND ut.company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$u) { flash_error(__('Not found.')); redirect('modules/utilities/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf(); $before = $u;
    $u['utility_type']   = in_array($_POST['utility_type'] ?? '', ['electricity','water','gas','internet','waste','other'], true) ? $_POST['utility_type'] : $u['utility_type'];
    $u['billing_model']  = in_array($_POST['billing_model'] ?? '', ['landlord_paid','tenant_paid_direct','shared','included_in_rent','meter_based'], true) ? $_POST['billing_model'] : $u['billing_model'];
    $u['rate_per_unit']  = $_POST['rate_per_unit'] !== '' ? (float)$_POST['rate_per_unit'] : null;
    $u['fixed_amount']   = $_POST['fixed_amount'] !== '' ? (float)$_POST['fixed_amount'] : null;
    $u['is_active']      = !empty($_POST['is_active']) ? 1 : 0;

    db_update('utilities', [
        'utility_type'=>$u['utility_type'],'billing_model'=>$u['billing_model'],
        'rate_per_unit'=>$u['rate_per_unit'],'fixed_amount'=>$u['fixed_amount'],
        'is_active'=>$u['is_active'],
    ], 'id = :id', [':id' => $id]);
    log_audit('utilities','update','utilities',$id,$before,$u);
    flash_success(__('Utility updated.'));
    redirect('modules/utilities/index.php');
}
$PAGE_TITLE = __('Edit Utility');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Utility')) ?></h1>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('Type')) ?></label>
      <select name="utility_type" class="form-select">
        <?php foreach (['electricity','water','gas','internet','waste','other'] as $t): ?>
          <option value="<?= e($t) ?>" <?= $u['utility_type']===$t?'selected':'' ?>><?= e(ucfirst($t)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Billing Model')) ?></label>
      <select name="billing_model" class="form-select">
        <?php foreach (['landlord_paid','tenant_paid_direct','shared','included_in_rent','meter_based'] as $b): ?>
          <option value="<?= e($b) ?>" <?= $u['billing_model']===$b?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$b))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-2"><label class="form-label"><?= e(__('Rate')) ?></label>
      <input type="number" step="0.0001" name="rate_per_unit" class="form-control" value="<?= e((string)$u['rate_per_unit']) ?>"></div>
    <div class="col-md-2"><label class="form-label"><?= e(__('Fixed')) ?></label>
      <input type="number" step="0.01" name="fixed_amount" class="form-control" value="<?= e((string)$u['fixed_amount']) ?>"></div>
    <div class="col-md-3"><div class="form-check mt-4">
      <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" <?= $u['is_active']?'checked':'' ?>>
      <label class="form-check-label" for="isActive"><?= e(__('Active')) ?></label>
    </div></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/utilities/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>