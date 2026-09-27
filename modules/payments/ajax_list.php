<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('payments.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search   = trim((string)($_GET['search']['value'] ?? ''));
$orderCol = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$fltMethod= trim((string)($_GET['method'] ?? ''));
$fltStatus= trim((string)($_GET['status'] ?? ''));
$fltFrom  = trim((string)($_GET['date_from'] ?? ''));
$fltTo    = trim((string)($_GET['date_to'] ?? ''));

$columns = ['p.payment_date','tenant_name','unit_number','p.amount','p.amount_unallocated','p.payment_method','p.payment_reference','p.status'];
$orderBy = $columns[$orderCol] ?? 'p.payment_date';

$where  = ['p.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltMethod !== '' && in_array($fltMethod, ['cash','bank_transfer','mobile_money','card','cheque','other'], true)) {
    $where[] = 'p.payment_method = :m'; $params[':m'] = $fltMethod;
}
if ($fltStatus !== '' && in_array($fltStatus, ['completed','pending','reversed'], true)) {
    $where[] = 'p.status = :st'; $params[':st'] = $fltStatus;
}
if ($fltFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fltFrom)) {
    $where[] = 'p.payment_date >= :df'; $params[':df'] = $fltFrom;
}
if ($fltTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fltTo)) {
    $where[] = 'p.payment_date <= :dt'; $params[':dt'] = $fltTo;
}
if ($search !== '') {
    $where[] = '(t.first_name LIKE :s OR t.last_name LIKE :s OR u.unit_number LIKE :s OR p.payment_reference LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}

if (has_role('Property Owner', $USER)) {
    $where[] = 'p.unit_id IN (SELECT u.id FROM units u JOIN owner_properties op ON op.property_id = u.property_id JOIN owners o ON o.id = op.owner_id WHERE o.user_id = :uid)';
    $params[':uid'] = (int)$USER['id'];
}

$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM payments WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c FROM payments p
       JOIN tenants t ON t.id = p.tenant_id
       LEFT JOIN units u ON u.id = p.unit_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT p.id, p.payment_date, p.amount, p.amount_unallocated, p.payment_method,
            p.payment_reference, p.status,
            CONCAT(t.first_name,' ',t.last_name) AS tenant_name,
            u.unit_number
       FROM payments p
       JOIN tenants t ON t.id = p.tenant_id
       LEFT JOIN units u ON u.id = p.unit_id
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$statusMap = [
    'completed' => 'badge-status-available',
    'pending'   => 'badge-status-reserved',
    'reversed'  => 'badge-status-under_maintenance',
];

$data = [];
foreach ($rows as $r) {
    $actions =
        '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
        '<ul class="dropdown-menu dropdown-menu-end">' .
          '<li><a class="dropdown-item" href="' . APP_URL . '/modules/payments/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
          '<li><a class="dropdown-item" href="' . APP_URL . '/modules/payments/receipt.php?id=' . (int)$r['id'] . '" target="_blank"><i data-lucide="file-text"></i> ' . e(__('Receipt')) . '</a></li>' .
          ((float)$r['amount_unallocated'] > 0 && can('payments.create')
            ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/payments/allocate.php?id=' . (int)$r['id'] . '"><i data-lucide="git-merge"></i> ' . e(__('Allocate')) . '</a></li>'
            : '') .
          ($r['status'] === 'completed' && can('payments.reverse')
            ? '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/payments/reverse.php?id=' . (int)$r['id'] . '"><i data-lucide="x-circle"></i> ' . e(__('Reverse')) . '</a></li>'
            : '') .
        '</ul></div>';

    $unallocCell = (float)$r['amount_unallocated'] > 0
        ? '<span class="text-warning fw-semibold">' . e(money($r['amount_unallocated'])) . '</span>'
        : '<span class="text-muted">—</span>';

    $data[] = [
        'date'      => e($r['payment_date']),
        'tenant'    => e($r['tenant_name']),
        'unit'      => e($r['unit_number'] ?? '—'),
        'amount'    => e(money($r['amount'])),
        'unalloc'   => $unallocCell,
        'method'    => e(ucfirst(str_replace('_',' ',$r['payment_method']))),
        'reference' => e($r['payment_reference'] ?? '—'),
        'status'    => '<span class="srp-badge-pill ' . ($statusMap[$r['status']] ?? 'badge-status-unavailable') . '"><span class="dot"></span>' . e(ucfirst($r['status'])) . '</span>',
        'actions'   => $actions,
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);