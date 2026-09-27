<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('leases.view');
$id = (int)($_GET['id'] ?? 0);
$l = db_one(
    'SELECT l.*,
            CONCAT(t.first_name," ",t.last_name) AS tenant_name,
            t.phone AS tenant_phone, t.email AS tenant_email,
            u.unit_number, u.id AS unit_id,
            p.name AS property_name, p.id AS property_id,
            u2.first_name AS mgr_first, u2.last_name AS mgr_last
       FROM leases l
       JOIN tenants t ON t.id = l.tenant_id
       JOIN units   u ON u.id = l.unit_id
       JOIN properties p ON p.id = l.property_id
       LEFT JOIN users u2 ON u2.id = p.assigned_manager_id
      WHERE l.id = :id AND l.company_id = :c LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$l) { flash_error(__('Lease not found.')); redirect('modules/leases/index.php'); }

$invoices = db_all(
    'SELECT id, invoice_number, invoice_type, amount, amount_paid, balance, due_date, status
       FROM invoices WHERE lease_id = :l ORDER BY due_date DESC LIMIT 50',
    [':l' => $id]
);
$daysLeft = (int)((strtotime($l['end_date']) - strtotime(date('Y-m-d'))) / 86400);

$PAGE_TITLE = $l['lease_number'];
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/leases/index.php"><?= e(__('Leases')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($l['lease_number']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e(__('Lease')) ?> <?= e($l['lease_number']) ?>
      <span class="srp-badge-pill badge-status-<?= $l['status']==='active'?'available':($l['status']==='expiring'?'notice_given':($l['status']==='expired'||$l['status']==='terminated'?'under_maintenance':'reserved')) ?> ms-2">
        <span class="dot"></span><?= e(ucfirst(str_replace('_',' ',$l['status']))) ?>
      </span>
    </h1>
    <div class="text-muted small">
      <a href="<?= APP_URL ?>/modules/tenants/view.php?id=<?= (int)$l['tenant_id'] ?>"><?= e($l['tenant_name']) ?></a> ·
      <a href="<?= APP_URL ?>/modules/units/view.php?id=<?= (int)$l['unit_id'] ?>"><?= e($l['unit_number']) ?></a> ·
      <a href="<?= APP_URL ?>/modules/properties/view.php?id=<?= (int)$l['property_id'] ?>"><?= e($l['property_name']) ?></a>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('leases.edit')): ?>
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/leases/edit.php?id=<?= (int)$id ?>"><i data-lucide="pencil"></i> <?= e(__('Edit')) ?></a>
    <?php endif; ?>
    <?php if (in_array($l['status'], ['active','expiring'], true) && can('leases.create')): ?>
      <a class="btn btn-primary" href="<?= APP_URL ?>/modules/leases/renew.php?id=<?= (int)$id ?>"><i data-lucide="refresh-cw"></i> <?= e(__('Renew')) ?></a>
    <?php endif; ?>
    <a class="btn btn-outline-primary" href="<?= APP_URL ?>/modules/documents/upload.php?related_type=lease&related_id=<?= (int)$id ?>"><i data-lucide="upload"></i> <?= e(__('Attach Document')) ?></a>
  </div>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Monthly Rent')) ?></div>
    <div class="kpi-value"><?= e(money($l['monthly_rent'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="lock"></i></div>
    <div class="kpi-label"><?= e(__('Deposit')) ?></div>
    <div class="kpi-value"><?= e(money($l['security_deposit'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="calendar"></i></div>
    <div class="kpi-label"><?= e(__('Days Remaining')) ?></div>
    <div class="kpi-value"><?= $daysLeft > 0 ? (int)$daysLeft : 0 ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="repeat"></i></div>
    <div class="kpi-label"><?= e(__('Frequency')) ?></div>
    <div class="kpi-value text-capitalize" style="font-size:1rem"><?= e(str_replace('_',' ',$l['payment_frequency'])) ?></div></div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-terms"><?= e(__('Terms')) ?></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-invoices"><?= e(__('Invoices')) ?></button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tab-terms">
    <div class="row g-3">
      <div class="col-lg-6"><div class="srp-card p-4">
        <h2 class="h6 mb-3"><?= e(__('Lease Terms')) ?></h2>
        <div class="row small g-2">
          <div class="col-6"><span class="text-muted"><?= e(__('Start')) ?>:</span> <?= e($l['start_date']) ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('End')) ?>:</span> <?= e($l['end_date']) ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Grace Period')) ?>:</span> <?= (int)$l['grace_period_days'] ?> <?= e(__('days')) ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Notice Period')) ?>:</span> <?= (int)$l['notice_period_days'] ?> <?= e(__('days')) ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Signed')) ?>:</span> <?= e($l['signed_at'] ?: '—') ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Manager')) ?>:</span> <?= e(trim(($l['mgr_first'] ?? '') . ' ' . ($l['mgr_last'] ?? '')) ?: '—') ?></div>
        </div>
      </div></div>
      <div class="col-lg-6"><div class="srp-card p-4 h-100">
        <h2 class="h6 mb-3"><?= e(__('Inclusions')) ?></h2>
        <?php $iu = json_decode((string)$l['included_utilities'], true) ?: [];
              $isv = json_decode((string)$l['included_services'], true) ?: []; ?>
        <div class="small">
          <div class="mb-2"><span class="text-muted"><?= e(__('Utilities')) ?>:</span>
            <?= $iu ? implode(', ', array_map('ucfirst', $iu)) : '—' ?></div>
          <div><span class="text-muted"><?= e(__('Services')) ?>:</span>
            <?= $isv ? implode(', ', array_map('ucfirst', $isv)) : '—' ?></div>
        </div>
        <?php if ($l['renewal_conditions']): ?>
          <hr><h3 class="h6"><?= e(__('Renewal Conditions')) ?></h3>
          <div class="small"><?= nl2br(e($l['renewal_conditions'])) ?></div>
        <?php endif; ?>
        <?php if ($l['special_clauses']): ?>
          <hr><h3 class="h6"><?= e(__('Special Clauses')) ?></h3>
          <div class="small"><?= nl2br(e($l['special_clauses'])) ?></div>
        <?php endif; ?>
      </div></div>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-invoices">
    <div class="srp-card p-3">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th><?= e(__('Invoice #')) ?></th><th><?= e(__('Type')) ?></th><th><?= e(__('Amount')) ?></th><th><?= e(__('Balance')) ?></th><th><?= e(__('Due')) ?></th><th><?= e(__('Status')) ?></th></tr></thead>
        <tbody>
          <?php if (!$invoices): ?><tr><td colspan="6" class="text-muted text-center py-4"><?= e(__('No invoices yet.')) ?></td></tr>
          <?php else: foreach ($invoices as $inv): ?>
            <tr>
              <td><a href="<?= APP_URL ?>/modules/invoices/view.php?id=<?= (int)$inv['id'] ?>"><?= e($inv['invoice_number']) ?></a></td>
              <td><?= e(ucfirst(str_replace('_',' ',$inv['invoice_type']))) ?></td>
              <td><?= e(money($inv['amount'])) ?></td>
              <td><?= e(money($inv['balance'])) ?></td>
              <td><?= e($inv['due_date']) ?></td>
              <td><?= e(ucfirst(str_replace('_',' ',$inv['status']))) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>