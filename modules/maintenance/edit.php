<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('maintenance.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$mr = db_one('SELECT * FROM maintenance_requests WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$mr) { flash_error(__('Not found.')); redirect('modules/maintenance/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $mr;

    $newStatus = in_array($_POST['status'] ?? '', ['new','assigned','in_progress','waiting_for_parts','completed','verified','closed'], true) ? $_POST['status'] : $mr['status'];
    $notes     = sanitize_string($_POST['notes'] ?? '', 500);
    $actual    = $_POST['actual_cost'] !== '' ? (float)$_POST['actual_cost'] : null;
    $estimated = $_POST['estimated_cost'] !== '' ? (float)$_POST['estimated_cost'] : null;

    if (!$errors) {
        db()->beginTransaction();
        try {
            $update = [
                'status' => $newStatus,
                'actual_cost' => $actual,
                'estimated_cost' => $estimated,
            ];
            if ($newStatus === 'completed' && !$mr['completed_at']) $update['completed_at'] = date('Y-m-d H:i:s');
            if ($newStatus === 'verified'  && !$mr['verified_at'])  $update['verified_at']  = date('Y-m-d H:i:s');
            if ($newStatus === 'closed'    && !$mr['closed_at'])    $update['closed_at']    = date('Y-m-d H:i:s');

            db_update('maintenance_requests', $update, 'id = :id', [':id' => $id]);

            if ($before['status'] !== $newStatus) {
                db_insert('maintenance_status_history', [
                    'maintenance_request_id' => $id,
                    'from_status' => $before['status'],
                    'to_status'   => $newStatus,
                    'changed_by'  => (int)$USER['id'],
                    'notes'       => $notes ?: null,
                ]);
            }

            log_audit('maintenance', 'update', 'maintenance_requests', $id, $before, $update);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[maintenance.edit] ' . $e->getMessage());
            $errors['_'] = __('Update failed.');
        }
        if (!$errors) {
            flash_success(__('Maintenance request updated.'));
            redirect('modules/maintenance/view.php?id=' . $id);
        }
    }
}

$PAGE_TITLE = __('Update Maintenance');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Update')) ?> <?= e($mr['ticket_number']) ?></h1>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['new','assigned','in_progress','waiting_for_parts','completed','verified','closed'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $mr['status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Estimated Cost')) ?></label>
      <input type="number" step="0.01" name="estimated_cost" class="form-control" value="<?= e((string)$mr['estimated_cost']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Actual Cost')) ?></label>
      <input type="number" step="0.01" name="actual_cost" class="form-control" value="<?= e((string)$mr['actual_cost']) ?>"></div>
    <div class="col-12"><label class="form-label"><?= e(__('Notes (logged with the status change)')) ?></label>
      <textarea name="notes" class="form-control" rows="3"></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/maintenance/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Update')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>