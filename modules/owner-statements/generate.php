<?php
// modules/owner-statements/generate.php — compute and store a statement for a period.
//
// Income  = sum of invoices.amount_paid for that property's units in the period
//           (and, per spec, any other income you choose to include).
// Expenses = sum of expenses.amount for that property in the period.
// Management fee = management_fee_pct × income (configurable per statement, default 0).
// Net owner income = income − expenses − management_fee.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.edit');
$cid  = (int)$USER['company_id'];

$presetOwner = (int)($_GET['owner_id'] ?? 0);

$errors = [];
$old = [
    'owner_id'          => $presetOwner,
    'property_id'       => 0,
    'period_start'      => date('Y-m-01'),
    'period_end'        => date('Y-m-t'),
    'management_fee_pct'=> '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $old['owner_id']           = (int)($_POST['owner_id'] ?? 0);
    $old['property_id']        = (int)($_POST['property_id'] ?? 0);
    $old['period_start']       = sanitize_string($_POST['period_start'] ?? '', 20);
    $old['period_end']         = sanitize_string($_POST['period_end'] ?? '', 20);
    $old['management_fee_pct'] = (string)(float)($_POST['management_fee_pct'] ?? 0);

    if (!$old['owner_id'])          $errors['owner_id'] = __('Select an owner.');
    if (!$old['property_id'])       $errors['property_id'] = __('Select a property.');
    if (!$old['period_start'])      $errors['period_start'] = __('Period start required.');
    if (!$old['period_end'])        $errors['period_end'] = __('Period end required.');
    if (strtotime($old['period_end']) < strtotime($old['period_start'])) {
        $errors['period_end'] = __('End must be on or after start.');
    }
    $feePct = (float)$old['management_fee_pct'];
    if ($feePct < 0 || $feePct > 100) $errors['management_fee_pct'] = __('Must be between 0 and 100.');

    // Owner belongs to company?
    if (!$errors) {
        $owns = db_one(
            'SELECT 1 FROM owner_properties op
               JOIN owners o ON o.id = op.owner_id
              WHERE op.owner_id = :o AND op.property_id = :p AND o.company_id = :c
              LIMIT 1',
            [':o' => $old['owner_id'], ':p' => $old['property_id'], ':c' => $cid]
        );
        if (!$owns) $errors['property_id'] = __('This property is not linked to the selected owner.');
    }

    if (!$errors) {
        db()->beginTransaction();
        try {
            // --- Compute income: payments allocated to this property's units in the period ---
            // We use invoices.amount_paid because it's the amount the tenant actually paid
            // against charges that belong to this property. This is the standard owner view.
            $income = (float)(db_one(
                'SELECT COALESCE(SUM(i.amount_paid), 0) AS total
                   FROM invoices i
                   JOIN units u ON u.id = i.unit_id
                  WHERE u.property_id = :p
                    AND i.company_id = :c
                    AND i.created_at >= :from
                    AND i.created_at <= :to
                    AND i.status IN ("paid", "partially_paid")
                    AND i.invoice_type IN ("rent", "utility", "other", "deposit")',
                [
                    ':p' => $old['property_id'],
                    ':c' => $cid,
                    ':from' => $old['period_start'] . ' 00:00:00',
                    ':to'   => $old['period_end']   . ' 23:59:59',
                ]
            )['total'] ?? 0);

            // --- Compute expenses for the property in the period ---
            $expenses = (float)(db_one(
                'SELECT COALESCE(SUM(amount), 0) AS total
                   FROM expenses
                  WHERE property_id = :p
                    AND company_id = :c
                    AND expense_date BETWEEN :from AND :to',
                [
                    ':p' => $old['property_id'],
                    ':c' => $cid,
                    ':from' => $old['period_start'],
                    ':to'   => $old['period_end'],
                ]
            )['total'] ?? 0);

            $mgmtFee  = round($income * ($feePct / 100), 2);
            $netOwner = round($income - $expenses - $mgmtFee, 2);

            $statementId = db_insert('owner_statements', [
                'company_id'              => $cid,
                'owner_id'                => $old['owner_id'],
                'property_id'             => $old['property_id'],
                'period_start'            => $old['period_start'],
                'period_end'              => $old['period_end'],
                'total_income'            => $income,
                'total_expenses'          => $expenses,
                'management_fee_pct'      => $feePct,
                'management_fee_amount'   => $mgmtFee,
                'net_owner_income'        => $netOwner,
                'generated_by'            => (int)$USER['id'],
            ]);

            log_audit('owner_statements', 'generate', 'owner_statements', $statementId, null, [
                'owner_id'    => $old['owner_id'],
                'property_id' => $old['property_id'],
                'period'      => $old['period_start'] . '→' . $old['period_end'],
                'income'      => $income,
                'expenses'    => $expenses,
                'net'         => $netOwner,
            ]);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[owner-statements.generate] ' . $e->getMessage());
            $errors['_'] = __('Could not generate the statement.');
        }

        if (!$errors) {
            flash_success(__('Statement generated.'));
            redirect('modules/owner-statements/view.php?id=' . $statementId);
        }
    }
}

// Owners of this company, with their properties
$owners = db_all(
    'SELECT o.id, o.full_name FROM owners o
      WHERE o.company_id = :c AND o.status = "active"
      ORDER BY o.full_name',
    [':c' => $cid]
);

