<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('leases.terminate');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$l = db_one('SELECT * FROM leases WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$l) { flash_error(__('Lease not found.')); redirect('modules/leases/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $reason = sanitize_string($_POST['reason'] ?? '', 255);
    $endNow = !empty($_POST['end_now']);

    db()->beginTransaction();
    try {
        $update = [
            'status' => 'terminated',
            'terminated_at' => date('Y-m-d H:i:s'),
            'termination_reason' => $reason ?: __('Terminated by user'),
        ];
        if ($endNow) $update['end_date'] = date('Y-m-d');

        db_update('leases', $update, 'id = :id', [':id' => $id]);
        db_update('units', ['status' => 'available'], 'id = :id', [':id' => (int)$l['unit_id']]);
        db_update('rent_schedules', ['is_active' => 0], 'lease_id = :id', [':id' => $id]);

        log_audit('leases', 'terminate', 'leases', $id, $l, $update);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        error_log('[terminate] ' . $e->getMessage());
        flash_error(__('Could not terminate.'));
        redirect('modules/leases/view.php?id=' . $id);
    }

    flash_success(__('Lease terminated.'));
    redirect('modules/leases/index.php');
}

$PAGE_TITLE = __('Terminate Lease');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5 mb-3"><?= e(__('Terminate Lease')) ?> <?= e($l['lease_number']) ?></h1>
  <p class="text-muted small"><?= e(__('Leases are never hard-deleted. Termination preserves the audit trail.')) ?></p>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
    <div class="mb-3"><label class="form-label"><?= e(__('Reason')) ?></label>
      <textarea name="reason" class="form-control" rows="3" required></textarea></div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="end_now" id="endNow" value="1" checked>
      <label class="form-check-label" for="endNow"><?= e(__('Set end date to today')) ?></label>
    </div>
    <div class="d-flex justify-content-end gap-2">
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/leases/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
      <button class="btn btn-danger"><i data-lucide="x-circle"></i> <?= e(__('Terminate')) ?></button>
    </div>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>    