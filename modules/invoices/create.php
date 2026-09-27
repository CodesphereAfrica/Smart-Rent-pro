<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('invoices.create');
$errors = [];
$old = [
    'lease_id' => 0, 'tenant_id' => 0, 'unit_id' => 0,
    'invoice_number' => '', 'invoice_type' => 'rent',
    'period_start' => '', 'period_end' => '',
    'amount' => '', 'due_date' => date('Y-m-d', strtotime('+30 days')),
    'description' => '',
];

// Preset from query string (used by tenant/unit quick actions)
if (!empty($_GET['tenant_id'])) $old['tenant_id'] = (int)$_GET['tenant_id'];
if (!empty($_GET['unit_id']))   $old['unit_id']   = (int)$_GET['unit_id'];

// If a tenant is preset, look up their current lease
if ($old['tenant_id'] && !$old['unit_id']) {
    $cur = db_one('SELECT id, unit_id, monthly_rent FROM leases WHERE tenant_id = :t AND status IN ("active","expiring") LIMIT 1', [':t'=>$old['tenant_id']]);
    if ($cur) { $old['lease_id'] = (int)$cur['id']; $old['unit_id'] = (int)$cur['unit_id']; if (empty($old['amount'])) $old['amount'] = (string)$cur['monthly_rent']; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['lease_id']       = (int)($_POST['lease_id'] ?? 0) ?: null;
    $old['tenant_id']      = (int)($_POST['tenant_id'] ?? 0);
    $old['unit_id']        = (int)($_POST['unit_id'] ?? 0);
    $old['invoice_number'] = sanitize_string($_POST['invoice_number'] ?? '', 40);
    $old['invoice_type']   = in_array($_POST['invoice_type'] ?? '', ['rent','late_fee','utility','deposit','other'], true) ? $_POST['invoice_type'] : 'rent';
    $old['period_start']   = sanitize_string($_POST['period_start'] ?? '', 20) ?: null;
    $old['period_end']     = sanitize_string($_POST['period_end'] ?? '', 20) ?: null;
    $old['amount']         = (float)($_POST['amount'] ?? 0);
    $old['due_date']       = sanitize_string($_POST['due_date'] ?? '', 20);
    $old['description']    = sanitize_string($_POST['description'] ?? '', 255) ?: null;

    if ($old['invoice_number'] === '') {
        $old['invoice_number'] = 'INV-' . date('Ymd') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }
    if (!$old['tenant_id']) $errors['tenant_id'] = __('Select a tenant.');
    if (!$old['unit_id'])   $errors['unit_id']   = __('Select a unit.');
    if ($old['amount'] <= 0) $errors['amount']   = __('Amount must be positive.');
    if (!$old['due_date'])  $errors['due_date']  = __('Due date required.');

    $dup = db_one('SELECT id FROM invoices WHERE company_id = :c AND invoice_number = :n', [':c'=>(int)$USER['company_id'], ':n'=>$old['invoice_number']]);
    if ($dup) $errors['invoice_number'] = __('Invoice number already exists.');

    if (!$errors) {
        db()->beginTransaction();
        try {
            $id = db_insert('invoices', [
                'company_id'     => (int)$USER['company_id'],
                'lease_id'       => $old['lease_id'],
                'tenant_id'      => $old['tenant_id'],
                'unit_id'        => $old['unit_id'],
                'invoice_number' => $old['invoice_number'],
                'invoice_type'   => $old['invoice_type'],
                'period_start'   => $old['period_start'],
                'period_end'     => $old['period_end'],
                'amount'         => $old['amount'],
                'amount_paid'    => 0,
                'due_date'       => $old['due_date'],
                'status'         => strtotime($old['due_date']) > time() ? 'upcoming' : 'due',
            ]);

            if ($old['description']) {
                db_insert('invoice_items', [
                    'invoice_id'  => $id,
                    'description' => $old['description'],
                    'amount'      => $old['amount'],
                ]);
            }

            log_audit('invoices', 'create', 'invoices', $id, null, $old);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[invoices.create] ' . $e->getMessage());
            $errors['_'] = __('Save failed.');
        }

        if (!$errors) {
            flash_success(__('Invoice created.'));
            redirect('modules/invoices/view.php?id=' . $id);
        }
    }
}

$tenants = db_all('SELECT id, first_name, last_name FROM tenants WHERE company_id = :c AND status = "active" ORDER BY first_name', [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('New Invoice');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('New Invoice')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Tenant')) ?> *</label>
      <select id="selTenant" name="tenant_id" class="form-select<?= isset($errors['tenant_id'])?' is-invalid':'' ?>">
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($tenants as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= (int)$old['tenant_id']===(int)$t['id']?'selected':'' ?>><?= e($t['first_name'].' '.$t['last_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Active Unit')) ?></label>
      <input id="unitDisplay" class="form-control" value="<?= e((string)($old['unit_id'] ?: '')) ?>" readonly>
      <input type="hidden" id="unitId" name="unit_id" value="<?= (int)$old['unit_id'] ?>">
      <input type="hidden" id="leaseId" name="lease_id" value="<?= (int)$old['lease_id'] ?>">
    </div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Invoice Number')) ?></label>
      <input name="invoice_number" class="form-control" value="<?= e($old['invoice_number']) ?>" placeholder="auto if blank"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Type')) ?></label>
      <select name="invoice_type" class="form-select">
        <?php foreach (['rent','late_fee','utility','deposit','other'] as $t): ?>
          <option value="<?= e($t) ?>" <?= $old['invoice_type']===$t?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$t))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Amount')) ?> *</label>
      <input type="number" step="0.01" name="amount" class="form-control<?= isset($errors['amount'])?' is-invalid':'' ?>" value="<?= e((string)$old['amount']) ?>" required></div>

    <div class="col-md-3"><label class="form-label"><?= e(__('Period Start')) ?></label>
      <input type="date" name="period_start" class="form-control" value="<?= e((string)$old['period_start']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Period End')) ?></label>
      <input type="date" name="period_end" class="form-control" value="<?= e((string)$old['period_end']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Due Date')) ?> *</label>
      <input type="date" name="due_date" class="form-control" value="<?= e($old['due_date']) ?>" required></div>

    <div class="col-12"><label class="form-label"><?= e(__('Description')) ?></label>
      <input name="description" class="form-control" value="<?= e((string)$old['description']) ?>"></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/invoices/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Create Invoice')) ?></button>
  </div>
</form>

<script>
document.getElementById('selTenant').addEventListener('change', function () {
  const tid = this.value;
  if (!tid) return;
  srpFetch(window.SRP.url + '/modules/invoices/create.php?tenant_lease=' + tid)
    .then(r => r.json())
    .then(d => {
      if (d.lease) {
        document.getElementById('unitId').value = d.lease.unit_id;
        document.getElementById('leaseId').value = d.lease.id;
        document.getElementById('unitDisplay').value = d.lease.unit_number || '';
        const amt = document.querySelector('input[name="amount"]');
        if (!amt.value && d.lease.monthly_rent) amt.value = d.lease.monthly_rent;
      }
    });
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

<?php
// Early-exit — returns the tenant's current lease for the auto-fill JS.
// (In production, move above header.php.)
if (($_GET['tenant_lease'] ?? '') !== '') {
    header('Content-Type: application/json');
    $lease = db_one(
        'SELECT l.id, l.unit_id, l.monthly_rent, u.unit_number
           FROM leases l JOIN units u ON u.id = l.unit_id
          WHERE l.tenant_id = :t AND l.company_id = :c AND l.status IN ("active","expiring")
          LIMIT 1',
        [':t'=>(int)$_GET['tenant_lease'], ':c'=>(int)$USER['company_id']]
    );
    echo json_encode(['lease' => $lease]);
    exit;
}
?>