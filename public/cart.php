<?php
/**
 * GiftAura - shopping cart.
 * GET  : show the cart with live prices and stock checks.
 * POST : action=add|update|remove (CSRF protected), then redirect back.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

if (is_post()) {
    Csrf::verify();
    $action    = post_string('action');
    $productId = ctype_digit(post_string('product_id')) ? (int) post_string('product_id') : 0;
    $quantity  = preg_match('/^\d{1,3}$/', post_string('quantity')) ? (int) post_string('quantity') : 1;

    switch ($action) {
        case 'add':
            $result = CartService::add($productId, $quantity);
            Flash::add($result['ok'] ? 'success' : 'error', $result['message']);
            // If adding failed, go back to the page the customer came from so they see why.
            $back = post_string('return');
            if (!$result['ok'] && $back !== '') {
                header('Location: ' . safe_next($back, 'cart.php'), true, 303);
                exit;
            }
            redirect('cart.php');

        case 'update':
            $result = CartService::update($productId, $quantity);
            Flash::add($result['ok'] ? 'success' : 'error', $result['message']);
            redirect('cart.php');

        case 'remove':
            CartService::remove($productId);
            Flash::add('success', 'The item was removed from your cart.');
            redirect('cart.php');

        default:
            abort(400, 'Unknown cart action.');
    }
}

View::render('shop/cart', [
    'title'   => 'Your cart',
    'cart'    => CartService::summary(),
    'maxQty'  => CartService::maxPerItem(),
    'user'    => current_user(),
]);
