<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('users.manage');

$rows = db_all(
    'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id
      WHERE u.company_id = :c ORDER BY u.first_name, u.last_name',
    [':c' => (int)$USER['company_id']]
);

$PAGE_TITLE = __('Users');
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Users')) ?></h1>
  <a class="btn btn-primary" href="<?= APP_URL ?>/modules/users/create.php"><i data-lucide="plus"></i> <?= e(__('Add User')) ?></a>
</div>
<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Name')) ?></th><th><?= e(__('Username')) ?></th><th><?= e(__('Email')) ?></th>
      <th><?= e(__('Role')) ?></th><th><?= e(__('Status')) ?></th><th><?= e(__('Last Login')) ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $u): ?>
      <tr>
        <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
        <td><?= e($u['username']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['role_name']) ?></td>
        <td><?= e(ucfirst($u['status'])) ?></td>
        <td><?= e($u['last_login_at'] ?? '—') ?></td>
        <td class="text-end">
          <a class="btn btn-sm btn-light" href="<?= APP_URL ?>/modules/users/edit.php?id=<?= (int)$u['id'] ?>"><i data-lucide="pencil"></i></a>
          <a class="btn btn-sm btn-light text-danger" href="<?= APP_URL ?>/modules/users/delete.php?id=<?= (int)$u['id'] ?>"><i data-lucide="trash-2"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>