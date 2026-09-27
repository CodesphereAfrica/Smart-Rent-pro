<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_login();
if (!is_super_admin($USER)) { http_response_code(403); exit('Forbidden'); }

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$c = db_one('SELECT * FROM companies WHERE id = :id LIMIT 1', [':id'=>$id]);
if (!$c) { flash_error(__('Not found.')); redirect('modules/companies/index.php'); }

if ((int)$c['id'] === (int)$USER['company_id']) {
    flash_error(__('You cannot delete your own company.'));
    redirect('modules/companies/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    // Soft deactivate rather than hard delete — FK cascades would remove financial data.
    db_update('companies', ['is_active' => 0], 'id = :id', [':id' => $id]);
    log_audit('companies','deactivate','companies',$id,$c,['is_active'=>0]);
    flash_success(__('Company deactivated (data preserved).'));
    redirect('modules/companies/index.php');
}

$PAGE_TITLE = __('Deactivate Company');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Deactivate Company')) ?></h1>
  <p><?= e(__('Deactivate')) ?> <strong><?= e($c['name']) ?></strong>?</p>
  <p class="text-muted small"><?= e(__('Companies are never hard-deleted — deactivating blocks logins and preserves all financial history for audit.')) ?></p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/companies/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><i data-lucide="power"></i> <?= e(__('Deactivate')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>