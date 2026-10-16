<?php
/** GiftAura - My account: order history. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireLogin();
$page = ctype_digit(query_string('page')) ? (int) query_string('page') : 1;

View::render('shop/account/orders', [
    'title'  => 'My orders',
    'user'   => $user,
    'orders' => OrderService::forCustomer($user['id'], $page),
]);
