<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

$rows = db_all(
    'SELECT * FROM notification_templates WHERE company_id = :c ORDER BY event_code, channel',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Notification Templates');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Notification Templates')) ?></h1>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/templates/create.php"><i data-lucide="plus"></i> <?= e(__('Add Template')) ?></a>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Event')) ?></th><th><?= e(__('Channel')) ?></th><th><?= e(__('Subject')) ?></th>
      <th><?= e(__('Active')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4"><?= e(__('No templates yet.')) ?></td></tr>
      <?php else: foreach ($rows as $t): ?>
        <tr>
          <td><code><?= e($t['event_code']) ?></code></td>
          <td><?= e(ucfirst($t['channel'])) ?></td>
          <td><?= e($t['subject'] ?? '—') ?></td>
          <td><?= $t['is_active'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/templates/edit.php?id=<?= (int)$t['id'] ?>"><i data-lucide="pencil"></i></a>
            <a class="btn btn-sm btn-light text-danger" href="<?= APP_URL ?>/modules/templates/delete.php?id=<?= (int)$t['id'] ?>"><i data-lucide="trash-2"></i></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>