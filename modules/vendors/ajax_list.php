<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('vendors.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search    = trim((string)($_GET['search']['value'] ?? ''));
$orderCol  = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir  = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$fltStatus = trim((string)($_GET['status'] ?? ''));
$fltCat    = trim((string)($_GET['category'] ?? ''));

$columns = ['v.name','v.category','v.phone','v.rating','v.jobs_completed','v.avg_response_minutes','v.total_paid','v.status'];
$orderBy = $columns[$orderCol] ?? 'v.name';

$where  = ['v.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltStatus !== '' && in_array($fltStatus, ['active','inactive'], true)) {
    $where[] = 'v.status = :st'; $params[':st'] = $fltStatus;
}
if ($fltCat !== '') {
    $where[] = 'v.category LIKE :cat'; $params[':cat'] = '%' . $fltCat . '%';
}
if ($search !== '') {
    $where[] = '(v.name LIKE :s OR v.category LIKE :s OR v.phone LIKE :s OR v.email LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM vendors WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one("SELECT COUNT(*) AS c FROM vendors v WHERE $w", $params)['c'] ?? 0);

$rows = db_all(
    "SELECT v.id, v.name, v.vendor_type, v.category, v.phone, v.email, v.rating,
            v.jobs_completed, v.avg_response_minutes, v.avg_completion_hours, v.total_paid, v.status
       FROM vendors v
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$data = [];
foreach ($rows as $r) {
    $actions =
        '<div class="dropdown">' .
          '<button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="' . e(__('Actions')) . '">' .
            '<i data-lucide="more-vertical"></i>' .
          '</button>' .
          '<ul class="dropdown-menu dropdown-menu-end">' .
            '<li><a class="dropdown-item" href="' . APP_URL . '/modules/vendors/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
            (can('vendors.edit') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/vendors/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' : '') .
            (can('vendors.delete') ? '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/vendors/delete.php?id=' . (int)$r['id'] . '"><i data-lucide="trash-2"></i> ' . e(__('Remove')) . '</a></li>' : '') .
          '</ul>' .
        '</div>';

    $rating = $r['rating'] !== null
        ? '<div class="d-flex align-items-center gap-1"><i data-lucide="star" style="width:13px;height:13px;color:#f59e0b"></i>' . number_format((float)$r['rating'], 1) . '</div>'
        : '<span class="text-muted">—</span>';

    $avgResp = $r['avg_response_minutes']
        ? (int)$r['avg_response_minutes'] . ' min'
        : '—';

    $contact = e($r['phone'] ?: '—');
    if (!empty($r['email'])) $contact .= '<div class="text-muted small">' . e($r['email']) . '</div>';

    $data[] = [
        'vendor'    => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/vendors/view.php?id=' . (int)$r['id'] . '">' . e($r['name']) . '</a>',
        'category'  => e($r['category']),
        'contact'   => $contact,
        'rating'    => $rating,
        'jobs'      => (int)$r['jobs_completed'],
        'avg_resp'  => $avgResp,
        'paid'      => e(money($r['total_paid'])),
        'status'    => '<span class="srp-badge-pill ' . ($r['status']==='active'?'badge-status-available':'badge-status-unavailable') . '"><span class="dot"></span>' . e(ucfirst($r['status'])) . '</span>',
        'actions'   => $actions,
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);