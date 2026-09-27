<?php
// api/v1/auth/refresh.php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
use Firebase\JWT\JWT;

$user = api_require_auth();
$now = time();
$token = JWT::encode([
    'iss' => APP_URL,
    'iat' => $now,
    'exp' => $now + JWT_TTL,
    'sub' => (int)$user['id'],
    'cid' => (int)$user['company_id'],
    'role'=> $user['role_name'],
], JWT_SECRET, 'HS256');

api_ok(['token' => $token, 'expires_in' => JWT_TTL]);