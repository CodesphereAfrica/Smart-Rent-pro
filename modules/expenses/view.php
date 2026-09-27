<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('expenses.view');
$id = (int)($_GET['id'] ?? 0);
$e = db_one(
    'SELECT e.*, cat.name AS category_name,
            p.name AS property_name, u.unit_number, v.name AS vendor_name,
            wo.work_order_number,
            CONCAT(us.first_name," ",us.last_name) AS creator
       FROM expenses e
       LEFT JOIN expense_categories cat ON cat.id = e.category_id
       LEFT JOIN properties p ON p.id = e.property_id
       LEFT JOIN units u ON u.id = e.unit_id
       LEFT JOIN vendors v ON v.id = e.vendor_id
       LEFT JOIN work_orders wo ON wo.id = e.related_work_order_id
       LEFT JOIN users us ON us.id = e.created_by
      WHERE e.id = :id AND e.company_id = :c LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$e) { flash_error(__('Not found.')); redirect('modules/expenses/index.php'); }

$approval = $e['approval_request_id']
    ? db_one('SELECT ar.*, CONCAT(u.first_name," ",u.last_name) AS approver FROM approval_requests ar LEFT JOIN users u ON u.id = ar.approver_id WHERE ar.id = :a', [':a'=>(int)$e['approval_request_id']])
    : null;

$PAGE_TITLE = __('Expense') . ' #' . $id;
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/expenses/index.php"><?= e(__('Expenses')) ?></a></li>
  <li class="breadcrumb-item active">#<?= (int)$id ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e($e['description']) ?></h1>
    <div class="text-muted small">
      <?= e($e['category_name'] ?? '—') ?>
      <?php if ($e['property_name']): ?> · <?= e($e['property_name']) ?><?= $e['unit_number'] ? ' · ' . e($e['unit_number']) : '' ?><?php endif; ?>
      <?php if ($e['vendor_name']): ?> · <?= e($e['vendor_name']) ?><?php endif; ?>
    </div>
  </div>
  <?php if (can('expenses.edit')): ?>
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/expenses/edit.php?id=<?= (int)$id ?>"><i data-lucide="pencil"></i> <?= e(__('Edit')) ?></a>
  <?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-lg-6"><div class="srp-card p-4">
    <h2 class="h6 mb-3"><?= e(__('Details')) ?></h2>
    <div class="row small g-2">
      <div class="col-6"><span class="text-muted"><?= e(__('Amount')) ?>:</span> <span class="fw-semibold"><?= e(money($e['amount'])) ?></span></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Date')) ?>:</span> <?= e($e['expense_date']) ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Payment')) ?>:</span> <?= e(ucfirst(str_replace('_',' ',$e['payment_status']))) ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Recorded by')) ?>:</span> <?= e($e['creator'] ?? '—') ?></div>
      <?php if ($e['work_order_number']): ?>
        <div class="col-12"><span class="text-muted"><?= e(__('Work Order')) ?>:</span>
          <a href="<?= APP_URL ?>/modules/work-orders/view.php?id=<?= (int)$e['related_work_order_id'] ?>"><?= e($e['work_order_number']) ?></a></div>
      <?php endif; ?>
    </div>
  </div></div>

  <?php if ($approval): ?>
  <div class="col-lg-6"><div class="srp-card p-4 h-100">
    <h2 class="h6 mb-3"><?= e(__('Approval')) ?></h2>
    <div class="small">
      <div class="mb-2"><span class="text-muted"><?= e(__('Status')) ?>:</span>
        <span class="srp-badge-pill badge-status-<?= $approval['status']==='approved'?'available':($approval['status']==='rejected'?'under_maintenance':'reserved') ?>">
          <span class="dot"></span><?= e(ucfirst($approval['status'])) ?>
        </span></div>
      <?php if ($approval['amount']): ?>
        <div class="mb-2"><span class="text-muted"><?= e(__('Amount')) ?>:</span> <?= e(money($approval['amount'])) ?></div>
      <?php endif; ?>
      <?php if ($approval['approver']): ?>
        <div class="mb-2"><span class="text-muted"><?= e(__('Approver')) ?>:</span> <?= e($approval['approver']) ?></div>
      <?php endif; ?>
      <?php if ($approval['decision_notes']): ?>
        <div class="mb-2"><span class="text-muted"><?= e(__('Notes')) ?>:</span> <?= e($approval['decision_notes']) ?></div>
      <?php endif; ?>
      <?php if (can('expenses.approve') && $approval['status'] === 'pending'): ?>
        <a class="btn btn-primary w-100 mt-2" href="<?= APP_URL ?>/modules/approvals/view.php?id=<?= (int)$approval['id'] ?>"><i data-lucide="check-square"></i> <?= e(__('Review Approval')) ?></a>
      <?php endif; ?>
    </div>
  </div></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>