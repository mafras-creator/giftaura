<?php
/**
 * GiftAura - shopping cart.
 * GET  : show the cart with live prices and stock checks.
 * POST : action=add|update|message|remove (CSRF protected), then redirect back.
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
            $customText = mb_substr(post_string('customization_text'), 0, 2000);
            $result = CartService::add($productId, $quantity, $customText);
            Flash::add($result['ok'] ? 'success' : 'error', $result['message']);
            // If adding failed, go back to the page the customer came from so they see why.
            $back = post_string('return');
            if (!$result['ok'] && $back !== '') {
                // Keep what was typed, so the customer can correct it instead of typing it again.
                $_SESSION['custom_old'] = ['product_id' => $productId, 'text' => $customText];
                header('Location: ' . safe_next($back, 'cart.php'), true, 303);
                exit;
            }
            redirect('cart.php');

        case 'update':
            $result = CartService::update($productId, $quantity);
            Flash::add($result['ok'] ? 'success' : 'error', $result['message']);
            redirect('cart.php');

        case 'message':
            $result = CartService::setMessage($productId, mb_substr(post_string('customization_text'), 0, 2000));
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
