<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('audit_logs.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

if (($_GET['meta'] ?? '') === 'modules') {
    $rows = db_all('SELECT DISTINCT module FROM audit_logs WHERE company_id = :c ORDER BY module', [':c'=>(int)$USER['company_id']]);
    echo json_encode(['modules'=>array_column($rows,'module')]); exit;
}

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search   = trim((string)($_GET['search']['value'] ?? ''));
$orderCol = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$fltMod   = trim((string)($_GET['module'] ?? ''));
$fltAct   = trim((string)($_GET['action'] ?? ''));
$fltFrom  = trim((string)($_GET['from'] ?? ''));
$fltTo    = trim((string)($_GET['to'] ?? ''));

$columns = ['al.created_at','user_name','al.module','al.action','record_label','al.ip_address'];
$orderBy = $columns[$orderCol] ?? 'al.created_at';

$where  = ['al.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];
if ($fltMod !== '') { $where[] = 'al.module = :mo'; $params[':mo'] = $fltMod; }
if ($fltAct !== '') { $where[] = 'al.action = :ac'; $params[':ac'] = $fltAct; }
if ($fltFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fltFrom)) { $where[] = 'DATE(al.created_at) >= :df'; $params[':df'] = $fltFrom; }
if ($fltTo   !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fltTo))   { $where[] = 'DATE(al.created_at) <= :dt'; $params[':dt'] = $fltTo; }
if ($search !== '') {
    $where[] = '(al.module LIKE :s OR al.action LIKE :s OR u.first_name LIKE :s OR u.last_name LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM audit_logs WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one("SELECT COUNT(*) AS c FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id WHERE $w", $params)['c'] ?? 0);

$rows = db_all(
    "SELECT al.id, al.created_at, al.module, al.action, al.record_type, al.record_id, al.ip_address,
            al.old_values, al.new_values,
            CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS user_name
       FROM audit_logs al
       LEFT JOIN users u ON u.id = al.user_id
      WHERE $w ORDER BY $orderBy $orderDir LIMIT $start, $length",
    $params
);

$data = [];
foreach ($rows as $r) {
    $data[] = [
        'when' => e(date('Y-m-d H:i:s', strtotime($r['created_at']))),
        'user' => e(trim($r['user_name']) ?: '—'),
        'module' => e($r['module']),
        'action' => '<span class="srp-badge-pill badge-status-reserved"><span class="dot"></span>' . e($r['action']) . '</span>',
        'record' => $r['record_type'] ? e($r['record_type']) . ' #' . (int)$r['record_id'] : '—',
        'ip' => e($r['ip_address'] ?? '—'),
        'actions' => '<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#auditModal" ' .
                     'data-old="' . e(json_encode($r['old_values'])) . '" ' .
                     'data-new="' . e(json_encode($r['new_values'])) . '"><i data-lucide="eye"></i></button>',
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);