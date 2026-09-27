<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('settings.manage');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$t = db_one('SELECT * FROM notification_templates WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$t) { flash_error(__('Not found.')); redirect('modules/templates/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $t;
    $t['event_code'] = sanitize_string($_POST['event_code'] ?? '', 80);
    $t['channel']    = in_array($_POST['channel'] ?? '', ['email','sms','in_app'], true) ? $_POST['channel'] : $t['channel'];
    $t['subject']    = sanitize_string($_POST['subject'] ?? '', 255) ?: null;
    $t['body']       = (string)($_POST['body'] ?? '');
    $t['is_active']  = !empty($_POST['is_active']) ? 1 : 0;

    if ($t['event_code'] === '') $errors['event_code'] = __('Event code required.');
    if (trim($t['body']) === '') $errors['body'] = __('Body required.');

    if (!$errors) {
        db_update('notification_templates', [
            'event_code'=>$t['event_code'],'channel'=>$t['channel'],'subject'=>$t['subject'],
            'body'=>$t['body'],'is_active'=>$t['is_active'],
        ], 'id = :id', [':id' => $id]);

        log_audit('notification_templates','update','notification_templates',$id,$before,$t);
        flash_success(__('Template updated.'));
        redirect('modules/templates/index.php');
    }
}

$PAGE_TITLE = __('Edit Template');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Template')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Event Code')) ?></label>
      <input name="event_code" class="form-control" value="<?= e($t['event_code']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Channel')) ?></label>
      <select name="channel" class="form-select">
        <?php foreach (['email','sms','in_app'] as $c): ?>
          <option value="<?= e($c) ?>" <?= $t['channel']===$c?'selected':'' ?>><?= e(ucfirst($c)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><div class="form-check mt-4">
      <input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= $t['is_active']?'checked':'' ?>>
      <label class="form-check-label" for="act"><?= e(__('Active')) ?></label>
    </div></div>
    <div class="col-12"><label class="form-label"><?= e(__('Subject')) ?></label>
      <input name="subject" class="form-control" value="<?= e((string)$t['subject']) ?>"></div>
    <div class="col-12"><label class="form-label"><?= e(__('Body')) ?></label>
      <textarea name="body" class="form-control" rows="8" required><?= e($t['body']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/templates/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>