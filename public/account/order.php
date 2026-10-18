<?php
/**
 * GiftAura - My account: one order (details, status timeline, payment).
 * POST actions: cancel (pending orders only), bank_reference.
 * The order is always looked up together with the logged-in user's id,
 * so changing the order number in the URL cannot show someone else's order.
 */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireLogin();
$number = query_string('order');
$order = OrderService::findForCustomer($user['id'], $number);
if ($order === null) {
    abort(404, 'Order not found.');
}

if (is_post()) {
    Csrf::verify();
    $action = post_string('action');
    if ($action === 'cancel') {
        $error = OrderService::cancelByCustomer($user['id'], $order['order_number'], post_string('reason'));
        Flash::add($error === null ? 'success' : 'error', $error ?? 'Your order has been cancelled.');
    } elseif ($action === 'bank_reference') {
        $error = PaymentService::submitReference($user['id'], $order['order_number'], post_string('reference'));
        Flash::add($error === null ? 'success' : 'error',
            $error ?? 'Thank you. Your transfer reference was sent; we will confirm the payment after checking our account.');
    } else {
        abort(400, 'Unknown action.');
    }
    redirect('account/order.php', ['order' => $order['order_number']]);
}

View::render('shop/account/order', [
    'title'   => 'Order ' . $order['order_number'],
    'user'    => $user,
    'order'   => $order,
    'items'   => OrderService::items((int) $order['id']),
    'history' => OrderService::history((int) $order['id']),
    'payment' => PaymentService::forOrder((int) $order['id']),
    'bank'    => PaymentService::bankDetails(),
    'reviewed' => $order['status'] === 'delivered'
        ? ReviewService::reviewedIds($user['id'], array_column(OrderService::items((int) $order['id']), 'product_id'))
        : [],
]);
