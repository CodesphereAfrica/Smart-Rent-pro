<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('invoices.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search    = trim((string)($_GET['search']['value'] ?? ''));
$orderCol  = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir  = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$fltStatus = trim((string)($_GET['status'] ?? ''));
$fltType   = trim((string)($_GET['type'] ?? ''));
$fltPeriod = trim((string)($_GET['period'] ?? ''));

$columns = ['i.invoice_number','tenant_name','unit_number','i.invoice_type','i.amount','i.balance','i.due_date','i.status'];
$orderBy = $columns[$orderCol] ?? 'i.due_date';

$where  = ['i.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltStatus !== '' && in_array($fltStatus, ['upcoming','due','partially_paid','paid','overdue','written_off'], true)) {
    $where[] = 'i.status = :st'; $params[':st'] = $fltStatus;
}
if ($fltType !== '' && in_array($fltType, ['rent','late_fee','utility','deposit','other'], true)) {
    $where[] = 'i.invoice_type = :tp'; $params[':tp'] = $fltType;
}
if ($fltPeriod !== '' && preg_match('/^\d{4}-\d{2}$/', $fltPeriod)) {
    $where[] = 'DATE_FORMAT(i.period_start, "%Y-%m") = :pp';
    $params[':pp'] = $fltPeriod;
}
if ($search !== '') {
    $where[] = '(i.invoice_number LIKE :s OR t.first_name LIKE :s OR t.last_name LIKE :s OR u.unit_number LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}

// Owner portal restriction
if (has_role('Property Owner', $USER)) {
    $where[] = 'i.unit_id IN (SELECT u.id FROM units u JOIN owner_properties op ON op.property_id = u.property_id JOIN owners o ON o.id = op.owner_id WHERE o.user_id = :uid)';
    $params[':uid'] = (int)$USER['id'];
}

$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM invoices WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c
       FROM invoices i
       JOIN tenants t ON t.id = i.tenant_id
       JOIN units   u ON u.id = i.unit_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT i.id, i.invoice_number, i.invoice_type, i.amount, i.amount_paid, i.balance,
            i.due_date, i.status,
            CONCAT(t.first_name,' ',t.last_name) AS tenant_name,
            u.unit_number
       FROM invoices i
       JOIN tenants t ON t.id = i.tenant_id
       JOIN units   u ON u.id = i.unit_id
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$statusMap = [
    'upcoming'       => 'badge-status-reserved',
    'due'            => 'badge-status-notice_given',
    'partially_paid' => 'badge-status-reserved',
    'paid'           => 'badge-status-available',
    'overdue'        => 'badge-status-under_maintenance',
    'written_off'    => 'badge-status-unavailable',
];

$data = [];
foreach ($rows as $r) {
    $actions =
        '<div class="dropdown">' .
          '<button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
          '<ul class="dropdown-menu dropdown-menu-end">' .
            '<li><a class="dropdown-item" href="' . APP_URL . '/modules/invoices/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
            (can('payments.create') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/payments/create.php?invoice_id=' . (int)$r['id'] . '"><i data-lucide="banknote"></i> ' . e(__('Record Payment')) . '</a></li>' : '') .
            '<li><a class="dropdown-item" href="' . APP_URL . '/modules/invoices/view.php?id=' . (int)$r['id'] . '&pdf=1" target="_blank"><i data-lucide="file-text"></i> ' . e(__('PDF')) . '</a></li>' .
            (can('invoices.void') ? '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/invoices/delete.php?id=' . (int)$r['id'] . '"><i data-lucide="x-circle"></i> ' . e(__('Void')) . '</a></li>' : '') .
          '</ul>' .
        '</div>';

    $data[] = [
        'invoice' => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/invoices/view.php?id=' . (int)$r['id'] . '">' . e($r['invoice_number']) . '</a>',
        'tenant'  => e($r['tenant_name']),
        'unit'    => e($r['unit_number']),
        'type'    => e(ucfirst(str_replace('_',' ',$r['invoice_type']))),
        'amount'  => e(money($r['amount'])),
        'balance' => '<span class="' . ((float)$r['balance'] > 0 ? 'text-danger fw-semibold' : 'text-muted') . '">' . e(money($r['balance'])) . '</span>',
        'due'     => e($r['due_date']),
        'status'  => '<span class="srp-badge-pill ' . ($statusMap[$r['status']] ?? 'badge-status-unavailable') . '"><span class="dot"></span>' . e(ucfirst(str_replace('_',' ',$r['status']))) . '</span>',
        'actions' => $actions,
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);