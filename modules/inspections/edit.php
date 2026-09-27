<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('inspections.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$i = db_one('SELECT * FROM inspections WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$i) { flash_error(__('Not found.')); redirect('modules/inspections/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $i;
    $i['inspection_type'] = in_array($_POST['inspection_type'] ?? '', ['move_in','move_out','routine','maintenance','safety'], true) ? $_POST['inspection_type'] : $i['inspection_type'];
    $i['inspection_date'] = sanitize_string($_POST['inspection_date'] ?? '', 20);
    $i['notes']           = sanitize_string($_POST['notes'] ?? '', 3000) ?: null;
    $i['status']          = in_array($_POST['status'] ?? '', ['scheduled','completed','cancelled'], true) ? $_POST['status'] : $i['status'];

    db_update('inspections', [
        'inspection_type'=>$i['inspection_type'],
        'inspection_date'=>$i['inspection_date'],
        'notes'=>$i['notes'],
        'status'=>$i['status'],
    ], 'id = :id', [':id'=>$id]);

    log_audit('inspections','update','inspections',$id,$before,$i);
    flash_success(__('Updated.'));
    redirect('modules/inspections/view.php?id=' . $id);
}

$PAGE_TITLE = __('Edit Inspection');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Inspection')) ?> #<?= (int)$id ?></h1>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('Type')) ?></label>
      <select name="inspection_type" class="form-select">
        <?php foreach (['move_in','move_out','routine','maintenance','safety'] as $t): ?>
          <option value="<?= e($t) ?>" <?= $i['inspection_type']===$t?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$t))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Date')) ?></label>
      <input type="date" name="inspection_date" class="form-control" value="<?= e($i['inspection_date']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['scheduled','completed','cancelled'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $i['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-12"><label class="form-label"><?= e(__('Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="4"><?= e((string)$i['notes']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/inspections/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>