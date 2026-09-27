<?php
// modules/inspections/compare.php — produces the deposit deduction comparison.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('inspections.view');
$moveOutId = (int)($_GET['move_out_id'] ?? 0);

$moveOut = db_one(
    'SELECT i.*, u.unit_number, l.id AS lease_id, l.tenant_id, l.security_deposit, l.unit_id
       FROM inspections i
       JOIN units u ON u.id = i.unit_id
       LEFT JOIN leases l ON l.unit_id = i.unit_id AND l.status IN ("expiring","expired","terminated")
      WHERE i.id = :id AND i.company_id = :c LIMIT 1',
    [':id'=>$moveOutId, ':c'=>(int)$USER['company_id']]
);
if (!$moveOut) { flash_error(__('Move-out inspection not found.')); redirect('modules/inspections/index.php'); }

// Find the corresponding move-in
$moveIn = db_one(
    'SELECT i.* FROM inspections i
      WHERE i.unit_id = :u AND i.inspection_type = "move_in" AND i.company_id = :c
      ORDER BY i.inspection_date ASC LIMIT 1',
    [':u'=>(int)$moveOut['unit_id'], ':c'=>(int)$USER['company_id']]
);

// Compare results by checklist item
$comparison = [];
if ($moveIn) {
    $mi = db_all(
        'SELECT ir.checklist_item_id, ir.condition_rating, ir.notes, ci.item_name
           FROM inspection_results ir
           LEFT JOIN inspection_checklist_items ci ON ci.id = ir.checklist_item_id
          WHERE ir.inspection_id = :i',
        [':i'=>(int)$moveIn['id']]
    );
    $mo = db_all(
        'SELECT ir.checklist_item_id, ir.condition_rating, ir.notes
           FROM inspection_results ir
          WHERE ir.inspection_id = :i',
        [':i'=>(int)$moveOutId]
    );

    $moById = [];
    foreach ($mo as $r) { $moById[(int)$r['checklist_item_id']] = $r; }

    foreach ($mi as $r) {
        $key = (int)$r['checklist_item_id'];
        $moveOutRating = $moById[$key]['condition_rating'] ?? null;
        $comparison[] = [
            'item' => $r['item_name'] ?? '—',
            'move_in' => $r['condition_rating'],
            'move_out' => $moveOutRating,
            'notes' => $moById[$key]['notes'] ?? '',
            'changed' => $moveOutRating !== null && $moveOutRating !== $r['condition_rating'],
        ];
    }
}

// Save comparison if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $moveIn && can('inspections.edit', $USER)) {
    require_csrf();
    $damage = sanitize_string($_POST['damage_notes'] ?? '', 2000) ?: null;
    $cost = $_POST['estimated_repair_cost'] !== '' ? (float)$_POST['estimated_repair_cost'] : null;
    $deduction = $_POST['deposit_deduction'] !== '' ? (float)$_POST['deposit_deduction'] : null;

    $existing = db_one('SELECT id FROM inspection_comparisons WHERE move_in_inspection_id = :mi AND move_out_inspection_id = :mo',
                       [':mi'=>(int)$moveIn['id'], ':mo'=>$moveOutId]);
    if ($existing) {
        db_update('inspection_comparisons', [
            'damage_notes'=>$damage,'estimated_repair_cost'=>$cost,'deposit_deduction'=>$deduction,
        ], 'id = :id', [':id'=>(int)$existing['id']]);
    } else {
        db_insert('inspection_comparisons', [
            'move_in_inspection_id' => (int)$moveIn['id'],
            'move_out_inspection_id' => $moveOutId,
            'damage_notes' => $damage,
            'estimated_repair_cost' => $cost,
            'deposit_deduction' => $deduction,
            'created_by' => (int)$USER['id'],
        ]);
    }
    log_audit('inspections', 'compare', 'inspections', $moveOutId, null, ['deduction'=>$deduction]);
    flash_success(__('Comparison saved.'));
    redirect('modules/inspections/compare.php?move_out_id=' . $moveOutId);
}

$existing = $moveIn ? db_one('SELECT * FROM inspection_comparisons WHERE move_in_inspection_id = :mi AND move_out_inspection_id = :mo', [':mi'=>(int)$moveIn['id'], ':mo'=>$moveOutId]) : null;

$PAGE_TITLE = __('Compare Inspections');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Compare Move-In vs Move-Out')) ?></h1>
<p class="text-muted small">
  <?= e(__('Unit')) ?>: <a href="<?= APP_URL ?>/modules/units/view.php?id=<?= (int)$moveOut['unit_id'] ?>"><?= e($moveOut['unit_number']) ?></a>
  <?php if ($moveIn): ?>
    · <?= e(__('Move-In')) ?>: <?= e($moveIn['inspection_date']) ?>
  <?php endif; ?>
  · <?= e(__('Move-Out')) ?>: <?= e($moveOut['inspection_date']) ?>
</p>

<?php if (!$moveIn): ?>
  <div class="alert alert-warning"><?= e(__('No move-in inspection found for this unit to compare against.')) ?></div>
<?php else: ?>

<form method="post">
  <?= csrf_field() ?>
  <div class="srp-card p-3 mb-3">
    <table class="table table-hover align-middle">
      <thead><tr><th><?= e(__('Item')) ?></th><th><?= e(__('Move-In')) ?></th><th><?= e(__('Move-Out')) ?></th><th><?= e(__('Change')) ?></th><th><?= e(__('Notes')) ?></th></tr></thead>
      <tbody>
        <?php foreach ($comparison as $c): ?>
          <tr class="<?= $c['changed'] ? 'table-warning' : '' ?>">
            <td><?= e($c['item']) ?></td>
            <td><?= e(ucfirst(str_replace('_',' ',$c['move_in']))) ?></td>
            <td><?= e($c['move_out'] ? ucfirst(str_replace('_',' ',$c['move_out'])) : '—') ?></td>
            <td><?= $c['changed'] ? '<i data-lucide="alert-triangle" class="text-warning"></i>' : '<i data-lucide="check" class="text-success"></i>' ?></td>
            <td><?= e($c['notes']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (can('inspections.edit')): ?>
  <div class="srp-card p-4">
    <h2 class="h6 mb-3"><?= e(__('Damage Assessment & Deposit Deduction')) ?></h2>
    <div class="row g-3">
      <div class="col-12"><label class="form-label"><?= e(__('Damage Notes')) ?></label>
        <textarea name="damage_notes" class="form-control" rows="3"><?= e((string)($existing['damage_notes'] ?? '')) ?></textarea></div>
      <div class="col-md-6"><label class="form-label"><?= e(__('Estimated Repair Cost')) ?></label>
        <input type="number" step="0.01" name="estimated_repair_cost" class="form-control" value="<?= e((string)($existing['estimated_repair_cost'] ?? '')) ?>"></div>
      <div class="col-md-6"><label class="form-label"><?= e(__('Deposit Deduction')) ?></label>
        <input type="number" step="0.01" name="deposit_deduction" class="form-control" value="<?= e((string)($existing['deposit_deduction'] ?? '')) ?>"></div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-4">
      <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Comparison')) ?></button>
    </div>
  </div>
  <?php endif; ?>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>