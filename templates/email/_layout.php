<?php /** @var string $title */ /** @var string $body */ ?>
<!doctype html>
<html><body style="margin:0;padding:0;background:#f6f7fb;font-family:Inter,system-ui,Segoe UI,Roboto,sans-serif;color:#0f172a">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px">
    <tr><td align="center">
      <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 4px 14px rgba(15,23,42,.06)">
        <tr><td style="padding:20px 24px;background:linear-gradient(135deg,#4f46e5,#06b6d4);color:#fff;font-size:18px;font-weight:700">
          <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?>
        </td></tr>
        <tr><td style="padding:24px">
          <h1 style="margin:0 0 12px 0;font-size:18px;color:#0f172a"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
          <div style="font-size:14px;line-height:1.55;color:#334155">
            <?= $body /* pre-rendered HTML */ ?>
          </div>
        </td></tr>
        <tr><td style="padding:14px 24px;background:#f6f7fb;color:#64748b;font-size:12px">
          This is an automated message from <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?>.
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>