<?php
// modules/tenants/view.php — Tenant 360°: single-page, tabbed, AJAX-loaded aggregates.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('tenants.view');
$id = (int)($_GET['id'] ?? 0);

$tenant = db_one('SELECT * FROM tenants WHERE id = :id AND company_id = :c LIMIT 1',
                 [':id' => $id, ':c' => (int)$USER['company_id']]);
if (!$tenant) { flash_error(__('Tenant not found.')); redirect('modules/tenants/index.php'); }

// Top-level KPIs for the header strip
$activeLease = db_one(
    'SELECT l.*, u.unit_number, p.name AS property_name
       FROM leases l
       JOIN units u ON u.id = l.unit_id
       JOIN properties p ON p.id = l.property_id
      WHERE l.tenant_id = :t AND l.status IN ("active","expiring")
      ORDER BY l.id DESC LIMIT 1',
    [':t' => $id]
);
$fin = db_one(
    'SELECT COALESCE(SUM(amount),0)      AS invoiced,
            COALESCE(SUM(amount_paid),0) AS paid,
            COALESCE(SUM(balance),0)     AS outstanding
       FROM invoices WHERE tenant_id = :t',
    [':t' => $id]
);

$PAGE_TITLE   = __('Tenant') . ' · ' . $tenant['first_name'] . ' ' . $tenant['last_name'];
$PAGE_SCRIPTS = ['360.js'];
require __DIR__ . '/../../includes/header.php';

