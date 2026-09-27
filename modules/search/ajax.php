<?php
// modules/search/ajax.php — unified cross-entity search endpoint.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
$USER = require_login();

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) { echo json_encode(['groups' => []]); exit; }

$like = '%' . $q . '%';
$cid  = (int)$USER['company_id'];
$groups = [];

// Tenants
if (can('tenants.view', $USER)) {
    $rows = db_all(
        'SELECT id, first_name, last_name, phone FROM tenants
          WHERE company_id = :c AND (first_name LIKE :q OR last_name LIKE :q OR phone LIKE :q OR email LIKE :q OR id_number LIKE :q)
          LIMIT 5',
        [':c'=>$cid, ':q'=>$like]
    );
    if ($rows) {
        $groups[] = ['label' => 'Tenants', 'items' => array_map(fn($r) => [
            'title' => $r['first_name'] . ' ' . $r['last_name'],
            'subtitle' => $r['phone'],
            'link' => APP_URL . '/modules/tenants/view.php?id=' . (int)$r['id'],
            'icon' => 'users',
        ], $rows)];
    }
}

// Properties
if (can('properties.view', $USER)) {
    $rows = db_all(
        'SELECT id, name, city, address FROM properties
          WHERE company_id = :c AND deleted_at IS NULL AND (name LIKE :q OR address LIKE :q OR city LIKE :q)
          LIMIT 5',
        [':c'=>$cid, ':q'=>$like]
    );
    if ($rows) {
        $groups[] = ['label' => 'Properties', 'items' => array_map(fn($r) => [
            'title' => $r['name'],
            'subtitle' => trim(($r['city'] ?? '') . ', ' . ($r['address'] ?? ''), ', '),
            'link' => APP_URL . '/modules/properties/view.php?id=' . (int)$r['id'],
            'icon' => 'building',
        ], $rows)];
    }
}

// Units
if (can('units.view', $USER)) {
    $rows = db_all(
        'SELECT u.id, u.unit_number, p.name AS property_name FROM units u
           JOIN properties p ON p.id = u.property_id
          WHERE u.company_id = :c AND (u.unit_number LIKE :q OR p.name LIKE :q)
          LIMIT 5',
        [':c'=>$cid, ':q'=>$like]
    );
    if ($rows) {
        $groups[] = ['label' => 'Units', 'items' => array_map(fn($r) => [
            'title' => $r['unit_number'],
            'subtitle' => $r['property_name'],
            'link' => APP_URL . '/modules/units/view.php?id=' . (int)$r['id'],
            'icon' => 'door-open',
        ], $rows)];
    }
}

// Invoices
if (can('invoices.view', $USER)) {
    $rows = db_all(
        'SELECT id, invoice_number, amount FROM invoices
          WHERE company_id = :c AND invoice_number LIKE :q LIMIT 5',
        [':c'=>$cid, ':q'=>$like]
    );
    if ($rows) {
        $groups[] = ['label' => 'Invoices', 'items' => array_map(fn($r) => [
            'title' => $r['invoice_number'],
            'subtitle' => money($r['amount']),
            'link' => APP_URL . '/modules/invoices/view.php?id=' . (int)$r['id'],
            'icon' => 'receipt',
        ], $rows)];
    }
}

// Payments — search by reference or amount
if (can('payments.view', $USER)) {
    $rows = db_all(
        'SELECT id, payment_reference, amount, payment_date FROM payments
          WHERE company_id = :c AND (payment_reference LIKE :q OR payment_date LIKE :q) LIMIT 5',
        [':c'=>$cid, ':q'=>$like]
    );
    if ($rows) {
        $groups[] = ['label' => 'Payments', 'items' => array_map(fn($r) => [
            'title' => $r['payment_reference'] ?: ('#' . $r['id']),
            'subtitle' => $r['payment_date'] . ' · ' . money($r['amount']),
            'link' => APP_URL . '/modules/payments/view.php?id=' . (int)$r['id'],
            'icon' => 'banknote',
        ], $rows)];
    }
}

// Leases
if (can('leases.view', $USER)) {
    $rows = db_all(
        'SELECT id, lease_number FROM leases WHERE company_id = :c AND lease_number LIKE :q LIMIT 5',
        [':c'=>$cid, ':q'=>$like]
    );
    if ($rows) {
        $groups[] = ['label' => 'Leases', 'items' => array_map(fn($r) => [
            'title' => $r['lease_number'],
            'link' => APP_URL . '/modules/leases/view.php?id=' . (int)$r['id'],
            'icon' => 'file-signature',
        ], $rows)];
    }
}

// Maintenance
if (can('maintenance.view', $USER)) {
    $rows = db_all(
        'SELECT id, ticket_number, description FROM maintenance_requests
          WHERE company_id = :c AND (ticket_number LIKE :q OR description LIKE :q) LIMIT 5',
        [':c'=>$cid, ':q'=>$like]
    );
    if ($rows) {
        $groups[] = ['label' => 'Maintenance', 'items' => array_map(fn($r) => [
            'title' => $r['ticket_number'],
            'subtitle' => mb_substr($r['description'], 0, 60),
            'link' => APP_URL . '/modules/maintenance/view.php?id=' . (int)$r['id'],
            'icon' => 'hammer',
        ], $rows)];
    }
}

// Vendors
if (can('vendors.view', $USER)) {
    $rows = db_all(
        'SELECT id, name, category FROM vendors
          WHERE company_id = :c AND (name LIKE :q OR category LIKE :q) LIMIT 5',
        [':c'=>$cid, ':q'=>$like]
    );
    if ($rows) {
        $groups[] = ['label' => 'Vendors', 'items' => array_map(fn($r) => [
            'title' => $r['name'],
            'subtitle' => $r['category'],
            'link' => APP_URL . '/modules/vendors/view.php?id=' . (int)$r['id'],
            'icon' => 'wrench',
        ], $rows)];
    }
}

// Documents
if (can('documents.view', $USER)) {
    $rows = db_all(
        'SELECT id, document_name FROM documents
          WHERE company_id = :c AND document_name LIKE :q LIMIT 5',
        [':c'=>$cid, ':q'=>$like]
    );
    if ($rows) {
        $groups[] = ['label' => 'Documents', 'items' => array_map(fn($r) => [
            'title' => $r['document_name'],
            'link' => APP_URL . '/modules/documents/view.php?id=' . (int)$r['id'],
            'icon' => 'file',
        ], $rows)];
    }
}

echo json_encode(['groups' => $groups]);