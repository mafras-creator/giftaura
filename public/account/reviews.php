<?php
/** GiftAura - My account: products I received, to review, and my reviews. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::requireLogin();

View::render('shop/account/reviews', [
    'title' => 'My reviews',
    'user'  => $user,
    'items' => $user['role'] === 'customer' ? ReviewService::reviewableProducts($user['id']) : [],
]);
