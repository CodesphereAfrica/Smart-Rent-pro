<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('tenants.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search   = trim((string)($_GET['search']['value'] ?? ''));
$orderCol = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$fltStage = trim((string)($_GET['stage'] ?? ''));

$columns = ['a.last_name','a.phone','unit_number','a.stage','a.created_at'];
$orderBy = $columns[$orderCol] ?? 'a.created_at';

$where  = ['a.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];
if ($fltStage !== '' && in_array($fltStage, ['lead','applied','screening','approved','rejected','converted'], true)) {
    $where[] = 'a.stage = :s'; $params[':s'] = $fltStage;
}
if ($search !== '') {
    $where[] = '(a.first_name LIKE :s OR a.last_name LIKE :s OR a.phone LIKE :s OR a.email LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM applicants WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one(
    "SELECT COUNT(*) AS c FROM applicants a
       LEFT JOIN units u ON u.id = a.unit_id
      WHERE $w",
    $params
)['c'] ?? 0);

$rows = db_all(
    "SELECT a.id, a.first_name, a.last_name, a.phone, a.email, a.stage, a.monthly_income, a.created_at,
            u.unit_number, u.id AS unit_id
       FROM applicants a
       LEFT JOIN units u ON u.id = a.unit_id
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$stageMap = [
    'lead'      => 'badge-status-notice_given',
    'applied'   => 'badge-status-reserved',
    'screening' => 'badge-status-under_maintenance',
    'approved'  => 'badge-status-available',
    'rejected'  => 'badge-status-under_maintenance',
    'converted' => 'badge-status-occupied',
];

$data = [];
foreach ($rows as $r) {
    $actions =
        '<div class="dropdown">' .
          '<button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="' . e(__('Actions')) . '">' .
            '<i data-lucide="more-vertical"></i>' .
          '</button>' .
          '<ul class="dropdown-menu dropdown-menu-end">' .
            '<li><a class="dropdown-item" href="' . APP_URL . '/modules/applicants/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
            '<li><a class="dropdown-item" href="' . APP_URL . '/modules/applicants/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' .
            ($r['stage'] === 'approved'
              ? '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-success" href="' . APP_URL . '/modules/applicants/convert.php?id=' . (int)$r['id'] . '"><i data-lucide="user-check"></i> ' . e(__('Convert to Tenant')) . '</a></li>'
              : '') .
            '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/applicants/delete.php?id=' . (int)$r['id'] . '"><i data-lucide="trash-2"></i> ' . e(__('Delete')) . '</a></li>' .
          '</ul>' .
        '</div>';

    $unitCell = $r['unit_number']
        ? '<a class="text-decoration-none" href="' . APP_URL . '/modules/units/view.php?id=' . (int)$r['unit_id'] . '">' . e($r['unit_number']) . '</a>'
        : '<span class="text-muted">—</span>';

    $data[] = [
        'applicant' => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/applicants/view.php?id=' . (int)$r['id'] . '">' . e($r['first_name'] . ' ' . $r['last_name']) . '</a>',
        'contact'   => e($r['phone'] ?: '—') . (!empty($r['email']) ? '<div class="text-muted small">' . e($r['email']) . '</div>' : ''),
        'unit'      => $unitCell,
        'stage'     => '<span class="srp-badge-pill ' . ($stageMap[$r['stage']] ?? 'badge-status-unavailable') . '"><span class="dot"></span>' . e(ucfirst($r['stage'])) . '</span>',
        'created'   => e(date('Y-m-d', strtotime($r['created_at']))),
        'actions'   => $actions,
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);