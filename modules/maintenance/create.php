<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('maintenance.create');

$errors = [];
$old = [
    'property_id' => 0, 'unit_id' => (int)($_GET['unit_id'] ?? 0), 'tenant_id' => 0,
    'category_id' => 0, 'description' => '',
    'priority' => 'medium',
    'estimated_cost' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['property_id']    = (int)($_POST['property_id'] ?? 0);
    $old['unit_id']        = (int)($_POST['unit_id'] ?? 0);
    $old['tenant_id']      = (int)($_POST['tenant_id'] ?? 0) ?: null;
    $old['category_id']    = (int)($_POST['category_id'] ?? 0) ?: null;
    $old['description']    = sanitize_string($_POST['description'] ?? '', 2000);
    $old['priority']       = in_array($_POST['priority'] ?? '', ['emergency','high','medium','low'], true) ? $_POST['priority'] : 'medium';
    $old['estimated_cost'] = $_POST['estimated_cost'] !== '' ? (float)$_POST['estimated_cost'] : null;

    if (!$old['property_id'])    $errors['property_id'] = __('Property required.');
    if (!$old['unit_id'])        $errors['unit_id']     = __('Unit required.');
    if ($old['description'] === '') $errors['description'] = __('Describe the issue.');

    // Photos
    $photos = [];
    if (!empty($_FILES['photos']['name'][0])) {
        $count = count($_FILES['photos']['name']);
        for ($i = 0; $i < $count; $i++) {
            $f = ['name'=>$_FILES['photos']['name'][$i], 'tmp_name'=>$_FILES['photos']['tmp_name'][$i],
                  'error'=>$_FILES['photos']['error'][$i], 'size'=>$_FILES['photos']['size'][$i]];
            $path = store_upload($f, 'maintenance', ['image/jpeg','image/png','image/webp']);
            if ($path) $photos[] = $path;
        }
    }

    if (!$errors) {
        db()->beginTransaction();
        try {
            $ticket = 'MT-' . date('Ymd') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
            $id = db_insert('maintenance_requests', [
                'company_id'    => (int)$USER['company_id'],
                'property_id'   => $old['property_id'],
                'unit_id'       => $old['unit_id'],
                'tenant_id'     => $old['tenant_id'],
                'reported_by'   => (int)$USER['id'],
                'category_id'   => $old['category_id'],
                'ticket_number' => $ticket,
                'description'   => $old['description'],
                'photos'        => $photos ? json_encode($photos) : null,
                'priority'      => $old['priority'],
                'status'        => 'new',
                'estimated_cost'=> $old['estimated_cost'],
            ]);

            db_insert('maintenance_status_history', [
                'maintenance_request_id' => $id,
                'from_status' => null,
                'to_status'   => 'new',
                'changed_by'  => (int)$USER['id'],
                'notes'       => 'Request created',
            ]);

            log_audit('maintenance', 'create', 'maintenance_requests', $id, null, $old);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[maintenance.create] ' . $e->getMessage());
            $errors['_'] = __('Save failed.');
        }
        if (!$errors) {
            flash_success(__('Maintenance request created.'));
            redirect('modules/maintenance/view.php?id=' . $id);
        }
    }
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name', [':c'=>(int)$USER['company_id']]);
$categories = db_all('SELECT id, name FROM maintenance_categories WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$tenants    = db_all('SELECT id, first_name, last_name FROM tenants WHERE company_id = :c AND status = "active" ORDER BY first_name', [':c'=>(int)$USER['company_id']]);
$units = $old['property_id']
    ? db_all('SELECT id, unit_number FROM units WHERE property_id = :p ORDER BY unit_number', [':p'=>$old['property_id']])
    : [];

$PAGE_TITLE = __('New Maintenance Request');
require __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3"><?= e(__('New Maintenance Request')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Property')) ?> *</label>
      <select id="selProperty" name="property_id" class="form-select<?= isset($errors['property_id'])?' is-invalid':'' ?>">
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Unit')) ?> *</label>
      <select id="selUnit" name="unit_id" class="form-select<?= isset($errors['unit_id'])?' is-invalid':'' ?>">
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)$old['unit_id']===(int)$u['id']?'selected':'' ?>><?= e($u['unit_number']) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-md-6"><label class="form-label"><?= e(__('Tenant (optional)')) ?></label>
      <select name="tenant_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($tenants as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= (int)$old['tenant_id']===(int)$t['id']?'selected':'' ?>><?= e($t['first_name'].' '.$t['last_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Category')) ?></label>
      <select name="category_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$old['category_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Priority')) ?></label>
      <select name="priority" class="form-select">
        <?php foreach (['emergency','high','medium','low'] as $p): ?>
          <option value="<?= e($p) ?>" <?= $old['priority']===$p?'selected':'' ?>><?= e(ucfirst($p)) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-12"><label class="form-label"><?= e(__('Describe the issue')) ?> *</label>
      <textarea name="description" class="form-control" rows="4" required><?= e($old['description']) ?></textarea></div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Estimated Cost')) ?></label>
      <input type="number" step="0.01" name="estimated_cost" class="form-control" value="<?= e((string)$old['estimated_cost']) ?>"></div>
    <div class="col-md-8"><label class="form-label"><?= e(__('Photos')) ?></label>
      <input type="file" name="photos[]" class="form-control" accept="image/*" multiple></div>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/maintenance/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Submit Request')) ?></button>
  </div>
</form>

<script>
document.getElementById('selProperty').addEventListener('change', function () {
  const sel = document.getElementById('selUnit');
  sel.innerHTML = '<option value="">— select —</option>';
  if (!this.value) return;
  srpFetch(window.SRP.url + '/modules/maintenance/create.php?cascade=units&property_id=' + this.value)
    .then(r => r.json()).then(d => {
      (d.units || []).forEach(u => {
        const o = document.createElement('option');
        o.value = u.id; o.textContent = u.unit_number;
        sel.appendChild(o);
      });
    });
});
</script>
<?php require __DIR__ . '/../../includes/footer.php'; ?>

<?php
// Early-exit cascade — move above header in production.
if (($_GET['cascade'] ?? '') === 'units') {
    header('Content-Type: application/json');
    $rows = db_all(
        'SELECT id, unit_number FROM units WHERE property_id = :p AND property_id IN (SELECT id FROM properties WHERE company_id = :c) ORDER BY unit_number',
        [':p'=>(int)$_GET['property_id'], ':c'=>(int)$USER['company_id']]
    );
    echo json_encode(['units'=>$rows]); exit;
}
?>