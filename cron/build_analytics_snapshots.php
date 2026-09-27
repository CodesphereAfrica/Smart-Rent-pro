<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

const SCRIPT = 'build_analytics_snapshots';
cron_start(SCRIPT);

$today = date('Y-m-d');
$total = 0;

foreach (cron_companies() as $company) {
    $cid = (int)$company['id'];

    // Portfolio-level snapshot (property_id = NULL)
    $kpi = db_one(
        'SELECT
            (SELECT COUNT(*) FROM units WHERE company_id = :c) AS total_units,
            (SELECT COUNT(*) FROM units WHERE company_id = :c AND status = "occupied") AS occupied_units,
            (SELECT COUNT(*) FROM units WHERE company_id = :c AND status = "available") AS vacant_units,
            (SELECT COALESCE(SUM(amount),0) FROM invoices WHERE company_id = :c AND period_start >= DATE_FORMAT(CURDATE(), "%Y-%m-01")) AS expected_rent,
            (SELECT COALESCE(SUM(amount_paid),0) FROM invoices WHERE company_id = :c AND period_start >= DATE_FORMAT(CURDATE(), "%Y-%m-01")) AS collected_rent,
            (SELECT COALESCE(SUM(balance),0) FROM invoices WHERE company_id = :c AND balance > 0) AS outstanding,
            (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE company_id = :c AND DATE_FORMAT(expense_date, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m")) AS total_expenses,
            (SELECT COUNT(*) FROM maintenance_requests WHERE company_id = :c AND status NOT IN ("closed","verified")) AS open_maint
        ',
        [':c'=>$cid]
    );

    $occupancyRate = ((int)$kpi['total_units']) > 0 ? round(((int)$kpi['occupied_units'] / (int)$kpi['total_units']) * 100, 2) : 0;
    $collectionRate = ((float)$kpi['expected_rent']) > 0 ? round(((float)$kpi['collected_rent'] / (float)$kpi['expected_rent']) * 100, 2) : 0;
    $noi = (float)$kpi['collected_rent'] - (float)$kpi['total_expenses'];

    db_query(
        'INSERT INTO analytics_daily_snapshots
            (company_id, property_id, snapshot_date, total_units, occupied_units, vacant_units,
             occupancy_rate, expected_rent, collected_rent, outstanding_rent, collection_rate,
             total_expenses, net_operating_income, open_maintenance_requests)
         VALUES (:cid, NULL, :d, :tu, :ou, :vu, :oc, :er, :cr, :or_, :crate, :te, :noi, :om)
         ON DUPLICATE KEY UPDATE
             total_units = VALUES(total_units), occupied_units = VALUES(occupied_units),
             vacant_units = VALUES(vacant_units), occupancy_rate = VALUES(occupancy_rate),
             expected_rent = VALUES(expected_rent), collected_rent = VALUES(collected_rent),
             outstanding_rent = VALUES(outstanding_rent), collection_rate = VALUES(collection_rate),
             total_expenses = VALUES(total_expenses), net_operating_income = VALUES(net_operating_income),
             open_maintenance_requests = VALUES(open_maintenance_requests)',
        [
            ':cid'=>$cid, ':d'=>$today,
            ':tu'=>(int)$kpi['total_units'], ':ou'=>(int)$kpi['occupied_units'], ':vu'=>(int)$kpi['vacant_units'],
            ':oc'=>$occupancyRate, ':er'=>$kpi['expected_rent'], ':cr'=>$kpi['collected_rent'],
            ':or_'=>$kpi['outstanding'], ':crate'=>$collectionRate,
            ':te'=>$kpi['total_expenses'], ':noi'=>$noi, ':om'=>(int)$kpi['open_maint'],
        ]
    );
    $total++;
}

cron_done(SCRIPT, "snapshot built for $total companies");