<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

const SCRIPT = 'apply_late_fees';
cron_start(SCRIPT);

$applied = 0;
foreach (cron_companies() as $company) {
    $cid = (int)$company['id'];

    // Overdue invoices with no late fee already applied this period
    $invoices = db_all(
        'SELECT i.id, i.amount, i.balance, i.due_date, i.lease_id, i.property_id,
                l.grace_period_days
           FROM invoices i
           LEFT JOIN leases l ON l.id = i.lease_id
          WHERE i.company_id = :c AND i.status IN ("due","partially_paid","overdue")
            AND i.balance > 0
            AND DATEDIFF(CURDATE(), i.due_date) > COALESCE(l.grace_period_days, 0)',
        [':c' => $cid]
    );

    foreach ($invoices as $inv) {
        // Choose rule: lease-specific, then property-specific, then company default
        $rule = db_one(
            'SELECT * FROM late_fee_rules
              WHERE company_id = :c AND is_active = 1
                AND (lease_id = :l OR (lease_id IS NULL AND property_id = :p) OR (lease_id IS NULL AND property_id IS NULL))
              ORDER BY (lease_id IS NOT NULL) DESC, (property_id IS NOT NULL) DESC LIMIT 1',
            [':c'=>$cid, ':l'=>$inv['lease_id'], ':p'=>$inv['property_id']]
        );
        if (!$rule) continue;

        $daysLate = max(0, (int)((strtotime(date('Y-m-d')) - strtotime($inv['due_date'])) / 86400) - (int)($rule['grace_period_days'] ?? 0));
        if ($daysLate <= 0) continue;

        $amount = 0.0;
        switch ($rule['fee_type']) {
            case 'fixed':      $amount = (float)$rule['amount']; break;
            case 'percentage': $amount = (float)$inv['amount'] * ((float)$rule['amount'] / 100); break;
            case 'daily':      $amount = (float)$rule['amount'] * $daysLate; break;
            case 'monthly':    $amount = (float)$rule['amount'] * ceil($daysLate / 30); break;
        }
        if ($rule['max_penalty'] !== null && $amount > (float)$rule['max_penalty']) {
            $amount = (float)$rule['max_penalty'];
        }
        if ($amount <= 0) continue;

        db()->beginTransaction();
        try {
            $feeNumber = 'LATE-' . date('Ymd') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            db_insert('invoices', [
                'company_id' => $cid,
                'lease_id' => $inv['lease_id'],
                'tenant_id' => (int)(db_one('SELECT tenant_id FROM invoices WHERE id = :i', [':i'=>$inv['id']])['tenant_id']),
                'unit_id' => (int)(db_one('SELECT unit_id FROM invoices WHERE id = :i', [':i'=>$inv['id']])['unit_id']),
                'invoice_number' => $feeNumber,
                'invoice_type' => 'late_fee',
                'amount' => round($amount, 2),
                'amount_paid' => 0,
                'due_date' => date('Y-m-d'),
                'status' => 'due',
            ]);
            db_update('invoices', ['status' => 'overdue'], 'id = :id', [':id' => (int)$inv['id']]);
            $applied++;
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            cron_log(SCRIPT, 'ERROR invoice #' . $inv['id'] . ': ' . $e->getMessage());
        }
    }
}

cron_done(SCRIPT, "applied $applied late fees");