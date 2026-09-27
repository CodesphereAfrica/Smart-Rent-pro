<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('communications.view');

$rows = db_all(
    'SELECT a.*, p.name AS property_name, CONCAT(u.first_name," ",u.last_name) AS creator
       FROM announcements a
       LEFT JOIN properties p ON p.id = a.property_id
       LEFT JOIN users u ON u.id = a.created_by
      WHERE a.company_id = :c
      ORDER BY a.id DESC LIMIT 200',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Announcements');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Announcements')) ?></h1>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/announcements/create.php"><i data-lucide="plus"></i> <?= e(__('New Announcement')) ?></a>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Title')) ?></th><th><?= e(__('Audience')) ?></th>
      <th><?= e(__('Property')) ?></th><th><?= e(__('Published')) ?></th><th><?= e(__('Expires')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4"><?= e(__('No announcements yet.')) ?></td></tr>
      <?php else: foreach ($rows as $a): ?>
        <tr>
          <td><span class="fw-semibold"><?= e($a['title']) ?></span>
            <div class="text-muted small"><?= e(mb_substr(strip_tags($a['body']), 0, 80)) ?></div></td>
          <td><?= e(ucfirst($a['audience'])) ?></td>
          <td><?= e($a['property_name'] ?? '—') ?></td>
          <td><?= e($a['published_at'] ? date('Y-m-d', strtotime($a['published_at'])) : '—') ?></td>
          <td><?= e($a['expires_at'] ? date('Y-m-d', strtotime($a['expires_at'])) : '—') ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/announcements/edit.php?id=<?= (int)$a['id'] ?>"><i data-lucide="pencil"></i></a>
            <a class="btn btn-sm btn-light text-danger" href="<?= APP_URL ?>/modules/announcements/delete.php?id=<?= (int)$a['id'] ?>"><i data-lucide="trash-2"></i></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>