<?php
$body = '<p>Dear ' . htmlspecialchars($recipientName ?? 'tenant', ENT_QUOTES, 'UTF-8') . ',</p>'
      . '<p>Your maintenance request <strong>' . htmlspecialchars($ticketNumber ?? '', ENT_QUOTES, 'UTF-8') . '</strong> has been completed on '
      . htmlspecialchars($completedAt ?? '', ENT_QUOTES, 'UTF-8') . '.</p>'
      . '<p>If the issue is not resolved, please contact your property manager.</p>';
require __DIR__ . '/_layout.php';