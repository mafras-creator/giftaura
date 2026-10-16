<?php
/**
 * GiftAura - track an order.
 * Logged-in customers see a list of their own orders to choose from.
 * Anyone can look up an order with its reference AND the email used for
 * the order; only the status and timeline are shown (no address or phone).
 * The form is sent by POST (so the email address does not appear in the
 * URL or server logs) and lookups are limited per browser session.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$user   = current_user();
$order  = null;
$error  = null;
$number = '';
$email  = '';

if (is_post()) {
    Csrf::verify();
    $number = strtoupper(post_string('order'));
    $email  = mb_strtolower(post_string('email'));
    // Simple per-session limit: 10 lookups per 10 minutes.
    $now = time();
    $_SESSION['track_lookups'] = array_values(array_filter(
        (array) ($_SESSION['track_lookups'] ?? []),
        static fn($t) => is_int($t) && $t > $now - 600
    ));
    if (count($_SESSION['track_lookups']) >= 10) {
        $error = 'Too many lookups. Please wait a few minutes and try again.';
    } else {
        $_SESSION['track_lookups'][] = $now;
        $order = OrderService::findForTracking($number, $email);
        if ($order === null) {
            $error = 'We could not find an order with that reference and email address. Please check both and try again.';
        } elseif ($user !== null && (int) $order['user_id'] === $user['id']) {
            redirect('account/order.php', ['order' => $order['order_number']]);
        }
    }
}

View::render('shop/track-order', [
    'title'   => 'Track your order',
    'user'    => $user,
    'number'  => $number,
    'email'   => $email,
    'order'   => $order,
    'error'   => $error,
    'history' => $order ? OrderService::history((int) $order['id']) : [],
    'payment' => $order ? PaymentService::forOrder((int) $order['id']) : null,
    'recent'  => $user ? OrderService::forCustomer($user['id'], 1, 5)['items'] : [],
]);
