<?php
// modules/reports/_export.php — shared helpers for report export.
// Included by each report file, not routed directly.
declare(strict_types=1);

/**
 * Standard report filters from the query string.
 * @return array{from:string,to:string,property_id:int,format:string}
 */
function report_filters(): array
{
    $from = $_GET['from'] ?? date('Y-01-01');
    $to   = $_GET['to']   ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$from)) $from = date('Y-01-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$to))   $to   = date('Y-m-d');
    $propertyId = (int)($_GET['property_id'] ?? 0);
    $format = in_array($_GET['format'] ?? '', ['html', 'csv', 'xlsx', 'pdf'], true) ? $_GET['format'] : 'html';
    return [
        'from' => $from,
        'to' => $to,
        'property_id' => $propertyId,
        'format' => $format,
    ];
}

/** Company properties list for the filter bar. */
function report_properties(int $cid): array
{
    return db_all(
        'SELECT id, name FROM properties WHERE company_id = :c AND deleted_at IS NULL ORDER BY name',
        [':c' => $cid]
    );
}

/** Common report header printed at the top of every report (HTML mode). */
function report_header(string $title, array $filters): void
{
    $prop = $filters['property_id'] ? db_one('SELECT name FROM properties WHERE id = :p', [':p' => $filters['property_id']]) : null;
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h1 class="h4 mb-1"><?= e($title) ?></h1>
    <div class="text-muted small">
      <?= e(__('Period')) ?>: <strong><?= e($filters['from']) ?></strong> → <strong><?= e($filters['to']) ?></strong>
      <?= $prop ? ' · ' . e($prop['name']) : ' · ' . e(__('All properties')) ?>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-light" href="?<?= e(http_build_query(array_merge($_GET, ['format' => 'pdf']))) ?>" target="_blank">
      <i data-lucide="file-text"></i> PDF
    </a>
    <a class="btn btn-light" href="?<?= e(http_build_query(array_merge($_GET, ['format' => 'xlsx']))) ?>">
      <i data-lucide="table"></i> Excel
    </a>
    <a class="btn btn-light" href="?<?= e(http_build_query(array_merge($_GET, ['format' => 'csv']))) ?>">
      <i data-lucide="download"></i> CSV
    </a>
  </div>
</div>
<?php
}

/** Filter bar (HTML mode). */
function report_filter_bar(array $filters, array $properties): void
{
?>
<form method="get" class="srp-card p-3 mb-3">
  <div class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label small"><?= e(__('From')) ?></label>
      <input type="date" name="from" class="form-control form-control-sm" value="<?= e($filters['from']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small"><?= e(__('To')) ?></label>
      <input type="date" name="to" class="form-control form-control-sm" value="<?= e($filters['to']) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label small"><?= e(__('Property')) ?></label>
      <select name="property_id" class="form-select form-select-sm">
        <option value=""><?= e(__('All Properties')) ?></option>
        <?php foreach ($properties as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)$filters['property_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <button class="btn btn-primary btn-sm w-100"><?= e(__('Apply')) ?></button>
    </div>
  </div>
</form>
<?php
}

/** Stream a CSV file. */
function report_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
    fputcsv($out, $headers);
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out);
    exit;
}

/** Stream an XLSX file via PhpSpreadsheet. */
function report_xlsx(string $filename, string $sheetTitle, array $headers, array $rows): void
{
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr($sheetTitle, 0, 31));
    // Header row
    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '1', (string)$h);
        $sheet->getStyle($col . '1')->getFont()->setBold(true);
        $col++;
    }
    // Data rows
    $row = 2;
    foreach ($rows as $r) {
        $col = 'A';
        foreach ($r as $v) {
            $sheet->setCellValue($col . $row, $v);
            $col++;
        }
        $row++;
    }
    // Auto width
    foreach (range('A', $sheet->getHighestColumn()) as $c) {
        $sheet->getColumnDimension($c)->setAutoSize(true);
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/** Stream a PDF via mPDF using templates/pdf/report.php. */
function report_pdf(string $filename, string $title, array $filters, array $headers, array $rows): void
{
    $html = (function () use ($title, $filters, $headers, $rows) {
        ob_start();
        require SRP_ROOT . '/templates/pdf/report.php';
        return ob_get_clean();
    })();

    $mpdf = new \Mpdf\Mpdf([
        'format' => 'A4-L',
        'margin_top' => 14, 'margin_bottom' => 14, 'margin_left' => 12, 'margin_right' => 12,
        'tempDir' => SRP_ROOT . '/storage/cache',
    ]);
    $mpdf->SetTitle($title);
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
    exit;
}
/**
 * Cache the current report's data in session so the export endpoints can stream it.
 * Called by every report page right before output.
 */
function report_cache(string $key, string $title, array $headers, array $rows, array $filters): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // auth.php always starts a session; defensive fallback
        @session_start();
    }
    if (!isset($_SESSION['report_cache']) || !is_array($_SESSION['report_cache'])) {
        $_SESSION['report_cache'] = [];
    }
    $_SESSION['report_cache'][$key] = [
        'title'   => $title,
        'headers' => $headers,
        'rows'    => $rows,
        'filters' => $filters,
        'built_at' => time(),
    ];

    // Prune stale entries — keep the 20 most recent
    if (count($_SESSION['report_cache']) > 20) {
        uasort($_SESSION['report_cache'], fn($a, $b) => $b['built_at'] <=> $a['built_at']);
        $_SESSION['report_cache'] = array_slice($_SESSION['report_cache'], 0, 20, true);
    }
}

/**
 * Fetch a cached report definition. Returns null if missing or too old (1 hour).
 */
function report_cached(string $key): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $cached = $_SESSION['report_cache'][$key] ?? null;
    if (!$cached) return null;
    if (time() - (int)($cached['built_at'] ?? 0) > 3600) {
        unset($_SESSION['report_cache'][$key]);
        return null;
    }
    return $cached;
}

/**
 * Add "Export to Excel" and "Export to PDF" links that use the shared endpoints.
 * Returns HTML — call inside report_header() or inline.
 */
function report_export_buttons(string $key): string
{
    $base = APP_URL . '/modules/reports/';
    return
        '<a class="btn btn-light" href="' . $base . 'export-pdf.php?key=' . urlencode($key) . '" target="_blank">' .
          '<i data-lucide="file-text"></i> PDF' .
        '</a>' .
        '<a class="btn btn-light" href="' . $base . 'export-excel.php?key=' . urlencode($key) . '">' .
          '<i data-lucide="table"></i> Excel' .
        '</a>' .
        '<a class="btn btn-light" href="?format=csv">' .
          '<i data-lucide="download"></i> CSV' .
        '</a>';
}