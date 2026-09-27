<?php
// config/config.php — loads .env, defines app-wide constants. No business logic.
declare(strict_types=1);

if (!defined('SRP_ROOT')) {
    define('SRP_ROOT', dirname(__DIR__));
}

require_once SRP_ROOT . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(SRP_ROOT);
$dotenv->load();

// --- App ---
define('APP_NAME',        $_ENV['APP_NAME']        ?? 'Smart Rent Pro');
define('APP_URL',         rtrim($_ENV['APP_URL']   ?? 'http://localhost:8000', '/'));
define('APP_ENV',         $_ENV['APP_ENV']         ?? 'production');
define('APP_DEBUG',       filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN));
define('APP_TIMEZONE',    $_ENV['APP_TIMEZONE']    ?? 'Africa/Kigali');
define('SESSION_TIMEOUT', (int)($_ENV['SESSION_TIMEOUT'] ?? 1800));  // seconds
define('LOGIN_MAX_ATTEMPTS', (int)($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5));
define('LOGIN_WINDOW_MIN',   (int)($_ENV['LOGIN_WINDOW_MIN']   ?? 15));

// --- DB ---
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', (int)($_ENV['DB_PORT'] ?? 3306));
define('DB_NAME', $_ENV['DB_NAME'] ?? 'smart_rent_pro');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// --- SMTP ---
define('SMTP_HOST',   $_ENV['SMTP_HOST']   ?? '');
define('SMTP_PORT',   (int)($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_USER',   $_ENV['SMTP_USER']   ?? '');
define('SMTP_PASS',   $_ENV['SMTP_PASS']   ?? '');
define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? 'tls');
define('SMTP_FROM',   $_ENV['SMTP_FROM']   ?? 'no-reply@example.com');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? APP_NAME);

// --- SMS ---
define('SMS_DRIVER', $_ENV['SMS_DRIVER'] ?? 'log');   // log|twilio|rwanda

// --- JWT ---
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'change-me-please');
define('JWT_TTL',    (int)($_ENV['JWT_TTL'] ?? 3600));

// --- Paths ---
define('UPLOAD_DIR',  SRP_ROOT . '/storage/uploads');
define('PDF_DIR',     SRP_ROOT . '/storage/pdf');
define('LOG_DIR',     SRP_ROOT . '/storage/logs');
define('UPLOAD_URL',  APP_URL . '/storage/uploads');

// --- Defaults (seeded values that must remain overrideable per company) ---
define('DEFAULT_CURRENCY', $_ENV['DEFAULT_CURRENCY'] ?? 'RWF');
define('DEFAULT_LANGUAGE', $_ENV['DEFAULT_LANGUAGE'] ?? 'en');

date_default_timezone_set(APP_TIMEZONE);
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', LOG_DIR . '/php-error.log');