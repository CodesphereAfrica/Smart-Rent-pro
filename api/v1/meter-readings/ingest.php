<?php
// api/v1/meter-readings/ingest.php — accepts manual OR IoT device readings from third parties.
// POST { meter_id, current_reading, reading_date, source: "iot_device" }
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';

$user = api_require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_err('method_not_allowed', 405);

$in = api_input();
$meterId = (int)($in['meter_id'] ?? 0);
$current = isset($in['current_reading']) ? (float)$in['current_reading'] : null;
$date    = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($in['reading_date'] ?? '')) ? $in['reading_date'] : date('Y-m-d');
$source  = in_array($in['source'] ?? 'manual', ['manual','iot_device'], true) ? $in['source'] : 'manual';

if (!$meterId || $current === null) api_err('missing_fields', 400);

$meter = db_one(
    'SELECT m.id, ut.company_id FROM utility_meters m
       JOIN utilities ut ON ut.id = m.utility_id
      WHERE m.id = :id LIMIT 1',
    [':id' => $meterId]
);
if (!$meter || (int)$meter['company_id'] !== (int)$user['company_id']) api_err('not_found', 404);

$prevRow = db_one('SELECT current_reading FROM meter_readings WHERE meter_id = :m ORDER BY id DESC LIMIT 1', [':m'=>$meterId]);
$prev = $prevRow ? (float)$prevRow['current_reading'] : 0.0;

$id = db_insert('meter_readings', [
    'meter_id' => $meterId,
    'previous_reading' => $prev,
    'current_reading' => $current,
    'reading_date' => $date,
    'source' => $source,
    'recorded_by' => (int)$user['id'],
]);

api_ok(['id' => $id, 'usage' => $current - $prev], 201);