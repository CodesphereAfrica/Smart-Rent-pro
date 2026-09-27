<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
$USER = require_role('Tenant');
$tenant = db_one('SELECT id FROM tenants WHERE user_id = :u LIMIT 1', [':u'=>(int)$USER['id']]);
if (!$tenant) { redirect('modules/dashboard/index.php'); }
$tid = (int)$tenant['id'];

$rows = db_all(
    'SELECT mr.id, mr.ticket_number, mr.priority, mr.status, mr.description, mr.created_at,
            u.unit_number, p.name AS property_name
       FROM maintenance_requests mr
       JOIN units u ON u.id = mr.unit_id
       JOIN properties p ON p.id = mr.property_id
      WHERE mr.tenant_id = :t ORDER BY mr.id DESC LIMIT 200',
    [':t' => $tid]
);

$PAGE_TITLE = __('My Maintenance');
require __DIR__ . '/../../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('My Maintenance Requests')) ?></h1>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/maintenance/create.php"><i data-lucide="plus"></i> <?= e(__('Submit Request')) ?></a>
</div>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr><th><?= e(__('Ticket')) ?></th><th><?= e(__('Priority')) ?></th><th><?= e(__('Status')) ?></th>
      <th><?= e(__('Description')) ?></th><th><?= e(__('Created')) ?></th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('No requests yet.')) ?></td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['ticket_number']) ?></td>
        <td><?= e(ucfirst($r['priority'])) ?></td>
        <td><?= e(ucfirst(str_replace('_',' ',$r['status']))) ?></td>
        <td class="text-truncate" style="max-width:320px"><?= e($r['description']) ?></td>
        <td><?= e(date('Y-m-d', strtotime($r['created_at']))) ?></td>
        <td class="text-end"><a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/maintenance/view.php?id=<?= (int)$r['id'] ?>"><i data-lucide="eye"></i></a></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>