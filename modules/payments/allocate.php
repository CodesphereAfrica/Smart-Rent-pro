<?php
// modules/payments/allocate.php — manual allocation UI for an under/over-applied payment.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('payments.create');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$p = db_one('SELECT * FROM payments WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$p) { flash_error(__('Payment not found.')); redirect('modules/payments/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $alloc = $_POST['alloc'] ?? [];
    $remaining = (float)$p['amount_unallocated'];

    db()->beginTransaction();
    try {
        foreach ((array)$alloc as $invId => $amt) {
            $invId = (int)$invId; $amt = (float)$amt;
            if ($amt <= 0 || $remaining <= 0) continue;

            $inv = db_one(
                'SELECT id, amount, amount_paid, balance FROM invoices
                  WHERE id = :i AND tenant_id = :t AND company_id = :c
                    AND status IN ("upcoming","due","partially_paid","overdue")
                  LIMIT 1',
                [':i'=>$invId, ':t'=>(int)$p['tenant_id'], ':c'=>(int)$USER['company_id']]
            );
            if (!$inv) continue;

            $apply = min($amt, (float)$inv['balance'], $remaining);
            if ($apply <= 0) continue;

            db_insert('payment_allocations', [
                'payment_id' => $id, 'invoice_id' => $invId,
                'amount_allocated' => $apply, 'allocation_type' => 'manual',
                'allocated_by' => (int)$USER['id'],
            ]);

            $newPaid = (float)$inv['amount_paid'] + $apply;
            db_update('invoices', [
                'amount_paid' => $newPaid,
                'status' => $newPaid >= (float)$inv['amount'] ? 'paid' : 'partially_paid',
            ], 'id = :i', [':i'=>$invId]);

            $remaining -= $apply;
        }

        db_update('payments', ['amount_unallocated' => max(0, $remaining)], 'id = :id', [':id' => $id]);
        log_audit('payments', 'manual_allocation', 'payments', $id, ['amount_unallocated' => $p['amount_unallocated']], ['amount_unallocated' => max(0, $remaining)]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        error_log('[allocate] ' . $e->getMessage());
        flash_error(__('Allocation failed.'));
        redirect('modules/payments/allocate.php?id=' . $id);
    }

    flash_success(__('Payment allocated.'));
    redirect('modules/payments/view.php?id=' . $id);
}

$outstanding = db_all(
    'SELECT id, invoice_number, invoice_type, amount, amount_paid, balance, due_date, status
       FROM invoices
      WHERE tenant_id = :t AND company_id = :c AND balance > 0
        AND status IN ("upcoming","due","partially_paid","overdue")
      ORDER BY due_date ASC',
    [':t'=>(int)$p['tenant_id'], ':c'=>(int)$USER['company_id']]
);

$PAGE_TITLE = __('Allocate Payment');
$PAGE_SCRIPTS = ['allocation.js'];
require __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center"><div class="col-lg-8">
  <div class="srp-card p-4">
    <h1 class="h5 mb-3"><?= e(__('Allocate Payment')) ?> #<?= (int)$id ?></h1>
    <div class="alert alert-info small mb-3">
      <?= e(__('Unallocated:')) ?> <strong><?= e(money($p['amount_unallocated'])) ?></strong>
    </div>

    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
      <table class="table table-hover align-middle">
        <thead><tr>
          <th><?= e(__('Invoice')) ?></th>
          <th><?= e(__('Type')) ?></th>
          <th><?= e(__('Due')) ?></th>
          <th class="text-end"><?= e(__('Balance')) ?></th>
          <th style="width:180px"><?= e(__('Apply')) ?></th>
        </tr></thead>
        <tbody>
          <?php if (!$outstanding): ?>
            <tr><td colspan="5" class="text-center text-muted py-4"><?= e(__('No outstanding invoices for this tenant.')) ?></td></tr>
          <?php else: foreach ($outstanding as $inv): ?>
            <tr>
              <td><a href="<?= APP_URL ?>/modules/invoices/view.php?id=<?= (int)$inv['id'] ?>"><?= e($inv['invoice_number']) ?></a></td>
              <td><?= e(ucfirst(str_replace('_',' ',$inv['invoice_type']))) ?></td>
              <td><?= e($inv['due_date']) ?></td>
              <td class="text-end fw-semibold"><?= e(money($inv['balance'])) ?></td>
              <td>
                <input type="number" step="0.01" min="0" max="<?= e((string)$inv['balance']) ?>"
                       name="alloc[<?= (int)$inv['id'] ?>]" class="form-control form-control-sm" value="0">
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <a class="btn btn-light" href="<?= APP_URL ?>/modules/payments/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
        <button class="btn btn-primary"><i data-lucide="check"></i> <?= e(__('Apply Allocation')) ?></button>
      </div>
    </form>
  </div>
</div></div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>