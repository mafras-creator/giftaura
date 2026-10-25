<?php
/**
 * GiftAura - admin: in-shop sales (invoices for walk-in customers).
 * GET filters: from, to, q, status, page. New sale: admin/sale-new.php.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

Auth::requireAdmin('shop_sales');

$date = static fn (string $v): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false ? $v : '';
$filters = array_filter([
    'from'   => $date(query_string('from')),
    'to'     => $date(query_string('to')),
    'status' => in_array(query_string('status'), ['completed', 'voided'], true) ? query_string('status') : '',
    'q'      => mb_substr(query_string('q'), 0, 60),
], static fn (string $v): bool => $v !== '');

$today = date('Y-m-d');
View::render('admin/sales', [
    'title'   => 'In-shop sales',
    'filters' => $filters,
    'result'  => ShopSaleService::list($filters + ['page' => ctype_digit(query_string('page')) ? (int) query_string('page') : 1]),
    'today'   => ShopSaleService::totals($today, $today),
    'month'   => ShopSaleService::totals(date('Y-m-01'), $today),
]);
