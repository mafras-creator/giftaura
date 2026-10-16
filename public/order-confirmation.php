<?php
/** GiftAura - "thank you" page shown right after an order is placed. */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$user  = Auth::requireLogin();
$order = OrderService::findForCustomer($user['id'], query_string('order'));
if ($order === null) {
    abort(404, 'Order not found.');
}

View::render('shop/order-confirmation', [
    'title'   => 'Thank you for your order',
    'order'   => $order,
    'items'   => OrderService::items((int) $order['id']),
    'payment' => PaymentService::forOrder((int) $order['id']),
    'bank'    => PaymentService::bankDetails(),
]);
