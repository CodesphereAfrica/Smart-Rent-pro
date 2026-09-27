<?php
// api/v1/_bootstrap.php — shared infra for all v1 endpoints (JWT auth + JSON helpers).
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function api_input(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode((string)$raw, true);
    if (is_array($json)) return $json;
    return $_POST;
}

function api_ok($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_err(string $message, int $status = 400, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => false, 'error' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function api_require_auth(): array
{
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $hdr, $m)) api_err('missing_token', 401);
    try {
        $decoded = (array) JWT::decode($m[1], new Key(JWT_SECRET, 'HS256'));
    } catch (Throwable $e) {
        api_err('invalid_token', 401);
    }
    if (empty($decoded['sub'])) api_err('invalid_claims', 401);
    $user = db_one(
        'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id
          WHERE u.id = :id AND u.status = "active" LIMIT 1',
        [':id' => (int)$decoded['sub']]
    );
    if (!$user) api_err('user_inactive', 401);
    return $user;
}