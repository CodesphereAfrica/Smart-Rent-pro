<?php
// includes/functions.php — pure helpers. No business logic.
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/* ---------- Output escaping ---------- */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function sanitize_string($v, int $max = 500): string
{
    $v = trim((string)$v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    return mb_substr($v, 0, $max);
}

function human_time_diff(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)        return $diff . 's';
    if ($diff < 3600)      return floor($diff / 60) . 'm';
    if ($diff < 86400)     return floor($diff / 3600) . 'h';
    if ($diff < 604800)    return floor($diff / 86400) . 'd';
    return date('Y-m-d', strtotime($datetime));
}
function sanitize_email($v): ?string
{
    $v = trim((string)$v);
    return filter_var($v, FILTER_VALIDATE_EMAIL) ?: null;
}

function sanitize_phone($v): ?string
{
    $v = preg_replace('/[^\d\+\-\s\(\)]/', '', (string)$v);
    return $v !== '' ? mb_substr($v, 0, 30) : null;
}

function money($amount, ?string $currency = null): string
{
    $currency = $currency ?? (current_user()['base_currency_code'] ?? DEFAULT_CURRENCY);
    return number_format((float)$amount, 2) . ' ' . $currency;
}

function num($n, int $dec = 2): string { return number_format((float)$n, $dec); }

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool
{
    return !empty($_SESSION['csrf_token'])
        && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    $ok = csrf_verify($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!$ok) {
        http_response_code(419);
        $_SESSION['flash_error'] = 'Security token expired. Please try again.';
        $back = $_SERVER['HTTP_REFERER'] ?? (APP_URL . '/modules/dashboard/index.php');
        header('Location: ' . $back);
        exit;
    }
}

/* ---------- Flash ---------- */
function flash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

function flash_success(string $m): void { flash('success', $m); }
function flash_error(string $m): void   { flash('error', $m); }
function flash_info(string $m): void    { flash('info', $m); }

function take_flash(): array
{
    $out = [];
    foreach (['success','error','info','warning'] as $t) {
        if (!empty($_SESSION['flash_' . $t])) {
            $out[$t] = $_SESSION['flash_' . $t];
            unset($_SESSION['flash_' . $t]);
        }
    }
    return $out;
}

function redirect(string $path): void
{
    header('Location: ' . (strpos($path, 'http') === 0 ? $path : APP_URL . '/' . ltrim($path, '/')));
    exit;
}

/* ---------- Audit log ---------- */
function log_audit(string $module, string $action, ?string $recordType = null, $recordId = null,
                   ?array $oldValues = null, ?array $newValues = null): void
{
    $u = function_exists('current_user') ? current_user() : null;
    if (!$u) return;
    try {
        db_insert('audit_logs', [
            'company_id'  => (int)$u['company_id'],
            'user_id'     => (int)$u['id'],
            'module'      => $module,
            'action'      => $action,
            'record_type' => $recordType,
            'record_id'   => $recordId !== null ? (int)$recordId : null,
            'old_values'  => $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
            'new_values'  => $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('[audit] ' . $e->getMessage());
    }
}

/* ---------- Translation ---------- */
function __(string $key, array $replace = []): string
{
    static $lang = null;
    static $code = null;

    $desired = $_SESSION['lang']
        ?? (function_exists('current_user') ? (current_user()['preferred_language'] ?? null) : null)
        ?? DEFAULT_LANGUAGE;

    if ($code !== $desired) {
        $code = $desired;
        $file = SRP_ROOT . '/lang/' . preg_replace('/[^a-z_]/i', '', $code) . '.php';
        $lang = is_file($file) ? (require $file) : [];
    }
    $text = $lang[$key] ?? $key;
    foreach ($replace as $k => $v) {
        $text = str_replace(':' . $k, (string)$v, $text);
    }
    return $text;
}

/* ---------- Misc ---------- */
function uuidv4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function generate_number(string $prefix, int $width = 6): string
{
    return $prefix . '-' . date('Ymd') . '-' . str_pad((string)random_int(1, 999999), $width, '0', STR_PAD_LEFT);
}

function safe_slug(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/** Best-effort storage of uploaded file into UPLOAD_DIR subfolder; returns relative path. */
function store_upload(array $file, string $subdir, array $allowedMimes = []): ?string
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
    $mime = mime_content_type($file['tmp_name']) ?: 'application/octet-stream';
    if ($allowedMimes && !in_array($mime, $allowedMimes, true)) return null;

    $ext   = pathinfo($file['name'], PATHINFO_EXTENSION);
    $ext   = preg_replace('/[^a-z0-9]/i', '', (string)$ext) ?: 'bin';
    $dir   = rtrim(UPLOAD_DIR, '/') . '/' . trim($subdir, '/');
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name  = uuidv4() . '.' . strtolower($ext);
    $dest  = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
    return trim($subdir, '/') . '/' . $name;
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}