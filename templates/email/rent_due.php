<?php
$body = '<p>Dear ' . htmlspecialchars($tenantName ?? 'tenant', ENT_QUOTES, 'UTF-8') . ',</p>'
      . '<p>Your rent of <strong>' . htmlspecialchars((string)$amount, ENT_QUOTES, 'UTF-8') . '</strong> for unit '
      . htmlspecialchars($unitNumber ?? '', ENT_QUOTES, 'UTF-8') . ' is due on <strong>' . htmlspecialchars($dueDate ?? '', ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
      . '<p>If you have already paid, please ignore this notice.</p>'
      . '<p style="text-align:center"><a href="' . htmlspecialchars($payLink ?? APP_URL, ENT_QUOTES, 'UTF-8') . '" '
      . 'style="display:inline-block;padding:10px 18px;background:#4f46e5;color:#fff;border-radius:10px;text-decoration:none">View invoice</a></p>';
require __DIR__ . '/_layout.php';