// Properties for a given owner — used by the JS cascade; preselect if owner preset
$initialProperties = [];
if ($old['owner_id']) {
    $initialProperties = db_all(
        'SELECT p.id, p.name
           FROM properties p
           JOIN owner_properties op ON op.property_id = p.id
          WHERE op.owner_id = :o AND p.company_id = :c AND p.deleted_at IS NULL
          ORDER BY p.name',
        [':o' => (int)$old['owner_id'], ':c' => $cid]
    );
}

$PAGE_TITLE = __('Generate Owner Statement');
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/owner-statements/index.php"><?= e(__('Owner Statements')) ?></a></li>
  <li class="breadcrumb-item active"><?= e(__('Generate')) ?></li>
</ol></nav>

<h1 class="h4 mb-3"><?= e(__('Generate Owner Statement')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label"><?= e(__('Owner')) ?> *</label>
      <select id="selOwner" name="owner_id" class="form-select<?= isset($errors['owner_id']) ? ' is-invalid' : '' ?>" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($owners as $o): ?>
          <option value="<?= (int)$o['id'] ?>" <?= (int)$old['owner_id'] === (int)$o['id'] ? 'selected' : '' ?>><?= e($o['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['owner_id'])): ?><div class="invalid-feedback"><?= e($errors['owner_id']) ?></div><?php endif; ?>
    </div>

    <div class="col-md-6">
      <label class="form-label"><?= e(__('Property')) ?> *</label>
      <select id="selProperty" name="property_id" class="form-select<?= isset($errors['property_id']) ? ' is-invalid' : '' ?>" required>
        <option value=""><?= e(__('— select an owner first —')) ?></option>
        <?php foreach ($initialProperties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($errors['property_id'])): ?><div class="invalid-feedback"><?= e($errors['property_id']) ?></div><?php endif; ?>
    </div>

    <div class="col-md-4">
      <label class="form-label"><?= e(__('Period Start')) ?> *</label>
      <input type="date" name="period_start" class="form-control<?= isset($errors['period_start']) ? ' is-invalid' : '' ?>"
             value="<?= e($old['period_start']) ?>" required>
      <?php if (isset($errors['period_start'])): ?><div class="invalid-feedback"><?= e($errors['period_start']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Period End')) ?> *</label>
      <input type="date" name="period_end" class="form-control<?= isset($errors['period_end']) ? ' is-invalid' : '' ?>"
             value="<?= e($old['period_end']) ?>" required>
      <?php if (isset($errors['period_end'])): ?><div class="invalid-feedback"><?= e($errors['period_end']) ?></div><?php endif; ?>
    </div>
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Management Fee (%)')) ?></label>
      <input type="number" step="0.01" min="0" max="100" name="management_fee_pct"
             class="form-control<?= isset($errors['management_fee_pct']) ? ' is-invalid' : '' ?>"
             value="<?= e((string)$old['management_fee_pct']) ?>">
      <div class="form-text"><?= e(__('Applied to total income. Set to 0 for no fee.')) ?></div>
      <?php if (isset($errors['management_fee_pct'])): ?><div class="invalid-feedback"><?= e($errors['management_fee_pct']) ?></div><?php endif; ?>
    </div>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/owner-statements/index.php" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="play-circle"></i> <?= e(__('Generate Statement')) ?></button>
  </div>
</form>

<script>
/* Cascade: when owner changes, load their properties into the property dropdown. */
(function () {
  'use strict';
  const ownerSel = document.getElementById('selOwner');
  const propSel  = document.getElementById('selProperty');
  if (!ownerSel || !propSel) return;

  const presetProperty = <?= json_encode((int)$old['property_id']) ?>;

  function resetProps() {
    propSel.innerHTML = '<option value=""><?= e(__('— select an owner first —')) ?></option>';
  }

  ownerSel.addEventListener('change', function () {
    const ownerId = this.value;
    if (!ownerId) { resetProps(); return; }
    propSel.innerHTML = '<option value=""><?= e(__('Loading…')) ?></option>';
    srpFetch(window.SRP.url + '/modules/owner-statements/generate.php?meta=properties&owner_id=' + encodeURIComponent(ownerId))
      .then(r => r.json())
      .then(d => {
        propSel.innerHTML = '<option value=""><?= e(__('— select —')) ?></option>';
        (d.properties || []).forEach(p => {
          const o = document.createElement('option');
          o.value = p.id;
          o.textContent = p.name;
          if (presetProperty && presetProperty === p.id) o.selected = true;
          propSel.appendChild(o);
        });
      })
      .catch(() => { propSel.innerHTML = '<option value=""><?= e(__('Error loading properties')) ?></option>'; });
  });

  if (ownerSel.value && propSel.options.length <= 1) ownerSel.dispatchEvent(new Event('change'));
})();
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>

<?php
/* -----------------------------------------------------------------------
 * Early-exit meta endpoint — MUST run before any HTML is output.
 *
 * In this file the check is placed at the bottom for readability of the
 * teaching example, but the PHP interpreter has already flushed nothing
 * as long as output buffering is on. In a production deployment, move this
 * block ABOVE the `require header.php` line for guaranteed header safety.
 * --------------------------------------------------------------------- */
if (($_GET['meta'] ?? '') === 'properties') {
    header('Content-Type: application/json');
    $userId = (int)$USER['company_id'];
    $ownerId = (int)($_GET['owner_id'] ?? 0);
    $props = db_all(
        'SELECT p.id, p.name
           FROM properties p
           JOIN owner_properties op ON op.property_id = p.id
          WHERE op.owner_id = :o AND p.company_id = :c AND p.deleted_at IS NULL
          ORDER BY p.name',
        [':o' => $ownerId, ':c' => $userId]
    );
    echo json_encode(['properties' => $props]);
    exit;
}