<?php
/**
 * GiftAura - admin: download a report as CSV (opens in Excel).
 * Cells that could be read as spreadsheet formulas are neutralised.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$admin = Auth::requireAdmin('reports');

$report = query_string('report');
if (!array_key_exists($report, ReportService::REPORTS)) {
    abort(404, 'Unknown report.');
}
[$from, $to] = ReportService::range(query_string('from'), query_string('to'));
$rows = ReportService::csvRows($report, $from, $to);
$name = in_array($report, ['inventory', 'low_stock'], true)
    ? "giftaura-{$report}-" . date('Y-m-d') . '.csv'
    : "giftaura-{$report}-{$from}-to-{$to}.csv";

ActivityLogService::log($admin['id'], 'report.export', 'report', null, $report . ' ' . $from . ' to ' . $to);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Cache-Control: no-store');
$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 marker so Excel shows Sinhala/Tamil text correctly
fputcsv($out, ['GiftAura - ' . ReportService::REPORTS[$report], in_array($report, ['inventory', 'low_stock'], true) ? 'As of ' . date('Y-m-d H:i') : "From {$from} to {$to}"]);
foreach ($rows as $row) {
    fputcsv($out, array_map([ReportService::class, 'csvCell'], $row));
}
fclose($out);
