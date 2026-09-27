<?php
// modules/leases/create.php — self-contained create. Handles the full lease record,
// schedule generation, unit status update, and audit — all inline.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('leases.create');

$presetUnit = (int)($_GET['unit_id'] ?? 0);
$presetTenant = (int)($_GET['tenant_id'] ?? 0);

$errors = [];
$old = [
    'property_id' => 0, 'unit_id' => $presetUnit, 'tenant_id' => $presetTenant,
    'lease_number' => '', 'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+1 year -1 day')),
    'monthly_rent' => '', 'security_deposit' => '',
    'payment_frequency' => 'monthly', 'grace_period_days' => 0,
    'notice_period_days' => 30,
    'included_utilities' => [], 'included_services' => [],
    'renewal_conditions' => '', 'special_clauses' => '',
    'status' => 'draft',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $old['property_id']         = (int)($_POST['property_id'] ?? 0);
    $old['unit_id']             = (int)($_POST['unit_id'] ?? 0);
    $old['tenant_id']           = (int)($_POST['tenant_id'] ?? 0);
    $old['lease_number']        = sanitize_string($_POST['lease_number'] ?? '', 40);
    $old['start_date']          = sanitize_string($_POST['start_date'] ?? '', 20);
    $old['end_date']            = sanitize_string($_POST['end_date'] ?? '', 20);
    $old['monthly_rent']        = (float)($_POST['monthly_rent'] ?? 0);
    $old['security_deposit']    = (float)($_POST['security_deposit'] ?? 0);
    $old['payment_frequency']   = in_array($_POST['payment_frequency'] ?? '', ['monthly','quarterly','semi_annual','annual','weekly'], true) ? $_POST['payment_frequency'] : 'monthly';
    $old['grace_period_days']   = max(0, (int)($_POST['grace_period_days'] ?? 0));
    $old['notice_period_days']  = max(0, (int)($_POST['notice_period_days'] ?? 30));
    $old['included_utilities']  = array_values(array_filter((array)($_POST['included_utilities'] ?? []), 'is_string'));
    $old['included_services']   = array_values(array_filter((array)($_POST['included_services'] ?? []), 'is_string'));
    $old['renewal_conditions']  = sanitize_string($_POST['renewal_conditions'] ?? '', 2000) ?: null;
    $old['special_clauses']     = sanitize_string($_POST['special_clauses'] ?? '', 3000) ?: null;
    $old['status']              = in_array($_POST['status'] ?? '', ['draft','pending_signature','active'], true) ? $_POST['status'] : 'draft';

    // Auto lease number if blank
    if ($old['lease_number'] === '') {
        $old['lease_number'] = 'LS-' . date('Ymd') . '-' . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    if (!$old['property_id'])     $errors['property_id'] = __('Select a property.');
    if (!$old['unit_id'])         $errors['unit_id']     = __('Select a unit.');
    if (!$old['tenant_id'])       $errors['tenant_id']   = __('Select a tenant.');
    if ($old['monthly_rent'] <= 0) $errors['monthly_rent'] = __('Enter a valid rent amount.');
    if (!$old['start_date'] || !$old['end_date']) $errors['start_date'] = __('Start and end dates required.');
    if (strtotime($old['end_date']) <= strtotime($old['start_date'])) $errors['end_date'] = __('End date must be after start.');

    // Verify unit belongs to company + property matches, and unit has no active lease
    $unit = db_one(
        'SELECT u.* FROM units u JOIN properties p ON p.id = u.property_id
          WHERE u.id = :u AND u.company_id = :c LIMIT 1',
        [':u'=>$old['unit_id'], ':c'=>(int)$USER['company_id']]
    );
    if (!$unit) $errors['unit_id'] = __('Invalid unit.');
    elseif ((int)$unit['property_id'] !== (int)$old['property_id']) $errors['property_id'] = __('Property does not match the selected unit.');
    else {
        $existingLease = db_one(
            'SELECT id FROM leases WHERE unit_id = :u AND status IN ("active","expiring") LIMIT 1',
            [':u'=>$old['unit_id']]
        );
        if ($existingLease) $errors['unit_id'] = __('Unit already has an active lease.');
    }

    // Duplicate lease number?
    $dup = db_one('SELECT id FROM leases WHERE company_id = :c AND lease_number = :n', [':c'=>(int)$USER['company_id'], ':n'=>$old['lease_number']]);
    if ($dup) $errors['lease_number'] = __('Lease number already exists.');

    if (!$errors) {
        db()->beginTransaction();
        try {
            $id = db_insert('leases', [
                'company_id' => (int)$USER['company_id'],
                'property_id' => $old['property_id'],
                'unit_id' => $old['unit_id'],
                'tenant_id' => $old['tenant_id'],
                'lease_number' => $old['lease_number'],
                'start_date' => $old['start_date'],
                'end_date' => $old['end_date'],
                'monthly_rent' => $old['monthly_rent'],
                'security_deposit' => $old['security_deposit'],
                'payment_frequency' => $old['payment_frequency'],
                'grace_period_days' => $old['grace_period_days'],
                'included_utilities' => $old['included_utilities'] ? json_encode($old['included_utilities']) : null,
                'included_services'  => $old['included_services']  ? json_encode($old['included_services'])  : null,
                'renewal_conditions' => $old['renewal_conditions'],
                'notice_period_days' => $old['notice_period_days'],
                'special_clauses'    => $old['special_clauses'],
                'status'             => $old['status'],
                'created_by'         => (int)$USER['id'],
            ]);

            // Create the rent schedule row
            db_insert('rent_schedules', [
                'lease_id' => $id,
                'due_day'  => 1,
                'frequency' => $old['payment_frequency'],
                'next_generation_date' => $old['start_date'],
                'is_active' => 1,
            ]);

            // Mark unit occupied if lease is active
            if ($old['status'] === 'active') {
                db_update('units', ['status' => 'occupied'], 'id = :id', [':id' => $old['unit_id']]);
                db_update('leases', ['signed_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]);
            } else {
                db_update('units', ['status' => 'reserved'], 'id = :id', [':id' => $old['unit_id']]);
            }

            // Lease occupants — the primary tenant
            db_insert('lease_occupants', ['lease_id' => $id, 'tenant_id' => $old['tenant_id'], 'is_primary' => 1]);

            // Lease expiry reminders placeholder rows (90/60/30/14/7) — will be dispatched by cron
            foreach ([90, 60, 30, 14, 7] as $d) {
                db_insert('lease_expiry_reminders', ['lease_id' => $id, 'days_before' => $d, 'sent_at' => null]);
            }

            log_audit('leases', 'create', 'leases', $id, null, $old);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[leases.create] ' . $e->getMessage());
            flash_error(__('Could not save lease.'));
            $errors['_'] = true;
        }

        if (!$errors) {
            flash_success(__('Lease created.'));
            redirect('modules/leases/view.php?id=' . $id);
        }
    }
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name', [':c'=>(int)$USER['company_id']]);
$units = $old['property_id']
    ? db_all('SELECT id, unit_number, base_rent, security_deposit FROM units WHERE property_id = :p AND status IN ("available","reserved") ORDER BY unit_number', [':p'=>$old['property_id']])
    : [];
$tenants = db_all('SELECT id, first_name, last_name FROM tenants WHERE company_id = :c AND status = "active" ORDER BY first_name', [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('New Lease');

if (($_GET['cascade'] ?? '') === 'units') {
    header('Content-Type: application/json');
    $rows = db_all(
        'SELECT id, unit_number, base_rent, security_deposit FROM units
          WHERE property_id = :p AND property_id IN (SELECT id FROM properties WHERE company_id = :c)
            AND status IN ("available","reserved")
          ORDER BY unit_number',
        [':p' => (int)$_GET['property_id'], ':c' => (int)$USER['company_id']]
    );
    echo json_encode(['units' => $rows]); exit;
}
require __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3"><?= e(__('New Lease')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>

<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Property')) ?> *</label>
      <select id="selProperty" name="property_id" class="form-select<?= isset($errors['property_id'])?' is-invalid':'' ?>">
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['property_id'])): ?><div class="invalid-feedback"><?= e($errors['property_id']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Unit')) ?> *</label>
      <select id="selUnit" name="unit_id" class="form-select<?= isset($errors['unit_id'])?' is-invalid':'' ?>">
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>" data-rent="<?= e((string)$u['base_rent']) ?>" data-deposit="<?= e((string)$u['security_deposit']) ?>" <?= (int)$old['unit_id']===(int)$u['id']?'selected':'' ?>>
            <?= e($u['unit_number']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['unit_id'])): ?><div class="invalid-feedback"><?= e($errors['unit_id']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Tenant')) ?> *</label>
      <select name="tenant_id" class="form-select<?= isset($errors['tenant_id'])?' is-invalid':'' ?>">
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($tenants as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= (int)$old['tenant_id']===(int)$t['id']?'selected':'' ?>><?= e($t['first_name'].' '.$t['last_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['tenant_id'])): ?><div class="invalid-feedback"><?= e($errors['tenant_id']) ?></div><?php endif; ?>
    </div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Lease Number')) ?></label>
      <input name="lease_number" class="form-control<?= isset($errors['lease_number'])?' is-invalid':'' ?>" value="<?= e($old['lease_number']) ?>" placeholder="auto if blank">
      <?php if (isset($errors['lease_number'])): ?><div class="invalid-feedback"><?= e($errors['lease_number']) ?></div><?php endif; ?></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Start Date')) ?> *</label>
      <input type="date" name="start_date" class="form-control<?= isset($errors['start_date'])?' is-invalid':'' ?>" value="<?= e($old['start_date']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('End Date')) ?> *</label>
      <input type="date" name="end_date" class="form-control<?= isset($errors['end_date'])?' is-invalid':'' ?>" value="<?= e($old['end_date']) ?>" required>
      <?php if (isset($errors['end_date'])): ?><div class="invalid-feedback"><?= e($errors['end_date']) ?></div><?php endif; ?></div>

    <div class="col-md-3"><label class="form-label"><?= e(__('Monthly Rent')) ?> *</label>
      <input id="rentInput" type="number" step="0.01" name="monthly_rent" class="form-control<?= isset($errors['monthly_rent'])?' is-invalid':'' ?>" value="<?= e((string)$old['monthly_rent']) ?>" required>
      <?php if (isset($errors['monthly_rent'])): ?><div class="invalid-feedback"><?= e($errors['monthly_rent']) ?></div><?php endif; ?></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Security Deposit')) ?></label>
      <input id="depositInput" type="number" step="0.01" name="security_deposit" class="form-control" value="<?= e((string)$old['security_deposit']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Payment Frequency')) ?></label>
      <select name="payment_frequency" class="form-select">
        <?php foreach (['monthly','quarterly','semi_annual','annual','weekly'] as $f): ?>
          <option value="<?= e($f) ?>" <?= $old['payment_frequency']===$f?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$f))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
      <select name="status" class="form-select">
        <option value="draft" <?= $old['status']==='draft'?'selected':'' ?>><?= e(__('Draft')) ?></option>
        <option value="pending_signature" <?= $old['status']==='pending_signature'?'selected':'' ?>><?= e(__('Pending Signature')) ?></option>
        <option value="active" <?= $old['status']==='active'?'selected':'' ?>><?= e(__('Active')) ?></option>
      </select></div>

    <div class="col-md-3"><label class="form-label"><?= e(__('Grace Period (days)')) ?></label>
      <input type="number" name="grace_period_days" class="form-control" value="<?= (int)$old['grace_period_days'] ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Notice Period (days)')) ?></label>
      <input type="number" name="notice_period_days" class="form-control" value="<?= (int)$old['notice_period_days'] ?>"></div>

    <div class="col-md-6">
      <label class="form-label"><?= e(__('Included Utilities')) ?></label>
      <div class="d-flex flex-wrap gap-3 small">
        <?php foreach (['electricity','water','gas','internet','waste'] as $u): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="included_utilities[]" value="<?= e($u) ?>" id="iu_<?= e($u) ?>"
                   <?= in_array($u, $old['included_utilities'], true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="iu_<?= e($u) ?>"><?= e(ucfirst($u)) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-md-6">
      <label class="form-label"><?= e(__('Included Services')) ?></label>
      <div class="d-flex flex-wrap gap-3 small">
        <?php foreach (['cleaning','security','parking','gym','pool'] as $s): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="included_services[]" value="<?= e($s) ?>" id="is_<?= e($s) ?>"
                   <?= in_array($s, $old['included_services'], true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_<?= e($s) ?>"><?= e(ucfirst($s)) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="col-12"><label class="form-label"><?= e(__('Renewal Conditions')) ?></label>
      <textarea name="renewal_conditions" class="form-control" rows="2"><?= e((string)$old['renewal_conditions']) ?></textarea></div>
    <div class="col-12"><label class="form-label"><?= e(__('Special Clauses')) ?></label>
      <textarea name="special_clauses" class="form-control" rows="3"><?= e((string)$old['special_clauses']) ?></textarea></div>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/leases/index.php" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Lease')) ?></button>
  </div>
</form>

<script>
(function () {
  const propSel = document.getElementById('selProperty');
  const unitSel = document.getElementById('selUnit');
  const rentInput = document.getElementById('rentInput');
  const depositInput = document.getElementById('depositInput');

  propSel.addEventListener('change', function () {
    unitSel.innerHTML = '<option value="">— select —</option>';
    if (!this.value) return;
    srpFetch(window.SRP.url + '/modules/leases/create.php?cascade=units&property_id=' + this.value)
      .then(r => r.json())
      .then(d => {
        (d.units || []).forEach(u => {
          const o = document.createElement('option');
          o.value = u.id;
          o.dataset.rent = u.base_rent;
          o.dataset.deposit = u.security_deposit;
          o.textContent = u.unit_number;
          unitSel.appendChild(o);
        });
      });
  });

  unitSel.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (!opt || !opt.value) return;
    if (!rentInput.value) rentInput.value = opt.dataset.rent || '';
    if (!depositInput.value) depositInput.value = opt.dataset.deposit || '';
  });
})();
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

<?php
// Early-exit cascade endpoint. Placed after the header in this teaching sample;
// in production, move ABOVE the header include.
if (($_GET['cascade'] ?? '') === 'units') {
    // (Early exit needs to happen before output; shown here for brevity — see note.)
}