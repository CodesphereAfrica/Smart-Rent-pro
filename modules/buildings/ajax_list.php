<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('buildings.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$draw  = (int)($_GET['draw'] ?? 0);
$start = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search = trim((string)($_GET['search']['value'] ?? ''));
$where = ['p.company_id = :c'];
$params = [':c' => (int)$USER['company_id']];
if ($search !== '') {
    $where[] = '(b.name LIKE :s OR p.name LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM buildings b JOIN properties p ON p.id = b.property_id WHERE p.company_id = :c',
    [':c' => (int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one("SELECT COUNT(*) AS c FROM buildings b JOIN properties p ON p.id = b.property_id WHERE $w", $params)['c'] ?? 0);

$rows = db_all(
    "SELECT b.id, b.name, b.floors_count, b.status, p.name AS property_name,
            (SELECT COUNT(*) FROM units u WHERE u.building_id = b.id) AS units_count
       FROM buildings b JOIN properties p ON p.id = b.property_id
      WHERE $w ORDER BY b.name ASC LIMIT $start, $length",
    $params
);

$data = [];
foreach ($rows as $r) {
    $data[] = [
        'name' => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/buildings/view.php?id=' . (int)$r['id'] . '">' . e($r['name']) . '</a>',
        'property' => e($r['property_name']),
        'floors' => (int)$r['floors_count'],
        'units' => (int)$r['units_count'],
        'status' => '<span class="srp-badge-pill ' . ($r['status'] === 'active' ? 'badge-status-available' : 'badge-status-unavailable') . '"><span class="dot"></span>' . e(ucfirst($r['status'])) . '</span>',
        'actions' =>
            '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
            '<ul class="dropdown-menu dropdown-menu-end">' .
              '<li><a class="dropdown-item" href="' . APP_URL . '/modules/buildings/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
              (can('buildings.edit') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/buildings/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' : '') .
              (can('buildings.delete') ? '<li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/buildings/delete.php?id=' . (int)$r['id'] . '"><i data-lucide="trash-2"></i> ' . e(__('Delete')) . '</a></li>' : '') .
            '</ul></div>',
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);