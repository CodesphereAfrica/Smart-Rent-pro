<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('SRPSESSID');
    session_start();
}

$error = null;
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $identifier = sanitize_string($_POST['identifier'] ?? '', 150);
    $password   = (string)($_POST['password'] ?? '');
    $remember   = !empty($_POST['remember']);

    if (is_rate_limited($identifier)) {
        $error = __('Too many failed attempts. Please try again later.');
    } elseif ($identifier === '' || $password === '') {
        $error = __('Enter your username/email and password.');
    } else {
        // Find user (username OR email, per-company scoping handled by login-form context).
        $user = db_one(
            'SELECT u.*, r.name AS role_name, c.name AS company_name,
                    c.base_currency_code, c.default_language
               FROM users u
               JOIN roles r ON r.id = u.role_id
               JOIN companies c ON c.id = u.company_id
              WHERE (u.username = :i OR u.email = :i)
                AND u.status = "active"
              ORDER BY u.id ASC LIMIT 1',
            [':i' => $identifier]
        );

        if ($user && password_verify($password, $user['password_hash'])) {
            record_login_attempt($identifier, true);

            // If 2FA enabled, park the pending user and go verify.
            if ((int)$user['two_factor_enabled'] === 1 && !empty($user['two_factor_secret'])) {
                $_SESSION['pending_2fa_user'] = (int)$user['id'];
                header('Location: ' . APP_URL . '/modules/auth/two-factor.php');
                exit;
            }

            // Success — bind session.
            session_regenerate_id(true);
            $_SESSION['user_id']    = (int)$user['id'];
            $_SESSION['role_name']  = $user['role_name'];
            $_SESSION['company_id'] = (int)$user['company_id'];
            $_SESSION['last_activity'] = time();

            db_update('users',
                ['last_login_at' => date('Y-m-d H:i:s'),
                 'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null],
                'id = :id', [':id' => (int)$user['id']]);

            log_audit('auth', 'login', 'users', (int)$user['id']);

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                db_update('users', ['remember_token' => $token], 'id = :id', [':id' => (int)$user['id']]);
                setcookie('srp_remember', $token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
            }

            $intended = $_SESSION['flash_intended'] ?? null;
            unset($_SESSION['flash_intended']);
            header('Location: ' . ($intended ?: APP_URL . '/modules/dashboard/index.php'));
            exit;
        }

        record_login_attempt($identifier, false);
        $error = __('Invalid credentials.');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(__('Sign in')) ?> · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">
<script src="https://unpkg.com/lucide@0.383.0/dist/umd/lucide.js"></script>
</head>
<body class="srp-auth">
  <div class="srp-auth-card">
    <div class="text-center mb-4">
      <div class="srp-auth-logo"><i data-lucide="building-2"></i></div>
      <h1 class="h4 mt-3 mb-0"><?= e(APP_NAME) ?></h1>
      <p class="text-muted small"><?= e(__('Property Management, Reimagined.')) ?></p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label"><?= e(__('Username or Email')) ?></label>
        <input type="text" name="identifier" class="form-control" required autofocus
               value="<?= e($identifier) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label"><?= e(__('Password')) ?></label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="remember" id="remember">
          <label class="form-check-label" for="remember"><?= e(__('Remember me')) ?></label>
        </div>
        <a href="<?= APP_URL ?>/modules/auth/forgot-password.php" class="small"><?= e(__('Forgot password?')) ?></a>
      </div>
      <button class="btn btn-primary w-100" type="submit"><?= e(__('Sign in')) ?></button>
    </form>
  </div>
<script>lucide.createIcons();</script>
</body>
</html>