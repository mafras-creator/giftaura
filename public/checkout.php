<?php
/**
 * GiftAura - checkout (logged-in customers).
 * GET : delivery, contact and payment form + order summary.
 * POST: OrderService::placeOrder() validates everything again on the
 *       server and places the order in one transaction.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

$user = Auth::requireLogin();
if (Auth::isPanelRole($user['role'])) {
    Flash::add('info', 'Administrator accounts cannot place orders. Please use a customer account.');
    redirect('cart.php');
}
if ($user['email_verified_at'] === null) {
    Flash::add('error', 'Please verify your e-mail address before placing an order. We sent a link to ' . $user['email'] . '.');
    redirect('cart.php');
}

$cart = CartService::summary();
if ($cart['lines'] === []) {
    Flash::add('info', 'Your cart is empty.');
    redirect('cart.php');
}
if ($cart['has_problems']) {
    Flash::add('error', 'Some items in your cart need attention before you can check out.');
    redirect('cart.php');
}

$errors  = [];
$general = null;
$addresses = AddressService::forUser($user['id']);
$default = $addresses[0] ?? null;

// Form values: last submission, otherwise sensible defaults from the profile
$old = [
    'customer_name'  => $user['full_name'],
    'customer_phone' => $user['phone'] ?? '',
    'address_id'     => $default !== null ? (string) $default['id'] : 'new',
    'recipient_name' => $user['full_name'],
    'phone'          => $user['phone'] ?? '',
    'save_address'   => '1',
    'payment_method' => array_key_first(PaymentService::enabledMethods()) ?? '',
];

if (is_post()) {
    Csrf::verify();
    $fields = ['customer_name', 'customer_phone', 'address_id', 'recipient_name', 'phone', 'address_line1',
               'address_line2', 'city', 'district', 'postal_code', 'save_address', 'delivery_notes',
               'payment_method', 'cart_fingerprint'];
    $input = [];
    foreach ($fields as $field) {
        $input[$field] = post_string($field);
    }
    if ($input['address_id'] === 'new') {
        $input['address_id'] = '';
    }

    $result = OrderService::placeOrder($user, $input);
    if (isset($result['order_number'])) {
        redirect('order-confirmation.php', ['order' => $result['order_number']]);
    }

    $errors  = $result['errors'];
    $general = $result['general'];
    $old     = $input + $old;
    if ($old['address_id'] === '') {
        $old['address_id'] = 'new';
    }
    $old['save_address'] = $input['save_address'];
    $cart = CartService::summary(); // show the latest prices/stock
    if ($cart['lines'] === []) {
        Flash::add('error', $general ?? 'Your cart is empty.');
        redirect('cart.php');
    }
}

View::render('shop/checkout', [
    'title'     => 'Checkout',
    'user'      => $user,
    'cart'      => $cart,
    'addresses' => $addresses,
    'methods'   => PaymentService::enabledMethods(),
    'errors'    => $errors,
    'general'   => $general,
    'old'       => $old,
]);
