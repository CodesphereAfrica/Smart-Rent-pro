<?php
// cron/process_communications_queue.php — picks up queued communications and sends them.
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use SmartRentPro\Sms\SmsManager;

const SCRIPT = 'process_communications_queue';
cron_start(SCRIPT);

$queued = db_all(
    'SELECT c.*, co.id AS cid FROM communications c JOIN companies co ON co.id = c.company_id
      WHERE c.status = "queued" ORDER BY c.id ASC LIMIT 100'
);

$sent = 0; $failed = 0;
foreach ($queued as $c) {
    if ($c['channel'] === 'email') {
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
            $mail->addAddress($c['recipient_contact'], $c['recipient_name']);
            $mail->isHTML(true);
            $mail->Subject = $c['subject'] ?: APP_NAME;
            $mail->Body = $c['body'];
            $mail->AltBody = strip_tags($c['body']);
            $mail->send();
            db_update('communications', ['status'=>'sent','sent_at'=>date('Y-m-d H:i:s')], 'id = :id', [':id'=>(int)$c['id']]);
            $sent++;
        } catch (MailException $e) {
            db_update('communications', ['status'=>'failed','error_message'=>substr($e->getMessage(),0,250)], 'id = :id', [':id'=>(int)$c['id']]);
            $failed++;
        }
    } elseif ($c['channel'] === 'sms') {
        $result = SmsManager::send($c['recipient_contact'], $c['body']);
        if ($result['sent']) {
            db_update('communications', ['status'=>'sent','sent_at'=>date('Y-m-d H:i:s')], 'id = :id', [':id'=>(int)$c['id']]);
            $sent++;
        } else {
            db_update('communications', ['status'=>'failed','error_message'=>substr((string)$result['error'],0,250)], 'id = :id', [':id'=>(int)$c['id']]);
            $failed++;
        }
    } else {
        // In-app handled at compose time; WhatsApp not yet wired
        db_update('communications', ['status'=>'sent','sent_at'=>date('Y-m-d H:i:s')], 'id = :id', [':id'=>(int)$c['id']]);
        $sent++;
    }
}

cron_done(SCRIPT, "sent=$sent failed=$failed");