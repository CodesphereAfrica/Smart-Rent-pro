<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('inspections.create');
$errors = [];
$old = [
    'property_id' => 0, 'unit_id' => (int)($_GET['unit_id'] ?? 0),
    'lease_id' => 0,
    'checklist_id' => 0,
    'inspection_type' => 'routine',
    'inspection_date' => date('Y-m-d'),
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['property_id']     = (int)($_POST['property_id'] ?? 0);
    $old['unit_id']         = (int)($_POST['unit_id'] ?? 0);
    $old['lease_id']        = (int)($_POST['lease_id'] ?? 0) ?: null;
    $old['checklist_id']    = (int)($_POST['checklist_id'] ?? 0) ?: null;
    $old['inspection_type'] = in_array($_POST['inspection_type'] ?? '', ['move_in','move_out','routine','maintenance','safety'], true) ? $_POST['inspection_type'] : 'routine';
    $old['inspection_date'] = sanitize_string($_POST['inspection_date'] ?? '', 20);
    $old['notes']           = sanitize_string($_POST['notes'] ?? '', 2000) ?: null;

    if (!$old['property_id']) $errors['property_id'] = __('Property required.');
    if (!$old['unit_id'])     $errors['unit_id'] = __('Unit required.');

    if (!$errors) {
        db()->beginTransaction();
        try {
            $id = db_insert('inspections', [
                'company_id' => (int)$USER['company_id'],
                'property_id' => $old['property_id'],
                'unit_id' => $old['unit_id'],
                'lease_id' => $old['lease_id'],
                'checklist_id' => $old['checklist_id'],
                'inspection_type' => $old['inspection_type'],
                'inspector_id' => (int)$USER['id'],
                'inspection_date' => $old['inspection_date'],
                'status' => 'scheduled',
                'notes' => $old['notes'],
            ]);

            // Auto-populate inspection_results rows from the checklist if one was chosen
            if ($old['checklist_id']) {
                $items = db_all('SELECT id FROM inspection_checklist_items WHERE checklist_id = :c', [':c'=>$old['checklist_id']]);
                foreach ($items as $it) {
                    db_insert('inspection_results', [
                        'inspection_id' => $id,
                        'checklist_item_id' => (int)$it['id'],
                        'condition_rating' => 'good',
                    ]);
                }
            }

            log_audit('inspections', 'create', 'inspections', $id, null, $old);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[inspection.create] ' . $e->getMessage());
            $errors['_'] = __('Save failed.');
        }
        if (!$errors) {
            flash_success(__('Inspection scheduled.'));
            redirect('modules/inspections/view.php?id=' . $id);
        }
    }
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name', [':c'=>(int)$USER['company_id']]);
$checklists = db_all('SELECT id, name, type FROM inspection_checklists WHERE company_id = :c AND is_active = 1 ORDER BY name', [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('Schedule Inspection');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Schedule Inspection')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Property')) ?> *</label>
      <select id="selProperty" name="property_id" class="form-select" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Unit')) ?> *</label>
      <select id="selUnit" name="unit_id" class="form-select" required>
        <option value=""><?= e(__('— select —')) ?></option>
      </select></div>

    <div class="col-md-3"><label class="form-label"><?= e(__('Type')) ?></label>
      <select name="inspection_type" class="form-select">
        <?php foreach (['move_in','move_out','routine','maintenance','safety'] as $t): ?>
          <option value="<?= e($t) ?>" <?= $old['inspection_type']===$t?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$t))) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Date')) ?></label>
      <input type="date" name="inspection_date" class="form-control" value="<?= e($old['inspection_date']) ?>" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Checklist (optional)')) ?></label>
      <select name="checklist_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($checklists as $cl): ?>
          <option value="<?= (int)$cl['id'] ?>" <?= (int)$old['checklist_id']===(int)$cl['id']?'selected':'' ?>><?= e($cl['name']) ?> (<?= e($cl['type']) ?>)</option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-12"><label class="form-label"><?= e(__('Notes')) ?></label>
      <textarea name="notes" class="form-control" rows="3"><?= e((string)$old['notes']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/inspections/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Schedule')) ?></button>
  </div>
</form>
<script>
document.getElementById('selProperty').addEventListener('change', function () {
  const sel = document.getElementById('selUnit');
  sel.innerHTML = '<option value="">— select —</option>';
  if (!this.value) return;
  srpFetch(window.SRP.url + '/modules/inspections/create.php?cascade=units&property_id=' + this.value)
    .then(r => r.json()).then(d => {
      (d.units || []).forEach(u => {
        const o = document.createElement('option'); o.value = u.id; o.textContent = u.unit_number; sel.appendChild(o);
      });
    });
});
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
<?php
// Cascade (move above header in production)
if (($_GET['cascade'] ?? '') === 'units') {
    header('Content-Type: application/json');
    $rows = db_all(
        'SELECT id, unit_number FROM units WHERE property_id = :p AND property_id IN (SELECT id FROM properties WHERE company_id = :c) ORDER BY unit_number',
        [':p'=>(int)$_GET['property_id'], ':c'=>(int)$USER['company_id']]
    );
    echo json_encode(['units'=>$rows]); exit;
}
?>