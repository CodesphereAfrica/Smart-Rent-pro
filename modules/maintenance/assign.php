<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('maintenance.assign');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$mr = db_one('SELECT * FROM maintenance_requests WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$mr) { flash_error(__('Not found.')); redirect('modules/maintenance/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $userId   = (int)($_POST['assigned_to_user_id'] ?? 0) ?: null;
    $vendorId = (int)($_POST['assigned_vendor_id'] ?? 0) ?: null;
    $notes    = sanitize_string($_POST['notes'] ?? '', 500);

    if (!$userId && !$vendorId) $errors['_'] = __('Choose either a user or a vendor.');

    if (!$errors) {
        db()->beginTransaction();
        try {
            db_update('maintenance_requests', [
                'assigned_to_user_id' => $userId,
                'assigned_vendor_id'  => $vendorId,
                'status'              => 'assigned',
            ], 'id = :id', [':id' => $id]);

            db_insert('maintenance_status_history', [
                'maintenance_request_id' => $id,
                'from_status' => $mr['status'],
                'to_status'   => 'assigned',
                'changed_by'  => (int)$USER['id'],
                'notes'       => $notes ?: 'Assigned',
            ]);

            log_audit('maintenance', 'assign', 'maintenance_requests', $id, $mr, ['user_id'=>$userId,'vendor_id'=>$vendorId]);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[assign] ' . $e->getMessage());
            $errors['_'] = __('Assignment failed.');
        }
        if (!$errors) {
            flash_success(__('Assigned.'));
            redirect('modules/maintenance/view.php?id=' . $id);
        }
    }
}

$staff = db_all(
    'SELECT u.id, CONCAT(u.first_name," ",u.last_name) AS name
       FROM users u JOIN roles r ON r.id = u.role_id
      WHERE u.company_id = :c AND u.status = "active"
        AND r.name IN ("Maintenance Manager","Maintenance Technician","Property Manager")
      ORDER BY u.first_name',
    [':c'=>(int)$USER['company_id']]
);
$vendors = db_all('SELECT id, name, category FROM vendors WHERE company_id = :c AND status = "active" ORDER BY name', [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('Assign Maintenance');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Assign')) ?> <?= e($mr['ticket_number']) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Assign to staff')) ?></label>
      <select name="assigned_to_user_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($staff as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (int)$mr['assigned_to_user_id']===(int)$s['id']?'selected':'' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Assign to vendor')) ?></label>
      <select name="assigned_vendor_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($vendors as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= (int)$mr['assigned_vendor_id']===(int)$v['id']?'selected':'' ?>><?= e($v['name']) ?> (<?= e($v['category']) ?>)</option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-12"><label class="form-label"><?= e(__('Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="2"></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/maintenance/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="user-check"></i> <?= e(__('Assign')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>