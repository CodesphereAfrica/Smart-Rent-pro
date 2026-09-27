<?php
$body = '<p>Dear ' . htmlspecialchars($tenantName ?? '', ENT_QUOTES, 'UTF-8') . ',</p>'
      . '<p>Your lease <strong>' . htmlspecialchars($leaseNumber ?? '', ENT_QUOTES, 'UTF-8') . '</strong> for unit '
      . htmlspecialchars($unitNumber ?? '', ENT_QUOTES, 'UTF-8') . ' ends on <strong>'
      . htmlspecialchars($endDate ?? '', ENT_QUOTES, 'UTF-8') . '</strong> (' . (int)($daysLeft ?? 0) . ' days).</p>'
      . '<p>Please let us know whether you wish to renew.</p>';
require __DIR__ . '/_layout.php';