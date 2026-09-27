<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');
$USER = require_login();
if (!can('expenses.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

if (($_GET['meta'] ?? '') === 'filters') {
    $cats = db_all('SELECT id, name FROM expense_categories WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
    $props = db_all('SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name', [':c'=>(int)$USER['company_id']]);
    echo json_encode(['categories'=>$cats,'properties'=>$props]); exit;
}

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search   = trim((string)($_GET['search']['value'] ?? ''));
$orderCol = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$fltStat  = trim((string)($_GET['status'] ?? ''));
$fltCat   = (int)($_GET['category_id'] ?? 0);
$fltProp  = (int)($_GET['property_id'] ?? 0);

$columns = ['e.expense_date','e.description','cat.name','property_name','vendor_name','e.amount','e.payment_status'];
$orderBy = $columns[$orderCol] ?? 'e.expense_date';

$where  = ['e.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];
if ($fltStat !== '' && in_array($fltStat, ['unpaid','paid','partially_paid'], true)) {
    $where[] = 'e.payment_status = :st'; $params[':st'] = $fltStat;
}
if ($fltCat) { $where[] = 'e.category_id = :cat'; $params[':cat'] = $fltCat; }
if ($fltProp) { $where[] = 'e.property_id = :pid'; $params[':pid'] = $fltProp; }
if ($search !== '') {
    $where[] = '(e.description LIKE :s OR v.name LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
if (has_role('Property Owner', $USER)) {
    $where[] = 'e.property_id IN (SELECT property_id FROM owner_properties WHERE owner_id IN (SELECT id FROM owners WHERE user_id = :uid))';
    $params[':uid'] = (int)$USER['id'];
}
$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM expenses WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c FROM expenses e
       LEFT JOIN expense_categories cat ON cat.id = e.category_id
       LEFT JOIN properties p ON p.id = e.property_id
       LEFT JOIN vendors v ON v.id = e.vendor_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT e.id, e.expense_date, e.description, e.amount, e.payment_status,
            cat.name AS category_name,
            p.name AS property_name, u.unit_number,
            v.name AS vendor_name
       FROM expenses e
       LEFT JOIN expense_categories cat ON cat.id = e.category_id
       LEFT JOIN properties p ON p.id = e.property_id
       LEFT JOIN units u ON u.id = e.unit_id
       LEFT JOIN vendors v ON v.id = e.vendor_id
      WHERE $w ORDER BY $orderBy $orderDir LIMIT $start, $length",
    $params
);

$data = [];
foreach ($rows as $r) {
    $statusMap = ['unpaid'=>'badge-status-under_maintenance','partially_paid'=>'badge-status-reserved','paid'=>'badge-status-available'];
    $data[] = [
        'date'     => e($r['expense_date']),
        'desc'     => '<a class="text-decoration-none fw-semibold" href="' . APP_URL . '/modules/expenses/view.php?id=' . (int)$r['id'] . '">' . e($r['description']) . '</a>',
        'category' => e($r['category_name'] ?? '—'),
        'property' => e($r['property_name'] ?? '—') . ($r['unit_number'] ? ' · ' . e($r['unit_number']) : ''),
        'vendor'   => e($r['vendor_name'] ?? '—'),
        'amount'   => e(money($r['amount'])),
        'status'   => '<span class="srp-badge-pill ' . ($statusMap[$r['payment_status']] ?? 'badge-status-unavailable') . '"><span class="dot"></span>' . e(ucfirst(str_replace('_',' ',$r['payment_status']))) . '</span>',
        'actions'  =>
            '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
            '<ul class="dropdown-menu dropdown-menu-end">' .
              '<li><a class="dropdown-item" href="' . APP_URL . '/modules/expenses/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
              (can('expenses.edit') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/expenses/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' : '') .
            '</ul></div>',
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);