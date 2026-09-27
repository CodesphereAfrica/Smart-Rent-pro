<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('maintenance.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

if (($_GET['meta'] ?? '') === 'vendors') {
    $rows = db_all('SELECT id, name FROM vendors WHERE company_id = :c AND status = "active" ORDER BY name', [':c'=>(int)$USER['company_id']]);
    echo json_encode(['vendors'=>$rows]); exit;
}

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search   = trim((string)($_GET['search']['value'] ?? ''));
$orderCol = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$fltStatus= trim((string)($_GET['status'] ?? ''));
$fltVendor= (int)($_GET['vendor_id'] ?? 0);

$columns = ['wo.work_order_number','mr.ticket_number','vendor_name','wo.scheduled_date','wo.actual_cost','wo.status'];
$orderBy = $columns[$orderCol] ?? 'wo.created_at';

$where  = ['wo.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltStatus !== '' && in_array($fltStatus, ['scheduled','in_progress','completed','cancelled'], true)) {
    $where[] = 'wo.status = :st'; $params[':st'] = $fltStatus;
}
if ($fltVendor) { $where[] = 'wo.vendor_id = :vid'; $params[':vid'] = $fltVendor; }
if ($search !== '') {
    $where[] = '(wo.work_order_number LIKE :s OR mr.ticket_number LIKE :s OR v.name LIKE :s OR wo.work_description LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
if (has_role('Property Owner', $USER)) {
    $where[] = 'mr.property_id IN (SELECT property_id FROM owner_properties WHERE owner_id IN (SELECT id FROM owners WHERE user_id = :uid))';
    $params[':uid'] = (int)$USER['id'];
}
$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM work_orders WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c
       FROM work_orders wo
       JOIN maintenance_requests mr ON mr.id = wo.maintenance_request_id
       LEFT JOIN vendors v ON v.id = wo.vendor_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT wo.id, wo.work_order_number, wo.scheduled_date, wo.completed_date, wo.status,
            wo.estimated_cost, wo.actual_cost, wo.expense_id,
            mr.ticket_number, mr.id AS maintenance_id,
            COALESCE(v.name, CONCAT(us.first_name,' ',us.last_name)) AS vendor_name
       FROM work_orders wo
       JOIN maintenance_requests mr ON mr.id = wo.maintenance_request_id
       LEFT JOIN vendors v ON v.id = wo.vendor_id
       LEFT JOIN users us ON us.id = wo.technician_user_id
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$statusMap = [
    'scheduled'   => 'badge-status-reserved',
    'in_progress' => 'badge-status-occupied',
    'completed'   => 'badge-status-available',
    'cancelled'   => 'badge-status-under_maintenance',
];

$data = [];
foreach ($rows as $r) {
    $actions =
        '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
        '<ul class="dropdown-menu dropdown-menu-end">' .
          '<li><a class="dropdown-item" href="' . APP_URL . '/modules/work-orders/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
          (can('maintenance.edit') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/work-orders/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' : '') .
        '</ul></div>';

    $data[] = [
        'wo'        => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/work-orders/view.php?id=' . (int)$r['id'] . '">' . e($r['work_order_number']) . '</a>',
        'ticket'    => '<a class="text-decoration-none" href="' . APP_URL . '/modules/maintenance/view.php?id=' . (int)$r['maintenance_id'] . '">' . e($r['ticket_number']) . '</a>',
        'vendor'    => e($r['vendor_name'] ?? '—'),
        'scheduled' => e($r['scheduled_date'] ?? '—'),
        'cost'      => e(money($r['actual_cost'] ?? $r['estimated_cost'] ?? 0)),
        'status'    => '<span class="srp-badge-pill ' . ($statusMap[$r['status']] ?? 'badge-status-unavailable') . '"><span class="dot"></span>' . e(ucfirst(str_replace('_',' ',$r['status']))) . '</span>',
        'actions'   => $actions,
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);