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
    $before = $e;
    foreach (['description'] as $k) $e[$k] = sanitize_string($_POST[$k] ?? '', 255);
    $e['property_id']      = (int)($_POST['property_id'] ?? 0) ?: null;
    $e['vendor_id']        = (int)($_POST['vendor_id'] ?? 0) ?: null;
    $e['category_id']      = (int)($_POST['category_id'] ?? 0);
    $e['amount']           = (float)($_POST['amount'] ?? 0);
    $e['expense_date']     = sanitize_string($_POST['expense_date'] ?? '', 20);
    $e['payment_status']   = in_array($_POST['payment_status'] ?? '', ['unpaid','paid','partially_paid'], true) ? $_POST['payment_status'] : $e['payment_status'];

    db_update('expenses', [
        'property_id'=>$e['property_id'],'vendor_id'=>$e['vendor_id'],'category_id'=>$e['category_id'],
        'description'=>$e['description'],'amount'=>$e['amount'],'expense_date'=>$e['expense_date'],
        'payment_status'=>$e['payment_status'],
    ], 'id = :id', [':id' => $id]);

    log_audit('expenses','update','expenses',$id,$before,$e);
    flash_success(__('Expense updated.'));
    redirect('modules/expenses/view.php?id=' . $id);
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$categories = db_all('SELECT id, name FROM expense_categories WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$vendors    = db_all('SELECT id, name FROM vendors WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('Edit Expense');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Expense')) ?></h1>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-8"><label class="form-label"><?= e(__('Description')) ?></label>
      <input name="description" class="form-control" value="<?= e($e['description']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Amount')) ?></label>
      <input type="number" step="0.01" name="amount" class="form-control" value="<?= e((string)$e['amount']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Category')) ?></label>
      <select name="category_id" class="form-select">
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$e['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Property')) ?></label>
      <select name="property_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$e['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Vendor')) ?></label>
      <select name="vendor_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($vendors as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= (int)$e['vendor_id']===(int)$v['id']?'selected':'' ?>><?= e($v['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Date')) ?></label>
      <input type="date" name="expense_date" class="form-control" value="<?= e($e['expense_date']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Payment Status')) ?></label>
      <select name="payment_status" class="form-select">
        <?php foreach (['unpaid','partially_paid','paid'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $e['payment_status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/expenses/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>