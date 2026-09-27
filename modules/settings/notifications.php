<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$USER = require_permission('settings.manage');

$events = [
    'rent_due' => 'Rent Due Reminder',
    'rent_overdue' => 'Rent Overdue',
    'payment_received' => 'Payment Received',
    'lease_expiring' => 'Lease Expiring',
    'maintenance_assigned' => 'Maintenance Assigned',
    'maintenance_completed' => 'Maintenance Completed',
    'approval_request' => 'Approval Request',
    'document_expiring' => 'Document Expiring',
];

$templates = db_all('SELECT * FROM notification_templates WHERE company_id = :c ORDER BY event_code, channel', [':c'=>(int)$USER['company_id']]);
$byEvent = [];
foreach ($templates as $t) $byEvent[$t['event_code']][$t['channel']] = $t;

$PAGE_TITLE = __('Notification Bindings');
require __DIR__ . '/../../includes/header.php';
?>
<h1 class="h4 mb-3"><?= e(__('Notification Bindings')) ?></h1>
<p class="text-muted small"><?= e(__('Each event code can be bound to a template per channel. Bindings drive the cron dispatchers and in-app notification fan-out.')) ?></p>

<div class="srp-card p-3">
  <table class="table table-hover align-middle">
    <thead><tr>
      <th><?= e(__('Event')) ?></th>
      <th><?= e(__('Email')) ?></th>
      <th><?= e(__('SMS')) ?></th>
      <th><?= e(__('In-App')) ?></th>
    </tr></thead>
    <tbody>
      <?php foreach ($events as $code => $label): ?>
        <tr>
          <td><code><?= e($code) ?></code><div class="text-muted small"><?= e($label) ?></div></td>
          <?php foreach (['email','sms','in_app'] as $ch):
            $tpl = $byEvent[$code][$ch] ?? null;
          ?>
            <td><?= $tpl ? '<a href="' . APP_URL . '/modules/templates/edit.php?id=' . (int)$tpl['id'] . '">' . e($tpl['subject'] ?: $tpl['event_code']) . '</a>' : '<span class="text-muted">—</span>' ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>