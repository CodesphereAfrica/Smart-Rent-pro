<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('users.manage');

$roles = db_all(
    'SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS users_count
       FROM roles r
      WHERE r.company_id IS NULL OR r.company_id = :c
      ORDER BY r.is_system_role DESC, r.name',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Roles & Permissions');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="h4 mb-0"><?= e(__('Roles & Permissions')) ?></h1>
    <p class="text-muted small mb-0"><?= e(__('System role templates are immutable; add custom roles per company and toggle any permission.')) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/roles/create.php"><i data-lucide="plus"></i> <?= e(__('New Role')) ?></a>
</div>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Role')) ?></th><th><?= e(__('Description')) ?></th>
      <th><?= e(__('System?')) ?></th><th><?= e(__('Users')) ?></th><th><?= e(__('Status')) ?></th><th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($roles as $r): ?>
        <tr>
          <td><span class="fw-semibold"><?= e($r['name']) ?></span></td>
          <td><?= e($r['description'] ?? '—') ?></td>
          <td><?= $r['is_system_role'] ? '<span class="badge bg-secondary">' . e(__('Template')) . '</span>' : '<span class="badge bg-primary">' . e(__('Custom')) . '</span>' ?></td>
          <td><?= (int)$r['users_count'] ?></td>
          <td><?= $r['is_active'] ? '<span class="badge bg-success">' . e(__('Active')) . '</span>' : '<span class="badge bg-secondary">' . e(__('Inactive')) . '</span>' ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/roles/edit.php?id=<?= (int)$r['id'] ?>"><i data-lucide="shield-check"></i> <?= e(__('Permissions')) ?></a>
            <?php if (!$r['is_system_role'] && (int)$r['company_id'] === (int)$USER['company_id']): ?>
              <a class="btn btn-sm btn-light text-danger" href="<?= APP_URL ?>/modules/roles/delete.php?id=<?= (int)$r['id'] ?>"><i data-lucide="trash-2"></i></a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>