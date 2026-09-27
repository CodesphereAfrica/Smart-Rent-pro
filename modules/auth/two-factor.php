<?php
// modules/auth/two-factor.php — verifies TOTP after correct password, before session is bound.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Endroid\QrCode\QrCode;

if (session_status() !== PHP_SESSION_ACTIVE) { session_name('SRPSESSID'); session_start(); }

$pendingId = $_SESSION['pending_2fa_user'] ?? null;
if (!$pendingId) {
    header('Location: ' . APP_URL . '/modules/auth/login.php'); exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
    $user = db_one('SELECT id, two_factor_secret, first_name FROM users WHERE id = :id AND status="active" LIMIT 1',
                   [':id' => (int)$pendingId]);
    if ($user && $code !== '' && verify_totp((string)$user['two_factor_secret'], $code)) {
        // Bind full session
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['last_activity'] = time();
        unset($_SESSION['pending_2fa_user']);
        log_audit('auth', 'login_2fa', 'users', (int)$user['id']);
        header('Location: ' . APP_URL . '/modules/dashboard/index.php'); exit;
    }
    $error = __('Invalid or expired 2FA code.');
}

/** Minimal TOTP verifier (RFC 6238). */
function verify_totp(string $secretBase32, string $code, int $window = 1): bool
{
    $key = base32_decode($secretBase32);
    if ($key === false) return false;
    $timeSlice = (int)floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (totp_at($key, $timeSlice + $i) === str_pad($code, 6, '0', STR_PAD_LEFT)) return true;
    }
    return false;
}
function totp_at(string $key, int $timeSlice): string
{
    $data = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $data, $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $bin = substr($hash, $offset, 4);
    $val = unpack('N', $bin)[1] & 0x7fffffff;
    return str_pad((string)($val % 1000000), 6, '0', STR_PAD_LEFT);
}
function base32_decode(string $b32): string|false
{
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $bits = ''; $out = '';
    for ($i = 0; $i < strlen($b32); $i++) {
        $pos = strpos($map, $b32[$i]);
        if ($pos === false) return false;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $out .= chr(bindec(substr($bits, $i, 8)));
    }
    return $out;
}
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(__('Two-factor verification')) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/custom.css">
</head><body class="srp-auth">
  <div class="srp-auth-card">
    <h1 class="h5"><?= e(__('Enter your 6-digit code')) ?></h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3">
        <input name="code" class="form-control form-control-lg text-center" maxlength="6"
               pattern="\d{6}" autocomplete="one-time-code" autofocus required>
      </div>
      <button class="btn btn-primary w-100"><?= e(__('Verify')) ?></button>
    </form>
  </div>
</body></html>