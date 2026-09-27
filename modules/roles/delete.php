<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('users.manage');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$r = db_one('SELECT * FROM roles WHERE id = :id AND company_id = :c AND is_system_role = 0', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$r) { flash_error(__('Custom role not found.')); redirect('modules/roles/index.php'); }

$inUse = (int)(db_one('SELECT COUNT(*) AS c FROM users WHERE role_id = :r', [':r'=>$id])['c'] ?? 0);
if ($inUse > 0) {
    flash_error(__('Cannot delete — role is assigned to ' . $inUse . ' user(s).'));
    redirect('modules/roles/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    db_query('DELETE FROM role_permissions WHERE role_id = :r', [':r'=>$id]);
    db_query('DELETE FROM roles WHERE id = :id', [':id'=>$id]);
    log_audit('roles','delete','roles',$id,$r,null);
    flash_success(__('Role deleted.'));
    redirect('modules/roles/index.php');
}
$PAGE_TITLE = __('Delete Role');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Delete Role')) ?></h1>
  <p><?= e(__('Delete')) ?> <strong><?= e($r['name']) ?></strong>?</p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/roles/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><?= e(__('Delete')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>