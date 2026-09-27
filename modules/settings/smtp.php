<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

$groups = ['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_secure','smtp_from','smtp_from_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach ($groups as $k) {
        $val = sanitize_string($_POST[$k] ?? '', 200);
        $exists = db_one('SELECT id FROM system_settings WHERE company_id = :c AND setting_group = "smtp" AND setting_key = :k',
                         [':c'=>(int)$USER['company_id'], ':k'=>$k]);
        if ($exists) {
            db_update('system_settings', ['setting_value' => $val], 'id = :id', [':id'=>(int)$exists['id']]);
        } else {
            db_insert('system_settings', [
                'company_id' => (int)$USER['company_id'],
                'setting_group' => 'smtp',
                'setting_key' => $k,
                'setting_value' => $val,
                'is_public' => 0,
            ]);
        }
    }
    log_audit('settings','update','system_settings',null,null,['group'=>'smtp']);
    flash_success(__('SMTP settings saved.'));
    redirect('modules/settings/smtp.php');
}

$rows = db_all('SELECT setting_key, setting_value FROM system_settings WHERE company_id = :c AND setting_group = "smtp"', [':c'=>(int)$USER['company_id']]);
$vals = [];
foreach ($rows as $r) $vals[$r['setting_key']] = $r['setting_value'];

$PAGE_TITLE = __('SMTP / Email');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('SMTP / Email Settings')) ?></h1>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('SMTP Host')) ?></label>
      <input name="smtp_host" class="form-control" value="<?= e($vals['smtp_host'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Port')) ?></label>
      <input name="smtp_port" class="form-control" value="<?= e($vals['smtp_port'] ?? '587') ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Encryption')) ?></label>
      <select name="smtp_secure" class="form-select">
        <option value="tls" <?= ($vals['smtp_secure']??'')==='tls'?'selected':'' ?>>TLS</option>
        <option value="ssl" <?= ($vals['smtp_secure']??'')==='ssl'?'selected':'' ?>>SSL</option>
        <option value=""    <?= ($vals['smtp_secure']??'')===''?'selected':'' ?>><?= e(__('None')) ?></option>
      </select></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('SMTP Username')) ?></label>
      <input name="smtp_user" class="form-control" value="<?= e($vals['smtp_user'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('SMTP Password')) ?></label>
      <input type="password" name="smtp_pass" class="form-control" value="<?= e($vals['smtp_pass'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('From Address')) ?></label>
      <input type="email" name="smtp_from" class="form-control" value="<?= e($vals['smtp_from'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('From Name')) ?></label>
      <input name="smtp_from_name" class="form-control" value="<?= e($vals['smtp_from_name'] ?? '') ?>"></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>