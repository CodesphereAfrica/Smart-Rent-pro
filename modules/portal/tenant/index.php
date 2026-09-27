<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
$USER = require_role('Tenant');

// Query-level tenant isolation
$tenant = db_one(
    'SELECT * FROM tenants WHERE user_id = :u LIMIT 1',
    [':u'=>(int)$USER['id']]
);
if (!$tenant) { flash_error(__('Your account is not linked to a tenant record.')); redirect('modules/dashboard/index.php'); }
$tenantId = (int)$tenant['id'];

$lease = db_one(
    'SELECT l.*, u.unit_number, p.name AS property_name
       FROM leases l       JOIN units u ON u.id = l.unit_id
       JOIN properties p ON p.id = l.property_id
      WHERE l.tenant_id = :t AND l.status IN ("active","expiring")
      ORDER BY l.id DESC LIMIT 1',
    [':t' => $tenantId]
);

$balance = db_one(
    'SELECT COALESCE(SUM(balance),0) AS owed,
            COALESCE(SUM(CASE WHEN status = "overdue" THEN balance ELSE 0 END),0) AS overdue
       FROM invoices WHERE tenant_id = :t',
    [':t' => $tenantId]
);

$PAGE_TITLE = __('My Account');
require __DIR__ . '/../../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Hello')) ?>, <?= e($USER['first_name']) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('Your tenancy, payments and requests at a glance.')) ?></p>
  </div>
</div>

<div class="kpi-grid mb-4">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="home"></i></div>
    <div class="kpi-label"><?= e(__('Unit')) ?></div>
    <div class="kpi-value"><?= e($lease['unit_number'] ?? '—') ?></div>
    <div class="text-muted small"><?= e($lease['property_name'] ?? '') ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div>
    <div class="kpi-label"><?= e(__('Monthly Rent')) ?></div>
    <div class="kpi-value"><?= e(money($lease['monthly_rent'] ?? 0)) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-triangle"></i></div>
    <div class="kpi-label"><?= e(__('Balance Owed')) ?></div>
    <div class="kpi-value <?= (float)$balance['owed'] > 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($balance['owed'])) ?></div>
    <div class="text-muted small"><?= (float)$balance['overdue'] > 0 ? e(money($balance['overdue'])) . ' ' . e(__('overdue')) : e(__('All current')) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="calendar"></i></div>
    <div class="kpi-label"><?= e(__('Lease Ends')) ?></div>
    <div class="kpi-value" style="font-size:1.1rem"><?= e($lease['end_date'] ?? '—') ?></div></div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <a class="srp-card p-4 d-block text-decoration-none text-reset" href="<?= APP_URL ?>/modules/portal/tenant/rent.php">
      <div class="kpi-icon mb-2"><i data-lucide="receipt"></i></div>
      <div class="fw-semibold"><?= e(__('Rent & Invoices')) ?></div>
      <div class="text-muted small"><?= e(__('View your rent schedule, invoices and receipts.')) ?></div>
    </a>
  </div>
  <div class="col-md-6">
    <a class="srp-card p-4 d-block text-decoration-none text-reset" href="<?= APP_URL ?>/modules/portal/tenant/maintenance.php">
      <div class="kpi-icon mb-2"><i data-lucide="hammer"></i></div>
      <div class="fw-semibold"><?= e(__('Maintenance')) ?></div>
      <div class="text-muted small"><?= e(__('Submit a request and track progress.')) ?></div>
    </a>
  </div>
  <div class="col-md-6">
    <a class="srp-card p-4 d-block text-decoration-none text-reset" href="<?= APP_URL ?>/modules/portal/tenant/documents.php">
      <div class="kpi-icon mb-2"><i data-lucide="folder"></i></div>
      <div class="fw-semibold"><?= e(__('My Documents')) ?></div>
      <div class="text-muted small"><?= e(__('Lease agreement, receipts and shared files.')) ?></div>
    </a>
  </div>
  <div class="col-md-6">
    <a class="srp-card p-4 d-block text-decoration-none text-reset" href="<?= APP_URL ?>/modules/portal/tenant/announcements.php">
      <div class="kpi-icon mb-2"><i data-lucide="megaphone"></i></div>
      <div class="fw-semibold"><?= e(__('Announcements')) ?></div>
      <div class="text-muted small"><?= e(__('Messages from your property manager.')) ?></div>
    </a>
  </div>
</div>

<?php require __DIR__ . '/../../../includes/footer.php'; ?>