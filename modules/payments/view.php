<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('payments.view');
$id = (int)($_GET['id'] ?? 0);
$p = db_one(
    'SELECT p.*, CONCAT(t.first_name," ",t.last_name) AS tenant_name,
            t.phone AS tenant_phone, t.email AS tenant_email,
            u.unit_number, u.id AS unit_id,
            p2.name AS property_name
       FROM payments p
       JOIN tenants t ON t.id = p.tenant_id
       LEFT JOIN units u ON u.id = p.unit_id
       LEFT JOIN properties p2 ON p2.id = u.property_id
      WHERE p.id = :id AND p.company_id = :c LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$p) { flash_error(__('Payment not found.')); redirect('modules/payments/index.php'); }

$allocs = db_all(
    'SELECT pa.id, pa.amount_allocated, pa.allocation_type, pa.created_at,
            i.id AS invoice_id, i.invoice_number, i.invoice_type
       FROM payment_allocations pa
       JOIN invoices i ON i.id = pa.invoice_id
      WHERE pa.payment_id = :p
      ORDER BY pa.id ASC',
    [':p' => $id]
);

$PAGE_TITLE = __('Payment') . ' #' . $p['id'];
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/payments/index.php"><?= e(__('Payments')) ?></a></li>
  <li class="breadcrumb-item active">#<?= (int)$p['id'] ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e(__('Payment')) ?> #<?= (int)$p['id'] ?>
      <span class="srp-badge-pill <?= $p['status']==='completed'?'badge-status-available':($p['status']==='pending'?'badge-status-reserved':'badge-status-under_maintenance') ?> ms-2">
        <span class="dot"></span><?= e(ucfirst($p['status'])) ?>
      </span>
    </h1>
    <div class="text-muted small">
      <a href="<?= APP_URL ?>/modules/tenants/view.php?id=<?= (int)$p['tenant_id'] ?>"><?= e($p['tenant_name']) ?></a>
      <?php if ($p['unit_number']): ?> · <?= e($p['unit_number']) ?> (<?= e($p['property_name']) ?>)<?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/payments/receipt.php?id=<?= (int)$id ?>" target="_blank"><i data-lucide="file-text"></i> <?= e(__('Receipt PDF')) ?></a>
    <?php if ((float)$p['amount_unallocated'] > 0 && can('payments.create')): ?>
      <a class="btn btn-primary" href="<?= APP_URL ?>/modules/payments/allocate.php?id=<?= (int)$id ?>"><i data-lucide="git-merge"></i> <?= e(__('Allocate')) ?></a>
    <?php endif; ?>
    <?php if ($p['status'] === 'completed' && can('payments.reverse')): ?>
      <a class="btn btn-outline-danger" href="<?= APP_URL ?>/modules/payments/reverse.php?id=<?= (int)$id ?>"><i data-lucide="x-circle"></i> <?= e(__('Reverse')) ?></a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6"><div class="srp-card p-4">
    <h2 class="h6 mb-3"><?= e(__('Payment Details')) ?></h2>
    <div class="row small g-2">
      <div class="col-6"><span class="text-muted"><?= e(__('Amount')) ?>:</span> <span class="fw-semibold"><?= e(money($p['amount'])) ?></span></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Unallocated')) ?>:</span> <?= e(money($p['amount_unallocated'])) ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Date')) ?>:</span> <?= e($p['payment_date']) ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Method')) ?>:</span> <?= e(ucfirst(str_replace('_',' ',$p['payment_method']))) ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Reference')) ?>:</span> <?= e($p['payment_reference'] ?? '—') ?></div>
      <div class="col-6"><span class="text-muted"><?= e(__('Recorded')) ?>:</span> <?= e($p['created_at']) ?></div>
      <?php if ($p['notes']): ?>
        <div class="col-12"><span class="text-muted"><?= e(__('Notes')) ?>:</span> <?= e($p['notes']) ?></div>
      <?php endif; ?>
    </div>
  </div></div>

  <div class="col-lg-6"><div class="srp-card p-4 h-100">
    <h2 class="h6 mb-3"><?= e(__('Allocations')) ?></h2>
    <?php if (!$allocs): ?>
      <div class="text-muted small"><?= e(__('No allocations yet — payment is unallocated.')) ?></div>
    <?php else: ?>
      <ul class="list-unstyled small mb-0">
        <?php foreach ($allocs as $a): ?>
          <li class="border-bottom pb-2 mb-2 d-flex justify-content-between">
            <div>
              <a href="<?= APP_URL ?>/modules/invoices/view.php?id=<?= (int)$a['invoice_id'] ?>"><?= e($a['invoice_number']) ?></a>
              <div class="text-muted"><?= e(ucfirst(str_replace('_',' ',$a['invoice_type']))) ?> · <?= e($a['allocation_type']) ?></div>
            </div>
            <div class="fw-semibold"><?= e(money($a['amount_allocated'])) ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>