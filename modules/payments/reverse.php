<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('payments.reverse');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = db_one('SELECT * FROM payments WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$p) { flash_error(__('Payment not found.')); redirect('modules/payments/index.php'); }
if ($p['status'] !== 'completed') { flash_error(__('Only completed payments can be reversed.')); redirect('modules/payments/view.php?id=' . $id); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $reason = sanitize_string($_POST['reason'] ?? '', 255);

    db()->beginTransaction();
    try {
        // Reverse allocations: subtract from invoice amount_paid
        $allocs = db_all('SELECT id, invoice_id, amount_allocated FROM payment_allocations WHERE payment_id = :p', [':p' => $id]);
        foreach ($allocs as $a) {
            $inv = db_one('SELECT amount, amount_paid FROM invoices WHERE id = :i', [':i' => (int)$a['invoice_id']]);
            if (!$inv) continue;
            $newPaid = max(0, (float)$inv['amount_paid'] - (float)$a['amount_allocated']);
            $newStatus = $newPaid <= 0 ? 'due' : ($newPaid >= (float)$inv['amount'] ? 'paid' : 'partially_paid');
            db_update('invoices', ['amount_paid' => $newPaid, 'status' => $newStatus], 'id = :i', [':i' => (int)$a['invoice_id']]);
        }
        db_update('payments', [
            'status' => 'reversed',
            'notes'  => trim((string)$p['notes'] . "\n[REVERSED] " . $reason),
        ], 'id = :id', [':id' => $id]);

        log_audit('payments', 'reverse', 'payments', $id, $p, ['status' => 'reversed', 'reason' => $reason]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        error_log('[reverse] ' . $e->getMessage());
        flash_error(__('Reversal failed.'));
        redirect('modules/payments/view.php?id=' . $id);
    }

    flash_success(__('Payment reversed. Invoices updated.'));
    redirect('modules/payments/view.php?id=' . $id);
}

$PAGE_TITLE = __('Reverse Payment');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5 mb-3"><?= e(__('Reverse Payment')) ?> #<?= (int)$id ?></h1>
  <p class="text-muted small"><?= e(__('Reversing a payment subtracts its allocations from all linked invoices and marks it as reversed. The original record and audit trail remain intact.')) ?></p>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="mb-3"><label class="form-label"><?= e(__('Reason')) ?></label>
      <textarea name="reason" class="form-control" rows="3" required></textarea></div>
    <div class="d-flex justify-content-end gap-2">
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/payments/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
      <button class="btn btn-danger"><i data-lucide="x-circle"></i> <?= e(__('Reverse Payment')) ?></button>
    </div>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>