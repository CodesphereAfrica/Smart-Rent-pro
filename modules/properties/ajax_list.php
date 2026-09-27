<?php
// modules/properties/ajax_list.php — DataTables server-side source. Self-contained.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$USER = require_login();
if (!can('properties.view', $USER)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']); exit;
}

$draw   = (int)($_GET['draw'] ?? 0);
$start  = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search = trim((string)($_GET['search']['value'] ?? ''));
$orderCol = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

$columns = ['p.name','p.property_type','p.city','o.full_name','m.first_name','p.num_units','p.status'];
$orderBy = $columns[$orderCol] ?? 'p.id';

$where = ['p.company_id = :cid', 'p.deleted_at IS NULL'];
$params = [':cid' => (int)$USER['company_id']];

if ($search !== '') {
    $where[] = '(p.name LIKE :s OR p.address LIKE :s OR p.city LIKE :s OR o.full_name LIKE :s OR p.property_type LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}

// Owner portal query-level restriction — owners see only their own.
if (has_role('Property Owner', $USER)) {
    $where[] = 'p.owner_id IN (SELECT id FROM owners WHERE user_id = :uid)';
    $params[':uid'] = (int)$USER['id'];
}

$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM properties p WHERE p.company_id = :cid AND p.deleted_at IS NULL',
    [':cid' => (int)$USER['company_id']])['c'] ?? 0);

$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c
       FROM properties p
       LEFT JOIN owners o ON o.id = p.owner_id
       LEFT JOIN users  m ON m.id = p.assigned_manager_id
      WHERE $w", $params)['c'] ?? 0);

$rows = db_all(
    "SELECT p.id, p.name, p.property_type, p.address, p.city, p.country,
            p.num_units, p.status, p.photos,
            o.full_name AS owner_name,
            CONCAT(m.first_name, ' ', m.last_name) AS manager_name
       FROM properties p
       LEFT JOIN owners o ON o.id = p.owner_id
       LEFT JOIN users  m ON m.id = p.assigned_manager_id
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$statusBadge = [
  'active' => 'badge-status-available',
  'inactive' => 'badge-status-unavailable',
  'under_construction' => 'badge-status-reserved',
  'sold' => 'badge-status-notice_given',
];

$data = [];
foreach ($rows as $r) {
    $badgeClass = $statusBadge[$r['status']] ?? 'badge-status-unavailable';
    $data[] = [
        'name' => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/properties/view.php?id=' . (int)$r['id'] . '">' . e($r['name']) . '</a>',
        'type' => '<span class="text-capitalize">' . e(str_replace('_', ' ', $r['property_type'])) . '</span>',
        'location' => e(trim(($r['city'] ?? '') . ', ' . ($r['country'] ?? ''), ', ')),
        'owner' => e($r['owner_name'] ?? '—'),
        'manager' => e($r['manager_name'] ?? '—'),
        'units' => (int)$r['num_units'],
        'status' => '<span class="srp-badge-pill ' . $badgeClass . '"><span class="dot"></span>' . e(ucfirst(str_replace('_',' ',$r['status']))) . '</span>',
        'actions' =>
            '<div class="dropdown">' .
              '<button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
              '<ul class="dropdown-menu dropdown-menu-end">' .
                '<li><a class="dropdown-item" href="' . APP_URL . '/modules/properties/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
                (can('properties.edit') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/properties/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' : '') .
                (can('properties.delete') ? '<li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/properties/delete.php?id=' . (int)$r['id'] . '"><i data-lucide="trash-2"></i> ' . e(__('Delete')) . '</a></li>' : '') .
              '</ul>' .
            '</div>',
    ];
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $total,
    'recordsFiltered' => $filtered,
    'data' => $data,
]);