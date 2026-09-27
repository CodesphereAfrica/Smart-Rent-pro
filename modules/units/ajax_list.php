<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
// Meta shortcut — feeds the property filter dropdown in units.js
if (($_GET['meta'] ?? '') === 'properties') {
    $rows = db_all('SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name',
                   [':c' => (int)$USER['company_id']]);
    echo json_encode(['properties' => $rows]);
    exit;
}
$USER = require_login();
if (!can('units.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$draw  = (int)($_GET['draw'] ?? 0);
$start = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search = trim((string)($_GET['search']['value'] ?? ''));
$fltProperty = (int)($_GET['property_id'] ?? 0);
$fltStatus   = trim((string)($_GET['status'] ?? ''));

$where = ['u.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];
if ($search !== '') {
    $where[] = '(u.unit_number LIKE :s OR p.name LIKE :s OR u.unit_type LIKE :s OR t.first_name LIKE :s OR t.last_name LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
if ($fltProperty) { $where[] = 'u.property_id = :pid'; $params[':pid'] = $fltProperty; }
if ($fltStatus && in_array($fltStatus, ['available','occupied','reserved','under_maintenance','under_renovation','unavailable','notice_given'], true)) {
    $where[] = 'u.status = :st'; $params[':st'] = $fltStatus;
}

// Owner portal query-level restriction
if (has_role('Property Owner', $USER)) {
    $where[] = 'u.property_id IN (SELECT property_id FROM owner_properties WHERE owner_id IN (SELECT id FROM owners WHERE user_id = :uid))';
    $params[':uid'] = (int)$USER['id'];
}

$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM units u WHERE u.company_id = :cid', [':cid' => (int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c
       FROM units u
       LEFT JOIN properties p ON p.id = u.property_id
       LEFT JOIN leases     l ON l.unit_id = u.id AND l.status IN ('active','expiring')
       LEFT JOIN tenants    t ON t.id = l.tenant_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT u.id, u.unit_number, u.unit_type, u.bedrooms, u.bathrooms, u.size_sqm,
            u.base_rent, u.status,
            p.name AS property_name,
            CONCAT(COALESCE(t.first_name,''), ' ', COALESCE(t.last_name,'')) AS tenant_name
       FROM units u
       LEFT JOIN properties p ON p.id = u.property_id
       LEFT JOIN leases     l ON l.unit_id = u.id AND l.status IN ('active','expiring')
       LEFT JOIN tenants    t ON t.id = l.tenant_id
      WHERE $w
      ORDER BY u.unit_number ASC
      LIMIT $start, $length",
    $params
);

$data = [];
foreach ($rows as $r) {
    $data[] = [
        'unit' => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/units/view.php?id=' . (int)$r['id'] . '">' . e($r['unit_number']) . '</a>',
        'property' => e($r['property_name']),
        'type' => e($r['unit_type'] ?? '—'),
        'bedbath' => (int)$r['bedrooms'] . ' / ' . (int)$r['bathrooms'],
        'rent' => e(money($r['base_rent'])),
        'tenant' => $r['tenant_name'] ? e(trim($r['tenant_name'])) : '—',
        'status' => '<span class="srp-badge-pill badge-status-' . e($r['status']) . '"><span class="dot"></span>' . e(ucfirst(str_replace('_',' ',$r['status']))) . '</span>',
        'actions' =>
            '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
            '<ul class="dropdown-menu dropdown-menu-end">' .
              '<li><a class="dropdown-item" href="' . APP_URL . '/modules/units/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View 360')) . '</a></li>' .
              (can('units.edit') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/units/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' : '') .
              (can('units.delete') ? '<li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/units/delete.php?id=' . (int)$r['id'] . '"><i data-lucide="trash-2"></i> ' . e(__('Delete')) . '</a></li>' : '') .
            '</ul></div>',
    ];
}

echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);