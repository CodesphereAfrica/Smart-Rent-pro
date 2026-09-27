<?php
$body = '<p>Dear Approver,</p>'
      . '<p>An approval request is pending your review.</p>'
      . '<ul>'
      .   '<li><strong>Type:</strong> ' . htmlspecialchars(str_replace('_',' ',$workflowType ?? ''), ENT_QUOTES, 'UTF-8') . '</li>'
      .   '<li><strong>Amount:</strong> ' . htmlspecialchars((string)($amount ?? ''), ENT_QUOTES, 'UTF-8') . '</li>'
      .   '<li><strong>Requested by:</strong> ' . htmlspecialchars($requester ?? '', ENT_QUOTES, 'UTF-8') . '</li>'
      . '</ul>'
      . '<p style="text-align:center"><a href="' . htmlspecialchars($link ?? APP_URL, ENT_QUOTES, 'UTF-8') . '" '
      . 'style="display:inline-block;padding:10px 18px;background:#4f46e5;color:#fff;border-radius:10px;text-decoration:none">Review now</a></p>';
require __DIR__ . '/_layout.php';