<?php
// api/v1/webhooks/sms-status.php — delivery status callback from SMS gateway.
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

$secret = $_ENV['SMS_WEBHOOK_SECRET'] ?? '';
$given  = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
if ($secret === '' || !hash_equals($secret, $given)) api_err('unauthorized', 401);

$in = api_input();
$commsId = (int)($in['communication_id'] ?? 0);
$status  = in_array($in['status'] ?? '', ['sent','delivered','failed','read'], true) ? $in['status'] : 'delivered';
$error   = sanitize_string($in['error'] ?? '', 250) ?: null;

if (!$commsId) api_err('missing_communication_id', 400);

db_update('communications', [
    'status' => $status,
    'error_message' => $error,
    'sent_at' => $status === 'sent' || $status === 'delivered' ? date('Y-m-d H:i:s') : null,
], 'id = :id', [':id' => $commsId]);

api_ok(['updated' => $commsId]);