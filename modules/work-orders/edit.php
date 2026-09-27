<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('maintenance.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$wo = db_one('SELECT * FROM work_orders WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$wo) { flash_error(__('Not found.')); redirect('modules/work-orders/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $wo;
    $wo['status']           = in_array($_POST['status'] ?? '', ['scheduled','in_progress','completed','cancelled'], true) ? $_POST['status'] : $wo['status'];
    $wo['actual_cost']      = $_POST['actual_cost'] !== '' ? (float)$_POST['actual_cost'] : null;
    $wo['estimated_cost']   = $_POST['estimated_cost'] !== '' ? (float)$_POST['estimated_cost'] : null;
    $wo['scheduled_date']   = sanitize_string($_POST['scheduled_date'] ?? '', 20) ?: null;
    $wo['work_description'] = sanitize_string($_POST['work_description'] ?? '', 3000) ?: null;
    $wo['notes']            = sanitize_string($_POST['notes'] ?? '', 2000) ?: null;

    if ($wo['status'] === 'completed' && !$wo['completed_date']) $wo['completed_date'] = date('Y-m-d');

    if (!$errors) {
        db_update('work_orders', [
            'status'=>$wo['status'],'actual_cost'=>$wo['actual_cost'],
            'estimated_cost'=>$wo['estimated_cost'],'scheduled_date'=>$wo['scheduled_date'],
            'work_description'=>$wo['work_description'],'notes'=>$wo['notes'],
            'completed_date'=>$wo['completed_date'],
        ], 'id = :id', [':id' => $id]);

        // Cascade status back to the maintenance request
        if ($wo['status'] === 'completed') {
            db_update('maintenance_requests', ['status'=>'completed','completed_at'=>date('Y-m-d H:i:s'),'actual_cost'=>$wo['actual_cost']], 'id = :id', [':id'=>(int)$wo['maintenance_request_id']]);
            db_insert('maintenance_status_history', [
                'maintenance_request_id' => (int)$wo['maintenance_request_id'],
                'from_status'=>null,'to_status'=>'completed',
                'changed_by'=>(int)$USER['id'],'notes'=>'Work order completed',
            ]);
        } elseif ($wo['status'] === 'cancelled') {
            db_update('maintenance_requests', ['status'=>'new'], 'id = :id', [':id'=>(int)$wo['maintenance_request_id']]);
        }

        log_audit('work_orders','update','work_orders',$id,$before,$wo);
        flash_success(__('Work order updated.'));
        redirect('modules/work-orders/view.php?id=' . $id);
    }
}

$PAGE_TITLE = __('Edit Work Order');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Work Order')) ?> <?= e($wo['work_order_number']) ?></h1>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['scheduled','in_progress','completed','cancelled'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $wo['status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Scheduled')) ?></label>
      <input type="date" name="scheduled_date" class="form-control" value="<?= e((string)$wo['scheduled_date']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Estimated Cost')) ?></label>
      <input type="number" step="0.01" name="estimated_cost" class="form-control" value="<?= e((string)$wo['estimated_cost']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Actual Cost')) ?></label>
      <input type="number" step="0.01" name="actual_cost" class="form-control" value="<?= e((string)$wo['actual_cost']) ?>"></div>
    <div class="col-12"><label class="form-label"><?= e(__('Work Description')) ?></label>
      <textarea name="work_description" class="form-control" rows="4"><?= e((string)$wo['work_description']) ?></textarea></div>
    <div class="col-12"><label class="form-label"><?= e(__('Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="2"><?= e((string)$wo['notes']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/work-orders/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>