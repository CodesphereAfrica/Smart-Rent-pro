<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('owners.view');
$id = (int)($_GET['id'] ?? 0);

$o = db_one('SELECT * FROM owners WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$o) { flash_error(__('Owner not found.')); redirect('modules/owners/index.php'); }

// Owner portal hard isolation: an owner-user can only view their own record
if (has_role('Property Owner', $USER) && (int)$o['user_id'] !== (int)$USER['id']) {
    http_response_code(403); flash_error(__('Access denied.')); redirect('modules/dashboard/index.php');
}

$properties = db_all(
    'SELECT p.id, p.name, p.property_type, p.address, p.city, p.status, op.ownership_percentage
       FROM owner_properties op JOIN properties p ON p.id = op.property_id
      WHERE op.owner_id = :o AND p.deleted_at IS NULL
      ORDER BY p.name',
    [':o' => $id]
);
$statements = db_all(
    'SELECT id, property_id, period_start, period_end, net_owner_income, file_path, generated_at
       FROM owner_statements WHERE owner_id = :o
      ORDER BY generated_at DESC LIMIT 20',
    [':o' => $id]
);

$PAGE_TITLE = $o['full_name'];
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/owners/index.php"><?= e(__('Owners')) ?></a></li>
  <li class="breadcrumb-item active"><?= e($o['full_name']) ?></li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e($o['full_name']) ?>
      <span class="srp-badge-pill <?= $o['status']==='active'?'badge-status-available':'badge-status-unavailable' ?> ms-2">
        <span class="dot"></span><?= e(ucfirst($o['status'])) ?>
      </span>
    </h1>
    <div class="text-muted small">
      <?= e(ucfirst($o['owner_type'])) ?>
      <?php if ($o['phone']): ?> · <?= e($o['phone']) ?><?php endif; ?>
      <?php if ($o['email']): ?> · <?= e($o['email']) ?><?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <?php if (can('owners.edit')): ?>
      <a class="btn btn-light" href="<?= APP_URL ?>/modules/owners/edit.php?id=<?= (int)$id ?>"><i data-lucide="pencil"></i> <?= e(__('Edit')) ?></a>
      <a class="btn btn-primary" href="<?= APP_URL ?>/modules/owner-statements/generate.php?owner_id=<?= (int)$id ?>"><i data-lucide="scroll-text"></i> <?= e(__('Generate Statement')) ?></a>
    <?php endif; ?>
  </div>
</div>

<div class="kpi-grid mb-3">
  <div class="kpi"><div class="kpi-icon"><i data-lucide="building"></i></div>
    <div class="kpi-label"><?= e(__('Properties')) ?></div>
    <div class="kpi-value"><?= count($properties) ?></div></div>
  <div class="kpi"><div class="kpi-icon"><i data-lucide="scroll-text"></i></div>
    <div class="kpi-label"><?= e(__('Statements Issued')) ?></div>
    <div class="kpi-value"><?= count($statements) ?></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="srp-card p-3">
      <h2 class="h6 mb-3"><?= e(__('Portfolio')) ?></h2>
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th><?= e(__('Property')) ?></th><th><?= e(__('Type')) ?></th><th><?= e(__('Location')) ?></th><th><?= e(__('Own %')) ?></th><th><?= e(__('Status')) ?></th></tr></thead>
        <tbody>
          <?php if (!$properties): ?><tr><td colspan="5" class="text-muted text-center py-4"><?= e(__('No properties linked yet.')) ?></td></tr>
          <?php else: foreach ($properties as $p): ?>
            <tr>
              <td><a href="<?= APP_URL ?>/modules/properties/view.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></td>
              <td><?= e(ucfirst(str_replace('_',' ',$p['property_type']))) ?></td>
              <td><?= e(trim(($p['city'] ?? '') . ', ' . ($p['address'] ?? ''), ', ')) ?></td>
              <td><?= number_format((float)$p['ownership_percentage'], 1) ?>%</td>
              <td><?= e(ucfirst($p['status'])) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="srp-card p-3 h-100">
      <h2 class="h6 mb-3"><?= e(__('Recent Owner Statements')) ?></h2>
      <?php if (!$statements): ?>
        <div class="text-muted small"><?= e(__('No statements generated yet.')) ?></div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($statements as $s): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold small"><?= e($s['period_start']) ?> → <?= e($s['period_end']) ?></div>
                <div class="text-muted small"><?= e(money($s['net_owner_income'])) ?></div>
              </div>
              <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/owner-statements/view.php?id=<?= (int)$s['id'] ?>"><i data-lucide="eye"></i></a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>