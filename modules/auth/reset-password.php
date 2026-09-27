<?php
// modules/auth/reset-password.php — self-contained: validates token + sets new password.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) { session_name('SRPSESSID'); session_start(); }

$token = sanitize_string($_GET['token'] ?? $_POST['token'] ?? '', 100);
$error = null; $ok = false;

$user = $token !== '' ? db_one(
    'SELECT id FROM users WHERE password_reset_token = :t AND password_reset_expires >= NOW() LIMIT 1',
    [':t' => $token]
) : null;

if (!$user) {
    $error = __('Invalid or expired reset link.');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $p1 = (string)$_POST['password'] ?? '';
    $p2 = (string)$_POST['password_confirmation'] ?? '';
    if (strlen($p1) < 8) {
        $error = __('Password must be at least 8 characters.');
    } elseif ($p1 !== $p2) {
        $error = __('Passwords do not match.');
    } else {
        db_update('users', [
            'password_hash' => password_hash($p1, PASSWORD_BCRYPT),
            'password_reset_token' => null,
            'password_reset_expires' => null,
            'must_change_password' => 0,
        ], 'id = :id', [':id' => (int)$user['id']]);
        log_audit('auth', 'password_reset', 'users', (int)$user['id']);
        $ok = true;
    }
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(__('Reset password')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">
</head><body class="srp-auth">
  <div class="srp-auth-card">
    <h1 class="h5"><?= e(__('Choose a new password')) ?></h1>
    <?php if ($ok): ?>
      <div class="alert alert-success"><?= e(__('Password updated. You can now sign in.')) ?></div>
      <a class="btn btn-primary w-100" href="<?= APP_URL ?>/modules/auth/login.php"><?= e(__('Sign in')) ?></a>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="mb-3"><label class="form-label"><?= e(__('New password')) ?></label>
          <input type="password" name="password" class="form-control" required minlength="8"></div>
        <div class="mb-3"><label class="form-label"><?= e(__('Confirm password')) ?></label>
          <input type="password" name="password_confirmation" class="form-control" required minlength="8"></div>
        <button class="btn btn-primary w-100"><?= e(__('Save new password')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</body></html>