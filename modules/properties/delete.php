<?php
// modules/properties/delete.php — soft-delete with confirmation and inline logic.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.delete');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = db_one('SELECT * FROM properties WHERE id = :id AND company_id = :c AND deleted_at IS NULL LIMIT 1',
            [':id' => $id, ':c' => (int)$USER['company_id']]);
if (!$p) { flash_error(__('Property not found.')); redirect('modules/properties/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    db_update('properties', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]);
    log_audit('properties', 'delete', 'properties', $id, $p, ['deleted_at' => date('Y-m-d H:i:s')]);
    flash_success(__('Property archived.'));
    redirect('modules/properties/index.php');
}

$PAGE_TITLE = __('Delete Property');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6">
  <div class="srp-card p-4">
    <h1 class="h5 mb-3"><?= e(__('Archive Property')) ?></h1>
    <p><?= e(__('Are you sure you want to archive:')) ?> <strong><?= e($p['name']) ?></strong>?</p>
    <p class="text-muted small"><?= e(__('This is a soft delete — data is preserved for audit and can be restored by an administrator.')) ?></p>
    <form method="post" class="d-flex justify-content-end gap-2">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <a href="<?= APP_URL ?>/modules/properties/index.php" class="btn btn-light"><?= e(__('Cancel')) ?></a>
      <button class="btn btn-danger"><i data-lucide="trash-2"></i> <?= e(__('Archive')) ?></button>
    </form>
  </div>
</div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>