<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $new = sanitize_string($_POST['default_language'] ?? '', 5);
    if ($new) {
        db_update('companies', ['default_language' => $new], 'id = :id', [':id' => (int)$USER['company_id']]);
        log_audit('settings','update_language','companies',(int)$USER['company_id'],null,['language'=>$new]);
    }
    flash_success(__('Language saved.'));
    redirect('modules/settings/language.php');
}

$company = db_one('SELECT default_language FROM companies WHERE id = :id', [':id'=>(int)$USER['company_id']]);
$languages = db_all('SELECT id, code, name, is_active FROM languages ORDER BY name');
$PAGE_TITLE = __('Language');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Language')) ?></h1>
<form method="post" class="srp-card p-4 mb-3">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('Default Language')) ?></label>
      <select name="default_language" class="form-select">
        <?php foreach ($languages as $l): ?>
          <option value="<?= e($l['code']) ?>" <?= $company['default_language']===$l['code']?'selected':'' ?>><?= e($l['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3 align-self-end"><button class="btn btn-primary w-100"><?= e(__('Save')) ?></button></div>
  </div>
</form>

<div class="srp-card p-3">
  <h2 class="h6 mb-3"><?= e(__('Available Languages')) ?></h2>
  <p class="text-muted small"><?= e(__('Translations are stored in the translations table. Add new languages without code changes.')) ?></p>
  <table class="table table-sm">
    <thead><tr><th><?= e(__('Code')) ?></th><th><?= e(__('Name')) ?></th><th><?= e(__('Active')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($languages as $l): ?>
        <tr><td><?= e($l['code']) ?></td><td><?= e($l['name']) ?></td>
            <td><?= $l['is_active'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>