<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('properties.edit');
$id = (int)($_GET['id'] ?? 0);
$l = db_one('SELECT * FROM listings WHERE id = :id AND company_id = :c LIMIT 1', [':id'=>$id, ':c'=>(int)$USER['company_id']]);
if (!$l) { flash_error(__('Not found.')); redirect('modules/listings/index.php'); }

$next = $l['is_published'] ? 0 : 1;
db_update('listings', [
    'is_published' => $next,
    'published_at' => $next ? date('Y-m-d H:i:s') : $l['published_at'],
], 'id = :id', [':id' => $id]);

log_audit('listings', $next ? 'publish' : 'unpublish', 'listings', $id, $l, ['is_published'=>$next]);
flash_success($next ? __('Listing published.') : __('Listing unpublished.'));
redirect('modules/listings/index.php');