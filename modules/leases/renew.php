<?php
// modules/leases/renew.php — one-click renewal without re-entry.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('leases.create');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$l = db_one('SELECT * FROM leases WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$l) { flash_error(__('Lease not found.')); redirect('modules/leases/index.php'); }

// Suggested values
$suggestedStart = date('Y-m-d', strtotime($l['end_date'] . ' +1 day'));
$suggestedEnd   = date('Y-m-d', strtotime($suggestedStart . ' +1 year -1 day'));
$suggestedRent  = $l['monthly_rent'];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $newStart = sanitize_string($_POST['start_date'] ?? '', 20);
    $newEnd   = sanitize_string($_POST['end_date'] ?? '', 20);
    $newRent  = (float)($_POST['monthly_rent'] ?? 0);
    $newDeposit = (float)($_POST['security_deposit'] ?? 0);
    $notes    = sanitize_string($_POST['notes'] ?? '', 500);
    $activateNow = !empty($_POST['activate_now']);

    if (!$newStart || !$newEnd) $errors['_'] = __('Dates required.');
    if ($newRent <= 0) $errors['_'] = __('Rent must be positive.');
    if (strtotime($newEnd) <= strtotime($newStart)) $errors['_'] = __('End must be after start.');

    if (!$errors) {
        db()->beginTransaction();
        try {
            $newNumber = $l['lease_number'] . '-R' . date('Ymd');
            $newId = db_insert('leases', [
                'company_id'        => (int)$USER['company_id'],
                'property_id'       => (int)$l['property_id'],
                'unit_id'           => (int)$l['unit_id'],
                'tenant_id'         => (int)$l['tenant_id'],
                'lease_number'      => $newNumber,
                'start_date'        => $newStart,
                'end_date'          => $newEnd,
                'monthly_rent'      => $newRent,
                'security_deposit'  => $newDeposit,
                'payment_frequency' => $l['payment_frequency'],
                'grace_period_days' => (int)$l['grace_period_days'],
                'notice_period_days'=> (int)$l['notice_period_days'],
                'included_utilities'=> $l['included_utilities'],
                'included_services' => $l['included_services'],
                'renewal_conditions'=> $l['renewal_conditions'],
                'special_clauses'   => $l['special_clauses'],
                'parent_lease_id'   => $id,
                'status'            => $activateNow ? 'active' : 'pending_signature',
                'created_by'        => (int)$USER['id'],
                'signed_at'         => $activateNow ? date('Y-m-d H:i:s') : null,
            ]);

            // Rent schedule
            db_insert('rent_schedules', [
                'lease_id' => $newId, 'due_day' => 1,
                'frequency' => $l['payment_frequency'],
                'next_generation_date' => $newStart, 'is_active' => 1,
            ]);

            // Expiry reminders
            foreach ([90,60,30,14,7] as $d) {
                db_insert('lease_expiry_reminders', ['lease_id' => $newId, 'days_before' => $d]);
            }

            // Renewal link
            db_insert('lease_renewals', [
                'original_lease_id' => $id,
                'new_lease_id'      => $newId,
                'renewal_date'      => date('Y-m-d'),
                'notes'             => $notes ?: null,
                'created_by'        => (int)$USER['id'],
            ]);

            // Close the old lease
            db_update('leases', ['status' => 'renewed'], 'id = :id', [':id' => $id]);

            log_audit('leases', 'renew', 'leases', $newId, $l, ['parent_lease_id' => $id]);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[renew] ' . $e->getMessage());
            $errors['_'] = __('Renewal failed.');
        }

        if (!$errors) {
            flash_success(__('Lease renewed.'));
            redirect('modules/leases/view.php?id=' . $newId);
        }
    }
}

$PAGE_TITLE = __('Renew Lease');
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/leases/index.php"><?= e(__('Leases')) ?></a></li>
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/leases/view.php?id=<?= (int)$id ?>"><?= e($l['lease_number']) ?></a></li>
  <li class="breadcrumb-item active"><?= e(__('Renew')) ?></li>
</ol></nav>

<h1 class="h4 mb-3"><?= e(__('Renew Lease')) ?> · <?= e($l['lease_number']) ?></h1>
<p class="text-muted small"><?= e(__('Terms are pre-filled from the current lease. Adjust as needed — no re-entry required.')) ?></p>

<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('New Start Date')) ?></label>
      <input type="date" name="start_date" class="form-control" value="<?= e($suggestedStart) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('New End Date')) ?></label>
      <input type="date" name="end_date" class="form-control" value="<?= e($suggestedEnd) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('New Rent')) ?></label>
      <input type="number" step="0.01" name="monthly_rent" class="form-control" value="<?= e((string)$suggestedRent) ?>" required></div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Deposit')) ?></label>
      <input type="number" step="0.01" name="security_deposit" class="form-control" value="<?= e((string)$l['security_deposit']) ?>"></div>
    <div class="col-md-4">
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" name="activate_now" id="activateNow" value="1" checked>
        <label class="form-check-label" for="activateNow"><?= e(__('Activate immediately (skip pending signature)')) ?></label>
      </div>
    </div>
    <div class="col-12"><label class="form-label"><?= e(__('Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="2"></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/leases/view.php?id=<?= (int)$id ?>"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="refresh-cw"></i> <?= e(__('Renew Lease')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>