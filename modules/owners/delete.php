<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('owners.delete');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$o = db_one('SELECT * FROM owners WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$o) { flash_error(__('Owner not found.')); redirect('modules/owners/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $hasProps = db_one('SELECT id FROM owner_properties WHERE owner_id = :o LIMIT 1', [':o' => $id]);
    if ($hasProps) {
        db_update('owners', ['status' => 'inactive'], 'id = :id', [':id' => $id]);
        log_audit('owners', 'deactivate', 'owners', $id, $o, ['status' => 'inactive']);
        flash_success(__('Owner deactivated (properties still linked).'));
    } else {
        db_query('DELETE FROM owners WHERE id = :id', [':id' => $id]);
        log_audit('owners', 'delete', 'owners', $id, $o, null);
        flash_success(__('Owner removed.'));
    }
    redirect('modules/owners/index.php');
}
$PAGE_TITLE = __('Remove Owner');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Remove Owner')) ?></h1>
  <p><?= e(__('Remove')) ?> <strong><?= e($o['full_name']) ?></strong>?</p>
  <p class="text-muted small"><?= e(__('If the owner still has properties, the record is only deactivated.')) ?></p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/owners/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><i data-lucide="trash-2"></i> <?= e(__('Remove')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>