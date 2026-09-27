<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

const SCRIPT = 'dispatch_lease_expiry_reminders';
cron_start(SCRIPT);

$total = 0;
foreach (cron_companies() as $company) {
    $cid = (int)$company['id'];

    $rows = db_all(
        'SELECT ler.id AS reminder_id, ler.days_before,
                l.id AS lease_id, l.lease_number, l.end_date, l.tenant_id,
                DATEDIFF(l.end_date, CURDATE()) AS days_left,
                t.first_name, t.last_name, t.phone, t.email
           FROM lease_expiry_reminders ler
           JOIN leases l ON l.id = ler.lease_id
           JOIN tenants t ON t.id = l.tenant_id
          WHERE l.company_id = :c AND l.status IN ("active","expiring")
            AND ler.sent_at IS NULL
            AND DATEDIFF(l.end_date, CURDATE()) <= ler.days_before
            AND DATEDIFF(l.end_date, CURDATE()) >= 0',
        [':c' => $cid]
    );

    foreach ($rows as $r) {
        $channel = $r['email'] ? 'email' : ($r['phone'] ? 'sms' : 'in_app');
        $recipient = $channel === 'email' ? $r['email'] : ($channel === 'sms' ? $r['phone'] : null);

        if ($recipient) {
            db_insert('communications', [
                'company_id' => $cid,
                'channel' => $channel,
                'direction' => 'outbound',
                'related_type' => 'lease',
                'related_id' => (int)$r['lease_id'],
                'recipient_name' => $r['first_name'] . ' ' . $r['last_name'],
                'recipient_contact' => $recipient,
                'subject' => 'Lease expiring in ' . $r['days_left'] . ' days',
                'body' => "Dear {$r['first_name']}, your lease {$r['lease_number']} for unit expires on {$r['end_date']}. Please contact us to renew.",
                'status' => 'queued',
                'sent_by' => null,
            ]);
            $total++;
        }

        db_update('lease_expiry_reminders', ['sent_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => (int)$r['reminder_id']]);
    }
}

cron_done(SCRIPT, "queued $total reminders");