<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_login();

$PAGE_TITLE = __('Notifications');
$PAGE_SCRIPTS = ['notifications.js'];
require __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0"><?= e(__('Notifications')) ?></h1>
  <button class="btn btn-light" id="markAllRead"><i data-lucide="check-check"></i> <?= e(__('Mark all as read')) ?></button>
</div>

<div class="srp-card p-3" id="srpNotifFullList">
  <div class="srp-skeleton-row" style="height:48px"></div>
  <div class="srp-skeleton-row" style="height:48px;width:80%"></div>
  <div class="srp-skeleton-row" style="height:48px;width:60%"></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>