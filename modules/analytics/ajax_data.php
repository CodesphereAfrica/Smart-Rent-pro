<?php
// modules/analytics/ajax_data.php — returns live portfolio KPIs as JSON.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

$USER = require_login();
if (!can('analytics.view', $USER)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$cid = (int)$USER['company_id'];

$kpi = db_one(
    'SELECT
        (SELECT COUNT(*) FROM units WHERE company_id = :c)                                       AS units_total,
        (SELECT COUNT(*) FROM units WHERE company_id = :c AND status = "occupied")                AS units_occupied,
        (SELECT COUNT(*) FROM units WHERE company_id = :c AND status = "available")               AS units_vacant,
        (SELECT COUNT(*) FROM tenants WHERE company_id = :c AND status = "active")                AS tenants_active,
        (SELECT COALESCE(SUM(amount_paid), 0) FROM invoices WHERE company_id = :c AND period_start >= DATE_FORMAT(CURDATE(), "%Y-%m-01")) AS collected_this_month,
        (SELECT COALESCE(SUM(balance), 0) FROM invoices WHERE company_id = :c AND balance > 0) AS outstanding
    ',
    [':c' => $cid]
);

$unitsTotal = (int)$kpi['units_total'];
$occRate    = $unitsTotal > 0 ? round(((int)$kpi['units_occupied'] / $unitsTotal) * 100, 1) : 0.0;
$vacRate    = $unitsTotal > 0 ? round(((int)$kpi['units_vacant']   / $unitsTotal) * 100, 1) : 0.0;

echo json_encode([
    'ok' => true,
    'kpi' => [
        'units_total'       => $unitsTotal,
        'units_occupied'    => (int)$kpi['units_occupied'],
        'units_vacant'      => (int)$kpi['units_vacant'],
        'tenants_active'    => (int)$kpi['tenants_active'],
        'occupancy_rate'    => $occRate,
        'vacancy_rate'      => $vacRate,
        'collected_month'   => (float)$kpi['collected_this_month'],
        'outstanding'       => (float)$kpi['outstanding'],
    ],
    'generated_at' => date('c'),
]);