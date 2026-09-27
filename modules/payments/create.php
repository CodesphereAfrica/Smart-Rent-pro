<?php
// modules/payments/create.php — records a payment AND runs the automatic allocation
// algorithm inline, in the same file, inside a transaction. Users can override.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('payments.create');

$presetInvoiceId = (int)($_GET['invoice_id'] ?? 0);
$presetTenantId  = (int)($_GET['tenant_id']  ?? 0);
$presetUnitId    = (int)($_GET['unit_id']    ?? 0);

// Derive tenant from invoice if needed
if ($presetInvoiceId && !$presetTenantId) {
    $inv = db_one('SELECT tenant_id, unit_id, lease_id FROM invoices WHERE id = :i AND company_id = :c', [':i'=>$presetInvoiceId, ':c'=>(int)$USER['company_id']]);
    if ($inv) { $presetTenantId = (int)$inv['tenant_id']; $presetUnitId = (int)$inv['unit_id']; }
}

$errors = [];
$old = [
    'tenant_id' => $presetTenantId, 'unit_id' => $presetUnitId, 'lease_id' => 0,
    'amount' => '', 'payment_date' => date('Y-m-d'),
    'payment_method' => 'cash', 'payment_reference' => '', 'notes' => '',
    'apply_to_invoice_id' => $presetInvoiceId,
    'manual_allocation' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['tenant_id']            = (int)($_POST['tenant_id'] ?? 0);
    $old['unit_id']              = (int)($_POST['unit_id'] ?? 0) ?: null;
    $old['lease_id']             = (int)($_POST['lease_id'] ?? 0) ?: null;
    $old['amount']               = (float)($_POST['amount'] ?? 0);
    $old['payment_date']         = sanitize_string($_POST['payment_date'] ?? '', 20);
    $old['payment_method']       = in_array($_POST['payment_method'] ?? '', ['cash','bank_transfer','mobile_money','card','cheque','other'], true) ? $_POST['payment_method'] : 'cash';
    $old['payment_reference']    = sanitize_string($_POST['payment_reference'] ?? '', 100) ?: null;
    $old['notes']                = sanitize_string($_POST['notes'] ?? '', 500) ?: null;
    $old['apply_to_invoice_id']  = (int)($_POST['apply_to_invoice_id'] ?? 0);
    $old['manual_allocation']    = !empty($_POST['manual_allocation']) ? 1 : 0;

    if (!$old['tenant_id'])          $errors['tenant_id'] = __('Select a tenant.');
    if ($old['amount'] <= 0)         $errors['amount']    = __('Enter a positive amount.');
    if (!$old['payment_date'])       $errors['payment_date'] = __('Pick a date.');

    // Optional explicit allocations array from the UI (invoice_id => amount)
    $manualAllocs = $_POST['alloc'] ?? [];  // e.g. ['12' => '50000', '13' => '20000']

    if (!$errors) {
        db()->beginTransaction();
        try {
            $paymentId = db_insert('payments', [
                'company_id'         => (int)$USER['company_id'],
                'tenant_id'          => $old['tenant_id'],
                'unit_id'            => $old['unit_id'],
                'lease_id'           => $old['lease_id'],
                'amount'             => $old['amount'],
                'amount_unallocated' => $old['amount'],
                'payment_date'       => $old['payment_date'],
                'payment_method'     => $old['payment_method'],
                'payment_reference'  => $old['payment_reference'],
                'recorded_by'        => (int)$USER['id'],
                'status'             => 'completed',
                'notes'              => $old['notes'],
            ]);

            // ------------------------------------------------
            // ALLOCATION
            // ------------------------------------------------
            $unallocated = (float)$old['amount'];

            if ($old['manual_allocation'] && is_array($manualAllocs)) {
                // MANUAL OVERRIDE — trust the amount per invoice id.
                foreach ($manualAllocs as $invId => $amt) {
                    $invId = (int)$invId;
                    $amt   = (float)$amt;
                    if ($amt <= 0 || $unallocated <= 0) continue;

                    $inv = db_one(
                        'SELECT id, balance FROM invoices
                          WHERE id = :i AND tenant_id = :t AND company_id = :c
                            AND status IN ("upcoming","due","partially_paid","overdue")
                          LIMIT 1',
                        [':i'=>$invId, ':t'=>$old['tenant_id'], ':c'=>(int)$USER['company_id']]
                    );
                    if (!$inv) continue;

                    $apply = min($amt, (float)$inv['balance'], $unallocated);
                    if ($apply <= 0) continue;

                    db_insert('payment_allocations', [
                        'payment_id'       => $paymentId,
                        'invoice_id'       => $invId,
                        'amount_allocated' => $apply,
                        'allocation_type'  => 'manual',
                        'allocated_by'     => (int)$USER['id'],
                    ]);

                    $newPaid = (float)db_one('SELECT amount_paid FROM invoices WHERE id = :i', [':i'=>$invId])['amount_paid'] + $apply;
                    $invTotal = (float)db_one('SELECT amount FROM invoices WHERE id = :i', [':i'=>$invId])['amount'];
                    $newStatus = $newPaid >= $invTotal ? 'paid' : 'partially_paid';

                    db_update('invoices', ['amount_paid' => $newPaid, 'status' => $newStatus], 'id = :i', [':i'=>$invId]);

                    $unallocated -= $apply;
                }
            } else {
                // AUTOMATIC ALLOCATION — driven by payment_allocation_rules
                $unallocated = auto_allocate_payment($paymentId, (int)$USER['company_id'], $old['tenant_id'], (float)$old['amount'], $old['apply_to_invoice_id']);
            }

            // Update the amount_unallocated on the payment row
            db_update('payments', ['amount_unallocated' => max(0, $unallocated)], 'id = :id', [':id'=>$paymentId]);

            log_audit('payments', 'create', 'payments', $paymentId, null, $old);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[payments.create] ' . $e->getMessage());
            $errors['_'] = __('Payment could not be saved.');
        }

        if (!$errors) {
            flash_success(__('Payment recorded.'));
            redirect('modules/payments/view.php?id=' . $paymentId);
        }
    }
}

