<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!empty($_FILES['logo']['tmp_name'])) {
        $path = store_upload($_FILES['logo'], 'companies/logos', ['image/jpeg','image/png','image/svg+xml','image/webp']);
        if ($path) {
            db_update('companies', ['logo_path' => $path], 'id = :id', [':id' => (int)$USER['company_id']]);
            log_audit('settings','update_logo','companies',(int)$USER['company_id'],null,['logo'=>$path]);
            flash_success(__('Logo updated.'));
        }
    }
    redirect('modules/settings/branding.php');
}

$c = db_one('SELECT logo_path FROM companies WHERE id = :id', [':id' => (int)$USER['company_id']]);
$PAGE_TITLE = __('Branding');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Branding')) ?></h1>
<form method="post" enctype="multipart/form-data" class="srp-card p-4">
  <?= csrf_field() ?>
  <?php if (!empty($c['logo_path'])): ?>
    <div class="mb-3"><img src="<?= e(UPLOAD_URL . '/' . $c['logo_path']) ?>" style="max-width:180px;max-height:80px"></div>
  <?php endif; ?>
  <div class="mb-3"><label class="form-label"><?= e(__('Upload Logo')) ?></label>
    <input type="file" name="logo" class="form-control" accept="image/*"></div>
  <div class="d-flex justify-content-end">
    <button class="btn btn-primary"><i data-lucide="upload"></i> <?= e(__('Save Logo')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>