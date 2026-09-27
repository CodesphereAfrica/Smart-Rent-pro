<?php
$body = '<p>Dear ' . htmlspecialchars($recipientName ?? 'team', ENT_QUOTES, 'UTF-8') . ',</p>'
      . '<p>Ticket <strong>' . htmlspecialchars($ticketNumber ?? '', ENT_QUOTES, 'UTF-8') . '</strong> has been assigned to you.</p>'
      . '<ul>'
      .   '<li><strong>Unit:</strong> ' . htmlspecialchars($unitNumber ?? '', ENT_QUOTES, 'UTF-8') . '</li>'
      .   '<li><strong>Property:</strong> ' . htmlspecialchars($propertyName ?? '', ENT_QUOTES, 'UTF-8') . '</li>'
      .   '<li><strong>Priority:</strong> ' . htmlspecialchars(ucfirst($priority ?? 'medium'), ENT_QUOTES, 'UTF-8') . '</li>'
      . '</ul>'
      . '<p>' . nl2br(htmlspecialchars($description ?? '', ENT_QUOTES, 'UTF-8')) . '</p>'
      . '<p style="text-align:center"><a href="' . htmlspecialchars($link ?? APP_URL, ENT_QUOTES, 'UTF-8') . '" '
      . 'style="display:inline-block;padding:10px 18px;background:#4f46e5;color:#fff;border-radius:10px;text-decoration:none">Open ticket</a></p>';
require __DIR__ . '/_layout.php';