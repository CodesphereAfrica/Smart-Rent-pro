<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
if (!can('communications.view', $USER)) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$draw   = (int)($_GET['draw'] ?? 0);
$start  = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 25);
if ($length < 1 || $length > 200) $length = 25;

$search   = trim((string)($_GET['search']['value'] ?? ''));
$orderCol = (int)($_GET['order'][0]['column'] ?? 0);
$orderDir = strtolower((string)($_GET['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
$fltChan  = trim((string)($_GET['channel'] ?? ''));
$fltStat  = trim((string)($_GET['status'] ?? ''));
$fltDir   = trim((string)($_GET['direction'] ?? ''));

$columns = ['c.created_at','c.channel','c.recipient_name','c.subject','related_label','c.status'];
$orderBy = $columns[$orderCol] ?? 'c.created_at';

$where  = ['c.company_id = :cid'];
$params = [':cid' => (int)$USER['company_id']];

if ($fltChan !== '' && in_array($fltChan, ['email','sms','in_app','whatsapp'], true)) {
    $where[] = 'c.channel = :ch'; $params[':ch'] = $fltChan;
}
if ($fltStat !== '' && in_array($fltStat, ['queued','sent','delivered','failed','read'], true)) {
    $where[] = 'c.status = :st'; $params[':st'] = $fltStat;
}
if ($fltDir !== '' && in_array($fltDir, ['outbound','inbound'], true)) {
    $where[] = 'c.direction = :dr'; $params[':dr'] = $fltDir;
}
if ($search !== '') {
    $where[] = '(c.recipient_name LIKE :s OR c.recipient_contact LIKE :s OR c.subject LIKE :s OR c.body LIKE :s)';
    $params[':s'] = '%' . $search . '%';
}
$w = implode(' AND ', $where);

$total = (int)(db_one('SELECT COUNT(*) AS c FROM communications WHERE company_id = :cid', [':cid'=>(int)$USER['company_id']])['c'] ?? 0);
$filtered = (int)(db_one("SELECT COUNT(*) AS c FROM communications c WHERE $w", $params)['c'] ?? 0);

$rows = db_all(
    "SELECT c.id, c.channel, c.direction, c.recipient_name, c.recipient_contact,
            c.subject, c.body, c.status, c.related_type, c.related_id, c.created_at, c.sent_at,
            CASE c.related_type
                WHEN 'tenant' THEN 'Tenant'
                WHEN 'property' THEN 'Property'
                WHEN 'unit' THEN 'Unit'
                WHEN 'lease' THEN 'Lease'
                WHEN 'maintenance_request' THEN 'Maintenance'
                WHEN 'invoice' THEN 'Invoice'
                WHEN 'payment' THEN 'Payment'
                ELSE COALESCE(c.related_type, '—')
            END AS related_label
       FROM communications c
      WHERE $w
      ORDER BY $orderBy $orderDir
      LIMIT $start, $length",
    $params
);

$channelIcons = ['email'=>'mail','sms'=>'message-square','in_app'=>'bell','whatsapp'=>'phone'];
$statusMap = [
    'queued' => 'badge-status-reserved',
    'sent' => 'badge-status-notice_given',
    'delivered' => 'badge-status-available',
    'failed' => 'badge-status-under_maintenance',
    'read' => 'badge-status-occupied',
];

$data = [];
foreach ($rows as $r) {
    $relatedLink = $r['related_type'] && $r['related_id']
        ? APP_URL . '/modules/' . str_replace('_', '-', $r['related_type']) . '/view.php?id=' . (int)$r['related_id']
        : null;

    $actions =
        '<div class="dropdown"><button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i data-lucide="more-vertical"></i></button>' .
        '<ul class="dropdown-menu dropdown-menu-end">' .
          '<li><a class="dropdown-item" href="' . APP_URL . '/modules/communications/view.php?id=' . (int)$r['id'] . '"><i data-lucide="eye"></i> ' . e(__('View')) . '</a></li>' .
          ($r['status'] === 'failed' && can('communications.send')
            ? '<li><a class="dropdown-item" href="' . APP_URL . '/modules/communications/compose.php?resend=' . (int)$r['id'] . '"><i data-lucide="refresh-cw"></i> ' . e(__('Retry')) . '</a></li>'
            : '') .
        '</ul></div>';

    $data[] = [
        'date' => e(date('Y-m-d H:i', strtotime($r['created_at']))),
        'channel' => '<i data-lucide="' . e($channelIcons[$r['channel']] ?? 'message-square') . '"></i> ' . e(ucfirst(str_replace('_',' ',$r['channel']))),
        'recipient' => e($r['recipient_name'] ?: '—') . '<div class="text-muted small">' . e($r['recipient_contact'] ?? '') . '</div>',
        'subject' => e($r['subject'] ?: mb_substr(strip_tags((string)$r['body']), 0, 60)),
        'related' => $relatedLink
            ? '<a class="text-decoration-none" href="' . $relatedLink . '">' . e($r['related_label']) . ' #' . (int)$r['related_id'] . '</a>'
            : e($r['related_label']),
        'status' => '<span class="srp-badge-pill ' . ($statusMap[$r['status']] ?? 'badge-status-unavailable') . '"><span class="dot"></span>' . e(ucfirst($r['status'])) . '</span>',
        'actions' => $actions,
    ];
}
echo json_encode(['draw'=>$draw,'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'data'=>$data]);