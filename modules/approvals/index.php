<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_login();

// Pending approvals for this user's role
$rows = db_all(
    'SELECT ar.*, CONCAT(u.first_name," ",u.last_name) AS requester
       FROM approval_requests ar
       LEFT JOIN users u ON u.id = ar.requested_by
      WHERE ar.company_id = :c AND ar.status = "pending"
        AND EXISTS (
            SELECT 1 FROM approval_workflows aw
             WHERE aw.company_id = ar.company_id
               AND aw.workflow_type = ar.workflow_type
               AND aw.required_role_id = :rid
               AND aw.is_active = 1
               AND (ar.amount IS NULL OR (aw.min_amount <= ar.amount AND (aw.max_amount IS NULL OR aw.max_amount >= ar.amount)))
        )
      ORDER BY ar.created_at DESC',
    [':c'=>(int)$USER['company_id'], ':rid'=>(int)$USER['role_id']]
);

$PAGE_TITLE = __('Pending Approvals');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Pending Approvals')) ?></h1>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Requested')) ?></th><th><?= e(__('Type')) ?></th><th><?= e(__('Item')) ?></th>
      <th><?= e(__('Amount')) ?></th><th><?= e(__('Requested By')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('You have no pending approvals.')) ?></td></tr>
      <?php else: foreach ($rows as $a): ?>
        <tr>
          <td><?= e(date('Y-m-d H:i', strtotime($a['created_at']))) ?></td>
          <td><?= e(str_replace('_',' ',$a['workflow_type'])) ?></td>
          <td><?= e($a['related_type']) ?> #<?= (int)$a['related_id'] ?></td>
          <td><?= $a['amount'] ? e(money($a['amount'])) : '—' ?></td>
          <td><?= e($a['requester'] ?? '—') ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-primary" href="<?= APP_URL ?>/modules/approvals/view.php?id=<?= (int)$a['id'] ?>"><i data-lucide="eye"></i> <?= e(__('Review')) ?></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<div class="d-flex justify-content-end mt-3">
  <a class="btn btn-light" href="<?= APP_URL ?>/modules/approvals/workflows.php"><i data-lucide="settings-2"></i> <?= e(__('Workflow Settings')) ?></a>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>