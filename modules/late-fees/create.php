<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');
$errors = []; $old = ['property_id'=>0,'lease_id'=>0,'fee_type'=>'fixed','amount'=>'','grace_period_days'=>0,'max_penalty'=>'','is_active'=>1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['property_id']       = (int)($_POST['property_id'] ?? 0) ?: null;
    $old['lease_id']          = (int)($_POST['lease_id'] ?? 0) ?: null;
    $old['fee_type']          = in_array($_POST['fee_type'] ?? '', ['fixed','percentage','daily','monthly'], true) ? $_POST['fee_type'] : 'fixed';
    $old['amount']            = (float)($_POST['amount'] ?? 0);
    $old['grace_period_days'] = max(0, (int)($_POST['grace_period_days'] ?? 0));
    $old['max_penalty']       = $_POST['max_penalty'] !== '' ? (float)$_POST['max_penalty'] : null;
    $old['is_active']         = !empty($_POST['is_active']) ? 1 : 0;

    if ($old['amount'] <= 0) $errors['amount'] = __('Amount must be positive.');

    if (!$errors) {
        $id = db_insert('late_fee_rules', [
            'company_id' => (int)$USER['company_id'],
            'property_id'=>$old['property_id'],'lease_id'=>$old['lease_id'],
            'fee_type'=>$old['fee_type'],'amount'=>$old['amount'],
            'grace_period_days'=>$old['grace_period_days'],'max_penalty'=>$old['max_penalty'],
            'is_active'=>$old['is_active'],
        ]);
        log_audit('late_fee_rules','create','late_fee_rules',$id,null,$old);
        flash_success(__('Rule created.'));
        redirect('modules/late-fees/index.php');
    }
}
$properties = db_all('SELECT id, name FROM properties WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
$PAGE_TITLE = __('Add Late Fee Rule');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Add Late Fee Rule')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label"><?= e(__('Applies to Property (optional)')) ?></label>
      <select name="property_id" class="form-select">
        <option value=""><?= e(__('Company-wide')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$old['property_id']===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Fee Type')) ?></label>
      <select name="fee_type" class="form-select">
        <?php foreach (['fixed','percentage','daily','monthly'] as $t): ?>
          <option value="<?= e($t) ?>" <?= $old['fee_type']===$t?'selected':'' ?>><?= e(ucfirst($t)) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Amount / Percent')) ?></label>
      <input type="number" step="0.01" name="amount" class="form-control" value="<?= e((string)$old['amount']) ?>" required></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Grace Period (days)')) ?></label>
      <input type="number" name="grace_period_days" class="form-control" value="<?= (int)$old['grace_period_days'] ?>"></div>
    <div class="col-md-3"><label class="form-label"><?= e(__('Max Penalty')) ?></label>
      <input type="number" step="0.01" name="max_penalty" class="form-control" value="<?= e((string)$old['max_penalty']) ?>"></div>
    <div class="col-md-3"><div class="form-check mt-4">
      <input class="form-check-input" type="checkbox" name="is_active" id="active" value="1" <?= $old['is_active']?'checked':'' ?>>
      <label class="form-check-label" for="active"><?= e(__('Active')) ?></label>
    </div></div>
  </div>
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/late-fees/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="save"></i> <?= e(__('Save')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>