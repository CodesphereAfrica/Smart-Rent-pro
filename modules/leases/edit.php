<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('leases.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$l = db_one('SELECT * FROM leases WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$l) { flash_error(__('Lease not found.')); redirect('modules/leases/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $l;

    $l['start_date']          = sanitize_string($_POST['start_date'] ?? '', 20);
    $l['end_date']            = sanitize_string($_POST['end_date'] ?? '', 20);
    $l['monthly_rent']        = (float)($_POST['monthly_rent'] ?? 0);
    $l['security_deposit']    = (float)($_POST['security_deposit'] ?? 0);
    $l['payment_frequency']   = in_array($_POST['payment_frequency'] ?? '', ['monthly','quarterly','semi_annual','annual','weekly'], true) ? $_POST['payment_frequency'] : 'monthly';
    $l['grace_period_days']   = max(0, (int)($_POST['grace_period_days'] ?? 0));
    $l['notice_period_days']  = max(0, (int)($_POST['notice_period_days'] ?? 30));
    $l['included_utilities']  = json_encode(array_values((array)($_POST['included_utilities'] ?? [])));
    $l['included_services']   = json_encode(array_values((array)($_POST['included_services'] ?? [])));
    $l['renewal_conditions']  = sanitize_string($_POST['renewal_conditions'] ?? '', 2000) ?: null;
    $l['special_clauses']     = sanitize_string($_POST['special_clauses'] ?? '', 3000) ?: null;
    $l['status']              = in_array($_POST['status'] ?? '', ['draft','pending_signature','active','expiring','expired','terminated','renewed'], true) ? $_POST['status'] : $l['status'];

    if (!$l['start_date'] || !$l['end_date']) $errors['start_date'] = __('Dates required.');
    if (strtotime($l['end_date']) <= strtotime($l['start_date'])) $errors['end_date'] = __('End must be after start.');
    if ($l['monthly_rent'] <= 0) $errors['monthly_rent'] = __('Rent must be positive.');

    if (!$errors) {
        db_update('leases', [
            'start_date'=>$l['start_date'],'end_date'=>$l['end_date'],
            'monthly_rent'=>$l['monthly_rent'],'security_deposit'=>$l['security_deposit'],
            'payment_frequency'=>$l['payment_frequency'],
            'grace_period_days'=>$l['grace_period_days'],'notice_period_days'=>$l['notice_period_days'],
            'included_utilities'=>$l['included_utilities'],'included_services'=>$l['included_services'],
            'renewal_conditions'=>$l['renewal_conditions'],'special_clauses'=>$l['special_clauses'],
            'status'=>$l['status'],
        ], 'id = :id', [':id' => $id]);

        // Sync unit status when transitioning to active / terminating
        if ($before['status'] !== $l['status']) {
            if ($l['status'] === 'active') {
                db_update('units', ['status' => 'occupied'], 'id = :id', [':id' => (int)$l['unit_id']]);
                if (empty($l['signed_at'])) db_update('leases', ['signed_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]);
            } elseif (in_array($l['status'], ['terminated','expired'], true)) {
                db_update('units', ['status' => 'available'], 'id = :id', [':id' => (int)$l['unit_id']]);
            }
        }

        log_audit('leases','update','leases',$id,$before,$l);
        flash_success(__('Lease updated.'));
        redirect('modules/leases/view.php?id=' . $id);
    }
}

$PAGE_TITLE = __('Edit Lease');
require __DIR__ . '/../../includes/header.php';
$incUtil = json_decode((string)$l['included_utilities'], true) ?: [];
$incServ = json_decode((string)$l['included_services'], true) ?: [];
?>
<h1 class="h4 mb-3"><?= e(__('Edit Lease')) ?> · <?= e($l['lease_number']) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('Start Date')) ?></label>
      <input type="date" name="start_date" class="form-control" value="<?= e($l['start_date']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('End Date')) ?></label>
      <input type="date" name="end_date" class="form-control" value="<?= e($l['end_date']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <?php foreach (['draft','pending_signature','active','expiring','expired','terminated','renewed'] as $s): ?>
          <option value="<?= e($s) ?>" <?= $l['status']===$s?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$s))) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-md-3"><label class="form-label"><?= e(__('Monthly Rent')) ?></label>
      <input type="number" step="0.01" name="monthly_rent" class="form-control" value="<?= e((string)$l['monthly_rent']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Security Deposit')) ?></label>
      <input type="number" step="0.01" name="security_deposit" class="form-control" value="<?= e((string)$l['security_deposit']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Payment Frequency')) ?></label>
      <select name="payment_frequency" class="form-select">
        <?php foreach (['monthly','quarterly','semi_annual','annual','weekly'] as $f): ?>
          <option value="<?= e($f) ?>" <?= $l['payment_frequency']===$f?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$f))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Grace Period (days)')) ?></label>
      <input type="number" name="grace_period_days" class="form-control" value="<?= (int)$l['grace_period_days'] ?>"></div>

    <div class="col-md-3"><label class="form-label"><?= e(__('Notice Period (days)')) ?></label>
      <input type="number" name="notice_period_days" class="form-control" value="<?= (int)$l['notice_period_days'] ?>"></div>

    <div class="col-md-9">
      <label class="form-label"><?= e(__('Included Utilities & Services')) ?></label>
      <div class="d-flex flex-wrap gap-3 small">
        <?php foreach (['electricity','water','gas','internet','waste'] as $u): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="included_utilities[]" value="<?= e($u) ?>" id="iu_<?= e($u) ?>" <?= in_array($u, $incUtil, true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="iu_<?= e($u) ?>"><?= e(ucfirst($u)) ?></label>
          </div>
        <?php endforeach; ?>
        <?php foreach (['cleaning','security','parking','gym','pool'] as $s): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="included_services[]" value="<?= e($s) ?>" id="is_<?= e($s) ?>" <?= in_array($s, $incServ, true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_<?= e($s) ?>"><?= e(ucfirst($s)) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="col-12"><label class="form-label"><?= e(__('Renewal Conditions')) ?></label>
      <textarea name="renewal_conditions" class="form-control" rows="2"><?= e((string)$l['renewal_conditions']) ?></textarea></div>
    <div class="col-12"><label class="form-label"><?= e(__('Special Clauses')) ?></label>
      <textarea name="special_clauses" class="form-control" rows="3"><?= e((string)$l['special_clauses']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/leases/view.php?id=<?= (int)$id ?>" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>