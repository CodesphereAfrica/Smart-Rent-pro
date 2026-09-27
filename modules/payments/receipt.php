<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

$USER = require_permission('payments.view');
$id = (int)($_GET['id'] ?? 0);
$p = db_one(
    'SELECT p.*, CONCAT(t.first_name," ",t.last_name) AS tenant_name,
            t.phone AS tenant_phone, t.email AS tenant_email,
            u.unit_number,
            c.name AS company_name, c.address AS company_address,
            c.phone AS company_phone, c.email AS company_email, c.logo_path AS company_logo
       FROM payments p
       JOIN tenants t ON t.id = p.tenant_id
       LEFT JOIN units u ON u.id = p.unit_id
       JOIN companies c ON c.id = p.company_id
      WHERE p.id = :id AND p.company_id = :c LIMIT 1',
    [':id'=>$id, ':c'=>(int)$USER['company_id']]
);
if (!$p) { flash_error(__('Payment not found.')); redirect('modules/payments/index.php'); }

$allocs = db_all(
    'SELECT pa.amount_allocated, i.invoice_number, i.invoice_type
       FROM payment_allocations pa
       JOIN invoices i ON i.id = pa.invoice_id
      WHERE pa.payment_id = :p',
    [':p' => $id]
);

// Number formatting
$receiptNumber = 'RC-' . date('Ymd', strtotime($p['created_at'])) . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);

// Insert the receipts row (once) — records the generated receipt number in the DB
$existing = db_one('SELECT id FROM receipts WHERE payment_id = :p LIMIT 1', [':p' => $id]);
if (!$existing) {
    db_insert('receipts', [
        'payment_id' => $id,
        'receipt_number' => $receiptNumber,
    ]);
}

$html = (function () use ($p, $allocs, $receiptNumber) {
    ob_start();
    require SRP_ROOT . '/templates/pdf/receipt.php';
    return ob_get_clean();
});

$mpdf = new \Mpdf\Mpdf([
    'format' => 'A5-L',
    'margin_top' => 12, 'margin_bottom' => 12, 'margin_left' => 12, 'margin_right' => 12,
    'tempDir' => SRP_ROOT . '/storage/cache',
]);
$mpdf->SetTitle('Receipt ' . $receiptNumber);
$mpdf->WriteHTML($html);
$mpdf->Output('receipt-' . $receiptNumber . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;