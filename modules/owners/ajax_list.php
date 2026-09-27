<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('owners.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

// Tenants never see the owners list.
if (has_role('Tenant', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search    = trim((string)($_GET['search']['value'] ?? ''));
$orderCol  = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir  = strtolower((string)($_GET['order'][0]['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
$fltStatus = trim((string)($_GET['status'] ?? ''));
$fltType   = trim((string)($_GET['owner_type'] ?? ''));

$columns = ['o.full_name', 'o.phone', 'properties_count', 'ownership_total', 'o.user_id', 'o.status'];
$orderBy = $columns[$orderCol] ?? 'o.full_name';

$where  = ['o.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltStatus !== '' && in_array($fltStatus, ['active','inactive'], true)) {
    $where[] = 'o.status = :st'; $params[':st'] = $fltStatus;
}
if ($fltType !== '' && in_array($fltType, ['individual','company'], true)) {
    $where[] = 'o.owner_type = :ot'; $params[':ot'] = $fltType;
}
if ($search !== '') {
    $where[] = '(o.full_name LIKE :s OR o.phone LIKE :s OR o.email LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}

// Owner portal: an owner-user sees only their own record.
if (has_role('Property Owner', $USER)) {
    $where[] = 'o.user_id = :uid';
    $params[':uid'] = (int)$USER['id'];
}

$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM owners WHERE company_id = :cid', [':cid' => (int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one("SELECT COUNT(*) AS c FROM owners o WHERE $w", $params)['c'] ?? 0);

$rows = db_all(
    "SELECT o.id, o.full_name, o.owner_type, o.phone, o.email, o.user_id, o.status,
            (SELECT COUNT(*) FROM owner_properties op WHERE op.owner_id = o.id) AS properties_count,
            (SELECT COALESCE(SUM(op.ownership_percentage), 0) FROM owner_properties op WHERE op.owner_id = o.id) AS ownership_total
       FROM owners o
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
            '<li><a class="dropdown-item" href="' . APP_URL . '/modules/owners/view.php?id=' . (int)$r['id'] . '">' .
              '<i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
            (can('owners.edit') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/owners/edit.php?id=' . (int)$r['id'] . '">' .
              '<i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' : '') .
            (can('owners.edit') ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/owner-statements/generate.php?owner_id=' . (int)$r['id'] . '">' .
              '<i data-lucide="scroll-text"></i> ' . e(__('Generate Statement')) . '</a></li>' : '') .
            (can('owners.delete') ? '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/owners/delete.php?id=' . (int)$r['id'] . '">' .
              '<i data-lucide="trash-2"></i> ' . e(__('Archive')) . '</a></li>' : '') .
          '</ul>' .
        '</div>';

    $contact = e($r['phone'] ?: '—');
    if (!empty($r['email'])) $contact .= '<div class="text-muted small">' . e($r['email']) . '</div>';

    $portal = $r['user_id']
        ? '<span class="srp-badge-pill badge-status-available"><span class="dot"></span>' . e(__('Linked')) . '</span>'
        : '<span class="srp-badge-pill badge-status-unavailable"><span class="dot"></span>' . e(__('None')) . '</span>';

    $data[] = [
        'owner'      => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/owners/view.php?id=' . (int)$r['id'] . '">' . e($r['full_name']) . '</a>' .
                        '<div class="text-muted small">' . e(ucfirst($r['owner_type'])) . '</div>',
        'contact'    => $contact,
        'properties' => (int)$r['properties_count'],
        'ownership'  => number_format((float)$r['ownership_total'], 1) . '%',
        'portal'     => $portal,
        'status'     => '<span class="srp-badge-pill ' . ($r['status'] === 'active' ? 'badge-status-available' : 'badge-status-unavailable') . '">' .
                          '<span class="dot"></span>' . e(ucfirst($r['status'])) . '</span>',
        'actions'    => $actions,
    ];
}

echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);