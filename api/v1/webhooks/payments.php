<?php
// api/v1/webhooks/payments.php — inbound webhook from mobile-money providers.
// Verifies a shared secret in the X-Webhook-Secret header, then records a payment.
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

$secret = $_ENV['PAYMENT_WEBHOOK_SECRET'] ?? '';
$given  = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
if ($secret === '' || !hash_equals($secret, $given)) api_err('unauthorized', 401);

$in = api_input();
$companyId  = (int)($in['company_id'] ?? 0);
$tenantId   = (int)($in['tenant_id'] ?? 0);
$amount     = (float)($in['amount'] ?? 0);
$reference  = sanitize_string($in['reference'] ?? '', 100);
$method     = in_array($in['method'] ?? 'mobile_money', ['cash','bank_transfer','mobile_money','card','cheque','other'], true) ? $in['method'] : 'mobile_money';

if (!$companyId || !$tenantId || $amount <= 0) api_err('invalid_payload', 400);

// Verify tenant belongs to company
$tenant = db_one('SELECT id FROM tenants WHERE id = :t AND company_id = :c LIMIT 1', [':t'=>$tenantId, ':c'=>$companyId]);
if (!$tenant) api_err('tenant_not_found', 404);

// Find a system user to attribute the payment to (fallback: first active Super Admin of the company)
$systemUser = db_one(
    'SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id
      WHERE u.company_id = :c AND r.name = "Super Admin" AND u.status = "active"
      ORDER BY u.id ASC LIMIT 1',
    [':c'=>$companyId]
);
$recordedBy = (int)($systemUser['id'] ?? 0);
if (!$recordedBy) api_err('no_attributable_user', 500);

// Insert payment
$paymentId = db_insert('payments', [
    'company_id' => $companyId,
    'tenant_id'  => $tenantId,
    'amount'     => $amount,
    'amount_unallocated' => $amount,
    'payment_date' => date('Y-m-d'),
    'payment_method' => $method,
    'payment_reference' => $reference ?: null,
    'recorded_by' => $recordedBy,
    'status' => 'completed',
    'notes' => 'Received via webhook',
]);

// Note: allocation is left to the receipt-processing side to be idempotent;
// we only record here. A cron job or admin action will allocate.

api_ok(['payment_id' => $paymentId], 201);