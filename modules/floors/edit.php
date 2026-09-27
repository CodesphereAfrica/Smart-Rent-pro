<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('buildings.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$f = db_one('SELECT f.* FROM floors f JOIN buildings b ON b.id = f.building_id JOIN properties p ON p.id = b.property_id
             WHERE f.id = :id AND p.company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$f) { flash_error(__('Floor not found.')); redirect('modules/buildings/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $f;
    $f['floor_number'] = (int)($_POST['floor_number'] ?? 0);
    $f['floor_name']   = sanitize_string($_POST['floor_name'] ?? '', 60) ?: null;
    if ($f['floor_number'] === 0) $errors['floor_number'] = __('Required.');
    if (!$errors) {
        db_update('floors', ['floor_number'=>$f['floor_number'],'floor_name'=>$f['floor_name']], 'id=:id', [':id'=>$id]);
        log_audit('floors','update','floors',$id,$before,$f);
        flash_success(__('Floor updated.'));
        redirect('modules/floors/index.php?building_id=' . (int)$f['building_id']);
    }
}
$PAGE_TITLE = __('Edit Floor');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Floor')) ?></h1>
<form method="post" class="srp-card p-4" style="max-width:520px">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="mb-3"><label class="form-label"><?= e(__('Floor Number')) ?></label>
    <input type="number" name="floor_number" class="form-control<?= isset($errors['floor_number'])?' is-invalid':'' ?>" value="<?= (int)$f['floor_number'] ?>" required></div>
  <div class="mb-3"><label class="form-label"><?= e(__('Label')) ?></label>
    <input type="text" name="floor_name" class="form-control" value="<?= e($f['floor_name']) ?>"></div>
  <div class="d-flex justify-content-end gap-2">
    <a href="<?= APP_URL ?>/modules/floors/index.php?building_id=<?= (int)$f['building_id'] ?>" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>