<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('expenses.create');
$preset = [
    'property_id' => (int)($_GET['property_id'] ?? 0),
    'unit_id'     => (int)($_GET['unit_id'] ?? 0),
    'vendor_id'   => (int)($_GET['vendor_id'] ?? 0),
    'amount'      => (string)($_GET['amount'] ?? ''),
    'work_order_id' => (int)($_GET['work_order_id'] ?? 0),
];
$errors = [];
$old = array_merge([
    'property_id' => 0, 'building_id' => 0, 'unit_id' => 0, 'vendor_id' => 0,
    'category_id' => 0, 'description' => '', 'amount' => '', 'expense_date' => date('Y-m-d'),
    'payment_status' => 'unpaid', 'related_work_order_id' => 0,
], $preset);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['property_id']            = (int)($_POST['property_id'] ?? 0) ?: null;
    $old['building_id']            = (int)($_POST['building_id'] ?? 0) ?: null;
    $old['unit_id']                = (int)($_POST['unit_id'] ?? 0) ?: null;
    $old['vendor_id']              = (int)($_POST['vendor_id'] ?? 0) ?: null;
    $old['category_id']            = (int)($_POST['category_id'] ?? 0);
    $old['description']            = sanitize_string($_POST['description'] ?? '', 255);
    $old['amount']                 = (float)($_POST['amount'] ?? 0);
    $old['expense_date']           = sanitize_string($_POST['expense_date'] ?? '', 20);
    $old['payment_status']         = in_array($_POST['payment_status'] ?? '', ['unpaid','paid','partially_paid'], true) ? $_POST['payment_status'] : 'unpaid';
    $old['related_work_order_id']  = (int)($_POST['related_work_order_id'] ?? 0) ?: null;

    if (!$old['category_id'])           $errors['category_id'] = __('Select a category.');
    if ($old['description'] === '')     $errors['description'] = __('Description required.');
    if ($old['amount'] <= 0)            $errors['amount'] = __('Amount must be positive.');

    // Threshold-based approval flow
    $approvalRequired = false;
    $workflowForAmount = db_one(
        'SELECT id, required_role_id FROM approval_workflows
          WHERE company_id = :c AND workflow_type = "expense" AND is_active = 1
            AND min_amount <= :a AND (max_amount IS NULL OR max_amount >= :a)
          ORDER BY sequence_order ASC LIMIT 1',
        [':c'=>(int)$USER['company_id'], ':a'=>$old['amount']]
    );

    if (!$errors) {
        db()->beginTransaction();
        try {
            $id = db_insert('expenses', [
                'company_id' => (int)$USER['company_id'],
                'property_id' => $old['property_id'],
                'building_id' => $old['building_id'],
                'unit_id' => $old['unit_id'],
                'vendor_id' => $old['vendor_id'],
                'category_id' => $old['category_id'],
                'description' => $old['description'],
                'amount' => $old['amount'],
                'expense_date' => $old['expense_date'],
                'payment_status' => $old['payment_status'],
                'related_work_order_id' => $old['related_work_order_id'],
                'created_by' => (int)$USER['id'],
            ]);

            // Create approval request if a workflow matches
            if ($workflowForAmount) {
                $reqId = db_insert('approval_requests', [
                    'company_id' => (int)$USER['company_id'],
                    'workflow_type' => 'expense',
                    'related_type' => 'expenses',
                    'related_id' => $id,
                    'requested_by' => (int)$USER['id'],
                    'amount' => $old['amount'],
                    'status' => 'pending',
                ]);
                db_update('expenses', ['approval_request_id' => $reqId], 'id = :id', [':id' => $id]);
                log_audit('approvals', 'request', 'expenses', $id, null, ['workflow_id'=>(int)$workflowForAmount['id'],'request_id'=>$reqId]);
            }

            // Link back to a work order if this was created from one
            if ($old['related_work_order_id']) {
                db_update('work_orders', ['expense_id' => $id], 'id = :id', [':id' => $old['related_work_order_id']]);
            }

            log_audit('expenses', 'create', 'expenses', $id, null, $old);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[expense.create] ' . $e->getMessage());
            $errors['_'] = __('Save failed.');
        }
        if (!$errors) {
            flash_success(__('Expense recorded.'));
            redirect('modules/expenses/view.php?id=' . $id);
        }
    }
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name', [':c'=>(int)$USER['company_id']]);
$categories = db_all('SELECT id, name FROM expense_categories WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$vendors    = db_all('SELECT id, name FROM vendors WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('Add Expense');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Add Expense')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-8"><label class="form-label"><?= e(__('Description')) ?> *</label>
      <input name="description" class="form-control" value="<?= e((string)$old['description']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Amount')) ?> *</label>
      <input type="number" step="0.01" name="amount" class="form-control" value="<?= e((string)$old['amount']) ?>" required></div>

    <div class="col-md-3"><label class="form-label"><?= e(__('Category')) ?> *</label>
      <select name="category_id" class="form-select" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$old['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Property')) ?></label>
      <select name="property_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Vendor')) ?></label>
      <select name="vendor_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($vendors as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= (int)$old['vendor_id']===(int)$v['id']?'selected':'' ?>><?= e($v['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Date')) ?></label>
      <input type="date" name="expense_date" class="form-control" value="<?= e($old['expense_date']) ?>"></div>

    <div class="col-md-3"><label class="form-label"><?= e(__('Payment Status')) ?></label>
      <select name="payment_status" class="form-select">
        <?php foreach (['unpaid','partially_paid','paid'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $old['payment_status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <input type="hidden" name="related_work_order_id" value="<?= (int)$old['related_work_order_id'] ?>">
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/expenses/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Expense')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>