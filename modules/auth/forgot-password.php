<?php
// modules/auth/forgot-password.php — self-contained: form + email token issue.


require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

if (session_status() !== PHP_SESSION_ACTIVE) { session_name('SRPSESSID'); session_start(); }

$sent = false; $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = sanitize_email($_POST['email'] ?? '');
    if (!$email) { $error = __('Enter a valid email address.'); }
    else {
        $user = db_one('SELECT id, first_name, email FROM users WHERE email = :e AND status = "active" LIMIT 1',
                       [':e' => $email]);
        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            db_update('users',
                ['password_reset_token' => $token, 'password_reset_expires' => $expires],
                'id = :id', [':id' => (int)$user['id']]);

            $link = APP_URL . '/modules/auth/reset-password.php?token=' . urlencode($token);
            $body = '<p>' . e(__('Hello')) . ' ' . e($user['first_name']) . ',</p>'
                  . '<p>' . e(__('Use this link to reset your password (valid 1 hour):')) . '</p>'
                  . '<p><a href="' . e($link) . '">' . e($link) . '</a></p>';

            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = SMTP_USER !== '';
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->Port       = SMTP_PORT;
                $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = APP_NAME . ' — ' . __('Password reset');
                $mail->Body    = $body;
                $mail->send();
            } catch (MailException $e) {
                error_log('[forgot-password] ' . $e->getMessage());
            }
        }
        $sent = true;
    }
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(__('Forgot password')) ?> · <?= e(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">
</head><body class="srp-auth">
  <div class="srp-auth-card">
    <h1 class="h5"><?= e(__('Reset your password')) ?></h1>
    <?php if ($sent): ?>
      <div class="alert alert-info"><?= e(__('If that email exists, a reset link has been sent.')) ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label"><?= e(__('Email address')) ?></label>
        <input type="email" name="email" class="form-control" required>
      </div>
      <button class="btn btn-primary w-100"><?= e(__('Send reset link')) ?></button>
    </form>
    <div class="text-center mt-3"><a href="<?= APP_URL ?>/modules/auth/login.php"><?= e(__('Back to sign in')) ?></a></div>
  </div>
</body></html>