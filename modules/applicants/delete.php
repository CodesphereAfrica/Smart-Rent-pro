<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('tenants.delete');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$a = db_one('SELECT * FROM applicants WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$a) { flash_error(__('Applicant not found.')); redirect('modules/applicants/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    db_query('DELETE FROM applicants WHERE id = :id', [':id'=>$id]);
    log_audit('applicants','delete','applicants',$id,$a,null);
    flash_success(__('Applicant deleted.'));
    redirect('modules/applicants/index.php');
}
$PAGE_TITLE = __('Delete Applicant');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Delete Applicant')) ?></h1>
  <p><?= e(__('Delete')) ?> <strong><?= e($a['first_name'].' '.$a['last_name']) ?></strong>?</p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/applicants/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><i data-lucide="trash-2"></i> <?= e(__('Delete')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>