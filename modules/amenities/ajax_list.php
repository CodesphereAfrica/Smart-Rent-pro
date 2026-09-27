<?php
// modules/amenities/ajax_list.php — DataTables server-side source. Self-contained.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$USER = require_login();
if (!can('properties.view', $USER)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']); exit;
}

// Meta — property dropdown
if (($_GET['meta'] ?? '') === 'properties') {
    $rows = db_all(
        'SELECT id, name FROM properties
          WHERE company_id = :c AND deleted_at IS NULL ORDER BY name',
        [':c' => (int)$USER['company_id']]
    );
    echo json_encode(['properties' => $rows]); exit;
}

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search      = trim((string)($_GET['search']['value'] ?? ''));
$orderCol    = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir    = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$fltProperty = (int)($_GET['property_id'] ?? 0);
$fltStatus   = trim((string)($_GET['status'] ?? ''));

$columns = ['a.name', 'p.name', 'a.amenity_type', 'a.fee', 'upcoming_bookings', 'a.status'];
$orderBy = $columns[$orderCol] ?? 'a.name';

$where  = ['p.company_id = :cid', 'p.deleted_at IS NULL'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltProperty) { $where[] = 'a.property_id = :pid'; $params[':pid'] = $fltProperty; }
if ($fltStatus !== '' && in_array($fltStatus, ['active','under_maintenance','inactive'], true)) {
    $where[] = 'a.status = :st'; $params[':st'] = $fltStatus;
}
if ($search !== '') {
    $where[] = '(a.name LIKE :s OR p.name LIKE :s OR a.amenity_type LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}

if (has_role('Property Owner', $USER)) {
    $where[] = 'p.id IN (SELECT property_id FROM owner_properties WHERE owner_id IN (SELECT id FROM owners WHERE user_id = :uid))';
    $params[':uid'] = (int)$USER['id'];
}

$w = implode(' AND ', $where);

$total = (int)(db_one(
    'SELECT COUNT(*) AS c FROM amenities a
       JOIN properties p ON p.id = a.property_id
      WHERE p.company_id = :cid AND p.deleted_at IS NULL',
    [':cid' => (int)$USER['company_id']]
)['c'] ?? 0);

$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c FROM amenities a
       JOIN properties p ON p.id = a.property_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT a.id, a.name, a.amenity_type, a.fee, a.status,
            p.id AS property_id, p.name AS property_name,
            (SELECT COUNT(*) FROM amenity_bookings b
              WHERE b.amenity_id = a.id AND b.status IN ('pending','confirmed')) AS upcoming_bookings
       FROM amenities a
       JOIN properties p ON p.id = a.property_id
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$statusMap = [
    'active'            => 'badge-status-available',
    'under_maintenance' => 'badge-status-under_maintenance',
    'inactive'          => 'badge-status-unavailable',
];

$data = [];
foreach ($rows as $r) {
    $actions =
        '<div class="dropdown">' .
          '<button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="' . e(__('Actions')) . '">' .
            '<i data-lucide="more-vertical"></i>' .
          '</button>' .
          '<ul class="dropdown-menu dropdown-menu-end">' .
            '<li><a class="dropdown-item" href="' . APP_URL . '/modules/amenities/edit.php?id=' . (int)$r['id'] . '">' .
              '<i data-lucide="pencil"></i> ' . e(__('Edit')) .
            '</a></li>' .
            (can('properties.delete')
              ? '<li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/amenities/delete.php?id=' . (int)$r['id'] . '">' .
                  '<i data-lucide="trash-2"></i> ' . e(__('Delete')) .
                '</a></li>'
              : '') .
          '</ul>' .
        '</div>';

    $data[] = [
        'name'     => '<span class="fw-semibold">' . e($r['name']) . '</span>',
        'property' => '<a class="text-decoration-none" href="' . APP_URL . '/modules/properties/view.php?id=' . (int)$r['property_id'] . '">' . e($r['property_name']) . '</a>',
        'type'     => e($r['amenity_type'] ?? '—'),
        'fee'      => e(money($r['fee'])),
        'upcoming' => (int)$r['upcoming_bookings'],
        'status'   => '<span class="srp-badge-pill ' . ($statusMap[$r['status']] ?? 'badge-status-unavailable') . '">' .
                        '<span class="dot"></span>' . e(ucfirst(str_replace('_', ' ', $r['status']))) .
                      '</span>',
        'actions'  => $actions,
    ];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $total,
    'recordsFiltered' => $filtered,
    'data'            => $data,
]);