<?php
/** GiftAura - admin: all orders with search, status and date filters. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

Auth::requireAdmin('orders');

$date = static fn(string $v): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
$filters = array_filter([
    'q'       => mb_substr(query_string('q'), 0, 100),
    'status'  => isset(OrderService::STATUSES[query_string('status')]) ? query_string('status') : '',
    'payment' => isset(PaymentService::STATUSES[query_string('payment')]) ? query_string('payment') : '',
    'method'  => isset(PaymentService::METHODS[query_string('method')]) ? query_string('method') : '',
    'from'    => $date(query_string('from')),
    'to'      => $date(query_string('to')),
], static fn($v) => $v !== '');

View::render('admin/orders', [
    'title'   => 'Orders',
    'filters' => $filters,
    'counts'  => OrderAdminService::statusCounts(),
    'result'  => OrderAdminService::list($filters + ['page' => ctype_digit(query_string('page')) ? (int) query_string('page') : 1]),
]);
