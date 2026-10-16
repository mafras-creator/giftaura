<?php
/** GiftAura - admin: customer list with search. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

Auth::requireAdmin();

$filters = array_filter([
    'q'      => mb_substr(query_string('q'), 0, 100),
    'status' => in_array(query_string('status'), ['active', 'inactive'], true) ? query_string('status') : '',
], static fn($v) => $v !== '');

View::render('admin/customers', [
    'title'   => 'Customers',
    'filters' => $filters,
    'result'  => CustomerAdminService::list($filters + ['page' => ctype_digit(query_string('page')) ? (int) query_string('page') : 1]),
]);
