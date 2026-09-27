<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.create');
$errors = []; $old = ['property_id'=>0,'unit_id'=>0,'utility_type'=>'electricity','billing_model'=>'tenant_paid_direct','rate_per_unit'=>'','fixed_amount'=>'','is_active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['property_id']    = (int)($_POST['property_id'] ?? 0);
    $old['unit_id']        = (int)($_POST['unit_id'] ?? 0) ?: null;
    $old['utility_type']   = in_array($_POST['utility_type'] ?? '', ['electricity','water','gas','internet','waste','other'], true) ? $_POST['utility_type'] : 'electricity';
    $old['billing_model']  = in_array($_POST['billing_model'] ?? '', ['landlord_paid','tenant_paid_direct','shared','included_in_rent','meter_based'], true) ? $_POST['billing_model'] : 'tenant_paid_direct';
    $old['rate_per_unit']  = $_POST['rate_per_unit'] !== '' ? (float)$_POST['rate_per_unit'] : null;
    $old['fixed_amount']   = $_POST['fixed_amount'] !== '' ? (float)$_POST['fixed_amount'] : null;
    $old['is_active']      = !empty($_POST['is_active']) ? 1 : 0;

    if (!$old['property_id']) $errors['property_id'] = __('Property required.');

    if (!$errors) {
        $id = db_insert('utilities', [
            'company_id' => (int)$USER['company_id'],
            'property_id' => $old['property_id'],
            'unit_id' => $old['unit_id'],
            'utility_type' => $old['utility_type'],
            'billing_model' => $old['billing_model'],
            'rate_per_unit' => $old['rate_per_unit'],
            'fixed_amount' => $old['fixed_amount'],
            'is_active' => $old['is_active'],
        ]);
        log_audit('utilities','create','utilities',$id,null,$old);
        flash_success(__('Utility created.'));
        redirect('modules/utilities/index.php');
    }
}
$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('Add Utility');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Add Utility')) ?></h1>
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
    <div class="col-md-3"><label class="form-label"><?= e(__('Type')) ?></label>
      <select name="utility_type" class="form-select">
        <?php foreach (['electricity','water','gas','internet','waste','other'] as $t): ?>
          <option value="<?= e($t) ?>" <?= $old['utility_type']===$t?'selected':'' ?>><?= e(ucfirst($t)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Billing Model')) ?></label>
      <select name="billing_model" class="form-select">
        <?php foreach (['landlord_paid','tenant_paid_direct','shared','included_in_rent','meter_based'] as $b): ?>
          <option value="<?= e($b) ?>" <?= $old['billing_model']===$b?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$b))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Rate per Unit')) ?></label>
      <input type="number" step="0.0001" name="rate_per_unit" class="form-control" value="<?= e((string)$old['rate_per_unit']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Fixed Amount')) ?></label>
      <input type="number" step="0.01" name="fixed_amount" class="form-control" value="<?= e((string)$old['fixed_amount']) ?>"></div>
    <div class="col-md-3"><div class="form-check mt-4">
      <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" <?= $old['is_active']?'checked':'' ?>>
      <label class="form-check-label" for="isActive"><?= e(__('Active')) ?></label>
    </div></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/utilities/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>    