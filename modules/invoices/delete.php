<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('invoices.void');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$i = db_one('SELECT * FROM invoices WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$i) { flash_error(__('Invoice not found.')); redirect('modules/invoices/index.php'); }

if ((float)$i['amount_paid'] > 0) {
    flash_error(__('Cannot void — invoice has payment allocations. Reverse the payments first.'));
    redirect('modules/invoices/view.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $reason = sanitize_string($_POST['reason'] ?? '', 255);

    db_update('invoices', [
        'status' => 'written_off',
        'written_off_reason' => $reason ?: __('Voided by user'),
    ], 'id = :id', [':id'=>$id]);

    log_audit('invoices', 'void', 'invoices', $id, $i, ['status' => 'written_off', 'reason' => $reason]);
    flash_success(__('Invoice voided.'));
    redirect('modules/invoices/index.php');
}

$PAGE_TITLE = __('Void Invoice');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5 mb-3"><?= e(__('Void Invoice')) ?> <?= e($i['invoice_number']) ?></h1>
  <p class="text-muted small"><?= e(__('Invoices are never deleted. Voiding preserves the audit trail and marks the record as written off.')) ?></p>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="mb-3"><label class="form-label"><?= e(__('Reason')) ?></label>
      <textarea name="reason" class="form-control" rows="2" required></textarea></div>
    <div class="d-flex justify-content-end gap-2">
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/invoices/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
      <button class="btn btn-danger"><i data-lucide="x-circle"></i> <?= e(__('Void')) ?></button>
    </div>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>