<?php
// modules/templates/create.php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');
$errors = [];
$old = ['event_code'=>'','channel'=>'email','subject'=>'','body'=>'','is_active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['event_code'] = sanitize_string($_POST['event_code'] ?? '', 80);
    $old['channel']    = in_array($_POST['channel'] ?? '', ['email','sms','in_app'], true) ? $_POST['channel'] : 'email';
    $old['subject']    = sanitize_string($_POST['subject'] ?? '', 255) ?: null;
    $old['body']       = (string)($_POST['body'] ?? '');
    $old['is_active']  = !empty($_POST['is_active']) ? 1 : 0;

    if ($old['event_code'] === '') $errors['event_code'] = __('Event code required.');
    if (trim($old['body']) === '') $errors['body'] = __('Body required.');

    if (!$errors) {
        $id = db_insert('notification_templates', [
            'company_id' => (int)$USER['company_id'],
            'event_code' => $old['event_code'],
            'channel' => $old['channel'],
            'subject' => $old['subject'],
            'body' => $old['body'],
            'is_active' => $old['is_active'],
        ]);
        log_audit('notification_templates','create','notification_templates',$id,null,$old);
        flash_success(__('Template created.'));
        redirect('modules/templates/index.php');
    }
}
$PAGE_TITLE = __('New Template');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('New Template')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Event Code')) ?> *</label>
      <input name="event_code" class="form-control" value="<?= e($old['event_code']) ?>" placeholder="rent_due, payment_received, maintenance_assigned…">
      <div class="form-text"><?= e(__('Use a stable code from the list in the spec.')) ?></div></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Channel')) ?></label>
      <select name="channel" class="form-select">
        <?php foreach (['email','sms','in_app'] as $c): ?>
          <option value="<?= e($c) ?>" <?= $old['channel']===$c?'selected':'' ?>><?= e(ucfirst($c)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><div class="form-check mt-4">
      <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" <?= $old['is_active']?'checked':'' ?>>
      <label class="form-check-label" for="active"><?= e(__('Active')) ?></label>
    </div></div>
    <div class="col-12"><label class="form-label"><?= e(__('Subject')) ?></label>
      <input name="subject" class="form-control" value="<?= e((string)$old['subject']) ?>"></div>
    <div class="col-12"><label class="form-label"><?= e(__('Body')) ?> *</label>
      <textarea name="body" class="form-control" rows="6" required><?= e($old['body']) ?></textarea>
      <div class="form-text"><?= e(__('Placeholders: {tenant_name}, {unit}, {amount}, {due_date}')) ?></div></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/templates/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>