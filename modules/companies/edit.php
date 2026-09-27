<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_login();
if (!is_super_admin($USER)) { http_response_code(403); exit('Forbidden'); }

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$c = db_one('SELECT * FROM companies WHERE id = :id LIMIT 1', [':id'=>$id]);
if (!$c) { flash_error(__('Not found.')); redirect('modules/companies/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $c;
    foreach (['name','legal_name','slug','address','city','country','phone','website','default_timezone','subscription_plan'] as $k) {
        $c[$k] = sanitize_string($_POST[$k] ?? '', 200);
    }
    $c['email']              = sanitize_email($_POST['email'] ?? '');
    $c['base_currency_code'] = strtoupper(substr(sanitize_string($_POST['base_currency_code'] ?? 'RWF', 3), 0, 3));
    $c['default_language']   = sanitize_string($_POST['default_language'] ?? 'en', 5);
    $c['is_active']          = !empty($_POST['is_active']) ? 1 : 0;

    if ($c['name'] === '') $errors['name'] = __('Name required.');

    if (!$errors) {
        db_update('companies', [
            'name'=>$c['name'],'legal_name'=>$c['legal_name'] ?: null,'slug'=>$c['slug'],
            'address'=>$c['address'] ?: null,'city'=>$c['city'] ?: null,'country'=>$c['country'] ?: 'Rwanda',
            'phone'=>$c['phone'] ?: null,'email'=>$c['email'],'website'=>$c['website'] ?: null,
            'base_currency_code'=>$c['base_currency_code'],'default_language'=>$c['default_language'],
            'default_timezone'=>$c['default_timezone'],'subscription_plan'=>$c['subscription_plan'],
            'is_active'=>$c['is_active'],
        ], 'id = :id', [':id'=>$id]);

        log_audit('companies','update','companies',$id,$before,$c);
        flash_success(__('Company updated.'));
        redirect('modules/companies/index.php');
    }
}

$currencies = db_all('SELECT code, name FROM currencies WHERE is_active = 1 ORDER BY code');
$languages  = db_all('SELECT code, name FROM languages WHERE is_active = 1 ORDER BY name');
$PAGE_TITLE = __('Edit Company');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Company')) ?> · <?= e($c['name']) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Company Name')) ?></label>
      <input name="name" class="form-control" value="<?= e($c['name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Legal Name')) ?></label>
      <input name="legal_name" class="form-control" value="<?= e((string)$c['legal_name']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Slug')) ?></label>
      <input name="slug" class="form-control" value="<?= e($c['slug']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Currency')) ?></label>
      <select name="base_currency_code" class="form-select">
        <?php foreach ($currencies as $cu): ?>
          <option value="<?= e($cu['code']) ?>" <?= $c['base_currency_code']===$cu['code']?'selected':'' ?>><?= e($cu['code']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Language')) ?></label>
      <select name="default_language" class="form-select">
        <?php foreach ($languages as $l): ?>
          <option value="<?= e($l['code']) ?>" <?= $c['default_language']===$l['code']?'selected':'' ?>><?= e($l['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
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
    <div class="col-md-3"><label class="form-label"><?= e(__('Timezone')) ?></label>
      <input name="default_timezone" class="form-control" value="<?= e($c['default_timezone']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Plan')) ?></label>
      <input name="subscription_plan" class="form-control" value="<?= e((string)$c['subscription_plan']) ?>"></div>
    <div class="col-md-3"><div class="form-check mt-4">
      <input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= $c['is_active']?'checked':'' ?>>
      <label class="form-check-label" for="act"><?= e(__('Active')) ?></label>
    </div></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/companies/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>