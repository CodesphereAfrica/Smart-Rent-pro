<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('buildings.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$f = db_one('SELECT f.* FROM floors f JOIN buildings b ON b.id = f.building_id JOIN properties p ON p.id = b.property_id
             WHERE f.id = :id AND p.company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$f) { flash_error(__('Floor not found.')); redirect('modules/buildings/index.php'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $hasUnits = db_one('SELECT id FROM units WHERE floor_id = :id LIMIT 1', [':id'=>$id]);
    if ($hasUnits) { flash_error(__('Floor has units assigned; reassign them first.')); redirect('modules/floors/index.php?building_id=' . (int)$f['building_id']); }
    db_query('DELETE FROM floors WHERE id = :id', [':id'=>$id]);
    log_audit('floors','delete','floors',$id,$f,null);
    flash_success(__('Floor deleted.'));
    redirect('modules/floors/index.php?building_id=' . (int)$f['building_id']);
}
$PAGE_TITLE = __('Delete Floor');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Delete Floor')) ?></h1>
  <p><?= e(__('Delete floor')) ?> <strong><?= (int)$f['floor_number'] ?> <?= e($f['floor_name']) ?></strong>?</p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/floors/index.php?building_id=<?= (int)$f['building_id'] ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><?= e(__('Delete')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>