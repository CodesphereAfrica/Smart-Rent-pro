<?php
$body = '<p>Dear ' . htmlspecialchars($tenantName ?? 'tenant', ENT_QUOTES, 'UTF-8') . ',</p>'
      . '<p>We have received your payment of <strong>' . htmlspecialchars((string)$amount, ENT_QUOTES, 'UTF-8') . '</strong> on '
      . htmlspecialchars($paymentDate ?? '', ENT_QUOTES, 'UTF-8') . '.</p>'
      . '<p>Your receipt is attached. Thank you.</p>';
require __DIR__ . '/_layout.php';