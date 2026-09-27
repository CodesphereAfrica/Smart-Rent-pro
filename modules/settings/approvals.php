<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $data = [
        'workflow_type' => in_array($_POST['workflow_type'] ?? '', ['maintenance_expense','refund','rent_adjustment','write_off','discount','lease_termination','expense','other'], true) ? $_POST['workflow_type'] : 'expense',
        'name' => sanitize_string($_POST['name'] ?? '', 120),
        'min_amount' => (float)($_POST['min_amount'] ?? 0),
        'max_amount' => $_POST['max_amount'] !== '' ? (float)$_POST['max_amount'] : null,
        'required_role_id' => (int)($_POST['required_role_id'] ?? 0),
        'sequence_order' => (int)($_POST['sequence_order'] ?? 1),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    ];
    if ($id) {
        db_update('approval_workflows', $data, 'id = :id AND company_id = :c', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
        log_audit('approval_workflows','update','approval_workflows',$id,null,$data);
        flash_success(__('Workflow updated.'));
    } else {
        $data['company_id'] = (int)$USER['company_id'];
        $newId = db_insert('approval_workflows', $data);
        log_audit('approval_workflows','create','approval_workflows',$newId,null,$data);
        flash_success(__('Workflow created.'));
    }
    redirect('modules/settings/approvals.php');
}

$workflows = db_all(
    'SELECT aw.*, r.name AS role_name FROM approval_workflows aw
       LEFT JOIN roles r ON r.id = aw.required_role_id
      WHERE aw.company_id = :c ORDER BY aw.workflow_type, aw.sequence_order',
    [':c' => (int)$USER['company_id']]
);
$roles = db_all('SELECT id, name FROM roles WHERE company_id IS NULL OR company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('Approval Workflows');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Approval Workflows')) ?></h1>

<div class="srp-card p-3 mb-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Name')) ?></th><th><?= e(__('Type')) ?></th>
      <th><?= e(__('Threshold')) ?></th><th><?= e(__('Approver Role')) ?></th>
      <th><?= e(__('Order')) ?></th><th><?= e(__('Active')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$workflows): ?><tr><td colspan="7" class="text-muted text-center py-4"><?= e(__('No workflows defined.')) ?></td></tr>
      <?php else: foreach ($workflows as $w): ?>
        <tr>
          <td><?= e($w['name']) ?></td>
          <td><?= e(str_replace('_',' ',$w['workflow_type'])) ?></td>
          <td><?= e(money($w['min_amount'])) ?><?= $w['max_amount'] ? ' – ' . e(money($w['max_amount'])) : '+' ?></td>
          <td><?= e($w['role_name'] ?? '—') ?></td>
          <td><?= (int)$w['sequence_order'] ?></td>
          <td><?= $w['is_active'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
          <td class="text-end"><a class="btn btn-sm btn-light" href="?edit=<?= (int)$w['id'] ?>"><i data-lucide="pencil"></i></a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="srp-card p-4">
  <h2 class="h6 mb-3"><?= e(__('Add Workflow Rule')) ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label"><?= e(__('Name')) ?></label>
        <input name="name" class="form-control" required></div>
      <div class="col-md-3"><label class="form-label"><?= e(__('Type')) ?></label>
        <select name="workflow_type" class="form-select">
          <?php foreach (['maintenance_expense','refund','rent_adjustment','write_off','discount','lease_termination','expense','other'] as $t): ?>
            <option value="<?= e($t) ?>"><?= e(ucfirst(str_replace('_',' ',$t))) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-2"><label class="form-label"><?= e(__('Min Amount')) ?></label>
        <input type="number" step="0.01" name="min_amount" class="form-control" value="0"></div>
      <div class="col-md-2"><label class="form-label"><?= e(__('Max Amount')) ?></label>
        <input type="number" step="0.01" name="max_amount" class="form-control" placeholder="<?= e(__('(no limit)')) ?>"></div>
      <div class="col-md-2"><label class="form-label"><?= e(__('Sequence')) ?></label>
        <input type="number" name="sequence_order" class="form-control" value="1"></div>
      <div class="col-md-4"><label class="form-label"><?= e(__('Approver Role')) ?></label>
        <select name="required_role_id" class="form-select" required>
          <option value=""><?= e(__('— select —')) ?></option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="col-md-3"><div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" checked>
        <label class="form-check-label" for="active"><?= e(__('Active')) ?></label>
      </div></div>
    </div>
    <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary"><?= e(__('Add Rule')) ?></button></div>
  </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>