<?php
// cron/generate_rent_charges.php — creates monthly invoices for active leases whose
// rent_schedules.next_generation_date has arrived.
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

const SCRIPT = 'generate_rent_charges';
cron_start(SCRIPT);

$totalGenerated = 0;
$today = date('Y-m-d');

foreach (cron_companies() as $company) {
    $cid = (int)$company['id'];

    $due = db_all(
        'SELECT rs.id AS schedule_id, rs.frequency, rs.next_generation_date,
                l.id AS lease_id, l.company_id, l.tenant_id, l.unit_id, l.property_id,
                l.monthly_rent, l.grace_period_days
           FROM rent_schedules rs
           JOIN leases l ON l.id = rs.lease_id
          WHERE rs.is_active = 1 AND l.status IN ("active","expiring")
            AND l.company_id = :c AND rs.next_generation_date <= :today',
        [':c'=>$cid, ':today'=>$today]
    );

    foreach ($due as $r) {
        db()->beginTransaction();
        try {
            // Generate a unique invoice number
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);

            // Determine period (monthly default)
            $periodStart = date('Y-m-01', strtotime($r['next_generation_date']));
            $periodEnd   = date('Y-m-t', strtotime($r['next_generation_date']));
            $dueDate     = date('Y-m-d', strtotime($periodStart . ' +' . max(1, (int)$r['grace_period_days'] + 5) . ' day'));

            $invoiceId = db_insert('invoices', [
                'company_id' => (int)$r['company_id'],
                'lease_id' => (int)$r['lease_id'],
                'tenant_id' => (int)$r['tenant_id'],
                'unit_id' => (int)$r['unit_id'],
                'invoice_number' => $invoiceNumber,
                'invoice_type' => 'rent',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'amount' => $r['monthly_rent'],
                'amount_paid' => 0,
                'due_date' => $dueDate,
                'status' => strtotime($dueDate) > time() ? 'upcoming' : 'due',
            ]);

            // Advance next_generation_date by one month
            db_update('rent_schedules', [
                'next_generation_date' => date('Y-m-d', strtotime($r['next_generation_date'] . ' +1 month')),
            ], 'id = :id', [':id' => (int)$r['schedule_id']]);

            cron_log(SCRIPT, "Invoice $invoiceNumber for lease #{$r['lease_id']} created");
            $totalGenerated++;
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            cron_log(SCRIPT, 'ERROR lease #' . $r['lease_id'] . ': ' . $e->getMessage());
        }
    }
}

cron_done(SCRIPT, "generated $totalGenerated invoices");