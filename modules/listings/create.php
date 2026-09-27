<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.create');
$errors = [];
$old = ['unit_id'=>0,'title'=>'','description'=>'','rent'=>'','deposit'=>'','amenities'=>[],'availability_date'=>'','is_published'=>0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['unit_id']           = (int)($_POST['unit_id'] ?? 0);
    $old['title']             = sanitize_string($_POST['title'] ?? '', 200);
    $old['description']       = sanitize_string($_POST['description'] ?? '', 3000) ?: null;
    $old['rent']              = (float)($_POST['rent'] ?? 0);
    $old['deposit']           = $_POST['deposit'] !== '' ? (float)$_POST['deposit'] : null;
    $old['amenities']         = array_values(array_filter((array)($_POST['amenities'] ?? []), 'is_string'));
    $old['availability_date'] = sanitize_string($_POST['availability_date'] ?? '', 20) ?: null;
    $old['is_published']      = !empty($_POST['is_published']) ? 1 : 0;

    if (!$old['unit_id'])         $errors['unit_id'] = __('Unit required.');
    if ($old['title'] === '')     $errors['title'] = __('Title required.');
    if ($old['rent'] <= 0)        $errors['rent'] = __('Rent must be positive.');

    // Photos
    $photos = [];
    if (!empty($_FILES['photos']['name'][0])) {
        $count = count($_FILES['photos']['name']);
        for ($i = 0; $i < $count; $i++) {
            $f = ['name'=>$_FILES['photos']['name'][$i], 'tmp_name'=>$_FILES['photos']['tmp_name'][$i],
                  'error'=>$_FILES['photos']['error'][$i], 'size'=>$_FILES['photos']['size'][$i]];
            $path = store_upload($f, 'listings', ['image/jpeg','image/png','image/webp']);
            if ($path) $photos[] = $path;
        }
    }

    if (!$errors) {
        $slug = safe_slug($old['title']) . '-' . substr((string)time(), -6);
        $id = db_insert('listings', [
            'company_id' => (int)$USER['company_id'],
            'unit_id' => $old['unit_id'],
            'slug' => $slug,
            'title' => $old['title'],
            'description' => $old['description'],
            'rent' => $old['rent'],
            'deposit' => $old['deposit'],
            'photos' => $photos ? json_encode($photos) : null,
            'amenities_json' => $old['amenities'] ? json_encode($old['amenities']) : null,
            'availability_date' => $old['availability_date'],
            'is_published' => $old['is_published'],
            'published_at' => $old['is_published'] ? date('Y-m-d H:i:s') : null,
            'created_by' => (int)$USER['id'],
        ]);
        log_audit('listings','create','listings',$id,null,$old);
        flash_success(__('Listing created.'));
        redirect('modules/listings/index.php');
    }
}

$units = db_all(
    'SELECT u.id, u.unit_number, p.name AS property_name FROM units u
       JOIN properties p ON p.id = u.property_id
      WHERE u.company_id = :c AND u.status = "available"
      ORDER BY p.name, u.unit_number',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('New Listing');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('New Listing')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Unit')) ?> *</label>
      <select name="unit_id" class="form-select" required>
        <option value=""><?= e(__('— select —')) ?></option>
        <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)$old['unit_id']===(int)$u['id']?'selected':'' ?>><?= e($u['unit_number']) ?> — <?= e($u['property_name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Title')) ?> *</label>
      <input name="title" class="form-control" value="<?= e($old['title']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Rent')) ?> *</label>
      <input type="number" step="0.01" name="rent" class="form-control" value="<?= e((string)$old['rent']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Deposit')) ?></label>
      <input type="number" step="0.01" name="deposit" class="form-control" value="<?= e((string)$old['deposit']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Availability Date')) ?></label>
      <input type="date" name="availability_date" class="form-control" value="<?= e((string)$old['availability_date']) ?>"></div>
    <div class="col-md-3"><div class="form-check mt-4">
      <input class="form-check-input" type="checkbox" name="is_published" value="1" id="pub" <?= $old['is_published']?'checked':'' ?>>
      <label class="form-check-label" for="pub"><?= e(__('Publish immediately')) ?></label>
    </div></div>
    <div class="col-12"><label class="form-label"><?= e(__('Description')) ?></label>
      <textarea name="description" class="form-control" rows="4"><?= e((string)$old['description']) ?></textarea></div>
    <div class="col-12"><label class="form-label"><?= e(__('Amenities')) ?></label>
      <div class="d-flex flex-wrap gap-3 small">
        <?php foreach (['parking','gym','pool','wifi','security','furnished','balcony','garden'] as $a): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="amenities[]" value="<?= e($a) ?>" id="am_<?= e($a) ?>"
                   <?= in_array($a, $old['amenities'], true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="am_<?= e($a) ?>"><?= e(ucfirst($a)) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-12"><label class="form-label"><?= e(__('Photos')) ?></label>
      <input type="file" name="photos[]" class="form-control" accept="image/*" multiple></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/listings/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Listing')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>