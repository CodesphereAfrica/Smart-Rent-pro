<?php
// modules/parking/ajax_list.php — DataTables server-side source. Self-contained.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$USER = require_login();
if (!can('properties.view', $USER)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']); exit;
}

// ---------------------------------------------------------------------------
// Meta shortcut — properties dropdown.
// ---------------------------------------------------------------------------
if (($_GET['meta'] ?? '') === 'properties') {
    $rows = db_all(
        'SELECT id, name FROM properties
          WHERE company_id = :c AND deleted_at IS NULL
          ORDER BY name',
        [':c' => (int)$USER['company_id']]
    );
    echo json_encode(['properties' => $rows]);
    exit;
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

$columns = ['s.space_number', 'p.name', 's.space_type', 's.monthly_fee', 'active_assignments', 's.status'];
$orderBy = $columns[$orderCol] ?? 's.space_number';

$where  = ['p.company_id = :cid', 'p.deleted_at IS NULL'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltProperty) { $where[] = 's.property_id = :pid'; $params[':pid'] = $fltProperty; }
if ($fltStatus !== '' && in_array($fltStatus, ['available','assigned','unavailable'], true)) {
    $where[] = 's.status = :st'; $params[':st'] = $fltStatus;
}
if ($search !== '') {
    $where[] = '(s.space_number LIKE :s OR p.name LIKE :s OR s.space_type LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}

// Owner portal query-level restriction
if (has_role('Property Owner', $USER)) {
    $where[] = 'p.id IN (SELECT property_id FROM owner_properties WHERE owner_id IN (SELECT id FROM owners WHERE user_id = :uid))';
    $params[':uid'] = (int)$USER['id'];
}

$w = implode(' AND ', $where);

$total = (int)(db_one(
    'SELECT COUNT(*) AS c
       FROM parking_spaces s
       JOIN properties p ON p.id = s.property_id
      WHERE p.company_id = :cid AND p.deleted_at IS NULL',
    [':cid' => (int)$USER['company_id']]
)['c'] ?? 0);

$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c
       FROM parking_spaces s
       JOIN properties p ON p.id = s.property_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT s.id, s.space_number, s.space_type, s.monthly_fee, s.status,
            p.id AS property_id, p.name AS property_name,
            (SELECT COUNT(*) FROM parking_assignments a
              WHERE a.parking_space_id = s.id AND a.status = 'active') AS active_assignments
       FROM parking_spaces s
       JOIN properties p ON p.id = s.property_id
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$statusMap = [
    'available'   => 'badge-status-available',
    'assigned'    => 'badge-status-occupied',
    'unavailable' => 'badge-status-unavailable',
];

$data = [];
foreach ($rows as $r) {
    $actions =
        '<div class="dropdown">' .
          '<button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="' . e(__('Actions')) . '">' .
            '<i data-lucide="more-vertical"></i>' .
          '</button>' .
          '<ul class="dropdown-menu dropdown-menu-end">' .
            '<li><a class="dropdown-item" href="' . APP_URL . '/modules/parking/edit.php?id=' . (int)$r['id'] . '">' .
              '<i data-lucide="pencil"></i> ' . e(__('Edit')) .
            '</a></li>' .
            (can('properties.delete')
              ? '<li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/parking/delete.php?id=' . (int)$r['id'] . '">' .
                  '<i data-lucide="trash-2"></i> ' . e(__('Delete')) .
                '</a></li>'
              : '') .
          '</ul>' .
        '</div>';

    $data[] = [
        'space'    => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/parking/edit.php?id=' . (int)$r['id'] . '">' . e($r['space_number']) . '</a>',
        'property' => '<a class="text-decoration-none" href="' . APP_URL . '/modules/properties/view.php?id=' . (int)$r['property_id'] . '">' . e($r['property_name']) . '</a>',
        'type'     => e($r['space_type'] ?? '—'),
        'fee'      => e(money($r['monthly_fee'])),
        'assignments' => (int)$r['active_assignments'],
        'status'   => '<span class="srp-badge-pill ' . ($statusMap[$r['status']] ?? 'badge-status-unavailable') . '">' .
                        '<span class="dot"></span>' . e(ucfirst($r['status'])) .
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