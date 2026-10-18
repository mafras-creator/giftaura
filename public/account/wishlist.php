<?php
/** GiftAura - My account: wishlist. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireLogin();

View::render('shop/account/wishlist', [
    'title' => 'My wishlist',
    'user'  => $user,
    'items' => $user['role'] === 'customer' ? WishlistService::items($user['id']) : [],
]);
