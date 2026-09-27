<?php
// api/v1/index.php — human-readable API reference.
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Smart Rent Pro — API v1</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head><body class="bg-light">
<div class="container py-5">
  <h1 class="h3">Smart Rent Pro — REST API v1</h1>
  <p class="text-muted">JWT-authenticated JSON API for properties, units, tenants, leases, invoices, payments, maintenance and meter readings.</p>

  <div class="card mb-3"><div class="card-body">
    <h2 class="h5">Authentication</h2>
    <p class="mb-1">POST <code>/api/v1/auth/login.php</code></p>
    <pre class="bg-dark text-light p-3 rounded small">{ "identifier": "superadmin", "password": "…" }</pre>
    <p>Response includes <code>token</code>. Send as: <code>Authorization: Bearer &lt;token&gt;</code>.</p>
  </div></div>

  <div class="card mb-3"><div class="card-body">
    <h2 class="h5">Resources</h2>
    <table class="table table-sm">
      <thead><tr><th>Method</th><th>Path</th><th>Description</th></tr></thead>
      <tbody>
        <tr><td>GET</td><td>/api/v1/auth/me.php</td><td>Current authenticated user</td></tr>
        <tr><td>POST</td><td>/api/v1/auth/refresh.php</td><td>Rotate JWT</td></tr>
        <tr><td>GET</td><td>/api/v1/properties/list.php</td><td>List properties (paginated)</td></tr>
        <tr><td>GET</td><td>/api/v1/units/list.php</td><td>List units</td></tr>
        <tr><td>GET</td><td>/api/v1/tenants/list.php</td><td>List tenants</td></tr>
        <tr><td>GET</td><td>/api/v1/leases/list.php</td><td>List leases</td></tr>
        <tr><td>GET</td><td>/api/v1/invoices/list.php</td><td>List invoices</td></tr>
        <tr><td>GET</td><td>/api/v1/payments/list.php</td><td>List payments</td></tr>
        <tr><td>GET</td><td>/api/v1/maintenance/list.php</td><td>List maintenance requests</td></tr>
        <tr><td>POST</td><td>/api/v1/meter-readings/ingest.php</td><td>Ingest a manual or IoT meter reading</td></tr>
        <tr><td>POST</td><td>/api/v1/webhooks/payments.php</td><td>Mobile-money webhook</td></tr>
        <tr><td>POST</td><td>/api/v1/webhooks/sms-status.php</td><td>SMS delivery status webhook</td></tr>
      </tbody>
    </table>
  </div></div>

  <div class="card"><div class="card-body">
    <h2 class="h5">Pagination</h2>
    <p class="text-muted small">Add <code>?page=1&per_page=25</code> to any <code>/list.php</code> endpoint. Max <code>per_page</code> is 100.</p>
  </div></div>
</div>
</body></html>