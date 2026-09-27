<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_login();

$id = (int)($_GET['id'] ?? 0);
$a = db_one(
    'SELECT ar.*, CONCAT(u.first_name," ",u.last_name) AS requester, CONCAT(ap.first_name," ",ap.last_name) AS approver
       FROM approval_requests ar
       LEFT JOIN users u ON u.id = ar.requested_by
       LEFT JOIN users ap ON ap.id = ar.approver_id
      WHERE ar.id = :id AND ar.company_id = :c LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$a) { flash_error(__('Approval not found.')); redirect('modules/approvals/index.php'); }

// Handle decision
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!can('expenses.approve', $USER) && !can('maintenance.approve', $USER)) {
        flash_error(__('You are not authorized to decide on approvals.'));
        redirect('modules/approvals/view.php?id=' . $id);
    }
    $decision = in_array($_POST['decision'] ?? '', ['approved','rejected'], true) ? $_POST['decision'] : 'approved';
    $notes = sanitize_string($_POST['notes'] ?? '', 500);

    db()->beginTransaction();
    try {
        db_update('approval_requests', [
            'status' => $decision,
            'approver_id' => (int)$USER['id'],
            'decision_notes' => $notes ?: null,
            'decided_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', [':id' => $id]);

        // Cascade: mark the linked expense as paid (example — real logic depends on related_type)
        if ($a['related_type'] === 'expenses' && $decision === 'approved') {
            // nothing to do unless spec decides so — leave as-is
        }

        log_audit('approvals', 'decide', 'approval_requests', $id, $a, ['decision'=>$decision,'notes'=>$notes]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        error_log('[approval.decide] ' . $e->getMessage());
        flash_error(__('Could not save decision.'));
    }
    flash_success(__('Decision recorded.'));
    redirect('modules/approvals/index.php');
}

$PAGE_TITLE = __('Approval Request');
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/approvals/index.php"><?= e(__('Approvals')) ?></a></li>
  <li class="breadcrumb-item active">#<?= (int)$id ?></li>
</ol></nav>

<div class="row justify-content-center"><div class="col-lg-8">
  <div class="srp-card p-4">
    <h1 class="h5 mb-3"><?= e(__('Approval Request')) ?> #<?= (int)$id ?></h1>

    <div class="row small g-2 mb-3">
      <div class="col-6"><span class="text-muted"><?= e(__('Type')) ?>:</span> <?= e(str_replace('_',' ',$a['workflow_type'])) ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Related')) ?>:</span> <?= e($a['related_type']) ?> #<?= (int)$a['related_id'] ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Amount')) ?>:</span> <?= $a['amount'] ? e(money($a['amount'])) : '—' ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Requested By')) ?>:</span> <?= e($a['requester'] ?? '—') ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Status')) ?>:</span> <?= e(ucfirst($a['status'])) ?></div>
      <?php if ($a['decided_at']): ?>
        <div class="col-6"><span class="text-muted"><?= e(__('Decided')) ?>:</span> <?= e($a['decided_at']) ?> <?= e(__('by')) ?> <?= e($a['approver'] ?? '') ?></div>
      <?php endif; ?>
      <?php if ($a['decision_notes']): ?>
        <div class="col-12"><span class="text-muted"><?= e(__('Notes')) ?>:</span> <?= e($a['decision_notes']) ?></div>
      <?php endif; ?>
    </div>

    <?php if ($a['status'] === 'pending'): ?>
      <form method="post" class="border-top pt-3">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label"><?= e(__('Decision Notes')) ?></label>
          <textarea name="notes" class="form-control" rows="2"></textarea></div>
        <div class="d-flex gap-2">
          <button name="decision" value="approved" class="btn btn-success"><i data-lucide="check"></i> <?= e(__('Approve')) ?></button>
          <button name="decision" value="rejected" class="btn btn-danger"><i data-lucide="x"></i> <?= e(__('Reject')) ?></button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>