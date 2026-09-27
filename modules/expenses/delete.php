<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('expenses.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$e = db_one('SELECT * FROM expenses WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$e) { flash_error(__('Not found.')); redirect('modules/expenses/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    // Never hard-delete; write-off approach
    db_update('expenses', [
        'payment_status' => 'paid',
        'description' => '[VOIDED] ' . $e['description'],
    ], 'id = :id', [':id' => $id]);
    log_audit('expenses','void','expenses',$id,$e,['payment_status'=>'paid']);
    flash_success(__('Expense voided.'));
    redirect('modules/expenses/index.php');
}
$PAGE_TITLE = __('Void Expense');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5"><?= e(__('Void Expense')) ?></h1>
  <p class="text-muted small"><?= e(__('Expenses are not hard-deleted. Voiding preserves the audit trail.')) ?></p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/expenses/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><?= e(__('Void')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>