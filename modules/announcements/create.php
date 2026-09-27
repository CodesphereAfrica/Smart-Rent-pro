<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('communications.send');
$errors = []; $old = ['title'=>'','body'=>'','property_id'=>0,'audience'=>'all','published_at'=>date('Y-m-d H:i:s'),'expires_at'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['title'] = sanitize_string($_POST['title'] ?? '', 200);
    $old['body']  = (string)($_POST['body'] ?? '');
    $old['property_id'] = (int)($_POST['property_id'] ?? 0) ?: null;
    $old['audience'] = in_array($_POST['audience'] ?? '', ['all','tenants','owners','staff'], true) ? $_POST['audience'] : 'all';
    $old['published_at'] = sanitize_string($_POST['published_at'] ?? '', 25) ?: null;
    $old['expires_at']   = sanitize_string($_POST['expires_at'] ?? '', 25) ?: null;

    if ($old['title'] === '') $errors['title'] = __('Title required.');
    if (trim($old['body']) === '') $errors['body'] = __('Body required.');

    if (!$errors) {
        $id = db_insert('announcements', [
            'company_id' => (int)$USER['company_id'],
            'property_id' => $old['property_id'],
            'title' => $old['title'],
            'body' => $old['body'],
            'audience' => $old['audience'],
            'published_at' => $old['published_at'],
            'expires_at' => $old['expires_at'],
            'created_by' => (int)$USER['id'],
        ]);

        // Fan out notifications to relevant users
        $recipientIds = [];
        if ($old['audience'] === 'tenants') {
            $recipientIds = db_all(
                'SELECT user_id FROM tenants WHERE company_id = :c AND user_id IS NOT NULL' .
                ($old['property_id'] ? ' AND id IN (SELECT tenant_id FROM leases WHERE property_id = :p)' : ''),
                $old['property_id'] ? [':c'=>(int)$USER['company_id'], ':p'=>$old['property_id']] : [':c'=>(int)$USER['company_id']]
            );
        } elseif ($old['audience'] === 'owners') {
            $recipientIds = db_all(
                'SELECT user_id FROM owners WHERE company_id = :c AND user_id IS NOT NULL',
                [':c'=>(int)$USER['company_id']]
            );
        } else {
            $recipientIds = db_all('SELECT id AS user_id FROM users WHERE company_id = :c AND status = "active"', [':c'=>(int)$USER['company_id']]);
        }
        foreach ($recipientIds as $r) {
            if (empty($r['user_id'])) continue;
            db_insert('notifications', [
                'company_id' => (int)$USER['company_id'],
                'user_id' => (int)$r['user_id'],
                'type' => 'announcement',
                'title' => $old['title'],
                'message' => mb_substr(strip_tags($old['body']), 0, 400),
                'related_type' => 'announcement',
                'related_id' => $id,
            ]);
        }

        log_audit('announcements', 'create', 'announcements', $id, null, $old);
        flash_success(__('Announcement created and dispatched.'));
        redirect('modules/announcements/index.php');
    }
}
$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('New Announcement');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('New Announcement')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Title')) ?></label>
      <input name="title" class="form-control" value="<?= e($old['title']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Audience')) ?></label>
      <select name="audience" class="form-select">
        <?php foreach (['all','tenants','owners','staff'] as $a): ?>
          <option value="<?= e($a) ?>" <?= $old['audience']===$a?'selected':'' ?>><?= e(ucfirst($a)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Property (optional)')) ?></label>
      <select name="property_id" class="form-select">
        <option value=""><?= e(__('Company-wide')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Publish At')) ?></label>
      <input type="datetime-local" name="published_at" class="form-control" value="<?= e($old['published_at'] ? date('Y-m-d\TH:i', strtotime($old['published_at'])) : '') ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Expires At')) ?></label>
      <input type="datetime-local" name="expires_at" class="form-control" value="<?= e($old['expires_at'] ? date('Y-m-d\TH:i', strtotime($old['expires_at'])) : '') ?>"></div>
    <div class="col-12"><label class="form-label"><?= e(__('Body')) ?></label>
      <textarea name="body" class="form-control" rows="8" required><?= e($old['body']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/announcements/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="send"></i> <?= e(__('Publish')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>