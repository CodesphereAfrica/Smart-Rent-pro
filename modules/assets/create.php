<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.create');
$errors = [];
$old = [
    'name'=>'','property_id'=>0,'building_id'=>0,'unit_id'=>0,'category_id'=>0,
    'purchase_date'=>'','purchase_cost'=>'','warranty_expiry'=>'','serial_number'=>'',
    'location_notes'=>'','depreciation_method'=>'none','useful_life_years'=>'',
    'salvage_value'=>'0','replacement_date'=>'','status'=>'in_use',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['name']               = sanitize_string($_POST['name'] ?? '', 150);
    $old['property_id']        = (int)($_POST['property_id'] ?? 0);
    $old['building_id']        = (int)($_POST['building_id'] ?? 0) ?: null;
    $old['unit_id']            = (int)($_POST['unit_id'] ?? 0) ?: null;
    $old['category_id']        = (int)($_POST['category_id'] ?? 0) ?: null;
    $old['purchase_date']      = sanitize_string($_POST['purchase_date'] ?? '', 20) ?: null;
    $old['purchase_cost']      = $_POST['purchase_cost'] !== '' ? (float)$_POST['purchase_cost'] : null;
    $old['warranty_expiry']    = sanitize_string($_POST['warranty_expiry'] ?? '', 20) ?: null;
    $old['serial_number']      = sanitize_string($_POST['serial_number'] ?? '', 100) ?: null;
    $old['location_notes']     = sanitize_string($_POST['location_notes'] ?? '', 255) ?: null;
    $old['depreciation_method']= in_array($_POST['depreciation_method'] ?? '', ['straight_line','declining_balance','none'], true) ? $_POST['depreciation_method'] : 'none';
    $old['useful_life_years']  = (int)($_POST['useful_life_years'] ?? 0) ?: null;
    $old['salvage_value']      = (float)($_POST['salvage_value'] ?? 0);
    $old['replacement_date']   = sanitize_string($_POST['replacement_date'] ?? '', 20) ?: null;
    $old['status']             = in_array($_POST['status'] ?? '', ['in_use','under_repair','retired','disposed'], true) ? $_POST['status'] : 'in_use';

    if ($old['name'] === '') $errors['name'] = __('Name required.');
    if (!$old['property_id']) $errors['property_id'] = __('Property required.');

    if (!$errors) {
        $id = db_insert('assets', [
            'company_id' => (int)$USER['company_id'],
            'property_id'=>$old['property_id'],'building_id'=>$old['building_id'],'unit_id'=>$old['unit_id'],
            'category_id'=>$old['category_id'],'name'=>$old['name'],'purchase_date'=>$old['purchase_date'],
            'purchase_cost'=>$old['purchase_cost'],'warranty_expiry'=>$old['warranty_expiry'],
            'serial_number'=>$old['serial_number'],'location_notes'=>$old['location_notes'],
            'depreciation_method'=>$old['depreciation_method'],'useful_life_years'=>$old['useful_life_years'],
            'salvage_value'=>$old['salvage_value'],'replacement_date'=>$old['replacement_date'],
            'status'=>$old['status'],
        ]);
        log_audit('assets','create','assets',$id,null,$old);
        flash_success(__('Asset created.'));
        redirect('modules/assets/edit.php?id=' . $id);
    }
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name', [':c'=>(int)$USER['company_id']]);
$categories = db_all('SELECT id, name FROM asset_categories WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('Add Asset');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Add Asset')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Asset Name')) ?> *</label>
      <input name="name" class="form-control<?= isset($errors['name'])?' is-invalid':'' ?>" value="<?= e($old['name']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Property')) ?> *</label>
      <select name="property_id" class="form-select">
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Category')) ?></label>
      <select name="category_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$old['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-md-3"><label class="form-label"><?= e(__('Purchase Date')) ?></label>
      <input type="date" name="purchase_date" class="form-control" value="<?= e((string)$old['purchase_date']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Purchase Cost')) ?></label>
      <input type="number" step="0.01" name="purchase_cost" class="form-control" value="<?= e((string)$old['purchase_cost']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Warranty Expiry')) ?></label>
      <input type="date" name="warranty_expiry" class="form-control" value="<?= e((string)$old['warranty_expiry']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Serial Number')) ?></label>
      <input name="serial_number" class="form-control" value="<?= e((string)$old['serial_number']) ?>"></div>

    <div class="col-md-6"><label class="form-label"><?= e(__('Location Notes')) ?></label>
      <input name="location_notes" class="form-control" value="<?= e((string)$old['location_notes']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['in_use','under_repair','retired','disposed'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $old['status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Replacement Date')) ?></label>
      <input type="date" name="replacement_date" class="form-control" value="<?= e((string)$old['replacement_date']) ?>"></div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Depreciation Method')) ?></label>
      <select name="depreciation_method" class="form-select">
        <option value="none" <?= $old['depreciation_method']==='none'?'selected':'' ?>><?= e(__('None')) ?></option>
        <option value="straight_line" <?= $old['depreciation_method']==='straight_line'?'selected':'' ?>><?= e(__('Straight Line')) ?></option>
        <option value="declining_balance" <?= $old['depreciation_method']==='declining_balance'?'selected':'' ?>><?= e(__('Declining Balance')) ?></option>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Useful Life (years)')) ?></label>
      <input type="number" name="useful_life_years" class="form-control" value="<?= e((string)$old['useful_life_years']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Salvage Value')) ?></label>
      <input type="number" step="0.01" name="salvage_value" class="form-control" value="<?= e((string)$old['salvage_value']) ?>"></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/assets/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Asset')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>