<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('tenants.create');

$errors = [];
$old = [
    'unit_id' => (int)($_GET['unit_id'] ?? 0),
    'first_name'=>'', 'last_name'=>'', 'phone'=>'', 'email'=>'',
    'stage'=>'lead', 'employer'=>'', 'monthly_income'=>'',
    'screening_notes'=>'',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['unit_id']      = (int)($_POST['unit_id'] ?? 0) ?: null;
    $old['first_name']   = sanitize_string($_POST['first_name'] ?? '', 80);
    $old['last_name']    = sanitize_string($_POST['last_name'] ?? '', 80);
    $old['phone']        = sanitize_phone($_POST['phone'] ?? '');
    $old['email']        = sanitize_email($_POST['email'] ?? '');
    $old['stage']        = in_array($_POST['stage'] ?? '', ['lead','applied','screening','approved','rejected','converted'], true) ? $_POST['stage'] : 'lead';
    $old['employer']     = sanitize_string($_POST['employer'] ?? '', 150) ?: null;
    $old['monthly_income'] = $_POST['monthly_income'] !== '' ? (float)$_POST['monthly_income'] : null;
    $old['screening_notes'] = sanitize_string($_POST['screening_notes'] ?? '', 2000) ?: null;

    if ($old['first_name'] === '') $errors['first_name'] = __('First name is required.');
    if ($old['last_name']  === '') $errors['last_name']  = __('Last name is required.');

    if (!$errors) {
        $id = db_insert('applicants', [
            'company_id' => (int)$USER['company_id'],
            'unit_id'    => $old['unit_id'],
            'first_name' => $old['first_name'],
            'last_name'  => $old['last_name'],
            'phone'      => $old['phone'],
            'email'      => $old['email'],
            'stage'      => $old['stage'],
            'employer'   => $old['employer'],
            'monthly_income' => $old['monthly_income'],
            'screening_notes'=> $old['screening_notes'],
        ]);
        log_audit('applicants','create','applicants',$id,null,$old);
        flash_success(__('Applicant added.'));
        redirect('modules/applicants/view.php?id=' . $id);
    }
}

$units = db_all(
    'SELECT u.id, u.unit_number, p.name AS property_name
       FROM units u JOIN properties p ON p.id = u.property_id
      WHERE u.company_id = :c AND u.status IN ("available","reserved")
      ORDER BY p.name, u.unit_number',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Add Applicant');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Add Applicant')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('First Name')) ?> *</label>
      <input name="first_name" class="form-control<?= isset($errors['first_name'])?' is-invalid':'' ?>" value="<?= e($old['first_name']) ?>" required>
      <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= e($errors['first_name']) ?></div><?php endif; ?></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Last Name')) ?> *</label>
      <input name="last_name" class="form-control<?= isset($errors['last_name'])?' is-invalid':'' ?>" value="<?= e($old['last_name']) ?>" required>
      <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= e($errors['last_name']) ?></div><?php endif; ?></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Stage')) ?></label>
      <select name="stage" class="form-select">
        <?php foreach (['lead','applied','screening','approved','rejected','converted'] as $st): ?>
          <option value="<?= e($st) ?>" <?= $old['stage']===$st?'selected':'' ?>><?= e(ucfirst($st)) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Phone')) ?></label>
      <input name="phone" class="form-control" value="<?= e((string)$old['phone']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="email" class="form-control" value="<?= e((string)$old['email']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Unit of Interest')) ?></label>
      <select name="unit_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)$old['unit_id']===(int)$u['id']?'selected':'' ?>><?= e($u['unit_number'] . ' — ' . $u['property_name']) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-md-6"><label class="form-label"><?= e(__('Employer')) ?></label>
      <input name="employer" class="form-control" value="<?= e((string)$old['employer']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Monthly Income')) ?></label>
      <input type="number" step="0.01" name="monthly_income" class="form-control" value="<?= e((string)$old['monthly_income']) ?>"></div>

    <div class="col-12"><label class="form-label"><?= e(__('Screening Notes')) ?></label>
      <textarea name="screening_notes" class="form-control" rows="3"><?= e((string)$old['screening_notes']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/applicants/index.php" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Applicant')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>