$initials = strtoupper(substr($tenant['first_name'], 0, 1) . substr($tenant['last_name'], 0, 1));
$statusClass = ['active'=>'badge-status-available','former'=>'badge-status-unavailable','blacklisted'=>'badge-status-under_maintenance'][$tenant['status']] ?? 'badge-status-unavailable';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/tenants/index.php"><?= e(__('Tenants')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($tenant['first_name'].' '.$tenant['last_name']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div class="d-flex align-items-center gap-3">
    <?php if (!empty($tenant['profile_photo'])): ?>
      <img src="<?= e(UPLOAD_URL . '/' . $tenant['profile_photo']) ?>" style="width:64px;height:64px;border-radius:50%;object-fit:cover">
    <?php else: ?>
      <span class="srp-avatar" style="width:56px;height:56px;font-size:1.1rem"><?= e($initials) ?></span>
    <?php endif; ?>
    <div>
      <h1 class="h4 mb-1"><?= e($tenant['first_name'].' '.$tenant['last_name']) ?>
        <span class="srp-badge-pill <?= e($statusClass) ?> ms-2"><span class="dot"></span><?= e(ucfirst($tenant['status'])) ?></span>
      </h1>
      <div class="text-muted small">
        <i data-lucide="phone" style="width:13px;height:13px"></i> <?= e($tenant['phone']) ?>
        <?php if ($tenant['email']): ?> · <i data-lucide="mail" style="width:13px;height:13px"></i> <?= e($tenant['email']) ?><?php endif; ?>
        <?php if ($activeLease): ?> · <i data-lucide="home" style="width:13px;height:13px"></i> <?= e($activeLease['unit_number']) ?> · <?= e($activeLease['property_name']) ?><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (can('tenants.edit')): ?>
      <a href="<?= APP_URL ?>/modules/tenants/edit.php?id=<?= (int)$id ?>" class="btn btn-light"><i data-lucide="pencil"></i> <?= e(__('Edit')) ?></a>
    <?php endif; ?>
    <?php if (can('payments.create')): ?>
      <a href="<?= APP_URL ?>/modules/payments/create.php?tenant_id=<?= (int)$id ?>" class="btn btn-primary"><i data-lucide="banknote"></i> <?= e(__('Record Payment')) ?></a>
    <?php endif; ?>
    <?php if (can('communications.send')): ?>
      <a href="<?= APP_URL ?>/modules/communications/compose.php?related_type=tenant&related_id=<?= (int)$id ?>" class="btn btn-outline-primary"><i data-lucide="mail"></i> <?= e(__('Message')) ?></a>
    <?php endif; ?>
  </div>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="receipt"></i></div>
    <div class="kpi-label"><?= e(__('Invoiced')) ?></div>
    <div class="kpi-value"><?= e(money($fin['invoiced'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="check-circle-2"></i></div>
    <div class="kpi-label"><?= e(__('Paid')) ?></div>
    <div class="kpi-value"><?= e(money($fin['paid'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-triangle"></i></div>
    <div class="kpi-label"><?= e(__('Outstanding')) ?></div>
    <div class="kpi-value <?= (float)$fin['outstanding'] > 0 ? 'text-danger' : '' ?>"><?= e(money($fin['outstanding'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="lock"></i></div>
    <div class="kpi-label"><?= e(__('Deposit Held')) ?></div>
    <div class="kpi-value"><?= e(money($activeLease['security_deposit'] ?? 0)) ?></div></div>
</div>

<ul class="nav nav-tabs mb-3" id="srp360Tabs" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-srp-tab="overview"     data-bs-target="#tab-overview"><?= e(__('Overview')) ?></button></li>
  <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-srp-tab="leases"       data-bs-target="#tab-leases"><?= e(__('Leases')) ?></button></li>
  <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-srp-tab="invoices"     data-bs-target="#tab-invoices"><?= e(__('Invoices')) ?></button></li>
  <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-srp-tab="payments"     data-bs-target="#tab-payments"><?= e(__('Payments')) ?></button></li>
  <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-srp-tab="maintenance"  data-bs-target="#tab-maintenance"><?= e(__('Maintenance')) ?></button></li>
  <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-srp-tab="documents"    data-bs-target="#tab-documents"><?= e(__('Documents')) ?></button></li>
  <li class="nav-item"><button class="nav-link"        data-bs-toggle="tab" data-srp-tab="communications" data-bs-target="#tab-comms"><?= e(__('Communications')) ?></button></li>
</ul>

<div class="tab-content" id="srp360TabsContent"
     data-360-type="tenant" data-360-id="<?= (int)$id ?>">

  <div class="tab-pane fade show active" id="tab-overview">
    <div class="row g-3">
      <div class="col-lg-6"><div class="srp-card p-4">
        <h2 class="h6 mb-3"><?= e(__('Personal Details')) ?></h2>
        <div class="row small g-2">
          <div class="col-6"><span class="text-muted"><?= e(__('Type')) ?>:</span> <?= e(ucfirst($tenant['tenant_type'])) ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('ID')) ?>:</span> <?= e($tenant['id_number'] ?: '—') ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Occupation')) ?>:</span> <?= e($tenant['occupation'] ?: '—') ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Employer')) ?>:</span> <?= e($tenant['employer'] ?: '—') ?></div>
          <div class="col-12"><span class="text-muted"><?= e(__('Address')) ?>:</span> <?= e($tenant['address'] ?: '—') ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Emergency Contact')) ?>:</span> <?= e($tenant['emergency_contact_name'] ?: '—') ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Emergency Phone')) ?>:</span> <?= e($tenant['emergency_contact_phone'] ?: '—') ?></div>
        </div>
        <?php if ($tenant['notes']): ?><div class="mt-3 small"><span class="text-muted"><?= e(__('Notes')) ?>:</span><br><?= nl2br(e($tenant['notes'])) ?></div><?php endif; ?>
      </div></div>

      <div class="col-lg-6"><div class="srp-card p-4 h-100">
        <h2 class="h6 mb-3"><?= e(__('Current Lease')) ?></h2>
        <?php if ($activeLease): ?>
          <div class="small g-2">
            <div class="mb-1"><span class="text-muted"><?= e(__('Lease')) ?>:</span> <a href="<?= APP_URL ?>/modules/leases/view.php?id=<?= (int)$activeLease['id'] ?>"><?= e($activeLease['lease_number']) ?></a></div>
            <div class="mb-1"><span class="text-muted"><?= e(__('Unit')) ?>:</span> <a href="<?= APP_URL ?>/modules/units/view.php?id=<?= (int)$activeLease['unit_id'] ?>"><?= e($activeLease['unit_number']) ?></a> · <?= e($activeLease['property_name']) ?></div>
            <div class="mb-1"><span class="text-muted"><?= e(__('Period')) ?>:</span> <?= e($activeLease['start_date']) ?> → <?= e($activeLease['end_date']) ?></div>
            <div class="mb-1"><span class="text-muted"><?= e(__('Monthly Rent')) ?>:</span> <?= e(money($activeLease['monthly_rent'])) ?></div>
            <div><span class="text-muted"><?= e(__('Security Deposit')) ?>:</span> <?= e(money($activeLease['security_deposit'])) ?></div>
          </div>
        <?php else: ?>
          <div class="text-muted small"><?= e(__('No active lease.')) ?></div>
        <?php endif; ?>
      </div></div>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-leases">       <div class="srp-card p-3" data-360-panel="leases">       <div class="srp-skeleton-row" style="height:48px"></div></div></div>
  <div class="tab-pane fade" id="tab-invoices">     <div class="srp-card p-3" data-360-panel="invoices">     <div class="srp-skeleton-row" style="height:48px"></div></div></div>
  <div class="tab-pane fade" id="tab-payments">     <div class="srp-card p-3" data-360-panel="payments">     <div class="srp-skeleton-row" style="height:48px"></div></div></div>
  <div class="tab-pane fade" id="tab-maintenance">  <div class="srp-card p-3" data-360-panel="maintenance">  <div class="srp-skeleton-row" style="height:48px"></div></div></div>
  <div class="tab-pane fade" id="tab-documents">    <div class="srp-card p-3" data-360-panel="documents">    <div class="srp-skeleton-row" style="height:48px"></div></div></div>
  <div class="tab-pane fade" id="tab-comms">        <div class="srp-card p-3" data-360-panel="communications"><div class="srp-skeleton-row" style="height:48px"></div></div></div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>