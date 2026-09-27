<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('communications.send');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$a = db_one('SELECT * FROM announcements WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$a) { flash_error(__('Not found.')); redirect('modules/announcements/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $a;
    $a['title'] = sanitize_string($_POST['title'] ?? '', 200);
    $a['body']  = (string)($_POST['body'] ?? '');
    $a['property_id']  = (int)($_POST['property_id'] ?? 0) ?: null;
    $a['audience']     = in_array($_POST['audience'] ?? '', ['all','tenants','owners','staff'], true) ? $_POST['audience'] : $a['audience'];
    $a['published_at'] = sanitize_string($_POST['published_at'] ?? '', 25) ?: null;
    $a['expires_at']   = sanitize_string($_POST['expires_at'] ?? '', 25) ?: null;

    if ($a['title'] === '') $errors['title'] = __('Title required.');
    if (trim($a['body']) === '') $errors['body'] = __('Body required.');

    if (!$errors) {
        db_update('announcements', [
            'title'=>$a['title'],'body'=>$a['body'],'property_id'=>$a['property_id'],
            'audience'=>$a['audience'],'published_at'=>$a['published_at'],'expires_at'=>$a['expires_at'],
        ], 'id = :id', [':id' => $id]);

        log_audit('announcements','update','announcements',$id,$before,$a);
        flash_success(__('Announcement updated.'));
        redirect('modules/announcements/index.php');
    }
}

$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('Edit Announcement');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Announcement')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Title')) ?></label>
      <input name="title" class="form-control" value="<?= e($a['title']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Audience')) ?></label>
      <select name="audience" class="form-select">
        <?php foreach (['all','tenants','owners','staff'] as $au): ?>
          <option value="<?= e($au) ?>" <?= $a['audience']===$au?'selected':'' ?>><?= e(ucfirst($au)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Property')) ?></label>
      <select name="property_id" class="form-select">
        <option value=""><?= e(__('Company-wide')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$a['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Publish At')) ?></label>
      <input type="datetime-local" name="published_at" class="form-control"
             value="<?= e($a['published_at'] ? date('Y-m-d\TH:i', strtotime($a['published_at'])) : '') ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Expires At')) ?></label>
      <input type="datetime-local" name="expires_at" class="form-control"
             value="<?= e($a['expires_at'] ? date('Y-m-d\TH:i', strtotime($a['expires_at'])) : '') ?>"></div>
    <div class="col-12"><label class="form-label"><?= e(__('Body')) ?></label>
      <textarea name="body" class="form-control" rows="8" required><?= e($a['body']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/announcements/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>