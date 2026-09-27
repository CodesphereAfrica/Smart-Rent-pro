<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('invoices.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$inv = db_one('SELECT * FROM invoices WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$inv) { flash_error(__('Invoice not found.')); redirect('modules/invoices/index.php'); }

if ((float)$inv['amount_paid'] > 0) {
    flash_error(__('Cannot edit — invoice already has payments allocated. Reverse the payment first.'));
    redirect('modules/invoices/view.php?id=' . $id);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $inv;

    $inv['invoice_type'] = in_array($_POST['invoice_type'] ?? '', ['rent','late_fee','utility','deposit','other'], true) ? $_POST['invoice_type'] : $inv['invoice_type'];
    $inv['period_start'] = sanitize_string($_POST['period_start'] ?? '', 20) ?: null;
    $inv['period_end']   = sanitize_string($_POST['period_end'] ?? '', 20) ?: null;
    $inv['amount']       = (float)($_POST['amount'] ?? 0);
    $inv['due_date']     = sanitize_string($_POST['due_date'] ?? '', 20);
    $description         = sanitize_string($_POST['description'] ?? '', 255) ?: null;

    if ($inv['amount'] <= 0) $errors['amount'] = __('Amount must be positive.');
    if (!$inv['due_date'])   $errors['due_date'] = __('Due date required.');

    if (!$errors) {
        db()->beginTransaction();
        try {
            $newStatus = (float)$inv['amount_paid'] >= (float)$inv['amount']
                ? 'paid'
                : ((float)$inv['amount_paid'] > 0
                    ? 'partially_paid'
                    : (strtotime($inv['due_date']) > time() ? 'upcoming' : 'due'));

            db_update('invoices', [
                'invoice_type' => $inv['invoice_type'],
                'period_start' => $inv['period_start'],
                'period_end'   => $inv['period_end'],
                'amount'       => $inv['amount'],
                'due_date'     => $inv['due_date'],
                'status'       => $newStatus,
            ], 'id = :id', [':id' => $id]);

            // Replace the single "description" item if one exists, else insert
            db_query('DELETE FROM invoice_items WHERE invoice_id = :i', [':i' => $id]);
            if ($description) {
                db_insert('invoice_items', [
                    'invoice_id' => $id,
                    'description' => $description,
                    'amount' => $inv['amount'],
                ]);
            }

            log_audit('invoices', 'update', 'invoices', $id, $before, $inv);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[invoices.edit] ' . $e->getMessage());
            $errors['_'] = __('Save failed.');
        }
        if (!$errors) {
            flash_success(__('Invoice updated.'));
            redirect('modules/invoices/view.php?id=' . $id);
        }
    }
}

$item = db_one('SELECT description FROM invoice_items WHERE invoice_id = :i LIMIT 1', [':i' => $id]);
$description = $item['description'] ?? '';

$PAGE_TITLE = __('Edit Invoice');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Invoice')) ?> · <?= e($inv['invoice_number']) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('Type')) ?></label>
      <select name="invoice_type" class="form-select">
        <?php foreach (['rent','late_fee','utility','deposit','other'] as $t): ?>
          <option value="<?= e($t) ?>" <?= $inv['invoice_type']===$t?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$t))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Amount')) ?> *</label>
      <input type="number" step="0.01" name="amount" class="form-control" value="<?= e((string)$inv['amount']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Due Date')) ?> *</label>
      <input type="date" name="due_date" class="form-control" value="<?= e($inv['due_date']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Period Start')) ?></label>
      <input type="date" name="period_start" class="form-control" value="<?= e((string)$inv['period_start']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Period End')) ?></label>
      <input type="date" name="period_end" class="form-control" value="<?= e((string)$inv['period_end']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Description')) ?></label>
      <input name="description" class="form-control" value="<?= e($description) ?>"></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/invoices/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>