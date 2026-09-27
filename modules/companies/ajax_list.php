<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!is_super_admin($USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search = trim((string)($_GET['search']['value'] ?? ''));
$where  = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(c.name LIKE :s OR c.slug LIKE :s OR c.email LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
$w = implode(' AND ', $where);

$total    = (int)(db_one('SELECT COUNT(*) AS c FROM companies')['c'] ?? 0);
$filtered = (int)(db_one("SELECT COUNT(*) AS c FROM companies c WHERE $w", $params)['c'] ?? 0);

$rows = db_all(
    "SELECT c.id, c.name, c.slug, c.base_currency_code, c.default_language, c.is_active, c.created_at,
            (SELECT COUNT(*) FROM properties p WHERE p.company_id = c.id AND p.deleted_at IS NULL) AS props,
            (SELECT COUNT(*) FROM units u WHERE u.company_id = c.id) AS units,
            (SELECT COUNT(*) FROM users  u WHERE u.company_id = c.id AND u.status='active') AS users
       FROM companies c
      WHERE $w
      ORDER BY c.id DESC
      LIMIT $start, $length",
    $params
);

$data = [];
foreach ($rows as $r) {
    $data[] = [
        'name'     => '<a class="fw-semibold text-decoration-none" href="' . APP_URL . '/modules/companies/view.php?id=' . (int)$r['id'] . '">' . e($r['name']) . '</a>',
        'slug'     => '<code>' . e($r['slug']) . '</code>',
        'currency' => e($r['base_currency_code']),
        'language' => strtoupper(e($r['default_language'])),
        'props'    => (int)$r['props'],
        'units'    => (int)$r['units'],
        'users'    => (int)$r['users'],
        'status'   => $r['is_active']
            ? '<span class="srp-badge-pill badge-status-available"><span class="dot"></span>' . e(__('Active')) . '</span>'
            : '<span class="srp-badge-pill badge-status-unavailable"><span class="dot"></span>' . e(__('Inactive')) . '</span>',
        'actions'  =>
            '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
            '<ul class="dropdown-menu dropdown-menu-end">' .
              '<li><a class="dropdown-item" href="' . APP_URL . '/modules/companies/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
              '<li><a class="dropdown-item" href="' . APP_URL . '/modules/companies/edit.php?id=' . (int)$r['id'] . '"><i data-lucide="pencil"></i> ' . e(__('Edit')) . '</a></li>' .
              '<li><hr class="dropdown-divider"></li>' .
              '<li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/companies/delete.php?id=' . (int)$r['id'] . '"><i data-lucide="trash-2"></i> ' . e(__('Delete')) . '</a></li>' .
            '</ul></div>',
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);