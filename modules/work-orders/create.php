<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('maintenance.create');
$maintenanceId = (int)($_GET['maintenance_id'] ?? $_POST['maintenance_request_id'] ?? 0);

$mr = $maintenanceId
    ? db_one('SELECT * FROM maintenance_requests WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$maintenanceId, ':c'=>(int)$USER['company_id']])
    : null;

$errors = [];
$old = [
    'maintenance_request_id' => $maintenanceId,
    'vendor_id' => $mr['assigned_vendor_id'] ?? 0,
    'technician_user_id' => $mr['assigned_to_user_id'] ?? 0,
    'work_description' => '',
    'estimated_cost' => '',
    'scheduled_date' => date('Y-m-d', strtotime('+1 day')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['maintenance_request_id'] = (int)($_POST['maintenance_request_id'] ?? 0);
    $old['vendor_id']              = (int)($_POST['vendor_id'] ?? 0) ?: null;
    $old['technician_user_id']     = (int)($_POST['technician_user_id'] ?? 0) ?: null;
    $old['work_description']       = sanitize_string($_POST['work_description'] ?? '', 3000) ?: null;
    $old['estimated_cost']         = $_POST['estimated_cost'] !== '' ? (float)$_POST['estimated_cost'] : null;
    $old['scheduled_date']         = sanitize_string($_POST['scheduled_date'] ?? '', 20) ?: null;

    $mrCheck = db_one('SELECT id FROM maintenance_requests WHERE id = :id AND company_id = :c', [':id'=>$old['maintenance_request_id'], ':c'=>(int)$USER['company_id']]);
    if (!$mrCheck) $errors['maintenance_request_id'] = __('Select a maintenance request.');

    if (!$errors) {
        db()->beginTransaction();
        try {
            $woNumber = 'WO-' . date('Ymd') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
            $id = db_insert('work_orders', [
                'company_id' => (int)$USER['company_id'],
                'maintenance_request_id' => $old['maintenance_request_id'],
                'work_order_number' => $woNumber,
                'vendor_id' => $old['vendor_id'],
                'technician_user_id' => $old['technician_user_id'],
                'work_description' => $old['work_description'],
                'estimated_cost' => $old['estimated_cost'],
                'status' => 'scheduled',
                'scheduled_date' => $old['scheduled_date'],
                'created_by' => (int)$USER['id'],
            ]);

            db_update('maintenance_requests', ['status' => 'in_progress'], 'id = :id', [':id' => $old['maintenance_request_id']]);
            db_insert('maintenance_status_history', [
                'maintenance_request_id' => $old['maintenance_request_id'],
                'from_status' => null,
                'to_status'   => 'in_progress',
                'changed_by'  => (int)$USER['id'],
                'notes'       => 'Work order ' . $woNumber . ' created',
            ]);

            log_audit('work_orders', 'create', 'work_orders', $id, null, $old);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[wo.create] ' . $e->getMessage());
            $errors['_'] = __('Save failed.');
        }
        if (!$errors) {
            flash_success(__('Work order created.'));
            redirect('modules/work-orders/view.php?id=' . $id);
        }
    }
}

$openMaintenance = db_all(
    'SELECT id, ticket_number, description FROM maintenance_requests
      WHERE company_id = :c AND status NOT IN ("closed","verified") ORDER BY id DESC LIMIT 100',
    [':c'=>(int)$USER['company_id']]
);
$vendors = db_all('SELECT id, name FROM vendors WHERE company_id = :c AND status = "active" ORDER BY name', [':c'=>(int)$USER['company_id']]);
$techs = db_all(
    'SELECT u.id, CONCAT(u.first_name," ",u.last_name) AS name FROM users u JOIN roles r ON r.id = u.role_id
      WHERE u.company_id = :c AND u.status = "active" AND r.name IN ("Maintenance Technician","Maintenance Manager")
      ORDER BY u.first_name',
    [':c'=>(int)$USER['company_id']]
);

$PAGE_TITLE = __('New Work Order');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('New Work Order')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Maintenance Request')) ?> *</label>
      <select name="maintenance_request_id" class="form-select" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($openMaintenance as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= (int)$old['maintenance_request_id']===(int)$m['id']?'selected':'' ?>>
            <?= e($m['ticket_number']) ?> — <?= e(mb_substr($m['description'], 0, 60)) ?>
          </option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Vendor')) ?></label>
      <select name="vendor_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($vendors as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= (int)$old['vendor_id']===(int)$v['id']?'selected':'' ?>><?= e($v['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Technician')) ?></label>
      <select name="technician_user_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($techs as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= (int)$old['technician_user_id']===(int)$t['id']?'selected':'' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Scheduled Date')) ?></label>
      <input type="date" name="scheduled_date" class="form-control" value="<?= e($old['scheduled_date']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Estimated Cost')) ?></label>
      <input type="number" step="0.01" name="estimated_cost" class="form-control" value="<?= e((string)$old['estimated_cost']) ?>"></div>
    <div class="col-12"><label class="form-label"><?= e(__('Work Description')) ?></label>
      <textarea name="work_description" class="form-control" rows="4"><?= e((string)$old['work_description']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/work-orders/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Work Order')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>