/**
 * Automatic allocation: applies a payment to outstanding invoices following the
 * configurable priority_order from payment_allocation_rules. If no rule exists,
 * falls back to oldest-first across all types.
 *
 * @return float remaining unallocated amount
 */
function auto_allocate_payment(int $paymentId, int $companyId, int $tenantId, float $totalAmount, int $preferredInvoiceId = 0): float
{
    $remaining = $totalAmount;

    // Preferred invoice first (user clicked "Record Payment" from a specific invoice)
    if ($preferredInvoiceId > 0) {
        $inv = db_one(
            'SELECT id, balance FROM invoices
              WHERE id = :i AND tenant_id = :t AND company_id = :c
                AND status IN ("upcoming","due","partially_paid","overdue")
              LIMIT 1',
            [':i'=>$preferredInvoiceId, ':t'=>$tenantId, ':c'=>$companyId]
        );
        if ($inv && (float)$inv['balance'] > 0) {
            $apply = min($remaining, (float)$inv['balance']);
            if ($apply > 0) {
                db_insert('payment_allocations', [
                    'payment_id' => $paymentId, 'invoice_id' => (int)$inv['id'],
                    'amount_allocated' => $apply, 'allocation_type' => 'automatic',
                ]);
                $total = (float)db_one('SELECT amount FROM invoices WHERE id = :i', [':i'=>$inv['id']])['amount'];
                $newPaid = (float)db_one('SELECT amount_paid FROM invoices WHERE id = :i', [':i'=>$inv['id']])['amount_paid'] + $apply;
                db_update('invoices', [
                    'amount_paid' => $newPaid,
                    'status' => $newPaid >= $total ? 'paid' : 'partially_paid',
                ], 'id = :i', [':i'=>$inv['id']]);
                $remaining -= $apply;
            }
        }
    }

    if ($remaining <= 0) return 0.0;

    // Load allocation rules for this company, sorted by priority_order
    $rules = db_all(
        'SELECT priority_order, invoice_type, oldest_first
           FROM payment_allocation_rules
          WHERE company_id = :c ORDER BY priority_order ASC',
        [':c' => $companyId]
    );

    if (!$rules) {
        // Default fallback: oldest first, all types
        $rules = [
            ['priority_order'=>1,'invoice_type'=>'late_fee','oldest_first'=>1],
            ['priority_order'=>2,'invoice_type'=>'rent','oldest_first'=>1],
            ['priority_order'=>3,'invoice_type'=>'utility','oldest_first'=>1],
            ['priority_order'=>4,'invoice_type'=>'deposit','oldest_first'=>1],
            ['priority_order'=>5,'invoice_type'=>'other','oldest_first'=>1],
        ];
    }

    foreach ($rules as $rule) {
        if ($remaining <= 0) break;

        $orderSql = ((int)$rule['oldest_first']) ? 'due_date ASC, id ASC' : 'due_date DESC, id DESC';
        $type     = $rule['invoice_type'];

        $candidates = db_all(
            "SELECT id, amount, amount_paid, balance FROM invoices
              WHERE tenant_id = :t AND company_id = :c
                AND invoice_type = :ty
                AND status IN ('upcoming','due','partially_paid','overdue')
                AND balance > 0
              ORDER BY $orderSql",
            [':t'=>$tenantId, ':c'=>$companyId, ':ty'=>$type]
        );

        foreach ($candidates as $inv) {
            if ($remaining <= 0) break;
            $apply = min($remaining, (float)$inv['balance']);
            if ($apply <= 0) continue;

            db_insert('payment_allocations', [
                'payment_id' => $paymentId, 'invoice_id' => (int)$inv['id'],
                'amount_allocated' => $apply, 'allocation_type' => 'automatic',
            ]);
            $newPaid = (float)$inv['amount_paid'] + $apply;
            db_update('invoices', [
                'amount_paid' => $newPaid,
                'status' => $newPaid >= (float)$inv['amount'] ? 'paid' : 'partially_paid',
            ], 'id = :i', [':i'=>(int)$inv['id']]);
            $remaining -= $apply;
        }
    }

    return $remaining;
}

