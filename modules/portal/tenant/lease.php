<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
$USER = require_role('Tenant');
$tenant = db_one('SELECT id FROM tenants WHERE user_id = :u LIMIT 1', [':u'=>(int)$USER['id']]);
if (!$tenant) { redirect('modules/dashboard/index.php'); }
$tid = (int)$tenant['id'];

$lease = db_one(
    'SELECT l.*, u.unit_number, p.name AS property_name FROM leases l
       JOIN units u ON u.id = l.unit_id
       JOIN properties p ON p.id = l.property_id
      WHERE l.tenant_id = :t AND l.status IN ("active","expiring")
      ORDER BY l.id DESC LIMIT 1',
    [':t' => $tid]
);

$PAGE_TITLE = __('My Lease');
require __DIR__ . '/../../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('My Lease')) ?></h1>
<?php if (!$lease): ?>
  <div class="alert alert-info"><?= e(__('No active lease on file.')) ?></div>
<?php else: ?>
  <div class="srp-card p-4">
    <div class="row small g-2">
      <div class="col-md-6"><span class="text-muted"><?= e(__('Lease Number')) ?>:</span> <?= e($lease['lease_number']) ?></div>
      <div class="col-md-6"><span class="text-muted"><?= e(__('Unit')) ?>:</span> <?= e($lease['unit_number']) ?> · <?= e($lease['property_name']) ?></div>
      <div class="col-md-6"><span class="text-muted"><?= e(__('Start')) ?>:</span> <?= e($lease['start_date']) ?></div>
      <div class="col-md-6"><span class="text-muted"><?= e(__('End')) ?>:</span> <?= e($lease['end_date']) ?></div>
      <div class="col-md-6"><span class="text-muted"><?= e(__('Monthly Rent')) ?>:</span> <?= e(money($lease['monthly_rent'])) ?></div>
      <div class="col-md-6"><span class="text-muted"><?= e(__('Deposit')) ?>:</span> <?= e(money($lease['security_deposit'])) ?></div>
      <div class="col-md-6"><span class="text-muted"><?= e(__('Grace Period')) ?>:</span> <?= (int)$lease['grace_period_days'] ?> <?= e(__('days')) ?></div>
      <div class="col-md-6"><span class="text-muted"><?= e(__('Notice Period')) ?>:</span> <?= (int)$lease['notice_period_days'] ?> <?= e(__('days')) ?></div>
    </div>
    <?php if ($lease['special_clauses']): ?>
      <hr><h2 class="h6"><?= e(__('Special Clauses')) ?></h2>
      <div><?= nl2br(e($lease['special_clauses'])) ?></div>
    <?php endif; ?>
    <a class="btn btn-light mt-3" href="<?= APP_URL ?>/modules/documents/index.php"><i data-lucide="file-text"></i> <?= e(__('Related documents')) ?></a>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>