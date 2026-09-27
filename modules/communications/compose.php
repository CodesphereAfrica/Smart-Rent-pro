<?php
// modules/communications/compose.php — send email/SMS/in-app; writes to communications table
// and, when SMTP is configured, dispatches immediately (otherwise it queues for cron).
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use SmartRentPro\Sms\SmsManager;

$USER = require_permission('communications.send');

$presetRelatedType = sanitize_string($_GET['related_type'] ?? '', 60);
$presetRelatedId   = (int)($_GET['related_id'] ?? 0);
$presetTemplate    = sanitize_string($_GET['template'] ?? '', 80);
$resendId          = (int)($_GET['resend'] ?? 0);

$errors = [];
$old = [
    'channel' => 'email',
    'related_type' => $presetRelatedType,
    'related_id' => $presetRelatedId,
    'recipient_name' => '',
    'recipient_contact' => '',
    'subject' => '',
    'body' => '',
];

// Prefill from related entity
if ($presetRelatedType === 'tenant' && $presetRelatedId) {
    $t = db_one('SELECT first_name, last_name, phone, email FROM tenants WHERE id = :i AND company_id = :c', [':i'=>$presetRelatedId, ':c'=>(int)$USER['company_id']]);
    if ($t) {
        $old['recipient_name'] = $t['first_name'] . ' ' . $t['last_name'];
        $old['recipient_contact'] = $t['email'] ?: $t['phone'];
    }
}
if ($presetRelatedType === 'owner' && $presetRelatedId) {
    $o = db_one('SELECT full_name, phone, email FROM owners WHERE id = :i AND company_id = :c', [':i'=>$presetRelatedId, ':c'=>(int)$USER['company_id']]);
    if ($o) {
        $old['recipient_name'] = $o['full_name'];
        $old['recipient_contact'] = $o['email'] ?: $o['phone'];
    }
}

// Prefill from template
if ($presetTemplate) {
    $tpl = db_one(
        'SELECT subject, body FROM notification_templates
          WHERE company_id = :c AND event_code = :e AND channel = :ch AND is_active = 1 LIMIT 1',
        [':c'=>(int)$USER['company_id'], ':e'=>$presetTemplate, ':ch'=>$old['channel']]
    );
    if ($tpl) {
        $old['subject'] = (string)$tpl['subject'];
        $old['body']    = (string)$tpl['body'];
    }
}

