<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('properties.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$s = db_one('SELECT s.* FROM parking_spaces s JOIN properties p ON p.id = s.property_id WHERE s.id = :id AND p.company_id = :c',
            [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$s) { flash_error(__('Not found.')); redirect('modules/parking/index.php'); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf(); $before = $s;
    $s['space_number'] = sanitize_string($_POST['space_number'] ?? '', 30);
    $s['space_type']   = sanitize_string($_POST['space_type'] ?? '', 40) ?: null;
    $s['monthly_fee']  = (float)($_POST['monthly_fee'] ?? 0);
    $s['status']       = in_array($_POST['status'] ?? '', ['available','assigned','unavailable'], true) ? $_POST['status'] : 'available';
    if (!$errors) {
        db_update('parking_spaces', [
            'space_number'=>$s['space_number'],'space_type'=>$s['space_type'],
            'monthly_fee'=>$s['monthly_fee'],'status'=>$s['status'],
        ], 'id = :id', [':id'=>$id]);
        log_audit('parking_spaces','update','parking_spaces',$id,$before,$s);
        flash_success(__('Parking space updated.'));
        redirect('modules/parking/index.php');
    }
}
$PAGE_TITLE = __('Edit Parking Space');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Parking Space')) ?></h1>
<form method="post" class="srp-card p-4" style="max-width:640px">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Space #')) ?></label>
      <input name="space_number" class="form-control" value="<?= e($s['space_number']) ?>" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Type')) ?></label>
      <input name="space_type" class="form-control" value="<?= e($s['space_type']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Monthly Fee')) ?></label>
      <input type="number" step="0.01" name="monthly_fee" class="form-control" value="<?= e((string)$s['monthly_fee']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['available','assigned','unavailable'] as $st): ?>
          <option value="<?= e($st) ?>" <?= $s['status']===$st?'selected':'' ?>><?= e(ucfirst($st)) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/parking/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>