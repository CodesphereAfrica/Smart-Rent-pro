<?php
$body = '<p>Dear ' . htmlspecialchars($name ?? 'user', ENT_QUOTES, 'UTF-8') . ',</p>'
      . '<p>Welcome to <strong>' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</strong>!</p>'
      . '<p>Your account has been created.</p>'
      . '<ul>'
      .   '<li><strong>Username:</strong> ' . htmlspecialchars($username ?? '', ENT_QUOTES, 'UTF-8') . '</li>'
      .   '<li><strong>Company:</strong> ' . htmlspecialchars($company ?? '', ENT_QUOTES, 'UTF-8') . '</li>'
      . '</ul>'
      . '<p>Please sign in and change your password.</p>'
      . '<p style="text-align:center"><a href="' . htmlspecialchars(APP_URL . '/modules/auth/login.php', ENT_QUOTES, 'UTF-8') . '" '
      . 'style="display:inline-block;padding:10px 18px;background:#4f46e5;color:#fff;border-radius:10px;text-decoration:none">Sign in</a></p>';
require __DIR__ . '/_layout.php';