// Resend prefill
if ($resendId) {
    $r = db_one('SELECT * FROM communications WHERE id = :i AND company_id = :c', [':i'=>$resendId, ':c'=>(int)$USER['company_id']]);
    if ($r) {
        $old['channel'] = $r['channel'];
        $old['related_type'] = $r['related_type'] ?? '';
        $old['related_id'] = (int)($r['related_id'] ?? 0);
        $old['recipient_name'] = $r['recipient_name'];
        $old['recipient_contact'] = $r['recipient_contact'];
        $old['subject'] = $r['subject'];
        $old['body'] = $r['body'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $old['channel']           = in_array($_POST['channel'] ?? '', ['email','sms','in_app','whatsapp'], true) ? $_POST['channel'] : 'email';
    $old['related_type']      = sanitize_string($_POST['related_type'] ?? '', 60) ?: null;
    $old['related_id']        = (int)($_POST['related_id'] ?? 0) ?: null;
    $old['recipient_name']    = sanitize_string($_POST['recipient_name'] ?? '', 150);
    $old['recipient_contact'] = sanitize_string($_POST['recipient_contact'] ?? '', 150);
    $old['subject']           = sanitize_string($_POST['subject'] ?? '', 255);
    $old['body']              = (string)($_POST['body'] ?? '');
    $sendNow                  = !empty($_POST['send_now']);

    if ($old['recipient_contact'] === '') $errors['recipient_contact'] = __('Recipient contact required.');
    if (trim($old['body']) === '') $errors['body'] = __('Body required.');
    if ($old['channel'] === 'email' && !filter_var($old['recipient_contact'], FILTER_VALIDATE_EMAIL)) {
        $errors['recipient_contact'] = __('Enter a valid email address.');
    }

    if (!$errors) {
        $commsId = db_insert('communications', [
            'company_id' => (int)$USER['company_id'],
            'channel' => $old['channel'],
            'direction' => 'outbound',
            'related_type' => $old['related_type'],
            'related_id' => $old['related_id'],
            'recipient_name' => $old['recipient_name'],
            'recipient_contact' => $old['recipient_contact'],
            'subject' => $old['subject'],
            'body' => $old['body'],
            'status' => 'queued',
            'sent_by' => (int)$USER['id'],
        ]);

        $sendResult = ['sent' => false, 'error' => null];

        if ($sendNow) {
            if ($old['channel'] === 'email') {
                if (SMTP_HOST === '') {
                    $sendResult['error'] = 'SMTP not configured';
                } else {
                    try {
                        $mail = new PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = SMTP_USER !== '';
                        $mail->Username = SMTP_USER;
                        $mail->Password = SMTP_PASS;
                        $mail->SMTPSecure = SMTP_SECURE;
                        $mail->Port = SMTP_PORT;
                        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
                        $mail->addAddress($old['recipient_contact'], $old['recipient_name']);
                        $mail->isHTML(true);
                        $mail->Subject = $old['subject'] ?: APP_NAME;
                        $mail->Body = $old['body'];
                        $mail->AltBody = strip_tags($old['body']);
                        $mail->send();
                        $sendResult['sent'] = true;
                    } catch (MailException $e) {
                        $sendResult['error'] = $e->getMessage();
                    }
                }
            } elseif ($old['channel'] === 'sms') {
                $sendResult = SmsManager::send($old['recipient_contact'], $old['body']);
            } elseif ($old['channel'] === 'in_app') {
                // For in-app, we must know the linked user id
                $targetUser = null;
                if ($old['related_type'] === 'tenant' && $old['related_id']) {
                    $targetUser = db_one('SELECT user_id FROM tenants WHERE id = :i', [':i'=>$old['related_id']])['user_id'] ?? null;
                } elseif ($old['related_type'] === 'owner' && $old['related_id']) {
                    $targetUser = db_one('SELECT user_id FROM owners WHERE id = :i', [':i'=>$old['related_id']])['user_id'] ?? null;
                }
                if ($targetUser) {
                    db_insert('notifications', [
                        'company_id' => (int)$USER['company_id'],
                        'user_id' => (int)$targetUser,
                        'type' => 'in_app_message',
                        'title' => $old['subject'] ?: __('Message'),
                        'message' => mb_substr($old['body'], 0, 500),
                        'related_type' => $old['related_type'],
                        'related_id' => $old['related_id'],
                    ]);
                    $sendResult['sent'] = true;
                } else {
                    $sendResult['error'] = 'No linked portal user for in-app delivery';
                }
            } else {
                // WhatsApp not yet implemented — queued only
                $sendResult['error'] = 'WhatsApp driver not configured';
            }

            if ($sendResult['sent']) {
                db_update('communications', [
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', [':id' => $commsId]);
            } else {
                db_update('communications', [
                    'status' => 'failed',
                    'error_message' => substr((string)$sendResult['error'], 0, 250),
                ], 'id = :id', [':id' => $commsId]);
            }
        }

        log_audit('communications', 'send', 'communications', $commsId, null, [
            'channel'=>$old['channel'],'recipient'=>$old['recipient_contact'],'sent'=>$sendResult['sent'],
        ]);
        flash_success($sendResult['sent'] ? __('Message sent.') : __('Message queued.'));
        redirect('modules/communications/view.php?id=' . $commsId);
    }
}

$PAGE_TITLE = __('Compose Message');
require __DIR__ . '/../../includes/header.php';
?>

<h1 class="h4 mb-3"><?= e(__('Compose Message')) ?></h1>
<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form method="post" class="srp-card p-4">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Channel')) ?></label>
      <select name="channel" class="form-select">
        <?php foreach (['email','sms','in_app','whatsapp'] as $c): ?>
          <option value="<?= e($c) ?>" <?= $old['channel']===$c?'selected':'' ?>><?= e(ucfirst($c)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label"><?= e(__('Related To (optional)')) ?></label>
      <select name="related_type" class="form-select">
        <option value=""><?= e(__('— none —')) ?></option>
        <?php foreach (['tenant','owner','property','unit','lease','maintenance_request','invoice','payment'] as $rt): ?>
          <option value="<?= e($rt) ?>" <?= $old['related_type']===$rt?'selected':'' ?>><?= e(ucfirst(str_replace('_',' ',$rt))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label"><?= e(__('Related ID')) ?></label>
      <input type="number" name="related_id" class="form-control" value="<?= e((string)$old['related_id']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= e(__('Recipient Name')) ?></label>
      <input name="recipient_name" class="form-control" value="<?= e($old['recipient_name']) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label"><?= e(__('Recipient Contact')) ?> *</label>
      <input name="recipient_contact" class="form-control" value="<?= e($old['recipient_contact']) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label"><?= e(__('Subject')) ?></label>
      <input name="subject" class="form-control" value="<?= e($old['subject']) ?>">
    </div>

    <div class="col-12">
      <label class="form-label"><?= e(__('Body')) ?> *</label>
      <textarea name="body" class="form-control" rows="8" required><?= e($old['body']) ?></textarea>
    </div>

    <div class="col-12">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" name="send_now" id="sendNow" value="1" checked>
        <label class="form-check-label" for="sendNow"><?= e(__('Send immediately (uncheck to queue for cron)')) ?></label>
      </div>
    </div>
  </div>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="<?= APP_URL ?>/modules/communications/index.php"><?= e(__('Cancel')) ?></a>
    <button class="btn btn-primary"><i data-lucide="send"></i> <?= e(__('Send')) ?></button>
  </div>
</form>
<?php require __DIR__ . '/../../includes/footer.php'; ?>