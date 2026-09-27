<?php
// modules/users/delete.php — deactivation only (users are FK-referenced by audit_logs, payments, journal entries).
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('users.manage');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$u = db_one('SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id
              WHERE u.id = :id AND u.company_id = :c LIMIT 1',
            [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$u) { flash_error(__('User not found.')); redirect('modules/users/index.php'); }

if ((int)$u['id'] === (int)$USER['id']) {
    flash_error(__('You cannot deactivate your own account.'));
    redirect('modules/users/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ($u['role_name'] === 'Super Admin') {
        $others = (int)(db_one(
            'SELECT COUNT(*) AS c FROM users u JOIN roles r ON r.id = u.role_id
              WHERE u.company_id = :c AND r.name = "Super Admin" AND u.status = "active" AND u.id <> :id',
            [':c'=>(int)$USER['company_id'], ':id'=>$id]
        )['c'] ?? 0);
        if ($others === 0) {
            flash_error(__('Cannot deactivate the only active Super Admin.'));
            redirect('modules/users/index.php');
        }
    }

    db_update('users', ['status' => 'inactive'], 'id = :id', [':id' => $id]);
    log_audit('users', 'deactivate', 'users', $id, $u, ['status' => 'inactive']);
    flash_success(__('User deactivated.'));
    redirect('modules/users/index.php');
}

$PAGE_TITLE = __('Deactivate User');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6"><div class="srp-card p-4">
  <h1 class="h5 mb-3"><?= e(__('Deactivate User')) ?></h1>
  <p><?= e(__('Deactivate')) ?> <strong><?= e($u['first_name'].' '.$u['last_name']) ?></strong>?</p>
  <p class="text-muted small"><?= e(__('Users are never deleted — they are referenced by the audit trail and financial records. Deactivating blocks login and portal access.')) ?></p>
  <form method="post" class="d-flex justify-content-end gap-2">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/users/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-danger"><i data-lucide="user-x"></i> <?= e(__('Deactivate')) ?></button>
  </form>
</div></div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>