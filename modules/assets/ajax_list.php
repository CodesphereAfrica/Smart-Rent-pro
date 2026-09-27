<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');
$USER = require_login();
if (!can('properties.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

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
$orderDir  = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$fltStatus = trim((string)($_GET['status'] ?? ''));
$fltProp   = (int)($_GET['property_id'] ?? 0);

$columns = ['a.name','p.name','a.serial_number','a.purchase_cost','a.warranty_expiry','a.status'];
$orderBy = $columns[$orderCol] ?? 'a.name';

$where  = ['a.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];
if ($fltStatus !== '' && in_array($fltStatus, ['in_use','under_repair','retired','disposed'], true)) {
    $where[] = 'a.status = :st'; $params[':st'] = $fltStatus;
}
if ($fltProp) { $where[] = 'a.property_id = :pid'; $params[':pid'] = $fltProp; }
if ($search !== '') {
    $where[] = '(a.name LIKE :s OR a.serial_number LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
if (has_role('Property Owner', $USER)) {
    $where[] = 'a.property_id IN (SELECT property_id FROM owner_properties WHERE owner_id IN (SELECT id FROM owners WHERE user_id = :uid))';
    $params[':uid'] = (int)$USER['id'];
}
$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM assets WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one("SELECT COUNT(*) AS c FROM assets a JOIN properties p ON p.id = a.property_id WHERE $w", $params)['c'] ?? 0);

$rows = db_all(
    "SELECT a.id, a.name, a.serial_number, a.purchase_cost, a.warranty_expiry, a.status,
            p.name AS property_name, u.unit_number
       FROM assets a
       JOIN properties p ON p.id = a.property_id
       LEFT JOIN units u ON u.id = a.unit_id
      WHERE $w ORDER BY $orderBy $orderDir LIMIT $start, $length",
    $params
);

$statusMap = [
    'in_use' => 'badge-status-available','under_repair' => 'badge-status-under_maintenance',
    'retired' => 'badge-status-unavailable','disposed' => 'badge-status-unavailable',
];

$data = [];
foreach ($rows as $r) {
    $warranty = $r['warranty_expiry'] ?: '—';
    $warn = $r['warranty_expiry'] && strtotime($r['warranty_expiry']) < time();
    $warrantyCell = $warn ? '<span class="text-danger">' . e($warranty) . '</span>' : e($warranty);

    $data[] = [
        'name' => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/assets/edit.php?id=' . (int)$r['id'] . '">' . e($r['name']) . '</a>',
        'location' => e($r['property_name']) . ($r['unit_number'] ? ' · ' . e($r['unit_number']) : ''),
        'serial' => e($r['serial_number'] ?? '—'),
        'cost' => e(money($r['purchase_cost'] ?? 0)),
        'warranty' => $warrantyCell,
        'status' => '<span class="srp-badge-pill ' . ($statusMap[$r['status']] ?? 'badge-status-unavailable') . '"><span class="dot"></span>' . e(ucfirst(str_replace('_',' ',$r['status']))) . '</span>',
        'actions' =>
            '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
            '<ul class="dropdown-menu dropdown-menu-end">' .
              '<li><a class="dropdown-item" href="' . APP_URL . '/modules/assets/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' .
              (can('properties.delete') ? '<li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/assets/delete.php?id=' . (int)$r['id'] . '"><i data-lucide="trash-2"></i> ' . e(__('Delete')) . '</a></li>' : '') .
            '</ul></div>',
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);