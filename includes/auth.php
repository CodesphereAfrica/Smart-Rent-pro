<?php
// includes/auth.php — session bootstrap + permission gate. Infrastructure only.
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {

    $sessDir = SRP_ROOT . '/storage/sessions';
    if (!is_dir($sessDir)) { @mkdir($sessDir, 0775, true); }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        session_save_path($sessDir);
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (APP_URL !== '' && strpos(APP_URL, 'https://') === 0),
    ]);
    session_name('SRPSESSID');
    session_start();
}

/** Bootstrap current user object into memory (once per request). */
function current_user(): ?array
{
    static $cache = null;
    static $loaded = false;
    if ($loaded) return $cache;
    $loaded = true;

    if (empty($_SESSION['user_id'])) {
        return $cache = null;
    }

    // Session timeout enforcement
    $now = time();
    if (!empty($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset(); session_destroy();
        return $cache = null;
    }
    $_SESSION['last_activity'] = $now;

    $row = db_one(
        'SELECT u.*, r.name AS role_name, c.name AS company_name, c.base_currency_code, c.default_language
           FROM users u
           JOIN roles r ON r.id = u.role_id
           JOIN companies c ON c.id = u.company_id
          WHERE u.id = :id AND u.status = "active" LIMIT 1',
        [':id' => (int)$_SESSION['user_id']]
    );
    return $cache = $row ?: null;
}

function is_logged_in(): bool { return current_user() !== null; }

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['flash_intended'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: ' . APP_URL . '/modules/auth/login.php');
        exit;
    }
    return $u;
}

/** Super Admin = platform owner (company_id NULL role template, but user still has a company row). */
function is_super_admin(?array $u = null): bool
{
    $u = $u ?? current_user();
    return $u !== null && $u['role_name'] === 'Super Admin';
}

/**
 * Permission check: can('tenants.create').
 * Super Admin bypasses. Others consult role_permissions.
 * Result cached per-request for speed.
 */
function can(string $code, ?array $u = null): bool
{
    static $cache = [];
    $u = $u ?? current_user();
    if (!$u) return false;
    if (is_super_admin($u)) return true;

    $key = (int)$u['role_id'] . '|' . $code;
    if (array_key_exists($key, $cache)) return $cache[$key];

    $row = db_one(
        'SELECT 1 FROM role_permissions rp
           JOIN permissions p ON p.id = rp.permission_id
          WHERE rp.role_id = :rid AND p.code = :code LIMIT 1',
        [':rid' => (int)$u['role_id'], ':code' => $code]
    );
    return $cache[$key] = (bool)$row;
}

/** Hard gate — call this at the very top of every module action file. */
function require_permission(string $code): array
{
    $u = require_login();
    if (!can($code, $u)) {
        http_response_code(403);
        $_SESSION['flash_error'] = __('You do not have permission to perform this action.');
        header('Location: ' . APP_URL . '/modules/dashboard/index.php');
        exit;
    }
    return $u;
}

/** Role-name gate (used for portal routing). */
function has_role(string $roleName, ?array $u = null): bool
{
    $u = $u ?? current_user();
    return $u !== null && $u['role_name'] === $roleName;
}

function require_role(string $roleName): array
{
    $u = require_login();
    if (!has_role($roleName, $u)) {
        http_response_code(403);
        header('Location: ' . APP_URL . '/modules/dashboard/index.php');
        exit;
    }
    return $u;
}

/** Login-attempt throttling table: login_attempts (see migration for extra housekeeping table). */
function record_login_attempt(string $identifier, bool $success): void
{
    db_insert('login_attempts', [
        'identifier'  => $identifier,
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent'  => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'success'     => $success ? 1 : 0,
        'attempted_at'=> date('Y-m-d H:i:s'),
    ]);
}

function is_rate_limited(string $identifier): bool
{
    $since = date('Y-m-d H:i:s', time() - (LOGIN_WINDOW_MIN * 60));
    $row = db_one(
        'SELECT COUNT(*) AS c FROM login_attempts
          WHERE identifier = :i AND success = 0 AND attempted_at >= :since',
        [':i' => $identifier, ':since' => $since]
    );
    return ((int)($row['c'] ?? 0)) >= LOGIN_MAX_ATTEMPTS;
}