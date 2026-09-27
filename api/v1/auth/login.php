<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

use Firebase\JWT\JWT;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_err('method_not_allowed', 405);

$in = api_input();
$identifier = trim((string)($in['identifier'] ?? ''));
$password   = (string)($in['password'] ?? '');

if ($identifier === '' || $password === '') api_err('missing_credentials', 400);

$user = db_one(
    'SELECT u.*, r.name AS role_name, c.name AS company_name
       FROM users u
       JOIN roles r ON r.id = u.role_id
       JOIN companies c ON c.id = u.company_id
      WHERE (u.username = :i OR u.email = :i) AND u.status = "active" LIMIT 1',
    [':i' => $identifier]
);
if (!$user || !password_verify($password, $user['password_hash'])) {
    // Reuse the login_attempts throttle table
    if (function_exists('record_login_attempt')) {
        record_login_attempt($identifier, false);
    }
    api_err('invalid_credentials', 401);
}

$now = time();
$payload = [
    'iss' => APP_URL,
    'iat' => $now,
    'exp' => $now + JWT_TTL,
    'sub' => (int)$user['id'],
    'cid' => (int)$user['company_id'],
    'role'=> $user['role_name'],
];

$token = JWT::encode($payload, JWT_SECRET, 'HS256');

db_update('users', [
    'last_login_at' => date('Y-m-d H:i:s'),
    'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
], 'id = :id', [':id' => (int)$user['id']]);

api_ok([
    'token' => $token,
    'expires_in' => JWT_TTL,
    'user' => [
        'id' => (int)$user['id'],
        'name' => trim($user['first_name'].' '.$user['last_name']),
        'role' => $user['role_name'],
        'company' => $user['company_name'],
        'base_currency' => $user['company_id'] ? (db_one('SELECT base_currency_code FROM companies WHERE id = :c', [':c'=>(int)$user['company_id']])['base_currency_code'] ?? null) : null,
    ],
]);