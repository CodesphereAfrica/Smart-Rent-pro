<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('maintenance.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

if (($_GET['meta'] ?? '') === 'properties') {
    $rows = db_all('SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name', [':c'=>(int)$USER['company_id']]);
    echo json_encode(['properties'=>$rows]); exit;
}

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search    = trim((string)($_GET['search']['value'] ?? ''));
$orderCol  = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir  = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$fltStatus = trim((string)($_GET['status'] ?? ''));
$fltPrio   = trim((string)($_GET['priority'] ?? ''));
$fltProp   = (int)($_GET['property_id'] ?? 0);
$openOnly  = !empty($_GET['open_only']);

$columns = ['mr.ticket_number','unit_number','c.name','mr.priority','mr.status','assigned_name','mr.created_at'];
$orderBy = $columns[$orderCol] ?? 'mr.created_at';

$where  = ['mr.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltStatus !== '' && in_array($fltStatus, ['new','assigned','in_progress','waiting_for_parts','completed','verified','closed'], true)) {
    $where[] = 'mr.status = :st'; $params[':st'] = $fltStatus;
}
if ($fltPrio !== '' && in_array($fltPrio, ['emergency','high','medium','low'], true)) {
    $where[] = 'mr.priority = :pr'; $params[':pr'] = $fltPrio;
}
if ($fltProp) { $where[] = 'mr.property_id = :pid'; $params[':pid'] = $fltProp; }
if ($openOnly) $where[] = 'mr.status NOT IN ("closed","verified")';

if ($search !== '') {
    $where[] = '(mr.ticket_number LIKE :s OR u.unit_number LIKE :s OR mr.description LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}

if (has_role('Property Owner', $USER)) {
    $where[] = 'mr.property_id IN (SELECT property_id FROM owner_properties WHERE owner_id IN (SELECT id FROM owners WHERE user_id = :uid))';
    $params[':uid'] = (int)$USER['id'];
}
if (has_role('Tenant', $USER)) {
    $where[] = 'mr.tenant_id IN (SELECT id FROM tenants WHERE user_id = :tuid)';
    $params[':tuid'] = (int)$USER['id'];
}

$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM maintenance_requests WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c
       FROM maintenance_requests mr
       JOIN units u ON u.id = mr.unit_id
       LEFT JOIN maintenance_categories c ON c.id = mr.category_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT mr.id, mr.ticket_number, mr.priority, mr.status, mr.description, mr.created_at,
            u.unit_number, u.id AS unit_id, p.name AS property_name,
            c.name AS category_name,
            CONCAT(COALESCE(mr.assigned_to_user_id,0), '') AS ass_uid,
            COALESCE(
              (SELECT CONCAT(us.first_name,' ',us.last_name) FROM users us WHERE us.id = mr.assigned_to_user_id),
              (SELECT v.name FROM vendors v WHERE v.id = mr.assigned_vendor_id)
            ) AS assigned_name,
            DATEDIFF(NOW(), mr.created_at) AS age_days
       FROM maintenance_requests mr
       JOIN units u ON u.id = mr.unit_id
       JOIN properties p ON p.id = mr.property_id
       LEFT JOIN maintenance_categories c ON c.id = mr.category_id
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$statusMap = [
    'new'               => 'badge-status-reserved',
    'assigned'          => 'badge-status-notice_given',
    'in_progress'       => 'badge-status-occupied',
    'waiting_for_parts' => 'badge-status-under_maintenance',
    'completed'         => 'badge-status-available',
    'verified'          => 'badge-status-available',
    'closed'            => 'badge-status-unavailable',
];

$prioClass = [
    'emergency' => 'text-danger fw-bold',
    'high'      => 'text-warning fw-semibold',
    'medium'    => '',
    'low'       => 'text-muted',
];

$data = [];
foreach ($rows as $r) {
    $actions =
        '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
        '<ul class="dropdown-menu dropdown-menu-end">' .
          '<li><a class="dropdown-item" href="' . APP_URL . '/modules/maintenance/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
          (can('maintenance.assign') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/maintenance/assign.php?id=' . (int)$r['id'] . '"><i data-lucide="user-check"></i> ' . e(__('Assign')) . '</a></li>' : '') .
          (can('maintenance.edit')   ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/maintenance/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' : '') .
          (can('maintenance.create') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/work-orders/create.php?maintenance_id=' . (int)$r['id'] . '"><i data-lucide="clipboard-list"></i> ' . e(__('Create Work Order')) . '</a></li>' : '') .
        '</ul></div>';

    $data[] = [
        'ticket'   => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/maintenance/view.php?id=' . (int)$r['id'] . '">' . e($r['ticket_number']) . '</a>',
        'unit'     => '<a class="text-decoration-none" href="' . APP_URL . '/modules/units/view.php?id=' . (int)$r['unit_id'] . '">' . e($r['unit_number']) . '</a>' .
                      '<div class="text-muted small">' . e($r['property_name']) . '</div>',
        'category' => e($r['category_name'] ?? '—'),
        'priority' => '<span class="' . ($prioClass[$r['priority']] ?? '') . '">' . e(ucfirst($r['priority'])) . '</span>',
        'status'   => '<span class="srp-badge-pill ' . ($statusMap[$r['status']] ?? 'badge-status-unavailable') . '"><span class="dot"></span>' . e(ucfirst(str_replace('_',' ',$r['status']))) . '</span>',
        'assigned' => e($r['assigned_name'] ?? '—'),
        'reported' => e(date('Y-m-d', strtotime($r['created_at']))) . ' <span class="text-muted small">(' . (int)$r['age_days'] . 'd)</span>',
        'actions'  => $actions,
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);