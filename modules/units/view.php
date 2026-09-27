<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('units.view');
$id = (int)($_GET['id'] ?? 0);

$u = db_one(
    'SELECT u.*, p.name AS property_name, p.id AS property_id,
            b.name AS building_name, f.floor_number, f.floor_name
       FROM units u
       LEFT JOIN properties p ON p.id = u.property_id
       LEFT JOIN buildings  b ON b.id = u.building_id
       LEFT JOIN floors     f ON f.id = u.floor_id
      WHERE u.id = :id AND u.company_id = :c LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$u) { flash_error(__('Unit not found.')); redirect('modules/units/index.php'); }

// Connected data chains — Unit → Lease → Tenant → Payments → Invoices
$activeLease = db_one(
    'SELECT l.*, t.first_name, t.last_name, t.phone, t.email, t.id AS tenant_id
       FROM leases l JOIN tenants t ON t.id = l.tenant_id
      WHERE l.unit_id = :u AND l.status IN ("active","expiring") ORDER BY l.id DESC LIMIT 1',
    [':u'=>$id]
);
$leases = db_all('SELECT id, lease_number, start_date, end_date, monthly_rent, status FROM leases WHERE unit_id = :u ORDER BY id DESC LIMIT 8', [':u'=>$id]);
$invoices = db_all('SELECT id, invoice_number, invoice_type, amount, amount_paid, balance, due_date, status FROM invoices WHERE unit_id = :u ORDER BY id DESC LIMIT 8', [':u'=>$id]);
$payments = db_all('SELECT id, amount, payment_date, payment_method, status FROM payments WHERE unit_id = :u ORDER BY id DESC LIMIT 8', [':u'=>$id]);
$maintenance = db_all('SELECT id, ticket_number, priority, status, description, created_at FROM maintenance_requests WHERE unit_id = :u ORDER BY id DESC LIMIT 8', [':u'=>$id]);
$inspections = db_all('SELECT id, inspection_type, inspection_date, status FROM inspections WHERE unit_id = :u ORDER BY id DESC LIMIT 8', [':u'=>$id]);

// Financials
$fin = db_one(
    'SELECT
       COALESCE(SUM(amount), 0) AS invoiced,
       COALESCE(SUM(amount_paid), 0) AS paid,
       COALESCE(SUM(balance), 0) AS outstanding
     FROM invoices WHERE unit_id = :u',
    [':u'=>$id]
);

$PAGE_TITLE = __('Unit') . ' ' . $u['unit_number'];
require __DIR__ . '/../../includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/units/index.php"><?= e(__('Units')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($u['unit_number']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e(__('Unit')) ?> <?= e($u['unit_number']) ?>
      <span class="srp-badge-pill badge-status-<?= e($u['status']) ?> ms-2"><span class="dot"></span><?= e(ucfirst(str_replace('_',' ',$u['status']))) ?></span>
    </h1>
    <div class="text-muted small">
      <?= e($u['property_name']) ?>
      <?php if ($u['building_name']): ?> · <?= e($u['building_name']) ?><?php endif; ?>
      <?php if ($u['floor_number'] !== null): ?> · <?= e(__('Floor')) ?> <?= (int)$u['floor_number'] ?><?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <?php if (can('units.edit')): ?>
      <a href="<?= APP_URL ?>/modules/units/edit.php?id=<?= (int)$id ?>" class="btn btn-light"><i data-lucide="pencil"></i> <?= e(__('Edit')) ?></a>
    <?php endif; ?>
    <?php if (!$activeLease && can('leases.create')): ?>
      <a href="<?= APP_URL ?>/modules/leases/create.php?unit_id=<?= (int)$id ?>" class="btn btn-primary"><i data-lucide="file-plus"></i> <?= e(__('Create Lease')) ?></a>
    <?php endif; ?>
    <?php if (can('payments.create')): ?>
      <a href="<?= APP_URL ?>/modules/payments/create.php?unit_id=<?= (int)$id ?>" class="btn btn-outline-primary"><i data-lucide="banknote"></i> <?= e(__('Record Payment')) ?></a>
    <?php endif; ?>
  </div>
</div>

