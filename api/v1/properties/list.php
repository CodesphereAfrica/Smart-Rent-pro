<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

$user = api_require_auth();
if (!function_exists('can') || !can('properties.view', $user)) {
    // Small inline check — can() lives in includes/auth.php which we don't load here to keep API light.
    $allowed = db_one(
        'SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
          WHERE rp.role_id = :r AND p.code = "properties.view" LIMIT 1',
        [':r' => (int)$user['role_id']]
    );
    $isSuper = $user['role_name'] === 'Super Admin';
    if (!$allowed && !$isSuper) api_err('forbidden', 403);
}

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
$offset = ($page - 1) * $limit;

$rows = db_all(
    'SELECT id, name, property_type, address, city, country, status, num_units, num_buildings
       FROM properties
      WHERE company_id = :c AND deleted_at IS NULL
      ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
    [':c' => (int)$user['company_id']]
);
$total = (int)(db_one('SELECT COUNT(*) AS c FROM properties WHERE company_id = :c AND deleted_at IS NULL', [':c'=>(int)$user['company_id']])['c'] ?? 0);

api_ok([
    'items' => $rows,
    'page' => $page,
    'per_page' => $limit,
    'total' => $total,
]);