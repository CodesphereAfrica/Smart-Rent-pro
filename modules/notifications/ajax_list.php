<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();

$scope = sanitize_string($_GET['scope'] ?? 'full', 20);
$limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));

$rows = db_all(
    'SELECT id, type, title, message, related_type, related_id, is_read, created_at
       FROM notifications
      WHERE user_id = :u
      ORDER BY id DESC
      LIMIT ' . $limit,
    [':u' => (int)$USER['id']]
);

$unread = (int)(db_one('SELECT COUNT(*) AS c FROM notifications WHERE user_id = :u AND is_read = 0', [':u'=>(int)$USER['id']])['c'] ?? 0);

$items = [];
foreach ($rows as $r) {
    $link = '#';
    if ($r['related_type'] && $r['related_id']) {
        $link = APP_URL . '/modules/' . str_replace('_', '-', $r['related_type']) . '/view.php?id=' . (int)$r['related_id'];
    } elseif ($r['type'] === 'announcement') {
        $link = APP_URL . '/modules/announcements/index.php';
    }
    $items[] = [
        'id' => (int)$r['id'],
        'title' => e($r['title']),
        'message' => e($r['message']),
        'link' => $link,
        'is_read' => (int)$r['is_read'],
        'ago' => human_time_diff($r['created_at']),
    ];
}

if ($scope === 'bell') {
    echo json_encode(['unread' => $unread, 'items' => $items]);
    exit;
}

echo json_encode(['unread' => $unread, 'items' => $items, 'total' => count($items)]);