<?php
// api/v1/auth/me.php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
use Firebase\JWT\JWT;

$user = api_require_auth();
api_ok([
    'id' => (int)$user['id'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'email' => $user['email'],
    'role' => $user['role_name'],
    'company_id' => (int)$user['company_id'],
]);