<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('communications.view');
$id = (int)($_GET['id'] ?? 0);
$c = db_one(
    'SELECT c.*, CONCAT(us.first_name," ",us.last_name) AS sender
       FROM communications c LEFT JOIN users us ON us.id = c.sent_by
      WHERE c.id = :id AND c.company_id = :co LIMIT 1',
    [':id'=>$id, ':co'=>(int)$USER['company_id']]
);
if (!$c) { flash_error(__('Not found.')); redirect('modules/communications/index.php'); }

$PAGE_TITLE = __('Message') . ' #' . $id;
require __DIR__ . '/../../includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb small">
  <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/communications/index.php"><?= e(__('Communications')) ?></a></li>
  <li class="breadcrumb-item active">#<?= (int)$id ?></li>
</ol></nav>

<div class="srp-card p-4">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h1 class="h5 mb-1"><?= e($c['subject'] ?: __('(no subject)')) ?></h1>
      <div class="text-muted small">
        <?= e(ucfirst(str_replace('_',' ',$c['channel']))) ?> · <?= e(ucfirst($c['direction'])) ?> ·
        <?= e($c['recipient_name'] ?: '—') ?> &lt;<?= e($c['recipient_contact'] ?? '') ?>&gt;
      </div>
    </div>
    <span class="srp-badge-pill <?= $c['status']==='sent'||$c['status']==='delivered'?'badge-status-available':($c['status']==='failed'?'badge-status-under_maintenance':'badge-status-reserved') ?>">
      <span class="dot"></span><?= e(ucfirst($c['status'])) ?>
    </span>
  </div>

  <?php if ($c['error_message']): ?>
    <div class="alert alert-danger small"><?= e($c['error_message']) ?></div>
  <?php endif; ?>

  <div class="border-top pt-3" style="white-space: pre-wrap;"><?= e($c['body']) ?></div>

  <div class="text-muted small mt-4">
    <?= e(__('Sent by')) ?> <?= e($c['sender'] ?? '—') ?>
    <?php if ($c['sent_at']): ?> · <?= e($c['sent_at']) ?><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>