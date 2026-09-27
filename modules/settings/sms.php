<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

$keys = ['sms_driver','sms_from','sms_api_key','sms_api_url'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach ($keys as $k) {
        $val = sanitize_string($_POST[$k] ?? '', 200);
        $exists = db_one('SELECT id FROM system_settings WHERE company_id = :c AND setting_group = "sms" AND setting_key = :k',
                         [':c'=>(int)$USER['company_id'], ':k'=>$k]);
        if ($exists) {
            db_update('system_settings', ['setting_value' => $val], 'id = :id', [':id'=>(int)$exists['id']]);
        } else {
            db_insert('system_settings', [
                'company_id' => (int)$USER['company_id'],
                'setting_group' => 'sms',
                'setting_key' => $k,
                'setting_value' => $val,
                'is_public' => 0,
            ]);
        }
    }
    log_audit('settings','update','system_settings',null,null,['group'=>'sms']);
    flash_success(__('SMS settings saved.'));
    redirect('modules/settings/sms.php');
}

$rows = db_all('SELECT setting_key, setting_value FROM system_settings WHERE company_id = :c AND setting_group = "sms"', [':c'=>(int)$USER['company_id']]);
$vals = [];
foreach ($rows as $r) $vals[$r['setting_key']] = $r['setting_value'];

$PAGE_TITLE = __('SMS Provider');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('SMS Provider')) ?></h1>
<p class="text-muted small"><?= e(__('Pluggable drivers. The .env SMTP_DRIVER selects log / twilio / rwanda — all three ship in includes/sms/drivers/.')) ?></p>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('Driver')) ?></label>
      <select name="sms_driver" class="form-select">
        <?php foreach (['log','twilio','rwanda'] as $d): ?>
          <option value="<?= e($d) ?>" <?= ($vals['sms_driver']??'log')===$d?'selected':'' ?>><?= e(ucfirst($d)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Sender ID / From')) ?></label>
      <input name="sms_from" class="form-control" value="<?= e($vals['sms_from'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('API URL')) ?></label>
      <input name="sms_api_url" class="form-control" value="<?= e($vals['sms_api_url'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('API Key')) ?></label>
      <input type="password" name="sms_api_key" class="form-control" value="<?= e($vals['sms_api_key'] ?? '') ?>"></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>