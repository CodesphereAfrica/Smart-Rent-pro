<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('maintenance.view');
$id = (int)($_GET['id'] ?? 0);
$wo = db_one(
    'SELECT wo.*, mr.ticket_number, mr.description AS mr_description, mr.priority, mr.id AS maintenance_id,
            u.unit_number, p.name AS property_name,
            v.name AS vendor_name, v.phone AS vendor_phone,
            CONCAT(us.first_name," ",us.last_name) AS tech_name
       FROM work_orders wo
       JOIN maintenance_requests mr ON mr.id = wo.maintenance_request_id
       JOIN units u ON u.id = mr.unit_id
       JOIN properties p ON p.id = mr.property_id
       LEFT JOIN vendors v ON v.id = wo.vendor_id
       LEFT JOIN users us ON us.id = wo.technician_user_id
      WHERE wo.id = :id AND wo.company_id = :c LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$wo) { flash_error(__('Not found.')); redirect('modules/work-orders/index.php'); }

$PAGE_TITLE = $wo['work_order_number'];
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/work-orders/index.php"><?= e(__('Work Orders')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($wo['work_order_number']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e($wo['work_order_number']) ?>
      <span class="srp-badge-pill <?= $wo['status']==='completed'?'badge-status-available':($wo['status']==='cancelled'?'badge-status-under_maintenance':'badge-status-occupied') ?> ms-2">
        <span class="dot"></span><?= e(ucfirst(str_replace('_',' ',$wo['status']))) ?>
      </span>
    </h1>
    <div class="text-muted small">
      <a href="<?= APP_URL ?>/modules/maintenance/view.php?id=<?= (int)$wo['maintenance_id'] ?>"><?= e($wo['ticket_number']) ?></a> ·
      <?= e($wo['unit_number']) ?> · <?= e($wo['property_name']) ?>
    </div>
  </div>
  <?php if (can('maintenance.edit')): ?>
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/work-orders/edit.php?id=<?= (int)$id ?>"><i data-lucide="pencil"></i> <?= e(__('Edit')) ?></a>
  <?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-lg-8"><div class="srp-card p-4">
    <h2 class="h6 mb-3"><?= e(__('Work Details')) ?></h2>
    <p><?= nl2br(e((string)$wo['work_description'] ?: $wo['mr_description'])) ?></p>
    <div class="row small g-2 mt-3">
      <div class="col-6"><span class="text-muted"><?= e(__('Vendor')) ?>:</span> <?= e($wo['vendor_name'] ?? '—') ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Technician')) ?>:</span> <?= e($wo['tech_name'] ?? '—') ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Scheduled')) ?>:</span> <?= e($wo['scheduled_date'] ?? '—') ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Completed')) ?>:</span> <?= e($wo['completed_date'] ?? '—') ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Estimated')) ?>:</span> <?= e(money($wo['estimated_cost'] ?? 0)) ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Actual')) ?>:</span> <?= e(money($wo['actual_cost'] ?? 0)) ?></div>
    </div>
  </div></div>
  <div class="col-lg-4">
    <div class="srp-card p-4">
      <h2 class="h6 mb-3"><?= e(__('Expense Link')) ?></h2>
      <?php if ($wo['expense_id']): ?>
        <a class="btn btn-light w-100" href="<?= APP_URL ?>/modules/expenses/view.php?id=<?= (int)$wo['expense_id'] ?>">
          <i data-lucide="arrow-up-right"></i> <?= e(__('View Expense')) ?>
        </a>
      <?php elseif (can('expenses.create')): ?>
        <p class="text-muted small"><?= e(__('Convert this work order into an expense to hit the ledger.')) ?></p>
        <a class="btn btn-primary w-100" href="<?= APP_URL ?>/modules/expenses/create.php?work_order_id=<?= (int)$id ?>&amount=<?= e((string)$wo['actual_cost']) ?>&vendor_id=<?= (int)$wo['vendor_id'] ?>">
          <i data-lucide="receipt"></i> <?= e(__('Create Expense')) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>