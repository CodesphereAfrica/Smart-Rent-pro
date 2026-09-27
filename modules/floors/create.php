<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('buildings.edit');
$buildingId = (int)($_GET['building_id'] ?? $_POST['building_id'] ?? 0);
$building = $buildingId
    ? db_one('SELECT b.*, p.name AS property_name FROM buildings b JOIN properties p ON p.id = b.property_id
              WHERE b.id = :b AND p.company_id = :c LIMIT 1', [':b'=>$buildingId, ':c'=>(int)$USER['company_id']])
    : null;
if (!$building) { flash_error(__('Select a valid building.')); redirect('modules/buildings/index.php'); }

$errors = []; $old = ['floor_number'=>'', 'floor_name'=>''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['floor_number'] = (int)($_POST['floor_number'] ?? 0);
    $old['floor_name']   = sanitize_string($_POST['floor_name'] ?? '', 60) ?: null;
    if ($old['floor_number'] === 0) $errors['floor_number'] = __('Floor number is required (use negative for basements).');
    if (!$errors) {
        $id = db_insert('floors', [
            'building_id' => $buildingId,
            'floor_number' => $old['floor_number'],
            'floor_name' => $old['floor_name'],
        ]);
        log_audit('floors', 'create', 'floors', $id, null, $old);
        flash_success(__('Floor added.'));
        redirect('modules/floors/index.php?building_id=' . $buildingId);
    }
}
$PAGE_TITLE = __('Add Floor');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Add Floor to')) ?> <?= e($building['name']) ?></h1>
<form method="post" class="srp-card p-4" style="max-width:520px">
  <?= csrf_field() ?><input type="hidden" name="building_id" value="<?= (int)$buildingId ?>">
  <div class="mb-3">
    <label class="form-label"><?= e(__('Floor Number')) ?> *</label>
    <input type="number" name="floor_number" class="form-control<?= isset($errors['floor_number'])?' is-invalid':'' ?>" value="<?= e((string)$old['floor_number']) ?>" required>
    <?php if (isset($errors['floor_number'])): ?><div class="invalid-feedback"><?= e($errors['floor_number']) ?></div><?php endif; ?>
  </div>
  <div class="mb-3"><label class="form-label"><?= e(__('Label (optional)')) ?></label>
    <input type="text" name="floor_name" class="form-control" value="<?= e((string)$old['floor_name']) ?>" placeholder="Ground, Mezzanine…"></div>
  <div class="d-flex justify-content-end gap-2">
    <a href="<?= APP_URL ?>/modules/floors/index.php?building_id=<?= (int)$buildingId ?>" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>