// -----------------------------------------------------------------------------
// Meta endpoints — for AJAX fetches from this same page
// -----------------------------------------------------------------------------
if (($_GET['meta'] ?? '') === 'outstanding') {
    header('Content-Type: application/json');
    $tid = (int)($_GET['tenant_id'] ?? 0);
    if (!$tid) { echo json_encode(['invoices'=>[]]); exit; }
    $rows = db_all(
        'SELECT id, invoice_number, invoice_type, amount, amount_paid, balance, due_date, status
           FROM invoices
          WHERE tenant_id = :t AND company_id = :c AND balance > 0
            AND status IN ("upcoming","due","partially_paid","overdue")
          ORDER BY due_date ASC',
        [':t'=>$tid, ':c'=>(int)$USER['company_id']]
    );
    echo json_encode(['invoices' => $rows]);
    exit;
}

$tenants = db_all('SELECT id, first_name, last_name FROM tenants WHERE company_id = :c AND status = "active" ORDER BY first_name', [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('Record Payment');
$PAGE_SCRIPTS = ['allocation.js'];
require __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3"><?= e(__('Record Payment')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form method="post" class="srp-card p-4" id="srpPaymentForm">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label"><?= e(__('Tenant')) ?> *</label>
      <select id="selTenant" name="tenant_id" class="form-select<?= isset($errors['tenant_id'])?' is-invalid':'' ?>">
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($tenants as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= (int)$old['tenant_id']===(int)$t['id']?'selected':'' ?>><?= e($t['first_name'].' '.$t['last_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Amount')) ?> *</label>
      <input id="amountInput" type="number" step="0.01" name="amount" class="form-control<?= isset($errors['amount'])?' is-invalid':'' ?>" value="<?= e((string)$old['amount']) ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Date')) ?> *</label>
      <input type="date" name="payment_date" class="form-control" value="<?= e($old['payment_date']) ?>" required>
    </div>

    <div class="col-md-4">
      <label class="form-label"><?= e(__('Method')) ?></label>
      <select name="payment_method" class="form-select">
        <?php foreach (['cash','bank_transfer','mobile_money','card','cheque','other'] as $m): ?>
          <option value="<?= e($m) ?>" <?= $old['payment_method']===$m?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$m))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Reference')) ?></label>
      <input name="payment_reference" class="form-control" value="<?= e((string)$old['payment_reference']) ?>" placeholder="Bank ref, MoMo txn id…"></div>
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Notes')) ?></label>
      <input name="notes" class="form-control" value="<?= e((string)$old['notes']) ?>"></div>

    <div class="col-12"><hr class="my-2"></div>

    <div class="col-12">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <label class="form-label mb-0"><?= e(__('Outstanding Invoices')) ?></label>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="manual_allocation" id="manualAlloc" value="1" <?= $old['manual_allocation'] ? 'checked' : '' ?>>
          <label class="form-check-label small" for="manualAlloc"><?= e(__('Manually override allocation')) ?></label>
        </div>
      </div>
      <div id="invoiceList" class="border rounded p-3" style="background: var(--srp-surface-2)">
        <div class="text-muted small"><?= e(__('Select a tenant to load their outstanding invoices.')) ?></div>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/payments/index.php" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Payment')) ?></button>
  </div>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>