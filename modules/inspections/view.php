<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('inspections.view');
$id = (int)($_GET['id'] ?? 0);
$i = db_one(
    'SELECT i.*, u.unit_number, p.name AS property_name, p.id AS property_id,
            CONCAT(us.first_name," ",us.last_name) AS inspector_name,
            cl.name AS checklist_name
       FROM inspections i
       JOIN units u ON u.id = i.unit_id
       JOIN properties p ON p.id = i.property_id
       JOIN users us ON us.id = i.inspector_id
       LEFT JOIN inspection_checklists cl ON cl.id = i.checklist_id
      WHERE i.id = :id AND i.company_id = :c LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$i) { flash_error(__('Inspection not found.')); redirect('modules/inspections/index.php'); }

// Handle inline result update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('inspections.edit', $USER)) {
    require_csrf();
    $results = $_POST['results'] ?? [];
    db()->beginTransaction();
    try {
        foreach ((array)$results as $resultId => $r) {
            $resultId = (int)$resultId;
            $rating = in_array($r['rating'] ?? '', ['excellent','good','fair','poor','damaged','not_applicable'], true) ? $r['rating'] : 'good';
            db_update('inspection_results', [
                'condition_rating' => $rating,
                'notes'            => sanitize_string($r['notes'] ?? '', 500) ?: null,
            ], 'id = :id AND inspection_id = :insp', [':id'=>$resultId, ':insp'=>$id]);
        }

        db_update('inspections', [
            'status' => in_array($_POST['status'] ?? '', ['scheduled','completed','cancelled'], true) ? $_POST['status'] : $i['status'],
            'meter_readings' => $_POST['meter_readings'] ? json_encode($_POST['meter_readings']) : $i['meter_readings'],
            'notes' => sanitize_string($_POST['notes'] ?? '', 3000) ?: null,
        ], 'id = :id', [':id' => $id]);

        log_audit('inspections', 'update_results', 'inspections', $id, $i, ['status'=>$_POST['status'] ?? null]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        error_log('[inspection.results] ' . $e->getMessage());
        flash_error(__('Save failed.'));
    }
    flash_success(__('Inspection updated.'));
    redirect('modules/inspections/view.php?id=' . $id);
}

$results = db_all(
    'SELECT ir.id, ir.condition_rating, ir.notes, ci.item_name, ci.category
       FROM inspection_results ir
       LEFT JOIN inspection_checklist_items ci ON ci.id = ir.checklist_item_id
      WHERE ir.inspection_id = :i ORDER BY ci.sort_order, ir.id',
    [':i' => $id]
);

$PAGE_TITLE = __('Inspection') . ' #' . $id;
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/inspections/index.php"><?= e(__('Inspections')) ?></a></li>
  <li class="breadcrumb-item active">#<?= (int)$id ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e(ucfirst(str_replace('_',' ',$i['inspection_type']))) ?> <?= e(__('Inspection')) ?>
      <span class="srp-badge-pill badge-status-<?= $i['status']==='completed'?'available':($i['status']==='cancelled'?'unavailable':'reserved') ?> ms-2">
        <span class="dot"></span><?= e(ucfirst($i['status'])) ?>
      </span>
    </h1>
    <div class="text-muted small">
      <a href="<?= APP_URL ?>/modules/units/view.php?id=<?= (int)$i['unit_id'] ?>"><?= e($i['unit_number']) ?></a> ·
      <?= e($i['property_name']) ?> · <?= e($i['inspection_date']) ?> · <?= e($i['inspector_name']) ?>
    </div>
  </div>
  <?php if ($i['inspection_type'] === 'move_out' && can('inspections.edit')): ?>
    <a class="btn btn-outline-primary" href="<?= APP_URL ?>/modules/inspections/compare.php?move_out_id=<?= (int)$id ?>"><i data-lucide="git-compare"></i> <?= e(__('Compare to Move-In')) ?></a>
  <?php endif; ?>
</div>

<form method="post">
  <?= csrf_field() ?>
  <div class="srp-card p-4 mb-3">
    <h2 class="h6 mb-3"><?= e(__('Checklist Results')) ?></h2>
    <?php if (!$results): ?>
      <div class="text-muted small"><?= e(__('No checklist attached. Add one via the create form to capture item-level results.')) ?></div>
    <?php else: ?>
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th><?= e(__('Category')) ?></th><th><?= e(__('Item')) ?></th><th style="width:180px"><?= e(__('Condition')) ?></th><th><?= e(__('Notes')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
          <tr>
            <td><?= e($r['category'] ?? '—') ?></td>
            <td><?= e($r['item_name'] ?? '—') ?></td>
            <td>
              <select name="results[<?= (int)$r['id'] ?>][rating]" class="form-select form-select-sm">
                <?php foreach (['excellent','good','fair','poor','damaged','not_applicable'] as $rating): ?>
                  <option value="<?= e($rating) ?>" <?= $r['condition_rating']===$rating?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$rating))) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input name="results[<?= (int)$r['id'] ?>][notes]" class="form-control form-control-sm" value="<?= e((string)$r['notes']) ?>"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="srp-card p-4 mb-3">
    <h2 class="h6 mb-3"><?= e(__('Meter Readings')) ?></h2>
    <div class="row g-3">
      <div class="col-md-4"><label class="form-label"><?= e(__('Electricity')) ?></label>
        <input name="meter_readings[electricity]" class="form-control" value="<?= e((string)(json_decode((string)$i['meter_readings'], true)['electricity'] ?? '')) ?>"></div>
      <div class="col-md-4"><label class="form-label"><?= e(__('Water')) ?></label>
        <input name="meter_readings[water]" class="form-control" value="<?= e((string)(json_decode((string)$i['meter_readings'], true)['water'] ?? '')) ?>"></div>
      <div class="col-md-4"><label class="form-label"><?= e(__('Gas')) ?></label>
        <input name="meter_readings[gas]" class="form-control" value="<?= e((string)(json_decode((string)$i['meter_readings'], true)['gas'] ?? '')) ?>"></div>
    </div>
  </div>

  <div class="srp-card p-4 mb-3">
    <h2 class="h6 mb-3"><?= e(__('Notes & Status')) ?></h2>
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label"><?= e(__('Status')) ?></label>
        <select name="status" class="form-select">
          <?php foreach (['scheduled','completed','cancelled'] as $s): ?>
            <option value="<?= e($s) ?>" <?= $i['status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-12"><label class="form-label"><?= e(__('Notes')) ?></label>
        <textarea name="notes" class="form-control" rows="3"><?= e((string)$i['notes']) ?></textarea></div>
    </div>
  </div>

  <?php if (can('inspections.edit')): ?>
    <div class="d-flex justify-content-end gap-2">
      <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Inspection')) ?></button>
    </div>
  <?php endif; ?>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>