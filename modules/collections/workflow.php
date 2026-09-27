<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        db_update('collection_workflow_stages', [
            'name' => sanitize_string($_POST['name'] ?? '', 120),
            'stage_order' => (int)($_POST['stage_order'] ?? 1),
            'trigger_days_after_due' => (int)($_POST['trigger_days_after_due'] ?? 0),
            'action_type' => in_array($_POST['action_type'] ?? '', ['reminder','overdue_notice','formal_notice','legal_referral'], true) ? $_POST['action_type'] : 'reminder',
            'message_template' => sanitize_string($_POST['message_template'] ?? '', 1000) ?: null,
            'channel' => in_array($_POST['channel'] ?? '', ['email','sms','in_app','all'], true) ? $_POST['channel'] : 'all',
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        ], 'id = :id AND company_id = :c', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
        log_audit('collections','update_stage','collection_workflow_stages',$id,null,null);
        flash_success(__('Stage updated.'));
    }
    redirect('modules/collections/workflow.php');
}

$stages = db_all('SELECT * FROM collection_workflow_stages WHERE company_id = :c ORDER BY stage_order', [':c'=>(int)$USER['company_id']]);

$PAGE_TITLE = __('Collection Workflow');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Collection Workflow Stages')) ?></h1>
</div>
<div class="srp-card p-3">
  <?php if (!$stages): ?>
    <div class="alert alert-info"><?= e(__('No workflow stages configured. Add them to automate reminders, notices and legal referral.')) ?></div>
  <?php else: ?>
    <?php foreach ($stages as $s): ?>
      <form method="post" class="border rounded p-3 mb-2">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <div class="row g-2 align-items-end">
          <div class="col-md-3"><label class="form-label small"><?= e(__('Stage Name')) ?></label>
            <input name="name" class="form-control form-control-sm" value="<?= e($s['name']) ?>"></div>
          <div class="col-md-1"><label class="form-label small"><?= e(__('Order')) ?></label>
            <input type="number" name="stage_order" class="form-control form-control-sm" value="<?= (int)$s['stage_order'] ?>"></div>
          <div class="col-md-2"><label class="form-label small"><?= e(__('Trigger Days After Due')) ?></label>
            <input type="number" name="trigger_days_after_due" class="form-control form-control-sm" value="<?= (int)$s['trigger_days_after_due'] ?>"></div>
          <div class="col-md-2"><label class="form-label small"><?= e(__('Action')) ?></label>
            <select name="action_type" class="form-select form-select-sm">
              <?php foreach (['reminder','overdue_notice','formal_notice','legal_referral'] as $a): ?>
                <option value="<?= e($a) ?>" <?= $s['action_type']===$a?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$a))) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="col-md-2"><label class="form-label small"><?= e(__('Channel')) ?></label>
            <select name="channel" class="form-select form-select-sm">
              <?php foreach (['email','sms','in_app','all'] as $c): ?>
                <option value="<?= e($c) ?>" <?= $s['channel']===$c?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$c))) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="col-md-2 d-flex align-items-center gap-2">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= $s['is_active']?'checked':'' ?>>
              <label class="form-check-label small"><?= e(__('Active')) ?></label></div>
            <button class="btn btn-sm btn-primary"><?= e(__('Save')) ?></button>
          </div>
          <div class="col-12"><label class="form-label small"><?= e(__('Message Template')) ?></label>
            <textarea name="message_template" class="form-control form-control-sm" rows="2"><?= e((string)$s['message_template']) ?></textarea></div>
        </div>
      </form>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>