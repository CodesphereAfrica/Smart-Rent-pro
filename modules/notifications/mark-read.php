<?php
// modules/notifications/mark-read.php — self-contained: mark one/all as read.
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();
require_csrf();

$id = (int)($_POST['id'] ?? 0);
$all = !empty($_POST['all']);

if ($all) {
    db_query('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = :u AND is_read = 0', [':u'=>(int)$USER['id']]);
} elseif ($id) {
    db_query('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id AND user_id = :u', [':id'=>$id, ':u'=>(int)$USER['id']]);
}

echo json_encode(['ok' => true]);