<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('tenants.edit');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$a = db_one('SELECT * FROM applicants WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$a) { flash_error(__('Applicant not found.')); redirect('modules/applicants/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $before = $a;

    $a['unit_id']      = (int)($_POST['unit_id'] ?? 0) ?: null;
    $a['first_name']   = sanitize_string($_POST['first_name'] ?? '', 80);
    $a['last_name']    = sanitize_string($_POST['last_name'] ?? '', 80);
    $a['phone']        = sanitize_phone($_POST['phone'] ?? '');
    $a['email']        = sanitize_email($_POST['email'] ?? '');
    $a['stage']        = in_array($_POST['stage'] ?? '', ['lead','applied','screening','approved','rejected','converted'], true) ? $_POST['stage'] : 'lead';
    $a['employer']     = sanitize_string($_POST['employer'] ?? '', 150) ?: null;
    $a['monthly_income'] = $_POST['monthly_income'] !== '' ? (float)$_POST['monthly_income'] : null;
    $a['screening_notes'] = sanitize_string($_POST['screening_notes'] ?? '', 2000) ?: null;

    if ($a['first_name'] === '') $errors['first_name'] = __('First name is required.');
    if ($a['last_name']  === '') $errors['last_name']  = __('Last name is required.');

    if (!$errors) {
        db_update('applicants', [
            'unit_id'=>$a['unit_id'],'first_name'=>$a['first_name'],'last_name'=>$a['last_name'],
            'phone'=>$a['phone'],'email'=>$a['email'],'stage'=>$a['stage'],
            'employer'=>$a['employer'],'monthly_income'=>$a['monthly_income'],
            'screening_notes'=>$a['screening_notes'],
        ], 'id = :id', [':id' => $id]);
        log_audit('applicants','update','applicants',$id,$before,$a);

        // Log the stage change in applicant_activities
        if ($before['stage'] !== $a['stage']) {
            db_insert('applicant_activities', [
                'applicant_id' => $id,
                'activity_type' => 'stage_change',
                'description'   => 'Stage: ' . $before['stage'] . ' → ' . $a['stage'],
                'created_by'    => (int)$USER['id'],
            ]);
        }

        flash_success(__('Applicant updated.'));
        redirect('modules/applicants/view.php?id=' . $id);
    }
}

$units = db_all(
    'SELECT u.id, u.unit_number, p.name AS property_name
       FROM units u JOIN properties p ON p.id = u.property_id
      WHERE u.company_id = :c ORDER BY p.name, u.unit_number',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Edit Applicant');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Edit Applicant')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(__('Please fix the errors below.')) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label"><?= e(__('First Name')) ?> *</label>
      <input name="first_name" class="form-control<?= isset($errors['first_name'])?' is-invalid':'' ?>" value="<?= e($a['first_name']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Last Name')) ?> *</label>
      <input name="last_name" class="form-control<?= isset($errors['last_name'])?' is-invalid':'' ?>" value="<?= e($a['last_name']) ?>" required></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Stage')) ?></label>
      <select name="stage" class="form-select">
        <?php foreach (['lead','applied','screening','approved','rejected','converted'] as $st): ?>
          <option value="<?= e($st) ?>" <?= $a['stage']===$st?'selected':'' ?>><?= e(ucfirst($st)) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-md-4"><label class="form-label"><?= e(__('Phone')) ?></label>
      <input name="phone" class="form-control" value="<?= e((string)$a['phone']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Email')) ?></label>
      <input type="email" name="email" class="form-control" value="<?= e((string)$a['email']) ?>"></div>
    <div class="col-md-4"><label class="form-label"><?= e(__('Unit of Interest')) ?></label>
      <select name="unit_id" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach ($units as $u): ?>
          <option value="<?= (int)$u['id'] ?>" <?= (int)$a['unit_id']===(int)$u['id']?'selected':'' ?>><?= e($u['unit_number'] . ' — ' . $u['property_name']) ?></option>
        <?php endforeach; ?>
      </select></div>

    <div class="col-md-6"><label class="form-label"><?= e(__('Employer')) ?></label>
      <input name="employer" class="form-control" value="<?= e((string)$a['employer']) ?>"></div>
    <div class="col-md-6"><label class="form-label"><?= e(__('Monthly Income')) ?></label>
      <input type="number" step="0.01" name="monthly_income" class="form-control" value="<?= e((string)$a['monthly_income']) ?>"></div>

    <div class="col-12"><label class="form-label"><?= e(__('Screening Notes')) ?></label>
      <textarea name="screening_notes" class="form-control" rows="3"><?= e((string)$a['screening_notes']) ?></textarea></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= APP_URL ?>/modules/applicants/view.php?id=<?= (int)$id ?>" class="btn btn-light"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save Changes')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>