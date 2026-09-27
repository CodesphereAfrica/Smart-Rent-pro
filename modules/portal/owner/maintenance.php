<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
$USER = require_role('Property Owner');
$ownerId = (int)(db_one('SELECT id FROM owners WHERE user_id = :u LIMIT 1', [':u'=>(int)$USER['id']])['id'] ?? 0);

$rows = db_all(
    'SELECT mr.id, mr.ticket_number, mr.priority, mr.status, mr.description, mr.created_at,
            p.name AS property_name, u.unit_number
       FROM maintenance_requests mr
       JOIN units u ON u.id = mr.unit_id
       JOIN properties p ON p.id = mr.property_id
      WHERE mr.company_id = :c AND mr.property_id IN (SELECT property_id FROM owner_properties WHERE owner_id = :o)
      ORDER BY mr.id DESC LIMIT 200',
    [':c'=>(int)$USER['company_id'], ':o'=>$ownerId]
);

$PAGE_TITLE = __('Maintenance on My Properties');
require __DIR__ . '/../../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Maintenance')) ?></h1>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr><th><?= e(__('Ticket')) ?></th><th><?= e(__('Property / Unit')) ?></th><th><?= e(__('Priority')) ?></th>
      <th><?= e(__('Status')) ?></th><th><?= e(__('Description')) ?></th></tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4"><?= e(__('None.')) ?></td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= e($r['ticket_number']) ?></td>
          <td><?= e($r['property_name']) ?> · <?= e($r['unit_number']) ?></td>
          <td><?= e(ucfirst($r['priority'])) ?></td>
          <td><?= e(ucfirst(str_replace('_',' ',$r['status']))) ?></td>
          <td class="text-truncate" style="max-width:320px"><?= e($r['description']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>