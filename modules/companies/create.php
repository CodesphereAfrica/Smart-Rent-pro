<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_login();
if (!is_super_admin($USER)) { http_response_code(403); exit('Forbidden'); }

$errors = [];
$old = [
    'name'=>'','legal_name'=>'','slug'=>'','address'=>'','city'=>'','country'=>'Rwanda',
    'phone'=>'','email'=>'','website'=>'','base_currency_code'=>'RWF','default_language'=>'en',
    'default_timezone'=>'Africa/Kigali','subscription_plan'=>'standard','is_active'=>1,
    'admin_first'=>'','admin_last'=>'','admin_username'=>'','admin_email'=>'','admin_password'=>'',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach (['name','legal_name','slug','address','city','country','phone','website','admin_first','admin_last','admin_username'] as $k) {
        $old[$k] = sanitize_string($_POST[$k] ?? '', 200);
    }
    $old['email']             = sanitize_email($_POST['email'] ?? '');
    $old['admin_email']       = sanitize_email($_POST['admin_email'] ?? '');
    $old['base_currency_code']= strtoupper(substr(sanitize_string($_POST['base_currency_code'] ?? 'RWF', 3), 0, 3));
    $old['default_language']  = sanitize_string($_POST['default_language'] ?? 'en', 5);
    $old['default_timezone']  = sanitize_string($_POST['default_timezone'] ?? 'Africa/Kigali', 60);
    $old['subscription_plan'] = sanitize_string($_POST['subscription_plan'] ?? 'standard', 50);
    $old['is_active']         = !empty($_POST['is_active']) ? 1 : 0;
    $old['admin_password']    = (string)($_POST['admin_password'] ?? '');

    if ($old['name'] === '') $errors['name'] = __('Company name required.');
    if ($old['slug'] === '') $old['slug'] = safe_slug($old['name']);
    if ($old['slug'] === '') $errors['slug'] = __('Slug required.');

    $dup = db_one('SELECT id FROM companies WHERE slug = :s', [':s' => $old['slug']]);
    if ($dup) $errors['slug'] = __('Slug already in use.');

    $wantAdmin = $old['admin_username'] !== '';
    if ($wantAdmin) {
        if ($old['admin_first'] === '' || $old['admin_last'] === '') $errors['admin_first'] = __('Admin name required.');
        if (!$old['admin_email']) $errors['admin_email'] = __('Admin email required.');
        if (strlen($old['admin_password']) < 8) $errors['admin_password'] = __('Password must be at least 8 characters.');
    }

    if (!$errors) {
        db()->beginTransaction();
        try {
            $companyId = db_insert('companies', [
                'name' => $old['name'],
                'legal_name' => $old['legal_name'] ?: null,
                'slug' => $old['slug'],
                'address' => $old['address'] ?: null,
                'city' => $old['city'] ?: null,
                'country' => $old['country'] ?: 'Rwanda',
                'phone' => $old['phone'] ?: null,
                'email' => $old['email'],
                'website' => $old['website'] ?: null,
                'base_currency_code' => $old['base_currency_code'],
                'default_language' => $old['default_language'],
                'default_timezone' => $old['default_timezone'],
                'subscription_plan' => $old['subscription_plan'],
                'is_active' => $old['is_active'],
            ]);

            if ($wantAdmin) {
                $role = db_one('SELECT id FROM roles WHERE company_id IS NULL AND name = "Super Admin" LIMIT 1');
                if ($role) {
                    db_insert('users', [
                        'company_id' => $companyId,
                        'role_id' => (int)$role['id'],
                        'first_name' => $old['admin_first'],
                        'last_name' => $old['admin_last'],
                        'username' => $old['admin_username'],
                        'email' => $old['admin_email'],
                        'password_hash' => password_hash($old['admin_password'], PASSWORD_BCRYPT),
                        'preferred_language' => $old['default_language'],
                        'status' => 'active',
                        'must_change_password' => 1,
                    ]);
                }
            }

            // Seed a company-wide "expense" workflow as a sensible default
            db_insert('approval_workflows', [
                'company_id' => $companyId,
                'workflow_type' => 'expense',
                'name' => 'Manager Approval',
                'min_amount' => 0,
                'max_amount' => 100000,
                'required_role_id' => (int)(db_one('SELECT id FROM roles WHERE company_id IS NULL AND name = "Property Manager" LIMIT 1')['id'] ?? 1),
                'sequence_order' => 1,
                'is_active' => 1,
            ]);

            log_audit('companies', 'create', 'companies', $companyId, null, $old);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[companies.create] ' . $e->getMessage());
            $errors['_'] = __('Save failed.');
        }
        if (!$errors) {
            flash_success(__('Company created.'));
            redirect('modules/companies/index.php');
        }
    }
}

$currencies = db_all('SELECT code, name FROM currencies WHERE is_active = 1 ORDER BY code');
$languages  = db_all('SELECT code, name FROM languages WHERE is_active = 1 ORDER BY name');

$PAGE_TITLE = __('New Company');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('New Company')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Company Name')) ?> *</label>
      <input name="name" class="form-control" value="<?= e($old['name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Legal Name')) ?></label>
      <input name="legal_name" class="form-control" value="<?= e($old['legal_name']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Slug (URL friendly)')) ?></label>
      <input name="slug" class="form-control" value="<?= e($old['slug']) ?>" placeholder="auto"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Base Currency')) ?></label>
      <select name="base_currency_code" class="form-select">
        <?php foreach ($currencies as $c): ?>
          <option value="<?= e($c['code']) ?>" <?= $old['base_currency_code']===$c['code']?'selected':'' ?>><?= e($c['code'].' — '.$c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Default Language')) ?></label>
      <select name="default_language" class="form-select">
        <?php foreach ($languages as $l): ?>
          <option value="<?= e($l['code']) ?>" <?= $old['default_language']===$l['code']?'selected':'' ?>><?= e($l['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-8"><label class="form-label"><?= e(__('Address')) ?></label>
      <input name="address" class="form-control" value="<?= e($old['address']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('City')) ?></label>
      <input name="city" class="form-control" value="<?= e($old['city']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Country')) ?></label>
      <input name="country" class="form-control" value="<?= e($old['country']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Phone')) ?></label>
      <input name="phone" class="form-control" value="<?= e($old['phone']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="email" class="form-control" value="<?= e((string)$old['email']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Timezone')) ?></label>
      <input name="default_timezone" class="form-control" value="<?= e($old['default_timezone']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Subscription Plan')) ?></label>
      <input name="subscription_plan" class="form-control" value="<?= e($old['subscription_plan']) ?>"></div>
    <div class="col-md-3"><div class="form-check mt-4">
      <input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= $old['is_active']?'checked':'' ?>>
      <label class="form-check-label" for="act"><?= e(__('Active')) ?></label>
    </div></div>

    <div class="col-12"><hr class="my-2"><div class="fw-semibold"><?= e(__('Initial Company Administrator (optional)')) ?></div></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('First Name')) ?></label>
      <input name="admin_first" class="form-control" value="<?= e($old['admin_first']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Last Name')) ?></label>
      <input name="admin_last" class="form-control" value="<?= e($old['admin_last']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Username')) ?></label>
      <input name="admin_username" class="form-control" value="<?= e($old['admin_username']) ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="admin_email" class="form-control" value="<?= e((string)$old['admin_email']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Temp Password')) ?></label>
      <input name="admin_password" class="form-control" type="password"></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/companies/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Create Company')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>