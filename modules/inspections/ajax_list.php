<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');
$USER = require_login();
if (!can('inspections.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search   = trim((string)($_GET['search']['value'] ?? ''));
$orderCol = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$fltType  = trim((string)($_GET['type'] ?? ''));
$fltStat  = trim((string)($_GET['status'] ?? ''));

$columns = ['i.inspection_date','i.inspection_type','unit_number','inspector_name','i.status'];
$orderBy = $columns[$orderCol] ?? 'i.inspection_date';

$where  = ['i.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];
if ($fltType !== '' && in_array($fltType, ['move_in','move_out','routine','maintenance','safety'], true)) {
    $where[] = 'i.inspection_type = :ty'; $params[':ty'] = $fltType;
}
if ($fltStat !== '' && in_array($fltStat, ['scheduled','completed','cancelled'], true)) {
    $where[] = 'i.status = :st'; $params[':st'] = $fltStat;
}
if ($search !== '') {
    $where[] = '(u.unit_number LIKE :s OR p.name LIKE :s OR us.first_name LIKE :s OR us.last_name LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
if (has_role('Property Owner', $USER)) {
    $where[] = 'i.property_id IN (SELECT property_id FROM owner_properties WHERE owner_id IN (SELECT id FROM owners WHERE user_id = :uid))';
    $params[':uid'] = (int)$USER['id'];
}
$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM inspections WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c FROM inspections i
       JOIN units u ON u.id = i.unit_id
       JOIN properties p ON p.id = i.property_id
       JOIN users us ON us.id = i.inspector_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT i.id, i.inspection_date, i.inspection_type, i.status,
            u.unit_number, p.name AS property_name, p.id AS property_id,
            CONCAT(us.first_name,' ',us.last_name) AS inspector_name
       FROM inspections i
       JOIN units u ON u.id = i.unit_id
       JOIN properties p ON p.id = i.property_id
       JOIN users us ON us.id = i.inspector_id
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$typeMap = [
    'move_in' => 'badge-status-available',
    'move_out' => 'badge-status-notice_given',
    'routine' => 'badge-status-reserved',
    'maintenance' => 'badge-status-under_maintenance',
    'safety' => 'badge-status-occupied',
];

$data = [];
foreach ($rows as $r) {
    $actions =
        '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
        '<ul class="dropdown-menu dropdown-menu-end">' .
          '<li><a class="dropdown-item" href="' . APP_URL . '/modules/inspections/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
          (can('inspections.edit') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/inspections/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' : '') .
        '</ul></div>';

    $data[] = [
        'date'      => e($r['inspection_date']),
        'type'      => '<span class="srp-badge-pill ' . ($typeMap[$r['inspection_type']] ?? 'badge-status-unavailable') . '"><span class="dot"></span>' . e(ucfirst(str_replace('_',' ',$r['inspection_type']))) . '</span>',
        'unit'      => e($r['unit_number']) . '<div class="text-muted small">' . e($r['property_name']) . '</div>',
        'inspector' => e($r['inspector_name']),
        'status'    => e(ucfirst($r['status'])),
        'actions'   => $actions,
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);