<?php
// modules/reports/export-pdf.php — stream a cached report as .pdf via mPDF.
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/_export.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$USER = require_permission('reports.export');

$key = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['key'] ?? ''));
if ($key === '') {
    http_response_code(400);
    exit('Missing key');
}

$cached = report_cached($key);
if (!$cached) {
    http_response_code(404);
    exit('Report cache expired — open the report again and click Export.');
}

$title   = (string)$cached['title'];
$headers = (array)$cached['headers'];
$rows    = (array)$cached['rows'];
$filters = (array)($cached['filters'] ?? []);

$company = db_one(
    'SELECT name, address, phone, email, logo_path FROM companies WHERE id = :c',
    [':c' => (int)$USER['company_id']]
);
$propertyName = '';
if (!empty($filters['property_id'])) {
    $p = db_one('SELECT name FROM properties WHERE id = :p', [':p' => (int)$filters['property_id']]);
    if ($p) $propertyName = (string)$p['name'];
}

$html = (function () use ($title, $headers, $rows, $filters, $company, $propertyName, $USER) {
    ob_start();
    include SRP_ROOT . '/templates/pdf/report.php';
    return ob_get_clean();
});

$mpdf = new \Mpdf\Mpdf([
    'format' => 'A4-L',                       // landscape for wide tables
    'orientation' => 'L',
    'margin_top' => 18,
    'margin_bottom' => 18,
    'margin_left' => 12,
    'margin_right' => 12,
    'tempDir' => SRP_ROOT . '/storage/cache',
    'default_font' => 'dejavusans',
]);

$mpdf->SetTitle($title);
$mpdf->SetAuthor(trim(($USER['first_name'] ?? '') . ' ' . ($USER['last_name'] ?? '')));
$mpdf->SetCreator('Smart Rent Pro');
$mpdf->SetHTMLHeader(
    '<div style="text-align:right;font-size:9px;color:#94a3b8;border-bottom:1px solid #e6e8f0;padding-bottom:4px">'
    . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
    . '</div>'
);
$mpdf->SetHTMLFooter(
    '<div style="text-align:center;font-size:8px;color:#94a3b8">'
    . 'Page {PAGENO} of {nbpg} · Generated ' . date('Y-m-d H:i') . ' · ' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8')
    . '</div>'
);

$mpdf->WriteHTML($html);

$filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $title) . '-' . date('Ymd-Hi') . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);

log_audit('reports', 'export_pdf', null, null, null, ['key' => $key, 'rows' => count($rows)]);
exit;