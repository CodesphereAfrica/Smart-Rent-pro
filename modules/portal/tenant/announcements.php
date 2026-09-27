<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
$USER = require_role('Tenant');
$tenant = db_one('SELECT id FROM tenants WHERE user_id = :u LIMIT 1', [':u'=>(int)$USER['id']]);
$tid = $tenant ? (int)$tenant['id'] : 0;

$rows = db_all(
    'SELECT a.* FROM announcements a
      WHERE a.company_id = :c
        AND a.audience IN ("all","tenants")
        AND (a.published_at IS NULL OR a.published_at <= NOW())
        AND (a.expires_at IS NULL OR a.expires_at >= NOW())
        AND (
          a.property_id IS NULL
          OR a.property_id IN (SELECT property_id FROM leases WHERE tenant_id = :t)
        )
      ORDER BY a.published_at DESC LIMIT 100',
    [':c'=>(int)$USER['company_id'], ':t'=>$tid]
);

$PAGE_TITLE = __('Announcements');
require __DIR__ . '/../../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Announcements')) ?></h1>
<?php if (!$rows): ?><div class="alert alert-info"><?= e(__('No announcements.')) ?></div>
<?php else: foreach ($rows as $a): ?>
  <div class="srp-card p-4 mb-3">
    <h2 class="h6 mb-1"><?= e($a['title']) ?></h2>
    <div class="text-muted small mb-2"><?= e($a['published_at'] ? date('Y-m-d H:i', strtotime($a['published_at'])) : '') ?></div>
    <div><?= nl2br(e($a['body'])) ?></div>
  </div>
<?php endforeach; endif; ?>
<?php require __DIR__ . '/../../../includes/footer.php'; ?>