<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('tenants.view');

$rows = db_all(
    'SELECT l.*, u.unit_number, p.name AS property_name
       FROM leads l
       LEFT JOIN units u ON u.id = l.unit_id
       LEFT JOIN properties p ON p.id = u.property_id
      WHERE l.company_id = :c
      ORDER BY l.created_at DESC LIMIT 300',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Leads');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Public Inquiry Leads')) ?></h1>
</div>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('When')) ?></th><th><?= e(__('Name')) ?></th><th><?= e(__('Contact')) ?></th>
      <th><?= e(__('Unit')) ?></th><th><?= e(__('Status')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('No leads yet.')) ?></td></tr>
      <?php else: foreach ($rows as $l): ?>
        <tr>
          <td><?= e(date('Y-m-d H:i', strtotime($l['created_at']))) ?></td>
          <td><?= e($l['name']) ?></td>
          <td><?= e($l['phone'] ?? '') ?><?= $l['email'] ? ' · ' . e($l['email']) : '' ?></td>
          <td><?= e($l['unit_number'] ?? '—') ?></td>
          <td><?= e(ucfirst($l['status'])) ?></td>
          <td class="text-end">
            <?php if ($l['status'] !== 'converted' && can('tenants.create')): ?>
              <a class="btn btn-sm btn-primary" href="<?= APP_URL ?>/modules/applicants/create.php">
                <i data-lucide="user-plus"></i> <?= e(__('Convert to Applicant')) ?>
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>