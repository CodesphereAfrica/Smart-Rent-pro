<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

const SCRIPT = 'dispatch_collections_stages';
cron_start(SCRIPT);

$sent = 0;
foreach (cron_companies() as $company) {
    $cid = (int)$company['id'];
    $stages = db_all('SELECT * FROM collection_workflow_stages WHERE company_id = :c AND is_active = 1 ORDER BY stage_order', [':c'=>$cid]);
    if (!$stages) continue;

    foreach ($stages as $stage) {
        $candidates = db_all(
            'SELECT i.id AS invoice_id, i.balance, i.due_date, t.id AS tenant_id, t.first_name, t.phone, t.email
               FROM invoices i
               JOIN tenants t ON t.id = i.tenant_id
              WHERE i.company_id = :c AND i.balance > 0 AND i.status IN ("due","partially_paid","overdue")
                AND DATEDIFF(CURDATE(), i.due_date) >= :trigger
                AND NOT EXISTS (
                    SELECT 1 FROM collection_actions_log cal
                     WHERE cal.invoice_id = i.id AND cal.stage_id = :sid
                )
              LIMIT 50',
            [':c'=>$cid, ':trigger'=>(int)$stage['trigger_days_after_due'], ':sid'=>(int)$stage['id']]
        );

        foreach ($candidates as $c) {
            $channel = in_array($stage['channel'], ['email','sms','in_app','all'], true) ? $stage['channel'] : 'all';
            $useEmail = in_array($channel, ['email','all'], true) && $c['email'];
            $useSms   = in_array($channel, ['sms','all'], true) && $c['phone'];

            if ($useEmail) {
                db_insert('communications', [
                    'company_id' => $cid,
                    'channel' => 'email',
                    'direction' => 'outbound',
                    'related_type' => 'invoice',
                    'related_id' => (int)$c['invoice_id'],
                    'recipient_name' => $c['first_name'],
                    'recipient_contact' => $c['email'],
                    'subject' => $stage['name'],
                    'body' => (string)($stage['message_template'] ?: 'Overdue balance: ' . $c['balance']),
                    'status' => 'queued',
                ]);
            }
            if ($useSms) {
                db_insert('communications', [
                    'company_id' => $cid,
                    'channel' => 'sms',
                    'direction' => 'outbound',
                    'related_type' => 'invoice',
                    'related_id' => (int)$c['invoice_id'],
                    'recipient_name' => $c['first_name'],
                    'recipient_contact' => $c['phone'],
                    'subject' => null,
                    'body' => (string)($stage['message_template'] ?: 'Overdue balance: ' . $c['balance']),
                    'status' => 'queued',
                ]);
            }

            db_insert('collection_actions_log', [
                'company_id' => $cid,
                'tenant_id' => (int)$c['tenant_id'],
                'invoice_id' => (int)$c['invoice_id'],
                'stage_id' => (int)$stage['id'],
                'action_taken' => $stage['name'] . ' dispatched via ' . $channel,
            ]);
            $sent++;
        }
    }
}

cron_done(SCRIPT, "dispatched $sent collection actions");