<!-- KPI strip -->
<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="banknote"></i></div><div class="kpi-label"><?= e(__('Base Rent')) ?></div><div class="kpi-value"><?= e(money($u['base_rent'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="receipt"></i></div><div class="kpi-label"><?= e(__('Invoiced')) ?></div><div class="kpi-value"><?= e(money($fin['invoiced'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="check-circle-2"></i></div><div class="kpi-label"><?= e(__('Collected')) ?></div><div class="kpi-value"><?= e(money($fin['paid'])) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="alert-triangle"></i></div><div class="kpi-label"><?= e(__('Outstanding')) ?></div><div class="kpi-value"><?= e(money($fin['outstanding'])) ?></div></div>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview"><?= e(__('Overview')) ?></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-leases"><?= e(__('Leases')) ?></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-invoices"><?= e(__('Invoices')) ?></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payments"><?= e(__('Payments')) ?></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-maintenance"><?= e(__('Maintenance')) ?></button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-inspections"><?= e(__('Inspections')) ?></button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tab-overview">
    <div class="row g-3">
      <div class="col-lg-6"><div class="srp-card p-4">
        <h2 class="h6 mb-3"><?= e(__('Unit Details')) ?></h2>
        <div class="row small g-2">
          <div class="col-6"><span class="text-muted"><?= e(__('Type')) ?>:</span> <?= e($u['unit_type'] ?? '—') ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Furnishing')) ?>:</span> <?= e(ucfirst(str_replace('_',' ',$u['furnished_status']))) ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Bedrooms')) ?>:</span> <?= (int)$u['bedrooms'] ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Bathrooms')) ?>:</span> <?= (int)$u['bathrooms'] ?></div>
          <div class="col-6"><span class="text-muted"><?= e(__('Size')) ?>:</span> <?= e((string)$u['size_sqm']) ?> m²</div>
          <div class="col-6"><span class="text-muted"><?= e(__('Deposit')) ?>:</span> <?= e(money($u['security_deposit'])) ?></div>
          <?php if ($u['availability_date']): ?>
            <div class="col-12"><span class="text-muted"><?= e(__('Available From')) ?>:</span> <?= e($u['availability_date']) ?></div>
          <?php endif; ?>
        </div>
        <?php if ($u['description']): ?><div class="mt-3 small"><?= nl2br(e($u['description'])) ?></div><?php endif; ?>
      </div></div>

      <div class="col-lg-6"><div class="srp-card p-4 h-100">
        <h2 class="h6 mb-3"><?= e(__('Current Tenant')) ?></h2>
        <?php if ($activeLease): ?>
          <div class="d-flex align-items-center gap-3 mb-2">
            <span class="srp-avatar"><?= e(strtoupper(substr($activeLease['first_name'],0,1).substr($activeLease['last_name'],0,1))) ?></span>
            <div>
              <div class="fw-semibold"><a href="<?= APP_URL ?>/modules/tenants/view.php?id=<?= (int)$activeLease['tenant_id'] ?>" class="text-decoration-none"><?= e($activeLease['first_name'].' '.$activeLease['last_name']) ?></a></div>
              <div class="text-muted small"><?= e($activeLease['phone']) ?> · <?= e($activeLease['email'] ?? '—') ?></div>
            </div>
          </div>
          <div class="small text-muted">
            <?= e(__('Lease')) ?> <a href="<?= APP_URL ?>/modules/leases/view.php?id=<?= (int)$activeLease['id'] ?>"><?= e($activeLease['lease_number']) ?></a> ·
            <?= e($activeLease['start_date']) ?> → <?= e($activeLease['end_date']) ?>
          </div>
        <?php else: ?>
          <div class="text-muted small"><?= e(__('No active tenant. Unit is currently')) ?> <strong><?= e($u['status']) ?></strong>.</div>
        <?php endif; ?>
      </div></div>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-leases">
    <div class="srp-card p-3">
      <table class="table table-hover align-middle">
        <thead><tr><th><?= e(__('Lease #')) ?></th><th><?= e(__('Period')) ?></th><th><?= e(__('Rent')) ?></th><th><?= e(__('Status')) ?></th><th></th></tr></thead>
        <tbody>
          <?php if (!$leases): ?><tr><td colspan="5" class="text-muted text-center py-4"><?= e(__('No leases on record.')) ?></td></tr>
          <?php else: foreach ($leases as $l): ?>
            <tr>
              <td><a href="<?= APP_URL ?>/modules/leases/view.php?id=<?= (int)$l['id'] ?>"><?= e($l['lease_number']) ?></a></td>
              <td><?= e($l['start_date']) ?> → <?= e($l['end_date']) ?></td>
              <td><?= e(money($l['monthly_rent'])) ?></td>
              <td><span class="srp-badge-pill badge-status-<?= $l['status']==='active'?'available':'unavailable' ?>"><span class="dot"></span><?= e(ucfirst($l['status'])) ?></span></td>
              <td class="text-end"><a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/leases/view.php?id=<?= (int)$l['id'] ?>"><i data-lucide="arrow-right"></i></a></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-invoices">
    <div class="srp-card p-3">
      <table class="table table-hover align-middle">
        <thead><tr><th><?= e(__('Invoice')) ?></th><th><?= e(__('Type')) ?></th><th><?= e(__('Amount')) ?></th><th><?= e(__('Paid')) ?></th><th><?= e(__('Balance')) ?></th><th><?= e(__('Due')) ?></th><th><?= e(__('Status')) ?></th></tr></thead>
        <tbody>
          <?php if (!$invoices): ?><tr><td colspan="7" class="text-muted text-center py-4"><?= e(__('No invoices.')) ?></td></tr>
          <?php else: foreach ($invoices as $i): ?>
            <tr>
              <td><a href="<?= APP_URL ?>/modules/invoices/view.php?id=<?= (int)$i['id'] ?>"><?= e($i['invoice_number']) ?></a></td>
              <td><?= e(ucfirst(str_replace('_',' ',$i['invoice_type']))) ?></td>
              <td><?= e(money($i['amount'])) ?></td>
              <td><?= e(money($i['amount_paid'])) ?></td>
              <td><?= e(money($i['balance'])) ?></td>
              <td><?= e($i['due_date']) ?></td>
              <td><?= e(ucfirst(str_replace('_',' ',$i['status']))) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-payments">
    <div class="srp-card p-3">
      <table class="table table-hover align-middle">
        <thead><tr><th><?= e(__('Date')) ?></th><th><?= e(__('Amount')) ?></th><th><?= e(__('Method')) ?></th><th><?= e(__('Status')) ?></th><th></th></tr></thead>
        <tbody>
          <?php if (!$payments): ?><tr><td colspan="5" class="text-muted text-center py-4"><?= e(__('No payments.')) ?></td></tr>
          <?php else: foreach ($payments as $p): ?>
            <tr>
              <td><?= e($p['payment_date']) ?></td>
              <td><?= e(money($p['amount'])) ?></td>
              <td><?= e(ucfirst(str_replace('_',' ',$p['payment_method']))) ?></td>
              <td><?= e(ucfirst($p['status'])) ?></td>
              <td class="text-end"><a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/payments/view.php?id=<?= (int)$p['id'] ?>"><i data-lucide="arrow-right"></i></a></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-maintenance">
    <div class="srp-card p-3">
      <table class="table table-hover align-middle">
        <thead><tr><th><?= e(__('Ticket')) ?></th><th><?= e(__('Priority')) ?></th><th><?= e(__('Status')) ?></th><th><?= e(__('Description')) ?></th><th><?= e(__('Reported')) ?></th></tr></thead>
        <tbody>
          <?php if (!$maintenance): ?><tr><td colspan="5" class="text-muted text-center py-4"><?= e(__('No maintenance requests.')) ?></td></tr>
          <?php else: foreach ($maintenance as $m): ?>
            <tr>
              <td><a href="<?= APP_URL ?>/modules/maintenance/view.php?id=<?= (int)$m['id'] ?>"><?= e($m['ticket_number']) ?></a></td>
              <td><?= e(ucfirst($m['priority'])) ?></td>
              <td><?= e(ucfirst(str_replace('_',' ',$m['status']))) ?></td>
              <td class="text-truncate" style="max-width:360px"><?= e($m['description']) ?></td>
              <td><?= e(date('Y-m-d', strtotime($m['created_at']))) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="tab-pane fade" id="tab-inspections">
    <div class="srp-card p-3">
      <table class="table table-hover align-middle">
        <thead><tr><th><?= e(__('Type')) ?></th><th><?= e(__('Date')) ?></th><th><?= e(__('Status')) ?></th><th></th></tr></thead>
        <tbody>
          <?php if (!$inspections): ?><tr><td colspan="4" class="text-muted text-center py-4"><?= e(__('No inspections.')) ?></td></tr>
          <?php else: foreach ($inspections as $ins): ?>
            <tr>
              <td><?= e(ucfirst(str_replace('_',' ',$ins['inspection_type']))) ?></td>
              <td><?= e($ins['inspection_date']) ?></td>
              <td><?= e(ucfirst($ins['status'])) ?></td>
              <td class="text-end"><a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/inspections/view.php?id=<?= (int)$ins['id'] ?>"><i data-lucide="arrow-right"></i></a></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>