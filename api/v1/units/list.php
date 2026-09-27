<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

$user = api_require_auth();
$allowed = $user['role_name'] === 'Super Admin' || db_one(
    'SELECT 1 FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
      WHERE rp.role_id = :r AND p.code = "units.view" LIMIT 1', [':r' => (int)$user['role_id']]
);
if (!$allowed) api_err('forbidden', 403);

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
$offset = ($page - 1) * $limit;

$rows = db_all(
    'SELECT u.id, u.unit_number, u.unit_type, u.bedrooms, u.bathrooms, u.size_sqm,
            u.base_rent, u.security_deposit, u.status,
            p.name AS property_name, p.id AS property_id
       FROM units u JOIN properties p ON p.id = u.property_id
      WHERE u.company_id = :c
      ORDER BY u.id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
    [':c' => (int)$user['company_id']]
);
$total = (int)(db_one('SELECT COUNT(*) AS c FROM units WHERE company_id = :c', [':c'=>(int)$user['company_id']])['c'] ?? 0);

api_ok(['items' => $rows, 'page' => $page, 'per_page' => $limit, 'total' => $total]);