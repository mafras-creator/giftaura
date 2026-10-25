<?php
/**
 * GiftAura - shopping cart.
 * GET  : show the cart with live prices and stock checks.
 * POST : action=add|update|message|remove (CSRF protected), then redirect back.
 * A guest who adds a product is sent to log in / create an account; the product
 * waits in the session, is added to their saved cart automatically afterwards and
 * the customer returns to the page they were on (with an "added to your cart" message).
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
            if (!$result['ok'] || current_user() !== null) {
                Flash::add($result['ok'] ? 'success' : 'error', $result['message']);
            } elseif (($pos = strpos($result['message'], 'was added to your cart.')) !== false
                && ($note = trim(substr($result['message'], $pos + 23))) !== '') {
                // Guest: the "added" message comes after login; keep notes such as "quantity was limited".
                Flash::add('warning', $note);
            }
            // If adding failed, go back to the page the customer came from so they see why.
            $back = post_string('return');
            if (!$result['ok'] && $back !== '') {
                // Keep what was typed, so the customer can correct it instead of typing it again.
                $_SESSION['custom_old'] = ['product_id' => $productId, 'text' => $customText];
                header('Location: ' . safe_next($back, 'cart.php'), true, 303);
                exit;
            }
            if ($result['ok'] && current_user() === null) {
                // Guests must log in or create an account first. The product is kept in
                // the session and moved into their saved cart right after login/registration.
                $name = Database::fetchValue('SELECT name FROM products WHERE id = :id', ['id' => $productId]);
                $_SESSION['cart_waiting'] = is_string($name) ? $name : 'Your product';
                // After login/registration the customer comes back to the page they were on.
                $back = safe_next(post_string('return'), 'products.php');
                $_SESSION['cart_return'] = $back;
                redirect('login.php', ['next' => $back]);
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
