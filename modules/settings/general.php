<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $fields = ['name','legal_name','address','city','country','phone','email','website'];
    $data = [];
    foreach ($fields as $f) $data[$f] = sanitize_string($_POST[$f] ?? '', 200) ?: null;
    $data['name'] = $data['name'] ?: 'Company';
    db_update('companies', $data, 'id = :id', [':id' => (int)$USER['company_id']]);
    log_audit('settings', 'update', 'companies', (int)$USER['company_id'], null, $data);
    flash_success(__('General settings saved.'));
    redirect('modules/settings/general.php');
}

$c = db_one('SELECT * FROM companies WHERE id = :id', [':id' => (int)$USER['company_id']]);
$PAGE_TITLE = __('General Settings');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('General Settings')) ?></h1>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Company Name')) ?></label>
      <input name="name" class="form-control" value="<?= e($c['name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Legal Name')) ?></label>
      <input name="legal_name" class="form-control" value="<?= e((string)$c['legal_name']) ?>"></div>
    <div class="col-md-8"><label class="form-label"><?= e(__('Address')) ?></label>
      <input name="address" class="form-control" value="<?= e((string)$c['address']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('City')) ?></label>
      <input name="city" class="form-control" value="<?= e((string)$c['city']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Country')) ?></label>
      <input name="country" class="form-control" value="<?= e((string)$c['country']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Phone')) ?></label>
      <input name="phone" class="form-control" value="<?= e((string)$c['phone']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="email" class="form-control" value="<?= e((string)$c['email']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Website')) ?></label>
      <input name="website" class="form-control" value="<?= e((string)$c['website']) ?>"></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>