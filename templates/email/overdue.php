<?php
$body = '<p>Dear ' . htmlspecialchars($tenantName ?? 'tenant', ENT_QUOTES, 'UTF-8') . ',</p>'
      . '<p>Our records show an <strong>overdue balance of ' . htmlspecialchars((string)$balance, ENT_QUOTES, 'UTF-8') . '</strong> on unit '
      . htmlspecialchars($unitNumber ?? '', ENT_QUOTES, 'UTF-8') . '. The oldest unpaid invoice is from '
      . htmlspecialchars($oldestDue ?? '', ENT_QUOTES, 'UTF-8') . '.</p>'
      . '<p>Please arrange payment as soon as possible to avoid further action.</p>'
      . '<p style="text-align:center"><a href="' . htmlspecialchars($payLink ?? APP_URL, ENT_QUOTES, 'UTF-8') . '" '
      . 'style="display:inline-block;padding:10px 18px;background:#ef4444;color:#fff;border-radius:10px;text-decoration:none">View invoices</a></p>';
require __DIR__ . '/_layout.php';