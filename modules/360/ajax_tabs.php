<?php
// modules/360/ajax_tabs.php — per-tab data source for Property / Tenant / Unit 360 views.
// Self-contained: each case issues its own scoped, prepared query and returns JSON.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$USER = require_login();
$type = (string)($_GET['type'] ?? '');
$id   = (int)($_GET['id'] ?? 0);
$tab  = (string)($_GET['tab'] ?? '');
$cid  = (int)$USER['company_id'];

if ($id <= 0) { echo json_encode(['error' => 'invalid_id']); exit; }

// ---------------------------------------------------------------------------
// Ownership guard — every branch must confirm the entity belongs to the
// caller's company, and for the Owner role, belongs to the caller's portfolio.
// ---------------------------------------------------------------------------
$isOwnerRole = has_role('Property Owner', $USER);

switch ($type) {
    // =======================================================================
    // TENANT 360
    // =======================================================================
    case 'tenant':
        $tenant = db_one(
            'SELECT * FROM tenants WHERE id = :id AND company_id = :c LIMIT 1',
            [':id' => $id, ':c' => $cid]
        );
        if (!$tenant) { echo json_encode(['error' => 'not_found']); exit; }

        if ($tab === 'overview') {
            $financial = db_one(
                'SELECT
                   COALESCE(SUM(i.amount), 0)         AS invoiced,
                   COALESCE(SUM(i.amount_paid), 0)    AS paid,
                   COALESCE(SUM(i.balance), 0)        AS outstanding
                 FROM invoices i WHERE i.tenant_id = :t',
                [':t' => $id]
            );
            $deposit = db_one(
                'SELECT COALESCE(SUM(security_deposit), 0) AS dep
                   FROM leases WHERE tenant_id = :t AND status IN ("active","expiring")',
                [':t' => $id]
            );
            $activeLease = db_one(
                'SELECT l.*, u.unit_number, p.name AS property_name
                   FROM leases l
                   JOIN units u ON u.id = l.unit_id
                   JOIN properties p ON p.id = l.property_id
                  WHERE l.tenant_id = :t AND l.status IN ("active","expiring")
                  ORDER BY l.id DESC LIMIT 1',
                [':t' => $id]
            );
            $lastPayment = db_one(
                'SELECT payment_date, amount FROM payments
                  WHERE tenant_id = :t AND status = "completed"
                  ORDER BY payment_date DESC LIMIT 1',
                [':t' => $id]
            );
            $nextDue = db_one(
                'SELECT due_date, balance FROM invoices
                  WHERE tenant_id = :t AND status IN ("due","partially_paid","overdue","upcoming")
                  ORDER BY due_date ASC LIMIT 1',
                [':t' => $id]
            );
            $openMaint = (int)(db_one(
                'SELECT COUNT(*) AS c FROM maintenance_requests
                  WHERE tenant_id = :t AND status NOT IN ("closed","verified")',
                [':t' => $id]
            )['c'] ?? 0);

            echo json_encode([
                'tenant'    => $tenant,
                'financial' => $financial,
                'deposit'   => $deposit,
                'lease'     => $activeLease,
                'last_payment' => $lastPayment,
                'next_due'     => $nextDue,
                'open_maintenance' => $openMaint,
            ]);
            exit;
        }

        if ($tab === 'leases') {
            $rows = db_all(
                'SELECT l.id, l.lease_number, l.start_date, l.end_date, l.monthly_rent,
                        l.status, u.unit_number, p.name AS property_name
                   FROM leases l
                   JOIN units u ON u.id = l.unit_id
                   JOIN properties p ON p.id = l.property_id
                  WHERE l.tenant_id = :t
                  ORDER BY l.start_date DESC',
                [':t' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        if ($tab === 'invoices') {
            $rows = db_all(
                'SELECT id, invoice_number, invoice_type, amount, amount_paid, balance, due_date, status
                   FROM invoices WHERE tenant_id = :t
                  ORDER BY due_date DESC LIMIT 200',
                [':t' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        if ($tab === 'payments') {
            $rows = db_all(
                'SELECT id, payment_date, amount, payment_method, payment_reference, status
                   FROM payments WHERE tenant_id = :t
                  ORDER BY payment_date DESC LIMIT 200',
                [':t' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        if ($tab === 'maintenance') {
            $rows = db_all(
                'SELECT id, ticket_number, priority, status, description, created_at
                   FROM maintenance_requests WHERE tenant_id = :t
                  ORDER BY id DESC LIMIT 200',
                [':t' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        if ($tab === 'documents') {
            $rows = db_all(
                'SELECT id, document_name, file_path, file_type, uploaded_at
                   FROM documents
                  WHERE related_type = "tenant" AND related_id = :t
                  ORDER BY id DESC LIMIT 200',
                [':t' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        if ($tab === 'communications') {
            $rows = db_all(
                'SELECT id, channel, direction, subject, body, status, created_at
                   FROM communications
                  WHERE related_type = "tenant" AND related_id = :t
                  ORDER BY id DESC LIMIT 100',
                [':t' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        echo json_encode(['error' => 'unknown_tab']); exit;

    // =======================================================================
    // PROPERTY 360
    // =======================================================================
    case 'property':
        $prop = db_one(
            'SELECT * FROM properties WHERE id = :id AND company_id = :c AND deleted_at IS NULL LIMIT 1',
            [':id' => $id, ':c' => $cid]
        );
        if (!$prop) { echo json_encode(['error' => 'not_found']); exit; }

        if ($isOwnerRole) {
            $owns = db_one(
                'SELECT 1 FROM owner_properties op
                   JOIN owners o ON o.id = op.owner_id
                  WHERE op.property_id = :p AND o.user_id = :u LIMIT 1',
                [':p' => $id, ':u' => (int)$USER['id']]
            );
            if (!$owns) { echo json_encode(['error' => 'forbidden']); exit; }
        }

        if ($tab === 'units') {
            $rows = db_all(
                'SELECT u.id, u.unit_number, u.unit_type, u.base_rent, u.status,
                        (SELECT CONCAT(t.first_name," ",t.last_name)
                           FROM leases l JOIN tenants t ON t.id = l.tenant_id
                          WHERE l.unit_id = u.id AND l.status IN ("active","expiring") LIMIT 1) AS tenant_name
                   FROM units u WHERE u.property_id = :p ORDER BY u.unit_number',
                [':p' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        if ($tab === 'maintenance') {
            $rows = db_all(
                'SELECT id, ticket_number, priority, status, description, created_at
                   FROM maintenance_requests WHERE property_id = :p
                  ORDER BY id DESC LIMIT 100',
                [':p' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        if ($tab === 'expenses') {
            $rows = db_all(
                'SELECT e.id, e.description, e.amount, e.expense_date, e.payment_status,
                        c.name AS category_name
                   FROM expenses e
                   LEFT JOIN expense_categories c ON c.id = e.category_id
                  WHERE e.property_id = :p
                  ORDER BY e.expense_date DESC LIMIT 200',
                [':p' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        if ($tab === 'documents') {
            $rows = db_all(
                'SELECT id, document_name, file_path, file_type, uploaded_at
                   FROM documents WHERE related_type = "property" AND related_id = :p
                  ORDER BY id DESC LIMIT 200',
                [':p' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        echo json_encode(['error' => 'unknown_tab']); exit;

    // =======================================================================
    // UNIT 360
    // =======================================================================
    case 'unit':
        $unit = db_one(
            'SELECT u.* FROM units u
              WHERE u.id = :id AND u.company_id = :c LIMIT 1',
            [':id' => $id, ':c' => $cid]
        );
        if (!$unit) { echo json_encode(['error' => 'not_found']); exit; }

        if ($isOwnerRole) {
            $owns = db_one(
                'SELECT 1 FROM owner_properties op JOIN owners o ON o.id = op.owner_id
                  WHERE op.property_id = :p AND o.user_id = :u LIMIT 1',
                [':p' => (int)$unit['property_id'], ':u' => (int)$USER['id']]
            );
            if (!$owns) { echo json_encode(['error' => 'forbidden']); exit; }
        }

        if ($tab === 'leases') {
            $rows = db_all(
                'SELECT l.id, l.lease_number, l.start_date, l.end_date, l.monthly_rent, l.status,
                        CONCAT(t.first_name," ",t.last_name) AS tenant_name
                   FROM leases l JOIN tenants t ON t.id = l.tenant_id
                  WHERE l.unit_id = :u ORDER BY l.start_date DESC',
                [':u' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        if ($tab === 'invoices') {
            $rows = db_all(
                'SELECT id, invoice_number, invoice_type, amount, amount_paid, balance, due_date, status
                   FROM invoices WHERE unit_id = :u ORDER BY due_date DESC LIMIT 200',
                [':u' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        if ($tab === 'maintenance') {
            $rows = db_all(
                'SELECT id, ticket_number, priority, status, description, created_at
                   FROM maintenance_requests WHERE unit_id = :u ORDER BY id DESC LIMIT 200',
                [':u' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        if ($tab === 'inspections') {
            $rows = db_all(
                'SELECT id, inspection_type, inspection_date, status
                   FROM inspections WHERE unit_id = :u ORDER BY inspection_date DESC LIMIT 100',
                [':u' => $id]
            );
            echo json_encode(['rows' => $rows]); exit;
        }

        echo json_encode(['error' => 'unknown_tab']); exit;
}

echo json_encode(['error' => 'invalid_type']);