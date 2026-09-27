<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('properties.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search   = trim((string)($_GET['search']['value'] ?? ''));
$orderCol = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$fltStat  = trim((string)($_GET['status'] ?? ''));
$fltExp   = trim((string)($_GET['expiry'] ?? ''));

$columns = ['ct.title','ct.contract_type','v.name','ct.start_date','ct.amount','ct.status'];
$orderBy = $columns[$orderCol] ?? 'ct.start_date';

$where  = ['ct.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];
if ($fltStat !== '' && in_array($fltStat, ['active','expired','terminated'], true)) {
    $where[] = 'ct.status = :st'; $params[':st'] = $fltStat;
}
if (in_array($fltExp, ['30','60'], true)) {
    $where[] = 'ct.end_date IS NOT NULL AND DATEDIFF(ct.end_date, CURDATE()) BETWEEN 0 AND ' . (int)$fltExp;
}
if ($search !== '') {
    $where[] = '(ct.title LIKE :s OR ct.contract_type LIKE :s OR v.name LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
if (has_role('Property Owner', $USER)) {
    $where[] = 'ct.property_id IN (SELECT property_id FROM owner_properties WHERE owner_id IN (SELECT id FROM owners WHERE user_id = :uid))';
    $params[':uid'] = (int)$USER['id'];
}
$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM contracts WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one("SELECT COUNT(*) AS c FROM contracts ct LEFT JOIN vendors v ON v.id = ct.vendor_id WHERE $w", $params)['c'] ?? 0);

$rows = db_all(
    "SELECT ct.id, ct.title, ct.contract_type, ct.start_date, ct.end_date, ct.amount, ct.status,
            v.name AS vendor_name
       FROM contracts ct
       LEFT JOIN vendors v ON v.id = ct.vendor_id
      WHERE $w ORDER BY $orderBy $orderDir LIMIT $start, $length",
    $params
);

$statusMap = ['active'=>'badge-status-available','expired'=>'badge-status-unavailable','terminated'=>'badge-status-under_maintenance'];

$data = [];
foreach ($rows as $r) {
    $data[] = [
        'title'  => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/contracts/view.php?id=' . (int)$r['id'] . '">' . e($r['title']) . '</a>',
        'type'   => e(ucfirst(str_replace('_',' ',$r['contract_type']))),
        'vendor' => e($r['vendor_name'] ?? '—'),
        'period' => e($r['start_date']) . ($r['end_date'] ? ' → ' . e($r['end_date']) : ''),
        'amount' => e(money($r['amount'] ?? 0)),
        'status' => '<span class="srp-badge-pill ' . ($statusMap[$r['status']] ?? 'badge-status-unavailable') . '"><span class="dot"></span>' . e(ucfirst($r['status'])) . '</span>',
        'actions' =>
            '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
            '<ul class="dropdown-menu dropdown-menu-end">' .
              '<li><a class="dropdown-item" href="' . APP_URL . '/modules/contracts/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
              '<li><a class="dropdown-item" href="' . APP_URL . '/modules/contracts/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' .
              '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/contracts/delete.php?id=' . (int)$r['id'] . '"><i data-lucide="trash-2"></i> ' . e(__('Delete')) . '</a></li>' .
            '</ul></div>',
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);