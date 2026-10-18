<?php
/**
 * GiftAura - heart button (POST only): add a product to the wishlist or remove it.
 * Guests are sent to the login page; the product is added after they log in.
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

if (!is_post()) {
    redirect('account/wishlist.php');
}
Csrf::verify();

$productId = ctype_digit(post_string('product_id')) ? (int) post_string('product_id') : 0;
$back = safe_next(post_string('return'), 'account/wishlist.php');
$user = current_user();

if ($user === null) {
    if ($productId > 0) {
        WishlistService::remember($productId);
    }
    Flash::add('info', 'Please log in or create an account to save products to your wishlist.');
    redirect('login.php', ['next' => $back]);
}
if ($user['role'] !== 'customer') {
    Flash::add('info', 'Administrator accounts do not have a wishlist. Use a customer account.');
} elseif ($productId > 0) {
    if (post_string('action') === 'remove') {
        WishlistService::remove($user['id'], $productId);
        $result = ['saved' => false];
    } else {
        $result = WishlistService::toggle($user['id'], $productId);
    }
    if (isset($result['error'])) {
        Flash::add('error', $result['error']);
    } else {
        Flash::add('success', $result['saved'] ? 'Saved to your wishlist.' : 'Removed from your wishlist.');
    }
}
header('Location: ' . $back, true, 303);
exit;
