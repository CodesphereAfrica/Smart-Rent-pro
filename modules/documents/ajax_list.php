<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('documents.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

if (($_GET['meta'] ?? '') === 'categories') {
    $rows = db_all('SELECT id, name FROM document_categories WHERE company_id = :c ORDER BY name', [':c'=>(int)$USER['company_id']]);
    echo json_encode(['categories'=>$rows]); exit;
}

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search    = trim((string)($_GET['search']['value'] ?? ''));
$orderCol  = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir  = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$fltCat    = (int)($_GET['category_id'] ?? 0);
$fltRel    = trim((string)($_GET['related_type'] ?? ''));
$fltExpiry = trim((string)($_GET['expiry'] ?? ''));

$columns = ['d.document_name','dc.name','d.related_type','d.file_size_kb','d.version','d.expiry_date','d.uploaded_at'];
$orderBy = $columns[$orderCol] ?? 'd.uploaded_at';

$where  = ['d.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltCat) { $where[] = 'd.category_id = :cat'; $params[':cat'] = $fltCat; }
if ($fltRel !== '') { $where[] = 'd.related_type = :rt'; $params[':rt'] = $fltRel; }
if ($fltExpiry === 'expired') $where[] = 'd.expiry_date IS NOT NULL AND d.expiry_date < CURDATE()';
elseif (in_array($fltExpiry, ['30','60'], true)) $where[] = 'd.expiry_date IS NOT NULL AND DATEDIFF(d.expiry_date, CURDATE()) BETWEEN 0 AND ' . (int)$fltExpiry;

if ($search !== '') {
    $where[] = '(d.document_name LIKE :s OR d.notes LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
if (has_role('Property Owner', $USER)) {
    // Owners see docs tied to their properties, units, leases and to themselves
    $where[] = "(
        (d.related_type IN ('property','unit','lease','maintenance','inspection') AND d.related_id IN (
            SELECT p.id FROM properties p JOIN owner_properties op ON op.property_id = p.id JOIN owners o ON o.id = op.owner_id WHERE o.user_id = :uid
            UNION
            SELECT u.id FROM units u JOIN owner_properties op ON op.property_id = u.property_id JOIN owners o ON o.id = op.owner_id WHERE o.user_id = :uid
            UNION
            SELECT l.id FROM leases l JOIN owner_properties op ON op.property_id = l.property_id JOIN owners o ON o.id = op.owner_id WHERE o.user_id = :uid
        )) OR
        (d.related_type = 'owner' AND d.related_id IN (SELECT id FROM owners WHERE user_id = :uid))
    )";
    $params[':uid'] = (int)$USER['id'];
}
if (has_role('Tenant', $USER)) {
    $where[] = "(
        (d.related_type = 'tenant' AND d.related_id IN (SELECT id FROM tenants WHERE user_id = :tuid)) OR
        (d.related_type IN ('lease','unit','property') AND d.related_id IN (
            SELECT l.id FROM leases l JOIN tenants t ON t.id = l.tenant_id WHERE t.user_id = :tuid
            UNION
            SELECT l.unit_id FROM leases l JOIN tenants t ON t.id = l.tenant_id WHERE t.user_id = :tuid
            UNION
            SELECT l.property_id FROM leases l JOIN tenants t ON t.id = l.tenant_id WHERE t.user_id = :tuid
        ))
    )";
    $params[':tuid'] = (int)$USER['id'];
}

$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM documents WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one("SELECT COUNT(*) AS c FROM documents d LEFT JOIN document_categories dc ON dc.id = d.category_id WHERE $w", $params)['c'] ?? 0);

$rows = db_all(
    "SELECT d.*, dc.name AS category_name
       FROM documents d
       LEFT JOIN document_categories dc ON dc.id = d.category_id
      WHERE $w ORDER BY $orderBy $orderDir LIMIT $start, $length",
    $params
);

$data = [];
foreach ($rows as $r) {
    $expiryClass = '';
    if ($r['expiry_date']) {
        $days = (int)((strtotime($r['expiry_date']) - time()) / 86400);
        if ($days < 0)      $expiryClass = 'text-danger fw-semibold';
        elseif ($days <= 30) $expiryClass = 'text-warning fw-semibold';
    }

    $actions =
        '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
        '<ul class="dropdown-menu dropdown-menu-end">' .
          '<li><a class="dropdown-item" href="' . e(UPLOAD_URL . '/' . $r['file_path']) . '" target="_blank"><i data-lucide="download"></i> ' . e(__('Download')) . '</a></li>' .
          '<li><a class="dropdown-item" href="' . APP_URL . '/modules/documents/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
          (can('documents.delete') ? '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="' . APP_URL . '/modules/documents/delete.php?id=' . (int)$r['id'] . '"><i data-lucide="trash-2"></i> ' . e(__('Delete')) . '</a></li>' : '') .
        '</ul></div>';

    $data[] = [
        'name'     => '<a class="text-decoration-none fw-semibold" href="' . APP_URL . '/modules/documents/view.php?id=' . (int)$r['id'] . '">' . e($r['document_name']) . '</a>',
        'category' => e($r['category_name'] ?? '—'),
        'related'  => e(ucfirst(str_replace('_',' ',$r['related_type']))) . ' #' . (int)$r['related_id'],
        'size'     => $r['file_size_kb'] ? number_format((int)$r['file_size_kb']) . ' KB' : '—',
        'version'  => 'v' . (int)$r['version'],
        'expiry'   => $r['expiry_date'] ? '<span class="' . $expiryClass . '">' . e($r['expiry_date']) . '</span>' : '—',
        'uploaded' => e(date('Y-m-d', strtotime($r['uploaded_at']))),
        'actions'  => $actions,
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);