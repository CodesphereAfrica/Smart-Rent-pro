<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('buildings.delete');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$b = db_one('SELECT b.* FROM buildings b JOIN properties p ON p.id = b.property_id
             WHERE b.id = :id AND p.company_id = :c LIMIT 1',
            [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$b) { flash_error(__('Building not found.')); redirect('modules/buildings/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $hasUnits = db_one('SELECT id FROM units WHERE building_id = :b LIMIT 1', [':b'=>$id]);
    if ($hasUnits) {
        flash_error(__('Cannot delete — building still has units attached.'));
        redirect('modules/buildings/view.php?id=' . $id);
    }
    db_query('DELETE FROM buildings WHERE id = :id', [':id'=>$id]);
    log_audit('buildings', 'delete', 'buildings', $id, $b, null);
    flash_success(__('Building deleted.'));
    redirect('modules/buildings/index.php');
}

$PAGE_TITLE = __('Delete Building');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6">
  <div class="srp-card p-4">
    <h1 class="h5 mb-3"><?= e(__('Delete Building')) ?></h1>
    <p><?= e(__('Are you sure you want to permanently delete:')) ?> <strong><?= e($b['name']) ?></strong>?</p>
    <p class="text-muted small"><?= e(__('This is a hard delete; buildings with units attached cannot be deleted.')) ?></p>
    <form method="post" class="d-flex justify-content-end gap-2">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
      <a href="<?= APP_URL ?>/modules/buildings/index.php" class="btn btn-light"><?= e(__('Cancel')) ?></a>
      <button class="btn btn-danger"><i data-lucide="trash-2"></i> <?= e(__('Delete')) ?></button>
    </form>
  </div>
</div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>