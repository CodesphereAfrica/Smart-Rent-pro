<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$a = db_one('SELECT * FROM assets WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$a) { flash_error(__('Not found.')); redirect('modules/assets/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $a;
    foreach (['name','serial_number','location_notes'] as $k) $a[$k] = sanitize_string($_POST[$k] ?? '', 200);
    $a['property_id'] = (int)($_POST['property_id'] ?? 0);
    $a['category_id'] = (int)($_POST['category_id'] ?? 0) ?: null;
    $a['purchase_date'] = sanitize_string($_POST['purchase_date'] ?? '', 20) ?: null;
    $a['purchase_cost'] = $_POST['purchase_cost'] !== '' ? (float)$_POST['purchase_cost'] : null;
    $a['warranty_expiry'] = sanitize_string($_POST['warranty_expiry'] ?? '', 20) ?: null;
    $a['replacement_date'] = sanitize_string($_POST['replacement_date'] ?? '', 20) ?: null;
    $a['depreciation_method'] = in_array($_POST['depreciation_method'] ?? '', ['straight_line','declining_balance','none'], true) ? $_POST['depreciation_method'] : 'none';
    $a['useful_life_years'] = (int)($_POST['useful_life_years'] ?? 0) ?: null;
    $a['salvage_value'] = (float)($_POST['salvage_value'] ?? 0);
    $a['status'] = in_array($_POST['status'] ?? '', ['in_use','under_repair','retired','disposed'], true) ? $_POST['status'] : $a['status'];

    db_update('assets', [
        'property_id'=>$a['property_id'],'category_id'=>$a['category_id'],'name'=>$a['name'],
        'purchase_date'=>$a['purchase_date'],'purchase_cost'=>$a['purchase_cost'],
        'warranty_expiry'=>$a['warranty_expiry'],'serial_number'=>$a['serial_number'],
        'location_notes'=>$a['location_notes'],'depreciation_method'=>$a['depreciation_method'],
        'useful_life_years'=>$a['useful_life_years'],'salvage_value'=>$a['salvage_value'],
        'replacement_date'=>$a['replacement_date'],'status'=>$a['status'],
    ], 'id = :id', [':id' => $id]);

    log_audit('assets','update','assets',$id,$before,$a);
    flash_success(__('Asset updated.'));
    redirect('modules/assets/edit.php?id=' . $id);
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$categories = db_all('SELECT id, name FROM asset_categories WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$logs = db_all('SELECT * FROM asset_maintenance_logs WHERE asset_id = :a ORDER BY performed_at DESC LIMIT 20', [':a'=>$id]);

$PAGE_TITLE = __('Edit Asset');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Asset')) ?> · <?= e($a['name']) ?></h1>
<form method="post" class="srp-card p-4 mb-3">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Asset Name')) ?></label>
      <input name="name" class="form-control" value="<?= e($a['name']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Property')) ?></label>
      <select name="property_id" class="form-select">
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$a['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Category')) ?></label>
      <select name="category_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$a['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Purchase Date')) ?></label>
      <input type="date" name="purchase_date" class="form-control" value="<?= e((string)$a['purchase_date']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Cost')) ?></label>
      <input type="number" step="0.01" name="purchase_cost" class="form-control" value="<?= e((string)$a['purchase_cost']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Warranty Expiry')) ?></label>
      <input type="date" name="warranty_expiry" class="form-control" value="<?= e((string)$a['warranty_expiry']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Serial')) ?></label>
      <input name="serial_number" class="form-control" value="<?= e((string)$a['serial_number']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Depreciation')) ?></label>
      <select name="depreciation_method" class="form-select">
        <?php foreach (['none','straight_line','declining_balance'] as $m): ?>
          <option value="<?= e($m) ?>" <?= $a['depreciation_method']===$m?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$m))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Useful Life (years)')) ?></label>
      <input type="number" name="useful_life_years" class="form-control" value="<?= e((string)$a['useful_life_years']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Salvage Value')) ?></label>
      <input type="number" step="0.01" name="salvage_value" class="form-control" value="<?= e((string)$a['salvage_value']) ?>"></div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Replacement Date')) ?></label>
      <input type="date" name="replacement_date" class="form-control" value="<?= e((string)$a['replacement_date']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Location Notes')) ?></label>
      <input name="location_notes" class="form-control" value="<?= e((string)$a['location_notes']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['in_use','under_repair','retired','disposed'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $a['status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/assets/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>

<div class="srp-card p-4">
  <h2 class="h6 mb-3"><?= e(__('Maintenance History')) ?></h2>
  <?php if (!$logs): ?><div class="text-muted small"><?= e(__('No maintenance logs recorded.')) ?></div>
  <?php else: ?>
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th><?= e(__('Date')) ?></th><th><?= e(__('Description')) ?></th><th><?= e(__('Cost')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr><td><?= e($l['performed_at']) ?></td><td><?= e($l['description']) ?></td><td><?= e(money($l['cost'] ?? 0)) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>