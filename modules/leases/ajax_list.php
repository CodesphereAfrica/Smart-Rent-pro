<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('leases.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

if (($_GET['meta'] ?? '') === 'properties') {
    $rows = db_all(
        'SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name',
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
$fltStatus   = trim((string)($_GET['status'] ?? ''));
$fltProperty = (int)($_GET['property_id'] ?? 0);
$fltExpiring = trim((string)($_GET['expiring'] ?? ''));

$columns = ['l.lease_number','tenant_name','unit_number','l.start_date','l.monthly_rent','days_left','l.status'];
$orderBy = $columns[$orderCol] ?? 'l.start_date';

$where  = ['l.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltStatus !== '' && in_array($fltStatus, ['draft','pending_signature','active','expiring','expired','terminated','renewed'], true)) {
    $where[] = 'l.status = :st'; $params[':st'] = $fltStatus;
}
if ($fltProperty) { $where[] = 'l.property_id = :pid'; $params[':pid'] = $fltProperty; }

if ($fltExpiring === 'expired') {
    $where[] = 'l.end_date < CURDATE()';
} elseif (in_array($fltExpiring, ['30','60','90'], true)) {
    $where[] = 'DATEDIFF(l.end_date, CURDATE()) BETWEEN 0 AND ' . (int)$fltExpiring;
}

if ($search !== '') {
    $where[] = '(l.lease_number LIKE :s OR t.first_name LIKE :s OR t.last_name LIKE :s OR u.unit_number LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}

// Owner portal query-level restriction
if (has_role('Property Owner', $USER)) {
    $where[] = 'l.property_id IN (SELECT property_id FROM owner_properties WHERE owner_id IN (SELECT id FROM owners WHERE user_id = :uid))';
    $params[':uid'] = (int)$USER['id'];
}

$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM leases WHERE company_id = :cid', [':cid' => (int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c
       FROM leases l
       JOIN tenants t ON t.id = l.tenant_id
       JOIN units   u ON u.id = l.unit_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT l.id, l.lease_number, l.start_date, l.end_date, l.monthly_rent, l.status,
            CONCAT(t.first_name,' ',t.last_name) AS tenant_name,
            t.id AS tenant_id,
            u.unit_number, u.id AS unit_id,
            DATEDIFF(l.end_date, CURDATE()) AS days_left
       FROM leases l
       JOIN tenants t ON t.id = l.tenant_id
       JOIN units   u ON u.id = l.unit_id
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$statusMap = [
    'draft'             => 'badge-status-unavailable',
    'pending_signature' => 'badge-status-reserved',
    'active'            => 'badge-status-available',
    'expiring'          => 'badge-status-notice_given',
    'expired'           => 'badge-status-under_maintenance',
    'terminated'        => 'badge-status-under_maintenance',
    'renewed'           => 'badge-status-occupied',
];

$data = [];
foreach ($rows as $r) {
    $daysLeft = (int)$r['days_left'];
    $daysCell = '';
    if (in_array($r['status'], ['active','expiring'], true)) {
        if ($daysLeft < 0) {
            $daysCell = '<span class="text-danger fw-semibold">' . e(__('Expired')) . '</span>';
        } elseif ($daysLeft <= 30) {
            $daysCell = '<span class="text-danger fw-semibold">' . $daysLeft . ' ' . e(__('days')) . '</span>';
        } elseif ($daysLeft <= 90) {
            $daysCell = '<span class="text-warning fw-semibold">' . $daysLeft . ' ' . e(__('days')) . '</span>';
        } else {
            $daysCell = '<span class="text-muted">' . $daysLeft . ' ' . e(__('days')) . '</span>';
        }
    } else {
        $daysCell = '—';
    }

    $actions =
        '<div class="dropdown">' .
          '<button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="' . e(__('Actions')) . '"><i data-lucide="more-vertical"></i></button>' .
          '<ul class="dropdown-menu dropdown-menu-end">' .
            '<li><a class="dropdown-item" href="' . APP_URL . '/modules/leases/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
            (can('leases.edit') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/leases/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' : '') .
            (can('leases.create') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/leases/renew.php?id=' . (int)$r['id'] . '"><i data-lucide="refresh-cw"></i> ' . e(__('Renew')) . '</a></li>' : '') .
            '<li><a class="dropdown-item" href="' . APP_URL . '/modules/documents/upload.php?related_type=lease&related_id=' . (int)$r['id'] . '"><i data-lucide="upload"></i> ' . e(__('Attach Document')) . '</a></li>' .
            (can('leases.terminate') ? '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/leases/delete.php?id=' . (int)$r['id'] . '"><i data-lucide="x-circle"></i> ' . e(__('Terminate')) . '</a></li>' : '') .
          '</ul>' .
        '</div>';

    $data[] = [
        'lease'  => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/leases/view.php?id=' . (int)$r['id'] . '">' . e($r['lease_number']) . '</a>',
        'tenant' => '<a class="text-decoration-none" href="' . APP_URL . '/modules/tenants/view.php?id=' . (int)$r['tenant_id'] . '">' . e($r['tenant_name']) . '</a>',
        'unit'   => '<a class="text-decoration-none" href="' . APP_URL . '/modules/units/view.php?id=' . (int)$r['unit_id'] . '">' . e($r['unit_number']) . '</a>',
        'period' => e($r['start_date']) . ' → ' . e($r['end_date']),
        'rent'   => e(money($r['monthly_rent'])),
        'days'   => $daysCell,
        'status' => '<span class="srp-badge-pill ' . ($statusMap[$r['status']] ?? 'badge-status-unavailable') . '">' .
                      '<span class="dot"></span>' . e(ucfirst(str_replace('_',' ',$r['status']))) .
                    '</span>',
        'actions' => $actions,
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);