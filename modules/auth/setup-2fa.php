<?php
// modules/auth/setup-2fa.php — generates a TOTP secret + QR and persists it once confirmed.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$USER = require_login();

// 1) Generate secret if not yet pending in session.
if (empty($_SESSION['pending_2fa_secret'])) {
    $_SESSION['pending_2fa_secret'] = generate_base32_secret(32);
}
$secret = $_SESSION['pending_2fa_secret'];

// 2) Confirm with a valid code to activate.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
    if (strlen($code) === 6 && verify_totp_local($secret, $code)) {
        db_update('users', ['two_factor_enabled' => 1, 'two_factor_secret' => $secret],
                  'id = :id', [':id' => (int)$USER['id']]);
        log_audit('auth', 'enable_2fa', 'users', (int)$USER['id']);
        unset($_SESSION['pending_2fa_secret']);
        flash_success(__('Two-factor authentication enabled.'));
        redirect('modules/profile/index.php');
    }
    $error = __('Invalid code. Try again.');
}

// 3) Build QR (otpauth URL).
$label = rawurlencode(APP_NAME . ':' . $USER['email']);
$issuer = rawurlencode(APP_NAME);
$otpauth = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
$qr = (new PngWriter())->write(new QrCode($otpauth))->getDataUri();

// Helpers (shared with two-factor.php — kept local for self-containment).
function generate_base32_secret(int $len = 32): string {
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $map[random_int(0, 31)];
    return $s;
}
function verify_totp_local(string $secretBase32, string $code, int $window = 1): bool {
    $key = base32_decode_local($secretBase32); if ($key === false) return false;
    $t = (int)floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (totp_at_local($key, $t + $i) === str_pad($code, 6, '0', STR_PAD_LEFT)) return true;
    }
    return false;
}
function totp_at_local(string $key, int $t): string {
    $data = pack('N*', 0) . pack('N*', $t);
    $hash = hash_hmac('sha1', $data, $key, true);
    $offset = ord($hash[19]) & 0x0f;
    $val = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
    return str_pad((string)($val % 1000000), 6, '0', STR_PAD_LEFT);
}
function base32_decode_local(string $b32): string|false {
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $bits = ''; $out = '';
    for ($i = 0; $i < strlen($b32); $i++) {
        $pos = strpos($map, $b32[$i]); if ($pos === false) return false;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) $out .= chr(bindec(substr($bits, $i, 8)));
    return $out;
}

$PAGE_TITLE = __('Set up two-factor authentication');
require __DIR__ . '/../../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-6">
  <div class="srp-card p-4">
    <h1 class="h5 mb-3"><?= e($PAGE_TITLE) ?></h1>
    <p class="text-muted"><?= e(__('Scan this QR with Google Authenticator, Authy, or 1Password, then confirm the 6-digit code.')) ?></p>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <div class="text-center my-3"><img src="<?= e($qr) ?>" alt="2FA QR" width="220" height="220"></div>
    <div class="mb-3 text-center small text-muted"><?= e(__('Manual code:')) ?> <code><?= e(chunk_split($secret, 4, ' ')) ?></code></div>
    <form method="post" class="row g-2">
      <?= csrf_field() ?>
      <div class="col-8"><input name="code" class="form-control form-control-lg text-center" maxlength="6" pattern="\d{6}" required></div>
      <div class="col-4 d-grid"><button class="btn btn-primary"><?= e(__('Enable')) ?></button></div>
    </form>
  </div>
</div></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>