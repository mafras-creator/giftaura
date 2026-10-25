<?php
/** GiftAura - admin: reports for a date range, calculated from real records. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

Auth::requireAdmin('reports');

$report = array_key_exists(query_string('report'), ReportService::REPORTS) ? query_string('report') : 'sales';
[$from, $to, $notice] = ReportService::range(query_string('from'), query_string('to'));

$data = match ($report) {
    'sales'     => ['summary' => ReportService::summary($from, $to), 'days' => ReportService::salesByDay($from, $to),
                    'shop' => ShopSaleService::totals($from, $to)],
    'orders'    => ['rows' => ReportService::orders($from, $to)],
    'status'    => ['rows' => ReportService::statusTotals($from, $to)],
    'products'  => ['rows' => ReportService::bestSellers($from, $to)],
    'inventory' => ['rows' => ReportService::inventory(false)],
    'low_stock' => ['rows' => ReportService::inventory(true)],
};

View::render('admin/reports', [
    'title'  => 'Reports',
    'report' => $report,
    'from'   => $from,
    'to'     => $to,
    'notice' => $notice,
    'data'   => $data,
]);
