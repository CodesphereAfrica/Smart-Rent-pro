<?php
// modules/tenants/ajax_list.php — DataTables server-side source. Self-contained.
// Renders the tenant avatar as: uploaded profile photo → fallback to initials circle.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$USER = require_login();
if (!can('tenants.view', $USER)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search     = trim((string)($_GET['search']['value'] ?? ''));
$orderCol   = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir   = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$fltStatus  = trim((string)($_GET['status'] ?? ''));
$fltType    = trim((string)($_GET['tenant_type'] ?? ''));
$fltArrears = trim((string)($_GET['arrears'] ?? ''));

// Column index → ORDER BY map. Index 0 is the avatar+name cell, sorts by last_name then first_name.
$orderMap = [
    0 => 't.last_name ' . $orderDir . ', t.first_name ' . $orderDir,
    1 => 't.phone',
    2 => 'unit_number',
    3 => 'balance',
    4 => 't.status',
];
$orderBy = $orderMap[$orderCol] ?? ('t.last_name ' . $orderDir . ', t.first_name ' . $orderDir);

$where  = ['t.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltStatus !== '' && in_array($fltStatus, ['active','former','blacklisted'], true)) {
    $where[] = 't.status = :st';
    $params[':st'] = $fltStatus;
}
if ($fltType !== '' && in_array($fltType, ['individual','corporate'], true)) {
    $where[] = 't.tenant_type = :tt';
    $params[':tt'] = $fltType;
}
if ($search !== '') {
    $where[] = '(t.first_name LIKE :s OR t.last_name LIKE :s OR t.phone LIKE :s OR t.email LIKE :s OR t.id_number LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}

// Arrears filter — deferred aggregate via subquery so it doesn't fight the LEFT JOINs.
$arrearsSub = 'COALESCE((SELECT SUM(balance) FROM invoices i
                          WHERE i.tenant_id = t.id
                            AND i.status IN ("due","partially_paid","overdue")), 0)';
if ($fltArrears === 'yes') {
    $where[] = $arrearsSub . ' > 0';
} elseif ($fltArrears === 'no') {
    $where[] = $arrearsSub . ' = 0';
}

$w = implode(' AND ', $where);

$total = (int)(db_one(
    'SELECT COUNT(*) AS c FROM tenants WHERE company_id = :cid',
    [':cid' => (int)$USER['company_id']]
)['c'] ?? 0);

$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c
       FROM tenants t
       LEFT JOIN leases     l ON l.tenant_id = t.id AND l.status IN ('active','expiring')
       LEFT JOIN units      u ON u.id = l.unit_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT t.id, t.first_name, t.last_name, t.phone, t.email, t.tenant_type, t.status,
            t.profile_photo,
            u.unit_number,
            p.name AS property_name,
            COALESCE((
              SELECT SUM(balance) FROM invoices i
               WHERE i.tenant_id = t.id AND i.status IN ('due','partially_paid','overdue')
            ), 0) AS balance
       FROM tenants t
       LEFT JOIN leases     l ON l.tenant_id = t.id AND l.status IN ('active','expiring')
       LEFT JOIN units      u ON u.id = l.unit_id
       LEFT JOIN properties p ON p.id = l.property_id
      WHERE $w
      ORDER BY $orderBy
      LIMIT $start, $length",
    $params
);

$statusMap = [
    'active'      => 'badge-status-available',
    'former'      => 'badge-status-unavailable',
    'blacklisted' => 'badge-status-under_maintenance',
];

$data = [];
foreach ($rows as $r) {
    $initials = strtoupper(substr((string)$r['first_name'], 0, 1) . substr((string)$r['last_name'], 0, 1));
    $name     = e($r['first_name'] . ' ' . $r['last_name']);
    $hasPhoto = !empty($r['profile_photo']);

    // Avatar: uploaded photo when present, otherwise initials circle. Same dimensions either way.
    $avatarHTML = $hasPhoto
        ? '<img src="' . e(UPLOAD_URL . '/' . $r['profile_photo']) . '" class="srp-avatar-img" alt="">'
        : '<span class="srp-avatar">' . e($initials) . '</span>';

    $actions =
        '<div class="dropdown">' .
          '<button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="' . e(__('Actions')) . '">' .
            '<i data-lucide="more-vertical"></i>' .
          '</button>' .
          '<ul class="dropdown-menu dropdown-menu-end">' .
            '<li><a class="dropdown-item" href="' . APP_URL . '/modules/tenants/view.php?id=' . (int)$r['id'] . '">' .
              '<i data-lucide="eye"></i> ' . e(__('View 360')) . '</a></li>' .
            (can('tenants.edit')
              ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/tenants/edit.php?id=' . (int)$r['id'] . '">' .
                  '<i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>'
              : '') .
            (can('payments.create')
              ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/payments/create.php?tenant_id=' . (int)$r['id'] . '">' .
                  '<i data-lucide="banknote"></i> ' . e(__('Record Payment')) . '</a></li>'
              : '') .
            (can('communications.send')
              ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/communications/compose.php?related_type=tenant&related_id=' . (int)$r['id'] . '">' .
                  '<i data-lucide="mail"></i> ' . e(__('Send Message')) . '</a></li>'
              : '') .
            (can('tenants.delete')
              ? '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/tenants/delete.php?id=' . (int)$r['id'] . '">' .
                  '<i data-lucide="trash-2"></i> ' . e(__('Archive')) . '</a></li>'
              : '') .
          '</ul>' .
        '</div>';

    $contact = e($r['phone']);
    if (!empty($r['email'])) {
        $contact .= '<div class="text-muted small">' . e($r['email']) . '</div>';
    }

    $unitCell = $r['unit_number']
        ? '<div class="fw-semibold">' . e($r['unit_number']) . '</div><div class="text-muted small">' . e($r['property_name']) . '</div>'
        : '<span class="text-muted">—</span>';

    $balance = (float)$r['balance'];
    if ($balance > 0) {
        $balanceCell = '<span class="text-danger fw-semibold">' . e(money($balance)) . '</span>';
    } elseif ($balance < 0) {
        $balanceCell = '<span class="text-success">' . e(money($balance)) . '</span>';
    } else {
        $balanceCell = '<span class="text-muted">' . e(money(0)) . '</span>';
    }

    $data[] = [
        'tenant'  => '<div class="d-flex align-items-center gap-2">' .
                       $avatarHTML .
                       '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/tenants/view.php?id=' . (int)$r['id'] . '">' . $name . '</a>' .
                     '</div>',
        'contact' => $contact,
        'unit'    => $unitCell,
        'balance' => $balanceCell,
        'status'  => '<span class="srp-badge-pill ' . ($statusMap[$r['status']] ?? 'badge-status-unavailable') . '">' .
                        '<span class="dot"></span>' . e(ucfirst($r['status'])) . '</span>',
        'actions' => $actions,

        // Extra fields — safe for the current UI (DataTables ignores unused keys).
        // Available to any future consumer (mobile app, Excel export template).
        'has_photo' => $hasPhoto ? 1 : 0,
        'tenant_id' => (int)$r['id'],
    ];
}

echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $total,
    'recordsFiltered' => $filtered,
    'data'            => $data,
]);