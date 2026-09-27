<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

const SCRIPT = 'dispatch_document_expiry_reminders';
cron_start(SCRIPT);

$total = 0;
foreach (cron_companies() as $company) {
    $cid = (int)$company['id'];

    $docs = db_all(
        'SELECT d.id, d.document_name, d.expiry_date, d.uploaded_by
           FROM documents d
          WHERE d.company_id = :c AND d.expiry_date IS NOT NULL
            AND DATEDIFF(d.expiry_date, CURDATE()) BETWEEN 0 AND 30',
        [':c'=>$cid]
    );

    foreach ($docs as $d) {
        // Notify the uploader and Super Admins
        $targets = db_all(
            'SELECT u.id FROM users u LEFT JOIN roles r ON r.id = u.role_id
              WHERE u.company_id = :c AND u.status = "active"
                AND (r.name = "Super Admin" OR u.id = :uploader)',
            [':c'=>$cid, ':uploader'=>(int)$d['uploaded_by']]
        );
        foreach ($targets as $t) {
            db_insert('notifications', [
                'company_id' => $cid,
                'user_id' => (int)$t['id'],
                'type' => 'document_expiring',
                'title' => 'Document expiring: ' . $d['document_name'],
                'message' => 'Expires on ' . $d['expiry_date'],
                'related_type' => 'document',
                'related_id' => (int)$d['id'],
            ]);
            $total++;
        }
    }
}

cron_done(SCRIPT, "queued $total notifications");