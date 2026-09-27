<?php
// modules/amenity-bookings/ajax_list.php — DataTables server-side source. Self-contained.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$USER = require_login();
if (!can('properties.view', $USER)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']); exit;
}

// Meta — amenity + tenant dropdowns for the filter bar
if (($_GET['meta'] ?? '') === 'filters') {
    $amenities = db_all(
        'SELECT a.id, a.name, p.name AS property_name
           FROM amenities a
           JOIN properties p ON p.id = a.property_id
          WHERE p.company_id = :c AND a.status = "active"
          ORDER BY a.name',
        [':c' => (int)$USER['company_id']]
    );
    $tenants = db_all(
        'SELECT id, first_name, last_name FROM tenants
          WHERE company_id = :c AND status = "active"
          ORDER BY first_name',
        [':c' => (int)$USER['company_id']]
    );
    echo json_encode(['amenities' => $amenities, 'tenants' => $tenants]);
    exit;
}

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search     = trim((string)($_GET['search']['value'] ?? ''));
$orderCol   = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir   = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$fltAmenity = (int)($_GET['amenity_id'] ?? 0);
$fltTenant  = (int)($_GET['tenant_id'] ?? 0);
$fltStatus  = trim((string)($_GET['status'] ?? ''));

// DataTables column order must match the <thead> in index.php
$columns = ['b.booking_date', 'a.name', "CONCAT(t.first_name,' ',t.last_name)", 'b.fee_paid', 'b.status'];
$orderBy = $columns[$orderCol] ?? 'b.booking_date';

$where  = ['p.company_id = :cid', 'p.deleted_at IS NULL'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltAmenity) { $where[] = 'b.amenity_id = :aid'; $params[':aid'] = $fltAmenity; }
if ($fltTenant)  { $where[] = 'b.tenant_id = :tid';  $params[':tid'] = $fltTenant; }
if ($fltStatus !== '' && in_array($fltStatus, ['pending','confirmed','cancelled','completed'], true)) {
    $where[] = 'b.status = :st'; $params[':st'] = $fltStatus;
}
if ($search !== '') {
    $where[] = '(a.name LIKE :s OR p.name LIKE :s OR t.first_name LIKE :s OR t.last_name LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}

if (has_role('Property Owner', $USER)) {
    $where[] = 'p.id IN (SELECT property_id FROM owner_properties WHERE owner_id IN (SELECT id FROM owners WHERE user_id = :uid))';
    $params[':uid'] = (int)$USER['id'];
}

$w = implode(' AND ', $where);

$total = (int)(db_one(
    'SELECT COUNT(*) AS c
       FROM amenity_bookings b
       JOIN amenities a ON a.id = b.amenity_id
       JOIN properties p ON p.id = a.property_id
      WHERE p.company_id = :cid AND p.deleted_at IS NULL',
    [':cid' => (int)$USER['company_id']]
)['c'] ?? 0);

$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c
       FROM amenity_bookings b
       JOIN amenities  a ON a.id = b.amenity_id
       JOIN properties p ON p.id = a.property_id
       JOIN tenants    t ON t.id = b.tenant_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT b.id, b.booking_date, b.start_time, b.end_time, b.fee_paid, b.status,
            a.name AS amenity_name,
            t.id   AS tenant_id,
            CONCAT(t.first_name,' ',t.last_name) AS tenant_name
       FROM amenity_bookings b
       JOIN amenities  a ON a.id = b.amenity_id
       JOIN properties p ON p.id = a.property_id
       JOIN tenants    t ON t.id = b.tenant_id
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$statusMap = [
    'pending'   => 'badge-status-reserved',
    'confirmed' => 'badge-status-available',
    'cancelled' => 'badge-status-unavailable',
    'completed' => 'badge-status-occupied',
];

$data = [];
foreach ($rows as $r) {
    $timeRange = trim(($r['start_time'] ?? '') . ($r['end_time'] ? ' – ' . $r['end_time'] : ''));
    if ($timeRange === '') $timeRange = '—';

    $actions =
        '<div class="dropdown">' .
          '<button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="' . e(__('Actions')) . '">' .
            '<i data-lucide="more-vertical"></i>' .
          '</button>' .
          '<ul class="dropdown-menu dropdown-menu-end">' .
            '<li><a class="dropdown-item" href="' . APP_URL . '/modules/amenity-bookings/edit.php?id=' . (int)$r['id'] . '">' .
              '<i data-lucide="pencil"></i> ' . e(__('Edit')) .
            '</a></li>' .
            '<li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/amenity-bookings/delete.php?id=' . (int)$r['id'] . '">' .
              '<i data-lucide="trash-2"></i> ' . e(__('Delete')) .
            '</a></li>' .
          '</ul>' .
        '</div>';

    $data[] = [
        'amenity' => '<span class="fw-semibold">' . e($r['amenity_name']) . '</span>',
        'tenant'  => '<a class="text-decoration-none" href="' . APP_URL . '/modules/tenants/view.php?id=' . (int)$r['tenant_id'] . '">' . e($r['tenant_name']) . '</a>',
        'date'    => e($r['booking_date']),
        'time'    => e($timeRange),
        'fee'     => e(money($r['fee_paid'])),
        'status'  => '<span class="srp-badge-pill ' . ($statusMap[$r['status']] ?? 'badge-status-unavailable') . '">' .
                        '<span class="dot"></span>' . e(ucfirst($r['status'])) .
                      '</span>',
        'actions' => $actions,
    ];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $total,
    'recordsFiltered' => $filtered,
    'data'            => $data,
]);