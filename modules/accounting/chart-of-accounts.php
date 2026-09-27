<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('accounting.view');

// Inline create handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && can('accounting.manage', $USER)) {
    require_csrf();
    $code = sanitize_string($_POST['account_code'] ?? '', 20);
    $name = sanitize_string($_POST['account_name'] ?? '', 150);
    $type = in_array($_POST['account_type'] ?? '', ['asset','liability','equity','revenue','expense'], true) ? $_POST['account_type'] : 'asset';
    $parent = (int)($_POST['parent_account_id'] ?? 0) ?: null;
    if ($code !== '' && $name !== '') {
        $id = db_insert('chart_of_accounts', [
            'company_id' => (int)$USER['company_id'],
            'account_code' => $code,
            'account_name' => $name,
            'account_type' => $type,
            'parent_account_id' => $parent,
        ]);
        log_audit('accounting','create_account','chart_of_accounts',$id,null,['code'=>$code,'name'=>$name]);
        flash_success(__('Account created.'));
    }
    redirect('modules/accounting/chart-of-accounts.php');
}

$accounts = db_all('SELECT * FROM chart_of_accounts WHERE company_id = :c ORDER BY account_code', [':c'=>(int)$USER['company_id']]);
$typeGroups = ['asset'=>[],'liability'=>[],'equity'=>[],'revenue'=>[],'expense'=>[]];
foreach ($accounts as $a) $typeGroups[$a['account_type']][] = $a;

$PAGE_TITLE = __('Chart of Accounts');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Chart of Accounts')) ?></h1>
  <?php if (can('accounting.manage')): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal"><i data-lucide="plus"></i> <?= e(__('Add Account')) ?></button>
  <?php endif; ?>
</div>

<div class="row g-3">
  <?php foreach ($typeGroups as $type => $list): ?>
    <div class="col-md-6">
      <div class="srp-card p-4">
        <h2 class="h6 text-uppercase text-muted mb-3"><?= e($type) ?></h2>
        <table class="table table-sm mb-0">
          <thead><tr><th><?= e(__('Code')) ?></th><th><?= e(__('Name')) ?></th></tr></thead>
          <tbody>
          <?php if (!$list): ?><tr><td colspan="2" class="text-muted small">—</td></tr>
          <?php else: foreach ($list as $a): ?>
            <tr><td><?= e($a['account_code']) ?></td><td><?= e($a['account_name']) ?></td></tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (can('accounting.manage')): ?>
<div class="modal fade" id="addAccountModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post">
      <?= csrf_field() ?>
      <div class="modal-header"><h5 class="modal-title"><?= e(__('Add Account')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label"><?= e(__('Account Code')) ?></label>
          <input name="account_code" class="form-control" required></div>
        <div class="mb-3"><label class="form-label"><?= e(__('Account Name')) ?></label>
          <input name="account_name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label"><?= e(__('Type')) ?></label>
          <select name="account_type" class="form-select">
            <?php foreach (['asset','liability','equity','revenue','expense'] as $t): ?>
              <option value="<?= e($t) ?>"><?= e(ucfirst($t)) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="mb-3"><label class="form-label"><?= e(__('Parent Account (optional)')) ?></label>
          <select name="parent_account_id" class="form-select">
            <option value=""><?= e(__('— none —')) ?></option>
            <?php foreach ($accounts as $a): ?>
              <option value="<?= (int)$a['id'] ?>"><?= e($a['account_code'] . ' — ' . $a['account_name']) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?= e(__('Cancel')) ?></button>
        <button class="btn btn-primary"><?= e(__('Save')) ?></button>
      </div>
    </form>
  </div></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>